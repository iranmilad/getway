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
    protected $type;
    protected $cluster;
    public $flag;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$param,$categories,$flag,$type="simple",$cluster=[])
    {
        $this->user=$user;
        $this->param=$param;
        $this->categories=$categories;
        $this->flag=$flag;
        $this->type=$type;
        $this->cluster=$cluster;
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
            ),
            (object)array(
                'key' => 'wholesale_customer_wholesale_price',
                'value' => $this->param["wholesale_customer_wholesale_price"]
            )
        );
        if ($this->type=="variable") {
            $options=$this->variableOptions($this->cluster);
            $attributes = array(
                (object)array(
                    'id'        => 5,
                    'variation' => true,
                    'visible'   => true,
                    'options'   => $options,
                )
            );
            if ($this->categories !=null) {
                $category=array(
                    (object)array(
                        'id' => $this->categories["id"],
                        "name" => $this->categories["name"],
                    )
                );
                $data = array(
                    'name' => $this->param["holooName"],
                    'type' => $this->type,
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'categories' => $category,
                    'attributes' => $attributes,
                );
            }
            else{
                $data = array(
                    'name' => $this->param["holooName"],
                    'type' => $this->type,
                    'regular_price' => $this->param["holooRegularPrice"],
                    'stock_quantity' => $this->param["holooStockQuantity"],
                    'status' => 'draft',
                    "manage_stock" => true,
                    'meta_data' => $meta,
                    'attributes' => $attributes,
                );
            }
        }
        else {
            if ($this->categories !=null) {
                $category=array(
                    (object)array(
                        'id' => $this->categories["id"],
                        //"name" => $this->categories["name"],
                    )
                );
                $data = array(
                    'name' => $this->param["name"],
                    'type' => 'simple',
                    'regular_price' => $this->param["regular_price"],
                    'price' => $this->param["price"],
                    'sale_price' => $this->param["sale_price"],
                    'stock_quantity' =>$this->param["stock_quantity"],
                    "manage_stock" => true,
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'categories' => $category
                );
            }
            else{
                $data = array(
                    'name' => $this->param["name"],
                    'type' => 'simple',
                    'regular_price' => $this->param["regular_price"],
                    'stock_quantity' => $this->param["stock_quantity"],
                    "manage_stock" => true,
                    'status' => 'draft',
                    'meta_data' => $meta,
                );
            }
        }
        $data = json_encode($data);
        //return response($data);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 20,
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
        $decodedResponse = ($response) ?? json_decode($response);
        if ($this->type=="variable") {
            $this->AddProductVariation($decodedResponse->id,$this->param,$this->cluster);
        }
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


    private function AddProductVariation($id,$product,$clusters){

        $curl = curl_init();

        // $data = array(
        //     'name' => $product["holooName"],
        //     'type' => $type,
        //     'regular_price' => $product["holooRegularPrice"],
        //     'stock_quantity' => $product["holooStockQuantity"],
        //     'status' => 'draft',
        //     'meta_data' => $meta,
        //     'attributes' => $attributes,
        // );
        $meta = array(
            (object)array(
                'key' => '_holo_sku',
                'value' => $product["holooCode"]
            )
        );

        foreach($clusters as $cluster){

            $data=array(
                'description' => $cluster->Name,
                'regular_price' => $product["holooRegularPrice"],
                'sale_price' => $product["holooRegularPrice"],
                'stock_quantity' => $cluster->Few,
                "manage_stock" => true,
                //'status' => 'draft',
                'meta_data' => $meta,


                // 'weight' => $cluster->,
                // 'dimensions' => '<string>',
                //'meta_data' => $meta,
            );
            $data = json_encode($data);

            curl_setopt_array($curl, array(
              CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/'.$id.'/variations',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => $data,
              CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
              ),
            ));

            $response = curl_exec($curl);
            //return $response;
        }


        curl_close($curl);
        return $response;

    }


    private function variableOptions($clusters){
        $options=[];

        foreach ( $clusters as $key=>$cluster){
            $options[]=$cluster->Name;
        }

        return $options;

    }

}
