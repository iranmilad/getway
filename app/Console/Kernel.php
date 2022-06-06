<?php

namespace App\Console;

use Carbon\Carbon;
use App\Models\User;
use App\Models\ProductRequest;
use App\Jobs\UpdateProductFind;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        $schedule->call(function () {
            ProductRequest::latest()->where('request_time', '<', Carbon::now()->subHour(1))->delete();
        })->name('job_remove_old_request')->withoutOverlapping()->everyMinute();


        $schedule->call(function () {

            $users=User::get()->all();
            foreach($users as $key=>$user){
                if ($user->config==null) continue;
                log::info("run auto update night for user: ".$user->id);
                $config=$user->config["product_cat"];
                log::info($config);
                UpdateProductFind::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"serial"=>$user->serial,"apiKey"=>$user->apiKey,"holooDatabaseName"=>$user->holooDatabaseName,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret,"cloudTokenExDate"=>$user->cloudTokenExDate,"cloudToken"=>$user->cloudToken, "holo_unit"=>$user->holo_unit, "plugin_unit"=>$user->plugin_unit],$user->config["product_cat"],$user->config,1)->onQueue("high");


            }
        })->name('every day auto update')->withoutOverlapping()->dailyAt('02:12');

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }




}
