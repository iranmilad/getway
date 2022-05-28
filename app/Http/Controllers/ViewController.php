<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ViewController extends Controller{
    public function index($user_id,$token){
        return view('find',compact($user_id,$token));
    }

    public function compareProductsFromWoocommerceToHoloo(Request $config){
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes

        $user=auth()->user();
        // $size = count(array_filter($config->product_cat, ""));
        // $size_cat = count($config->product_cat);

        // if ($size>$size_cat){
        //     $this->compareProductsFromWoocommerceToHoloo2($config);
        // }
        if($config->search_category!=""){
            $callApi = $this->fetchAllWCProds(true,(int)$config->search_category);
            //$callApi = $this->fetchAllWCProds(true);

        }
        else{
            $callApi = $this->fetchAllWCProds(true);
        }
        $WCProds = $callApi;

        Log::info("fetch holo and wp complete");
        //$callApi = $this->fetchCategoryHolloProds($config->product_cat);
        $callApi = $this->fetchAllHolloProds();
        if (!isset($WCProds) or !isset($callApi)) {
            return $this->sendResponse('داده در سمت سرور موجود نیست', Response::HTTP_OK,['result' => []]);
        }
        $HolooProds = $callApi->result;
        //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $callApi);
        $counter_confid=0;
        $counter_wc=0;
        $products = [];
        $notneedtoProsse=[];

        foreach ($WCProds as $WCProd) {
            $counter_wc++;
            //array_push($products,$WCProd->id);
            // if ($counter_confid>$config->per_page) {
            //     break;
            // }
            if ($WCProd->type=='simple') {
                if (count($WCProd->meta_data)>0) {
                    //dd($WCProd->meta_data);

                    $wcHolooCode = $this->findKey($WCProd->meta_data, '_holo_sku');


                    if ($wcHolooCode!=null) {
                        $messages = [];
                        $messages_code = [];

                        $productFind = false;
                        foreach ($HolooProds as $key=>$HolooProd) {
                            //if( array_search($key, $notneedtoProsse)) continue;
                            $HolooProd=(object) $HolooProd;
                            //0 "قیمت محصول با هلو منطبق نیست"
                            //1 "نام محصول با هلو منطبق نیست"
                            //2 "مقدار موجودی محصول با هلو منطبق نیست"
                            //3 "کد هلو ثبت شده برای این محصول در نرم افزار هلو یافت نشد"
                            $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data, 'wholesale_customer_wholesale_price');

                            //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $wholesale_customer_wholesale_price);
                            if ($wcHolooCode == $HolooProd->a_Code) {
                                if (
                                isset($config->update_product_price) && $config->update_product_price=="1" &&
                                (
                                    (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field, $HolooProd)) or
                                (isset($config->special_price_field) && (int)$WCProd->sale_price!=0 && (int)$WCProd->sale_price<(int)$WCProd->regular_price && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field, $HolooProd)) or
                                (isset($config->wholesale_price_field) && $wholesale_customer_wholesale_price && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field, $HolooProd))
                                )

                                ) {
                                    array_push($messages, 'قیمت محصول با هلو منطبق نیست.');
                                    array_push($messages_code, 0);
                                }



                                if ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->a_Name))) {
                                    //dd($WCProd->name.'-'.trim($this->arabicToPersian($HolooProd->a_Name)));
                                    array_push($messages, 'نام محصول با هلو منطبق نیست.');
                                    array_push($messages_code, 1);
                                }
                                if ((isset($config->update_product_stock) && $config->update_product_stock=="1") &&  isset($WCProd->stock_quantity)  and $WCProd->stock_quantity != (int)$HolooProd->exist) {
                                    array_push($messages, 'مقدار موجودی محصول با هلو منطبق نیست.');
                                    array_push($messages_code, 2);
                                }

                                $notneedtoProsse[]=$key;
                                $productFind = true;
                                break;
                            }
                        }
                        if ($productFind == false) {
                            # if product dont find
                            array_push($messages, 'کد هلو ثبت شده برای این محصول در نرم افزار هلو یافت نشد.');
                            array_push($messages_code, 3);
                        }

                        if (count($messages_code)>0) {
                            array_push(
                                $products,
                                [
                                    'msg' => $messages,
                                    'product_name' => $WCProd->name,
                                    'price' => $WCProd->regular_price,
                                    'amount' => (isset($WCProd->stock_quantity)) ? $WCProd->stock_quantity : 0,
                                    'holo_code' => $wcHolooCode ,
                                    'woocommerce_product_id' => $WCProd->id,
                                    'msg_code' => $messages_code,

                                ]
                            );
                            $counter_confid=$counter_confid+1;
                        }
                    }
                }
            }
            else{
                if($user->user_traffic=='heavy') continue;
                $WCProdsVariation=$this->get_variation_product($WCProd->id);
                foreach ($WCProdsVariation as $WCProdVariation) {
                    if (count($WCProdVariation->meta_data)>0) {
                        //dd($WCProdVariation->meta_data);

                        $wcHolooCode = $this->findKey($WCProdVariation->meta_data, '_holo_sku');


                        if ($wcHolooCode!=null) {
                            $messages = [];
                            $messages_code = [];

                            $productFind = false;
                            foreach ($HolooProds as $key=>$HolooProd) {
                                //if( array_search($key, $notneedtoProsse)) continue;
                                $HolooProd=(object) $HolooProd;
                                //0 "قیمت محصول با هلو منطبق نیست"
                                //1 "نام محصول با هلو منطبق نیست"
                                //2 "مقدار موجودی محصول با هلو منطبق نیست"
                                //3 "کد هلو ثبت شده برای این محصول در نرم افزار هلو یافت نشد"
                                $wholesale_customer_wholesale_price= $this->findKey($WCProdVariation->meta_data, 'wholesale_customer_wholesale_price');

                                //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $wholesale_customer_wholesale_price);
                                if ($wcHolooCode == $HolooProd->a_Code) {
                                    if (
                                    isset($config->update_product_price) && $config->update_product_price=="1" &&
                                    (
                                    (isset($config->sales_price_field) && (int)$WCProdVariation->regular_price != $this->get_price_type($config->sales_price_field, $HolooProd)) or
                                    (isset($config->special_price_field) && (int)$WCProdVariation->sale_price!=0 && (int)$WCProdVariation->sale_price<(int)$WCProdVariation->regular_price && (int)$WCProdVariation->sale_price  != $this->get_price_type($config->special_price_field, $HolooProd)) or
                                    (isset($config->wholesale_price_field) && $wholesale_customer_wholesale_price && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field, $HolooProd))
                                    )

                                    ) {
                                        // log::info($wholesale_customer_wholesale_price);
                                        // log::info($this->get_price_type($config->wholesale_price_field, $HolooProd));
                                        array_push($messages, 'قیمت محصول با هلو منطبق نیست.');
                                        array_push($messages_code, 0);
                                    }



                                    if ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProdVariation->name != trim($this->arabicToPersian($HolooProd->a_Name))) {
                                        //dd($WCProdVariation->name.'-'.trim($this->arabicToPersian($HolooProd->a_Name)));
                                        array_push($messages, 'نام محصول با هلو منطبق نیست.');
                                        array_push($messages_code, 1);
                                    }
                                    if ((isset($config->update_product_stock) && $config->update_product_stock=="1") &&  isset($WCProdVariation->stock_quantity)  and $WCProdVariation->stock_quantity != (int)$HolooProd->exist) {
                                        array_push($messages, 'مقدار موجودی محصول با هلو منطبق نیست.');
                                        array_push($messages_code, 2);
                                    }

                                    $notneedtoProsse[]=$key;
                                    $productFind = true;
                                    break;
                                }
                            }
                            if ($productFind == false) {
                                # if product dont find
                                array_push($messages, 'کد هلو ثبت شده برای این محصول در نرم افزار هلو یافت نشد.');
                                array_push($messages_code, 3);
                            }

                            if (count($messages_code)>0) {
                                array_push(
                                    $products,
                                    [
                                        'msg' => $messages,
                                        'product_name' =>"متغییر ". $WCProd->name,
                                        'price' => $WCProdVariation->regular_price,
                                        'amount' => (isset($WCProdVariation->stock_quantity)) ? $WCProdVariation->stock_quantity : 0,
                                        'holo_code' => $wcHolooCode ,
                                        'woocommerce_product_id' => $WCProd->id.':'.$WCProdVariation->id,
                                        'msg_code' => $messages_code,

                                    ]
                                );
                                $counter_confid=$counter_confid+1;
                            }
                        }
                    }
                }
                $counter_confid=$counter_confid+1;
            }
        }

        if($counter_confid==0){
            return $this->sendResponse('عدم انطباقی در محصولات یافت نشد', Response::HTTP_OK, ['result' => [$counter_wc]]);
        }
        else{
            return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK, ['result' => $products]);
        }
    }

    /*
     * Fetch All Products from Woocommerce
     */
    private function fetchAllHolloProds(){

        $response=app('App\Http\Controllers\HolooController')->fetchAllHolloProds();
        return json_decode($response);
    }

    public function fetchAllWCProds($published=false,$category=null)
    {
        $user=auth()->user();
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
                CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products?'.$status.$category.'meta=_holo_sku&page='.$page.'&per_page=10000',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERAGENT => 'Holoo',
                CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            ));

            $response = curl_exec($curl);
            //log::info($response);
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

    public function get_tabdel_vahed(){
        $user=auth()->user();
        // log::alert($user->holo_unit);
        if ($user->holo_unit=="rial" and $user->plugin_unit=="toman"){
            return 0.1;
        }
        elseif ($user->holo_unit=="toman" and $user->plugin_unit=="rial"){
            return 10;
        }
        else{
            return 1;
        }

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
}
