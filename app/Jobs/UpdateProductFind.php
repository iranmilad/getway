<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Jobs\UpdateProductsUser;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdateProductFindStep2;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\UpdateProductsVariationUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;


class UpdateProductFind implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $param;
    protected $category;
    protected $config;
    public $flag;
    public $timeout = 2*60;
    public $failOnTimeout = true;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$category,$config,$flag)
    {
        Log::info(' queue update product find start');
        $this->user=$user;
        $this->config=$config;
        $this->category=$category;
        $this->flag=$flag;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle2()
    {
        $user_id=$this->user->id;
        Log::info("update for user id $user_id");




        $callApi = $this->fetchCategoryHolloProds($this->category);
        $holooProducts = $callApi;

        $callApi = $this->fetchAllWCProds();
        $wcProducts = $callApi;
        log::info('product fetch compelete');
        $response_product=[];

        $wcholooCounter=0;
        $holooFinded=0;
        $conflite=0;
        $wcCount=0;
        $variation=[];
        //log::info($this->config);
        foreach ($wcProducts as $WCProd) {
            if (count($WCProd->meta_data)>0) {
                if ($WCProd->type=='simple') {
                    $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                    if ($wcHolooCode) {
                        $wcholooCounter=$wcholooCounter+1;

                        $productFind = false;
                        foreach ($holooProducts as $key=>$HolooProd) {
                            $HolooProd=(object) $HolooProd;
                            if ($wcHolooCode == $HolooProd->a_Code) {

                                // log::info($this->config->sales_price_field);
                                // log::info((int)$WCProd->regular_price);
                                // log::info($this->get_price_type($this->config->sales_price_field,$HolooProd));

                                //log::info((isset($this->config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($this->config->sales_price_field,$HolooProd)));
                                $holooFinded=$holooFinded+1;
                                $productFind = true;
                                $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                                if (
                                isset($this->config->update_product_price) && $this->config->update_product_price=="1" &&
                                (
                                (isset($this->config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($this->config->sales_price_field,$HolooProd)) or
                                (isset($this->config->special_price_field) && (int)$WCProd->sale_price  != $this->get_price_type($this->config->special_price_field,$HolooProd)) or
                                (isset($this->config->wholesale_price_field) && $wholesale_customer_wholesale_price && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($this->config->wholesale_price_field,$HolooProd))
                                ) or
                                ((isset($this->config->update_product_stock) && $this->config->update_product_stock=="1")  and $WCProd->stock_quantity != (int)$HolooProd->exist) or
                                ((isset($this->config->update_product_name) && $this->config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->a_Name)))

                                ){


                                    $conflite=$conflite+1;



                                    $data = [
                                        'id' => $WCProd->id,
                                        'name' =>(isset($this->config->update_product_name) && $this->config->update_product_name=="1") && ($WCProd->name != $this->arabicToPersian($HolooProd->a_Name)) ? $this->arabicToPersian($HolooProd->a_Name) :$WCProd->name,
                                        'regular_price' => (isset($this->config->update_product_price) && $this->config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($this->config->sales_price_field,$HolooProd)) ? $this->get_price_type($this->config->sales_price_field,$HolooProd) : (int)$WCProd->regular_price,
                                        'price' => (isset($this->config->update_product_price) && $this->config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($this->config->special_price_field,$HolooProd)) ? $this->get_price_type($this->config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                        'sale_price' => (isset($this->config->update_product_price) && $this->config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($this->config->special_price_field,$HolooProd)) ? $this->get_price_type($this->config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                        'wholesale_customer_wholesale_price' => (isset($this->config->update_product_price) && $this->config->update_product_price=="1") && (isset($wholesale_customer_wholesale_price) && (int)$wholesale_customer_wholesale_price != $this->get_price_type($this->config->wholesale_price_field,$HolooProd)) ? $this->get_price_type($this->config->wholesale_price_field,$HolooProd)  : ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                                        'stock_quantity' => (isset($this->config->update_product_stock) && $this->config->update_product_stock=="1" && (int) $HolooProd->exist>0 and isset($WCProd->stock_quantity)) ? (int) $HolooProd->exist : 0,
                                    ];
                                    log::info("add new update product to queue for product ");
                                    log::info("for website id : ".$this->user->siteUrl);



                                    UpdateProductsUser::dispatch((object)["id"=>$this->user->id,"siteUrl"=>$this->user->siteUrl,"consumerKey"=>$this->user->consumerKey,"consumerSecret"=>$this->user->consumerSecret],$data,$wcHolooCode)->onQueue("high");


                                    unset($holooProducts[$key]);
                                    array_push($response_product,$wcHolooCode);

                                }
                                else{
                                    unset($holooProducts[$key]);
                                }
                            }

                        }

                    }

                }
                else if($WCProd->type=='variable'){
                    $variation[]=$WCProd->id;
                }
            }
        }
        if(count($variation)>0){
            $this->updateWCVariation($variation,$holooProducts,$this->config);
        }

        Log::info( $wcholooCounter ." product(s) update");


    }


    public function handle()
    {
        $user_id=$this->user->id;
        Log::info("update for user id $user_id");
        $this->getNewToken();
        log::info (json_decode($this->user));
        foreach ($this->category as $holoo_cat=>$wc_cat) {
            if ($wc_cat=="") {
                continue;
            }
            if(is_array($wc_cat)){
                foreach ($wc_cat as $wc_cat_id) {
                    UpdateProductFindStep2::dispatch((object)["id"=>$this->user->id,"siteUrl"=>$this->user->siteUrl,"serial"=>$this->user->serial,"apiKey"=>$this->user->apiKey,"holooDatabaseName"=>$this->user->holooDatabaseName,"consumerKey"=>$this->user->consumerKey,"consumerSecret"=>$this->user->consumerSecret,"cloudTokenExDate"=>$this->user->cloudTokenExDate,"cloudToken"=>$this->user->cloudToken, "holo_unit"=>$this->user->holo_unit, "plugin_unit"=>$this->user->plugin_unit,"user_traffic"=>$this->user->user_traffic],$this->config->product_cat,$this->config,1,$holoo_cat,$wc_cat_id)->onQueue("medium");
                }
            }
            else{
                UpdateProductFindStep2::dispatch((object)["id"=>$this->user->id,"siteUrl"=>$this->user->siteUrl,"serial"=>$this->user->serial,"apiKey"=>$this->user->apiKey,"holooDatabaseName"=>$this->user->holooDatabaseName,"consumerKey"=>$this->user->consumerKey,"consumerSecret"=>$this->user->consumerSecret,"cloudTokenExDate"=>$this->user->cloudTokenExDate,"cloudToken"=>$this->user->cloudToken, "holo_unit"=>$this->user->holo_unit, "plugin_unit"=>$this->user->plugin_unit,"user_traffic"=>$this->user->user_traffic],$this->config->product_cat,$this->config,1,$holoo_cat,$wc_cat)->onQueue("medium");
            }


        }





    }


    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->user->id.$this->flag;
    }


    private function getNewToken(): string
    {
        $userSerial = $this->user->serial;
        $userApiKey = $this->user->apiKey;
        if ($this->user->cloudTokenExDate > Carbon::now()) {

            return $this->user->cloudToken;
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

                $this->user->cloudTokenExDate = Carbon::now()->addDay(1);
                return $response->result->apikey;
            }
        }
    }


    public function fetchCategoryHolloProds($categorys)
    {
        $totalProduct=[];

        $curl = curl_init();
        foreach ($categorys as $category_key=>$category_value) {
            if ($category_value != "") {
                $m_groupcode=explode("-",$category_key)[0];
                $s_groupcode=explode("-",$category_key)[1];

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/Article/SearchArticles?from.date=2022',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: ' . $this->user->serial,
                        'database: ' . $this->user->holooDatabaseName,
                        'Authorization: Bearer ' .$this->getNewToken(),
                        'm_groupcode: ' . $m_groupcode,
                        's_groupcode: ' . $s_groupcode,
                        'isArticle: true',
                        'access_token: ' .$this->user->apiKey
                    ),
                ));
                $response = curl_exec($curl);

                if($response){
                    $totalProduct=array_merge(json_decode($response, true)??[],$totalProduct??[]);
                }

            }


        }


        return $totalProduct;
    }

    public function fetchAllWCProds($published=false,$category=null)
    {

        if($published){
            $status= "status=publish&" ;
        }
        else{
            $status= "";
        }
        if($category){
            $category= "category=$category&";
        }
        else{
            $category= "";
        }
        $curl = curl_init();
        $page = 1;
        $products = [];
        $all_products = [];
        do{
          try {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products?'.$status.$category.'meta=_holo_sku&page='.$page.'&per_page=10000',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_USERAGENT => 'Holoo',
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
            ));

            $response = curl_exec($curl);

            $products = json_decode($response);

            $all_products = array_merge($all_products,$products);
          }
          catch(\Throwable $th){
            break;
          }
          $page++;
        } while (count($products) > 0);

        curl_close($curl);

        return $all_products;



    }


    private function findKey($array, $key)
    {
        foreach ($array as $k => $v) {
            if (isset($v->key) and $v->key == $key) {
                return $v->value;
            }
        }
        return null;
    }


    private function get_price_type($price_field,$HolooProd){
        // "sales_price_field": "1",
        // "special_price_field": "2",
        // "wholesale_price_field": "3",

        // "sel_Price": 12000,
        // "sel_Price2": 0,
        // "sel_Price3": 0,
        // "sel_Price4": 0,
        // "sel_Price5": 0,
        // "sel_Price6": 0,
        // "sel_Price7": 0,
        // "sel_Price8": 0,
        // "sel_Price9": 0,
        // "sel_Price10": 0,


        if((int)$price_field==1){
            return (int)(float) $HolooProd->sel_Price*$this->get_tabdel_vahed();
        }
        else{
            return (int)(float) $HolooProd->{"sel_Price".$price_field}*$this->get_tabdel_vahed();
        }
    }


    public static function arabicToPersian($string){

        $characters = [
            'ك' => 'ک',
            'دِ' => 'د',
            'بِ' => 'ب',
            'زِ' => 'ز',
            'ذِ' => 'ذ',
            'شِ' => 'ش',
            'سِ' => 'س',
            'ى' => 'ی',
            'ي' => 'ی',
            '١' => '۱',
            '٢' => '۲',
            '٣' => '۳',
            '٤' => '۴',
            '٥' => '۵',
            '٦' => '۶',
            '٧' => '۷',
            '٨' => '۸',
            '٩' => '۹',
            '٠' => '۰',
        ];
        return str_replace(array_keys($characters), array_values($characters), $string);
    }

    public function get_tabdel_vahed(){

        // log::alert($user->holo_unit);
        if ($this->user->holo_unit=="rial" and $this->user->plugin_unit=="toman"){
            return 0.1;
        }
        elseif ($this->user->holo_unit=="toman" and $this->user->plugin_unit=="rial"){
            return 10;
        }
        else{
            return 1;
        }

    }

    public function updateWCVariation($variations,$holooProducts,$config){
        //return;

        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        set_time_limit(0);
        $notneedtoProsse=[];
        foreach ($variations as $wcId){

            $wcProducts=$this->get_variation_product($wcId);
            if(!$wcProducts){
                log::alert("not found wc product for variation $wcId");
                continue;
            }
            foreach ($wcProducts as $WCProd) {
                if (count($WCProd->meta_data)>0) {

                    $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                    if ($wcHolooCode) {

                        $productFind = false;
                        foreach ($holooProducts as $key=>$HolooProd) {
                            //if( array_search($key, $notneedtoProsse)) continue;

                            $HolooProd=(object) $HolooProd;
                            if ($wcHolooCode == $HolooProd->a_Code) {

                                $productFind = true;
                                $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                                if (
                                isset($config->update_product_price) && $config->update_product_price=="1" &&
                                (
                                (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                                (isset($config->special_price_field) && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                                (isset($config->wholesale_price_field) && $wholesale_customer_wholesale_price && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
                                ) or
                                ((isset($config->update_product_stock) && $config->update_product_stock=="1") and $WCProd->stock_quantity != (int)$HolooProd->exist) or
                                ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->a_Name)))

                                ){


                                    $data = [
                                        'id' => $wcId ,
                                        'variation_id' => $WCProd->id,

                                        'regular_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) ? $this->get_price_type($config->sales_price_field,$HolooProd) : (int)$WCProd->regular_price,
                                        'price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                        'sale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                        'wholesale_customer_wholesale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && (isset($wholesale_customer_wholesale_price) && (int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) ? $this->get_price_type($config->wholesale_price_field,$HolooProd)  : ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                                        'stock_quantity' => (isset($config->update_product_stock) && $config->update_product_stock=="1") ? (int) $HolooProd->exist : 0,
                                    ];
                                    log::info("add new update product to queue for product variation");
                                    log::info("for website id : ".$this->user->siteUrl);

                                    UpdateProductsVariationUser::dispatch((object)["id"=>$this->user->id,"siteUrl"=>$this->user->siteUrl,"consumerKey"=>$this->user->consumerKey,"consumerSecret"=>$this->user->consumerSecret],$data,$wcHolooCode)->onQueue("low");


                                    $notneedtoProsse[]=$key;



                                }
                                else{
                                    $notneedtoProsse[]=$key;

                                }
                            }

                        }


                    }

                }
            }
        }


    }

    public function get_variation_product($product_id){

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/'.$product_id.'/variations?per_page=100',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_USERAGENT => 'Holoo',
        CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        if ($response) {
            $decodedResponse = json_decode($response);
            return $decodedResponse;
        }
        return null;
    }
}
