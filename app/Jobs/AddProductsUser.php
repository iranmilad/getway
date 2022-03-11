<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AddProductsUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $param;
    protected $categories;
    public $flag;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$param,$categories,$flag)
    {
        $this->user=$user;
        $this->param=$param;
        $this->categories=$categories;
        $this->flag=$flag;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $meta = array(
            (object)array(
                'key' => '_holo_sku',
                'value' => $this->param["holooCode"]
            )
        );
        //$json = json_encode($value);
        if ($this->categories !=null) {
            $category=array(
                (object)array(
                    'id' => $this->categories["id"],
                    "name" => $this->categories["name"],
                )
            );
            $data = array(
                'name' => $this->param["holooName"],
                'type' => 'simple',
                'regular_price' => $this->param["holooRegularPrice"],
                'stock_quantity' => $this->param["holooStockQuantity"],
                'status' => 'draft',
                'meta_data' => $meta,
                'categories' => $category
            );
        }
        else{
            $data = array(
                'name' => $this->param["holooName"],
                'type' => 'simple',
                'regular_price' => $this->param["holooRegularPrice"],
                'stock_quantity' => $this->param["holooStockQuantity"],
                'status' => 'draft',
                'meta_data' => $meta,
            );
        }
        $data = json_encode($data);
        //return response($data);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
            CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
        ));

        $response = curl_exec($curl);

    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->user->id.'_'.$this->flag;
    }

}
