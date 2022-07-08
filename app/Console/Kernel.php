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

            $users=User::where(["user_traffic"=>'light'])->get()->all();
            foreach($users as $key=>$user){
                if ($user->config==null) continue;
                log::info("run auto update night for user: ".$user->id);
                $config=json_decode($user->config);
                $cloudToken= $this->getNewToken($user);
                //log::info($config->product_cat);
                UpdateProductFind::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"serial"=>$user->serial,"apiKey"=>$user->apiKey,"holooDatabaseName"=>$user->holooDatabaseName,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret,"cloudTokenExDate"=>$user->cloudTokenExDate,"cloudToken"=>$cloudToken, "holo_unit"=>$user->holo_unit, "plugin_unit"=>$user->plugin_unit,"user_traffic"=>$user->user_traffic],$config->product_cat,$config,1)->onQueue("high");


            }
        })->name('every day auto update')->withoutOverlapping()->dailyAt('01:12');

        $schedule->call(function () {

            $users=User::where(["user_traffic"=>'heavy'])->get()->all();
            foreach($users as $key=>$user){
                if ($user->config==null) continue;
                log::info("run auto update night for heavy user: ".$user->id);
                $config=json_decode($user->config);
                $cloudToken= $this->getNewToken($user);
                //log::info($config->product_cat);
                UpdateProductFind::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"serial"=>$user->serial,"apiKey"=>$user->apiKey,"holooDatabaseName"=>$user->holooDatabaseName,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret,"cloudTokenExDate"=>$user->cloudTokenExDate,"cloudToken"=>$cloudToken, "holo_unit"=>$user->holo_unit, "plugin_unit"=>$user->plugin_unit,"user_traffic"=>$user->user_traffic],$config->product_cat,$config,1)->onQueue("high");


            }
        })->name('every day auto update')->withoutOverlapping()->dailyAt('23:00');
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

    private function getNewToken($user): string
    {

        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        if ($user->cloudTokenExDate > Carbon::now()) {

            return $user->cloudToken;
        }
        else {


            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://myholoo.ir/api/Ticket/RegisterForPartner',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array('Serial' => $userSerial, 'RefreshToken' => 'false', 'DeleteService' => 'false', 'MakeService' => 'true', 'RefreshKey' => 'false'),
                CURLOPT_HTTPHEADER => array(
                    'apikey:' . $userApiKey,
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($response);

            if ($response) {
                log::info("take new token request and response");
                log::info(json_encode($response));
                return $response->result->apikey;
            }
            else {
                return $user->cloudToken;
            }
        }
    }


}
