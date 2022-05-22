<?php

namespace App\Http\Controllers;

use App\Jobs\test;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Http\Request;
use App\Jobs\UpdateProductFind;
use App\Jobs\UpdateProductsUser;
use App\Jobs\createSingleProduct;
use App\Jobs\updateWCSingleProduct;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;

use phpDocumentor\Reflection\Types\This;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Client\HttpClientException;

class WCController extends Controller
{

    /*
     * Fetch All Products from Woocommerce
     */
    public function fetchAllWCProducts()
    {

        $all_products=$this->fetchAllWCProds();

        if ($all_products) {
            return $this->sendResponse('اطلاعات تمامی محصولات', Response::HTTP_OK, ['response' => $all_products]);
        }

        return $this->sendResponse('مشکل در ارسال و دریافت ریسپانس', Response::HTTP_NOT_ACCEPTABLE, null);
    }

    /*
     * Fetch Single Product with ID from Woocommerce
     * @params: id
     */
    public function fetchSingleProduct($id)
    {

        $user=auth()->user();


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products/' . $id . '/variations/' . $id . '?context=view&context=view',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
        ));

        $response = curl_exec($curl);
        if ($response) {
            $decodedResponse = json_decode($response);
            curl_close($curl);
            return $this->sendResponse('اطلاعات محصول مورد نظر', Response::HTTP_OK, ['response' => $decodedResponse]);
        }
        curl_close($curl);
        return $this->sendResponse('مشکل در ارسال و دریافت ریسپانس', Response::HTTP_NOT_ACCEPTABLE, null);
    }

    public function fetchVariantSingleProduct($id)
    {

        $user=auth()->user();


        $curl = curl_init();
        ///wc/v3/products/:product_id/variations?
        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products/' . $id . '/variations/' . $id . '?context=view&context=view',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
        ));

        $response = curl_exec($curl);
        if ($response) {
            $decodedResponse = json_decode($response);
            curl_close($curl);
            return $this->sendResponse('اطلاعات محصول مورد نظر', Response::HTTP_OK, ['response' => $decodedResponse]);
        }
        curl_close($curl);
        return $this->sendResponse('مشکل در ارسال و دریافت ریسپانس', Response::HTTP_NOT_ACCEPTABLE, null);
    }


    /*
     * Create Single (and simple) product into Woocommerce
     */
    public function createSingleProduct($param,$categories=null,$type="simple",$cluster=[])
    {
        $user=auth()->user();

        $meta = array(
            (object)array(
                'key' => '_holo_sku',
                'value' => $param["holooCode"]
            )
        );
        if ($type=="variable") {
            $options=$this->variableOptions($cluster);
            $attributes = array(
                (object)array(
                    'id'        => 5,
                    'variation' => true,
                    'visible'   => true,
                    'options'   => $options,
                )
            );
            if ($categories !=null) {
                $category=array(
                    (object)array(
                        'id' => $categories["id"],
                        "name" => $categories["name"],
                    )
                );
                $data = array(
                    'name' => $param["holooName"],
                    'type' => $type,
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'categories' => $category,
                    'attributes' => $attributes,
                    "manage_stock" => true,
                );
            }
            else{
                $data = array(
                    'name' => $param["holooName"],
                    'type' => $type,
                    'regular_price' => $param["regular_price"],
                    'stock_quantity' => $param["stock_quantity"],
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'attributes' => $attributes,
                    "manage_stock" => true,
                );
            }
        }
        else {

            if ($categories !=null) {
                $category=array(
                    (object)array(
                        'id' => $categories["id"],
                        //"name" => $categories["name"],
                    )
                );
                $data = array(
                    'name' => $param["holooName"],
                    'type' => $type,
                    'regular_price' => $param["regular_price"],
                    'stock_quantity' => $param["stock_quantity"],
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'categories' => $category,
                    "manage_stock" => true,
                );
            }
            else{
                $data = array(
                    'name' => $param["holooName"],
                    'type' => $type,
                    'regular_price' => $param["regular_price"],
                    'stock_quantity' => $param["stock_quantity"],
                    'status' => 'draft',
                    'meta_data' => $meta,
                    "manage_stock" => true,
                );
            }
        }


        $data = json_encode($data);
        //return response($data);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $decodedResponse = ($response) ?? json_decode($response);

        if ($response && isset($decodedResponse->id)){

            if ($type=="variable") {
               $a= $this->AddProductVariation($decodedResponse->id,$param,$cluster);
               return $this->sendResponse('محصول متغییر مورد نظر با موفقیت در سایت ثبت شد.', Response::HTTP_OK, ['response' => $a]);
            }
            return $this->sendResponse('محصول مورد نظر با موفقیت در سایت ثبت شد.', Response::HTTP_OK, ['response' => $decodedResponse]);
        }

        return $this->sendResponse('مشکل در ارسال و دریافت ریسپانس', Response::HTTP_NOT_ACCEPTABLE, $response);

    }

    public function compareProductsFromWoocommerceToHoloo(Request $config){
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        Log::info(json_encode($config->all()));
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


        //$callApi = $this->fetchCategoryHolloProds($config->product_cat);
        $callApi = $this->fetchAllHolloProds();
        $HolooProds = $callApi->result;
        //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $callApi);
        $counter_confid=0;
        $products = [];
        $notneedtoProsse=[];
        foreach ($WCProds as $WCProd) {
            //array_push($products,$WCProd->id);
            if ($counter_confid==$config->per_page) {
                break;
            }
            if (count($WCProd->meta_data)>0) {
                //dd($WCProd->meta_data);

                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');


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
                        $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                        //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $wholesale_customer_wholesale_price);
                        if ($wcHolooCode == $HolooProd->a_Code) {
                            if (
                            isset($config->update_product_price) && $config->update_product_price=="1" &&
                            (
                            (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                            (isset($config->special_price_field) && (int)$WCProd->sale_price!=0 && (int)$WCProd->sale_price<(int)$WCProd->regular_price && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                            (isset($config->wholesale_price_field) && $wholesale_customer_wholesale_price && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
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

        if($counter_confid==0){
            return $this->sendResponse('عدم انطباقی در محصولات یافت نشد', Response::HTTP_OK, ['result' => []]);
        }
        else{
            return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK, ['result' => $products]);
        }
    }

    public function compareProductsFromWoocommerceToHoloo2(Request $config){
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        //Log::error(json_encode($config));

        foreach ($config->product_cat as $holoo_cat=>$wc_cat) {
            if ($wc_cat=="") continue;

            $callApi = $this->fetchAllWCProds(true,$wc_cat);
            $WCProds = $callApi;
            if (!$WCProds) continue;

            $callApi = $this->fetchCategoryHolloProds([$holoo_cat=>$wc_cat]);
            $HolooProds = $callApi;
            //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $WCProds);
            log::info([$holoo_cat=>$wc_cat]);

            if (!$HolooProds) continue;

            #return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  isset($config->wholesale_price_field));
            $counter_confid=0;
            $products = [];
            foreach ($WCProds as $WCProd) {
                //array_push($products,$WCProd->id);
                if ($counter_confid==30) {
                    break;
                }
                if (count($WCProd->meta_data)>0) {
                    //dd($WCProd->meta_data);

                    $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');


                    if ($wcHolooCode!=null) {
                        $messages = [];
                        $messages_code = [];

                        $productFind = false;
                        foreach ($HolooProds as $key=>$HolooProd) {
                            $HolooProd=(object) $HolooProd;
                            //0 "قیمت محصول با هلو منطبق نیست"
                            //1 "نام محصول با هلو منطبق نیست"
                            //2 "مقدار موجودی محصول با هلو منطبق نیست"
                            //3 "کد هلو ثبت شده برای این محصول در نرم افزار هلو یافت نشد"
                            $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                            //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $wholesale_customer_wholesale_price);
                            if ($wcHolooCode == $HolooProd->a_Code) {
                                if (
                                isset($config->update_product_price) && $config->update_product_price=="1" &&
                                (
                                (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                                (isset($config->special_price_field) && (int)$WCProd->sale_price!=0 && (int)$WCProd->sale_price<(int)$WCProd->regular_price && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                                (isset($config->wholesale_price_field) && $wholesale_customer_wholesale_price && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
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

                                unset($HolooProds[$key]);
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
        }



        if($counter_confid==0){
            return $this->sendResponse('عدم انطباقی در محصولات یافت نشد', Response::HTTP_OK, ['result' => []]);
        }
        else{
            return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK, ['result' => $products]);
        }
    }

    public function compareProductsFromWoocommerceToHoloo3(Request $config){
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        //Log::error(json_encode($config));
        // $size = count(array_filter($config->product_cat, ""));
        // $size_cat = count($config->product_cat);

        // if ($size>$size_cat){
        //     $this->compareProductsFromWoocommerceToHoloo2($config);
        // }

        $callApi = $this->fetchAllWCProds(true);
        $WCProds = $callApi;



        $callApi = $this->fetchAllHolloProds();
        $HolooProds = $callApi->result;

        //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $HolooProds);
        $counter_confid=0;
        $products = [];
        foreach ($WCProds as $WCProd) {
            //array_push($products,$WCProd->id);
            if ($counter_confid==30) {
                break;
            }
            if (count($WCProd->meta_data)>0) {
                //dd($WCProd->meta_data);

                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');


                if ($wcHolooCode!=null) {
                    $messages = [];
                    $messages_code = [];

                    $productFind = false;
                    foreach ($HolooProds as $key=>$HolooProd) {
                        $HolooProd= $HolooProd;
                        //0 "قیمت محصول با هلو منطبق نیست"
                        //1 "نام محصول با هلو منطبق نیست"
                        //2 "مقدار موجودی محصول با هلو منطبق نیست"
                        //3 "کد هلو ثبت شده برای این محصول در نرم افزار هلو یافت نشد"
                        $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                        //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $wholesale_customer_wholesale_price);
                        if ($wcHolooCode == $HolooProd->a_Code) {
                            if (
                            isset($config->update_product_price) && $config->update_product_price=="1" &&
                            (
                            (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                            (isset($config->special_price_field) && (int)$WCProd->sale_price!=0 && (int)$WCProd->sale_price<(int)$WCProd->regular_price && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                            (isset($config->wholesale_price_field) && $wholesale_customer_wholesale_price && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
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

                            unset($HolooProds[$key]);
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

        if($counter_confid==0){
            return $this->sendResponse('عدم انطباقی در محصولات یافت نشد', Response::HTTP_OK, ['result' => []]);
        }
        else{
            return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK, ['result' => $products]);
        }
    }

    public function compareProductsFromWoocommerceToHoloo4(Request $config){
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        Log::info(json_encode($config->all()));
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


        //$callApi = $this->fetchCategoryHolloProds($config->product_cat);
        $callApi = $this->fetchAllHolloProds();
        $HolooProds = $callApi->result;
        //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $callApi);
        $counter_confid=0;
        $products = [];
        $notneedtoProsse=[];
        foreach ($WCProds as $WCProd) {
            //array_push($products,$WCProd->id);
            if ($counter_confid==$config->per_page) {
                break;
            }
            if (count($WCProd->meta_data)>0) {
                //dd($WCProd->meta_data);

                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');


                if ($wcHolooCode!=null and !is_array($wcHolooCode)) {
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
                        $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                        //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $wholesale_customer_wholesale_price);
                        if ($wcHolooCode == $HolooProd->a_Code) {
                            if (
                            isset($config->update_product_price) && $config->update_product_price=="1" &&
                            (
                            (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                            (isset($config->special_price_field) && (int)$WCProd->sale_price!=0 && (int)$WCProd->sale_price<(int)$WCProd->regular_price && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                            (isset($config->wholesale_price_field) && $wholesale_customer_wholesale_price && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
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
                else if ($wcHolooCode!=null and is_array($wcHolooCode)) {
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
                        $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                        //return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK,  $wholesale_customer_wholesale_price);
                        if ($wcHolooCode == $HolooProd->a_Code) {
                            if (
                            isset($config->update_product_price) && $config->update_product_price=="1" &&
                            (
                            (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                            (isset($config->special_price_field) && (int)$WCProd->sale_price!=0 && (int)$WCProd->sale_price<(int)$WCProd->regular_price && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                            (isset($config->wholesale_price_field) && $wholesale_customer_wholesale_price && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
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

        if($counter_confid==0){
            return $this->sendResponse('عدم انطباقی در محصولات یافت نشد', Response::HTTP_OK, ['result' => []]);
        }
        else{
            return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK, ['result' => $products]);
        }
    }


    private function fetchAllHolloProds(){

        $response=app('App\Http\Controllers\HolooController')->fetchAllHolloProds();
        return json_decode($response);
    }
    private function fetchCategoryHolloProds($cat){

        $response=app('App\Http\Controllers\HolooController')->fetchCategoryHolloProds($cat);
        return $response;
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

    public function sendResponse($message, $responseCode, $response)
    {
        return response([
            'message' => $message,
            'responseCode' => $responseCode,
            'response' => $response
        ], $responseCode);
    }

    public static function arabicToPersian($string)
    {

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

    /*
     * Update Single Product
     */
    public function updateWCSingleProduct($params){
        $user=auth()->user();
        $curl = curl_init();
        $meta = array(
            (object)array(
                'key' => 'wholesale_customer_wholesale_price',
                'value' => $params["wholesale_customer_wholesale_price"]
            )
        );
        $data=[
            "regular_price"=>(string)$params['regular_price'],
            "price"=>$params['price'],
            "sale_price"=>((int)$params['sale_price']==0) ? null : (string) $params['sale_price'] ,
            //"wholesale_customer_wholesale_price"=>$params['wholesale_customer_wholesale_price'],
            "stock_quantity"=>(int)$params['stock_quantity'],
            "name"=>$params['name'],
            "meta_data"=>$meta,
        ];
        $data = json_encode($data);
        $this->recordLog('update single product',$data);



        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products/'. $params['id'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
              //'Content-Type: multipart/form-data',
              'Content-Type: application/json',
            ),
        ));
        $response = curl_exec($curl);


        $response = json_decode($response);
        $this->recordLog('update single product',json_encode($response));
        curl_close($curl);
        return $response;
        //$this->sendResponse('محصول به روز شد', Response::HTTP_OK, ['res' => $response]);
    }

    /*
     * Update All Products
     */
    public function updateAllProductFromHolooToWC(Request $config)
    {
        //return $config->special_price_field;
        $user=auth()->user();
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        set_time_limit(0);
        //$callApi = $this->fetchAllHolloProds();
        $callApi = $this->fetchCategoryHolloProds($config->product_cat);
        $holooProducts = $callApi;

        $callApi = $this->fetchAllWCProds(true);
        $wcProducts = $callApi;
        $response_product=[];
        //return $this->sendResponse('داده در سمت سرور موجود نیست', Response::HTTP_OK,$wcProducts);
        if (count($wcProducts)==0 or count($holooProducts)==0) {
            return $this->sendResponse('داده در سمت سرور موجود نیست', Response::HTTP_OK,null);
        }
        $wcholooCounter=0;
        $holooFinded=0;
        $conflite=0;
        $wcCount=0;
        $notneedtoProsse=[];
        foreach ($wcProducts as $WCProd) {
            if (count($WCProd->meta_data)>0) {

                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                if ($wcHolooCode) {
                    $wcholooCounter=$wcholooCounter+1;
                    $productFind = false;
                    foreach ($holooProducts as $key=>$HolooProd) {
                        //if( array_search($key, $notneedtoProsse)) continue;

                        $HolooProd=(object) $HolooProd;
                        if ($wcHolooCode == $HolooProd->a_Code) {
                            $holooFinded=$holooFinded+1;
                            $productFind = true;
                            $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');
                            // Log::info((int)$WCProd->regular_price);
                            // log::info($this->get_price_type($config->sales_price_field,$HolooProd));
                            // log::info((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd));
                            // log::info(isset($config->sales_price_field));
                            // log::info(isset($config->update_product_price) && $config->update_product_price=="1");
                            if (
                            isset($config->update_product_price) && $config->update_product_price=="1" &&
                            (
                            (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                            (isset($config->special_price_field) && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                            (isset($config->wholesale_price_field) && $wholesale_customer_wholesale_price && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
                            ) or
                            ((isset($config->update_product_stock) && $config->update_product_stock=="1") &&  isset($WCProd->stock_quantity)  and $WCProd->stock_quantity != (int)$HolooProd->exist) or
                            ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->a_Name)))

                            ){

                            // if (
                            //     ((isset($config->update_product_stock) && $config->update_product_stock=="1") && (int) $HolooProd->exist>0 and isset($WCProd->stock_quantity) and  $WCProd->stock_quantity !=(int) $HolooProd->exist) or
                            //     ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->a_Name))) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd))) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && isset($wholesale_customer_wholesale_price) && ((int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) )
                            // ) {
                                $conflite=$conflite+1;
                                # if product holoo was not same with product hoocomrece
                                // $data = [
                                //     'id' => $WCProd->id,
                                //     'name' => (isset($config->update_product_name) && $config->update_product_name=="1") && ($WCProd->name != $this->arabicToPersian($HolooProd->a_Name)) ? urlencode($this->arabicToPersian($HolooProd->a_Name)) :null,
                                //     'regular_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ($WCProd->regular_price != $HolooProd->sel_Price) ? $HolooProd->sel_Price ?? 0 : null,
                                //     'stock_quantity' =>(isset($config->update_product_stock) && $config->update_product_stock=="1") && (isset($WCProd->stock_quantity) and $WCProd->stock_quantity != $HolooProd->exist) ? (int)$HolooProd->exist ?? 0 : null,
                                // ];


                                $data = [
                                    'id' => $WCProd->id,
                                    'name' =>(isset($config->update_product_name) && $config->update_product_name=="1") && ($WCProd->name != $this->arabicToPersian($HolooProd->a_Name)) ? $this->arabicToPersian($HolooProd->a_Name) :$WCProd->name,
                                    'regular_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) ? $this->get_price_type($config->sales_price_field,$HolooProd) : (int)$WCProd->regular_price,
                                    'price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                    'sale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                    'wholesale_customer_wholesale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && (isset($wholesale_customer_wholesale_price) && (int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) ? $this->get_price_type($config->wholesale_price_field,$HolooProd)  : ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                                    'stock_quantity' => (isset($config->update_product_stock) && $config->update_product_stock=="1" && (int) $HolooProd->exist>0 and isset($WCProd->stock_quantity)) ? (int) $HolooProd->exist : 0,
                                ];
                                log::info("add new update product to queue for product ");
                                log::info("for website id : ".$user->siteUrl);


                                // log::info($data);
                                //$this->updateWCSingleProduct($data);
                                //$data=[(int)$WCProd->sale_price  ,$this->get_price_type($config->special_price_field,$HolooProd),((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)),$config->special_price_field];
                                //return $this->sendResponse('همه محصولات به روز رسانی شدند.', Response::HTTP_OK, $data);
                                UpdateProductsUser::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$data,$wcHolooCode)->onQueue("high");
                                // test::dispatch($user->siteUrl,$user->consumerKey,$user->consumerSecret)->onQueue("high");
                                //dispatch((new UpdateProductsUser($user,$data,$WCProd->meta_data[0]->value))->onConnection('queue')->onQueue('high'));

                            // if (
                            //     ((isset($config->update_product_stock) && $config->update_product_stock=="1") && (int) $HolooProd->exist>0 and isset($WCProd->stock_quantity) and  $WCProd->stock_quantity !=(int) $HolooProd->exist) or
                            //     ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->a_Name))) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd))) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && isset($wholesale_customer_wholesale_price) && ((int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) )
                            // ) {

                                $notneedtoProsse[]=$key;
                                //unset($holooProducts[$key]);
                                array_push($response_product,$wcHolooCode);

                            }
                            else{
                                $notneedtoProsse[]=$key;
                                //unset($holooProducts[$key]);
                            }
                        }

                    }


                }
            }
        }
        if (count($response_product)>0) {
            return $this->sendResponse('همه محصولات به روز رسانی شدند.', Response::HTTP_OK, ["result"=>["msg_code"=>1,"count"=>count($response_product),"products_cods"=>$response_product,"wcholoo"=>$wcholooCounter,"holooFinded"=>$holooFinded,'conflite'=>$conflite]]);
        }
        else{
            return $this->sendResponse('تمامی محصولات به روز هستند.', Response::HTTP_OK, ["result"=>["msg_code"=>0]]);
        }
    }


    public function updateAllProductFromHolooToWC3(Request $config)
    {
        //return $config->special_price_field;
        $user=auth()->user();
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        set_time_limit(0);
        UpdateProductFind::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"serial"=>$user->serial,"apiKey"=>$user->apiKey,"holooDatabaseName"=>$user->holooDatabaseName,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret,"cloudTokenExDate"=>$user->cloudTokenExDate,"cloudToken"=>$user->cloudToken],$config->product_cat,$config,1)->onQueue("high");

        return $this->sendResponse('درخواست به روزرسانی محصولات با موفقیت دریافت شد ', Response::HTTP_OK, ["result"=>["msg_code"=>0]]);

    }



    public function updateAllProductFromHolooToWC2(Request $config)
    {
        //return $config->special_price_field;
        $user=auth()->user();
        ini_set('max_execution_time',0); // 120 (seconds) = 2 Minutes
        set_time_limit(0);
        //$callApi = $this->fetchAllHolloProds();
        if(!isset($config->param)){

            if (($key = array_search("", $config->product_cat)) !== false) {
                unset($config->product_cat[$key]);
            }
            // $count=count($config->product_cat);
            // if ($count>0) {
            //     return $this->sendResponse('هیچ گروه محصولی جهت به روز رسانی یافت نشد', Response::HTTP_OK, ["result"=>["msg_code"=>0]]);
            // }
            $param=[
                "categorys"=>$config->product_cat,
                "count"=>0,
            ];
            $response=[
                "param" => $param,
                "url" =>route('updateAllProductFromHolooToWC2'),
                "message"=>"در حال دریافت اطلاعات در سرورهای هلو لطفا منتظر بمانید"
            ];
            $body=$config->all();
            $body=array_merge($body,$response);

            return $this->sendResponse('اغاز عملیات به روز رسانی لطفا منتظر بمانید', Response::HTTP_OK,$body);
        }


        foreach ($config->param->categorys->product_cat as $holoo_cat=>$wc_cat){

            if ($wc_cat=="") continue;
            $callApi = $this->fetchAllWCProds(true,$wc_cat);
            $wcProducts = $callApi;
            if (count($wcProducts)==0) continue;

            $callApi = $this->fetchCategoryHolloProds([$holoo_cat=>$wc_cat]);
            $holooProducts = $callApi;

            $config->param->categorys->product_cat = array_shift($config->param->categorys->product_cat);
            $config->param->count++;

            if (count($config->param->categorys->product_cat)==0){
                return $this->sendResponse('همه محصولات به روز رسانی شدند.', Response::HTTP_OK, ["result"=>["msg_code"=>1,"count"=>$config->param->count]]);
            }
            else{
                if (($key = array_search("", $config->product_cat)) !== false) {
                    unset($config->product_cat[$key]);
                }

                $param=[
                    "config"=>$config,
                    "categorys"=>$config->product_cat,
                    "count"=> $config->param->count,
                ];
                $response=[
                    "param" => $param,
                    "url" =>route('updateAllProductFromHolooToWC2'),
                    "message"=>$config->param->count." تا محصول تاکنون به روز شدند لطفا منتظر بمانید"
                ];

                return $this->sendResponse('عملیات به روز رسانی لطفا منتظر بمانید', Response::HTTP_OK,$response);
            }
        }

        $response_product=[];
        if (count($wcProducts)==0 or count($holooProducts)==0) {
            return $this->sendResponse('داده در سمت سرور موجود نیست', Response::HTTP_OK,null);
        }
        $wcholooCounter=0;
        $holooFinded=0;
        $conflite=0;
        $wcCount=0;
        foreach ($wcProducts as $WCProd) {
            if (count($WCProd->meta_data)>0) {

                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                if ($wcHolooCode) {
                    $wcholooCounter=$wcholooCounter+1;
                    $productFind = false;
                    foreach ($holooProducts as $key=>$HolooProd) {
                        $HolooProd=(object) $HolooProd;
                        if ($wcHolooCode == $HolooProd->a_Code) {
                            $holooFinded=$holooFinded+1;
                            $productFind = true;
                            $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');
                            // Log::info((int)$WCProd->regular_price);
                            // log::info($this->get_price_type($config->sales_price_field,$HolooProd));
                            // log::info((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd));
                            // log::info(isset($config->sales_price_field));
                            // log::info(isset($config->update_product_price) && $config->update_product_price=="1");
                            if (
                            isset($config->update_product_price) && $config->update_product_price=="1" &&
                            (
                            (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                            (isset($config->special_price_field) && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                            (isset($config->wholesale_price_field) && $wholesale_customer_wholesale_price && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
                            ) or
                            ((isset($config->update_product_stock) && $config->update_product_stock=="1") &&  isset($WCProd->stock_quantity)  and $WCProd->stock_quantity != (int)$HolooProd->exist) or
                            ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->a_Name)))

                            ){

                            // if (
                            //     ((isset($config->update_product_stock) && $config->update_product_stock=="1") && (int) $HolooProd->exist>0 and isset($WCProd->stock_quantity) and  $WCProd->stock_quantity !=(int) $HolooProd->exist) or
                            //     ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->a_Name))) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd))) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && isset($wholesale_customer_wholesale_price) && ((int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) )
                            // ) {
                                $conflite=$conflite+1;
                                # if product holoo was not same with product hoocomrece
                                // $data = [
                                //     'id' => $WCProd->id,
                                //     'name' => (isset($config->update_product_name) && $config->update_product_name=="1") && ($WCProd->name != $this->arabicToPersian($HolooProd->a_Name)) ? urlencode($this->arabicToPersian($HolooProd->a_Name)) :null,
                                //     'regular_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ($WCProd->regular_price != $HolooProd->sel_Price) ? $HolooProd->sel_Price ?? 0 : null,
                                //     'stock_quantity' =>(isset($config->update_product_stock) && $config->update_product_stock=="1") && (isset($WCProd->stock_quantity) and $WCProd->stock_quantity != $HolooProd->exist) ? (int)$HolooProd->exist ?? 0 : null,
                                // ];


                                $data = [
                                    'id' => $WCProd->id,
                                    'name' =>(isset($config->update_product_name) && $config->update_product_name=="1") && ($WCProd->name != $this->arabicToPersian($HolooProd->a_Name)) ? $this->arabicToPersian($HolooProd->a_Name) :$WCProd->name,
                                    'regular_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) ? $this->get_price_type($config->sales_price_field,$HolooProd) : (int)$WCProd->regular_price,
                                    'price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                    'sale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                    'wholesale_customer_wholesale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && (isset($wholesale_customer_wholesale_price) && (int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) ? $this->get_price_type($config->wholesale_price_field,$HolooProd)  : ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                                    'stock_quantity' => (isset($config->update_product_stock) && $config->update_product_stock=="1" && (int) $HolooProd->exist>0 and isset($WCProd->stock_quantity)) ? (int) $HolooProd->exist : 0,
                                ];
                                log::info("add new update product to queue for product ".$WCProd->id,$user->siteUrl);
                                // log::info($data);
                                //$this->updateWCSingleProduct($data);
                                //$data=[(int)$WCProd->sale_price  ,$this->get_price_type($config->special_price_field,$HolooProd),((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)),$config->special_price_field];
                                //return $this->sendResponse('همه محصولات به روز رسانی شدند.', Response::HTTP_OK, $data);
                                UpdateProductsUser::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$data,$wcHolooCode)->onQueue("high");
                                // test::dispatch($user->siteUrl,$user->consumerKey,$user->consumerSecret)->onQueue("high");
                                //dispatch((new UpdateProductsUser($user,$data,$WCProd->meta_data[0]->value))->onConnection('queue')->onQueue('high'));

                            // if (
                            //     ((isset($config->update_product_stock) && $config->update_product_stock=="1") && (int) $HolooProd->exist>0 and isset($WCProd->stock_quantity) and  $WCProd->stock_quantity !=(int) $HolooProd->exist) or
                            //     ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->a_Name))) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd))) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ) or
                            //     ((isset($config->update_product_price) && $config->update_product_price=="1") && isset($wholesale_customer_wholesale_price) && ((int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) )
                            // ) {


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
        }
        if (count($response_product)>0) {
            return $this->sendResponse('همه محصولات به روز رسانی شدند.', Response::HTTP_OK, ["result"=>["msg_code"=>1,"count"=>count($response_product),"products_cods"=>$response_product,"wcholoo"=>$wcholooCounter,"holooFinded"=>$holooFinded,'conflite'=>$conflite]]);
        }
        else{
            return $this->sendResponse('تمامی محصولات به روز هستند.', Response::HTTP_OK, ["result"=>["msg_code"=>0]]);
        }
    }
    public function get_all_holoo_code_exist(){
        $wcProducts=$this->fetchAllWCProds();
        $response_products=[];
        foreach ($wcProducts as $WCProd) {
            if (count($WCProd->meta_data)>0) {
                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                if ($wcHolooCode) {
                    $response_products[]=$wcHolooCode;
                }
            }
        }

        return $response_products;
    }

    public function holooWebHook(Request $request){
        // {
        //     "Dbname": "S11216632_holoo1",
        //     "Table": "Article",
        //     "MsgType": "0",
        //     "MsgValue": "0101001,0907057,0914097,0914098,0914099",
        //     "MsgError": "",
        //     "Message": "درج کالا"
        //   }
        // {
        //     "Dbname": "S11216632_holoo1",
        //     "Table": "Article",
        //     "MsgType": "1",
        //     "MsgValue": "0101001,0907057,0914097,0914098,0914099",
        //     "MsgError": "",
        //     "Message": "ویرایش"
        //   }
        log::info($request);
        log::info("webhook resived");
        $hook = new Webhook();

        if(isset($request->Table) && strtolower($request->Table)=="article" && ($request->MsgType==1 or $request->MsgType==0)){
            $Dbname=explode("_",$request->Dbname);
            $HolooUser=$Dbname[0];
            $HolooDb=$Dbname[1];
            $user = User::where(['holooDatabaseName'=>$HolooDb,'holooCustomerID'=>$HolooUser,])
            ->first();
            $hook->content = json_encode($request->all());

            $hook->user_id = ($user->id) ?? null;
            $hook->save();
            if($user==null){
                $this->sendResponse('کاربر مورد نظر یافت نشد', Response::HTTP_OK,[]);
            }
            auth()->login($user);
            $HolooIDs=explode(",",$request->MsgValue);
            $HolooIDs=array_reverse($HolooIDs);
            //array_shift($HolooIDs);

            $config=json_decode($this->getWcConfig());



            if ($request->MsgType==0 && isset($config->insert_new_product) && $config->insert_new_product==1) {
                $HolooProds  = $this->fetchCategoryHolloProds($config->product_cat);
            }
            foreach($HolooIDs as $holooID){

                $WCProd=$this->getWcProductWithHolooId($holooID);

                if ($request->MsgType==0 && $WCProd) {    // if ($request->MsgType==1) {


                    //update product

                    $holooProduct=app('App\Http\Controllers\HolooController')->GetSingleProductHoloo($holooID);
                    $holooProduct=json_decode($holooProduct);
                    $WCProd=$this->getWcProductWithHolooId($holooID);
                    $WCProd=$WCProd[0];

                    if(isset($WCProd->meta_data) and count($WCProd->meta_data)>0){
                        $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');
                    }
                    else{
                        $wholesale_customer_wholesale_price=0;
                    }

                    // //return $holooProduct;
                    // $holooProduct=$this->findProduct($holooProduct,$holooID);
                    if(isset($WCProd->id) and $WCProd->id){

                        $param = [
                            'id' => $WCProd->id,
                            'name' =>(isset($config->update_product_name) && $config->update_product_name=="1") ? $this->arabicToPersian($holooProduct->result->a_Name) : $WCProd->name,
                            'regular_price' =>(isset($config->update_product_price) && $config->update_product_price=="1")  ? (string) $this->get_price_type($config->sales_price_field,$holooProduct->result): (int)$WCProd->regular_price,
                            'price' => (isset($config->update_product_price) && $config->update_product_price=="1") ? $this->get_price_type($config->special_price_field,$holooProduct->result) :(int)$WCProd->sale_price ,
                            'sale_price' =>(isset($config->update_product_price) && $config->update_product_price=="1") ? (string) $this->get_price_type($config->special_price_field,$holooProduct->result):(int)$WCProd->sale_price,
                            'wholesale_customer_wholesale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && (isset($wholesale_customer_wholesale_price)) ? $this->get_price_type($config->wholesale_price_field,$holooProduct->result): ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                            'stock_quantity' => (isset($config->update_product_stock) && $config->update_product_stock=="1" && (int) $holooProduct->result->exist>0 and isset($WCProd->stock_quantity)) ? (int) $holooProduct->result->exist : 0
                        ];



                        $response = $this->updateWCSingleProduct($param);
                        log::info("webhook update product");
                        log::info(json_encode($response));
                    }
                    else{
                        continue;
                    }

                }
                else if ($request->MsgType==0 && property_exists($config, insert_new_product) && $config->insert_new_product==1) {

                    $holooProduct=$this->findProduct($HolooProds,$holooID);
                    //dd($holooProduct);
                    if(!$holooProduct) continue;

                    $param = [
                        "holooCode" => $holooID,
                        "holooName" => $this->arabicToPersian($holooProduct->a_Name),
                        'regular_price' => (string)$this->get_price_type($config->sales_price_field,$holooProduct),
                        'price' => $this->get_price_type($config->special_price_field,$holooProduct),
                        'sale_price' => (string)$this->get_price_type($config->special_price_field,$holooProduct),
                        'wholesale_customer_wholesale_price' => $this->get_price_type($config->wholesale_price_field,$holooProduct),
                        'stock_quantity' => ($holooProduct->exist>0) ? (int)$holooProduct->exist : 0,
                    ];

                    // if ((!isset($config->insert_product_with_zero_inventory) ) || (isset($config->insert_product_with_zero_inventory) && $config->insert_product_with_zero_inventory == "0")) {
                    //     $param = [
                    //         "holooCode" => $holooID,
                    //         "holooName" => $this->arabicToPersian($holooProduct->a_Name),
                    //         'regular_price' => (string)$this->get_price_type($config->sales_price_field,$holooProduct),
                    //         'price' => $this->get_price_type($config->special_price_field,$holooProduct),
                    //         'sale_price' => (string)$this->get_price_type($config->special_price_field,$holooProduct),
                    //         'wholesale_customer_wholesale_price' => $this->get_price_type($config->wholesale_price_field,$holooProduct),
                    //         'stock_quantity' => ($holooProduct->exist>0) ? (int)$holooProduct->exist : 0,
                    //     ];
                    // }
                    // elseif (isset($config->insert_product_with_zero_inventory) && $config->insert_product_with_zero_inventory == "1") {
                    //     $param = [
                    //         "holooCode" => $holooID,
                    //         "holooName" => $this->arabicToPersian($holooProduct->a_Name),
                    //         'regular_price' => (string)$this->get_price_type($config->sales_price_field,$holooProduct),
                    //         'price' => $this->get_price_type($config->special_price_field,$holooProduct),
                    //         'sale_price' => (string)$this->get_price_type($config->special_price_field,$holooProduct),
                    //         'wholesale_customer_wholesale_price' => $this->get_price_type($config->wholesale_price_field,$holooProduct),
                    //         'stock_quantity' => ($holooProduct->exist>0) ? (int)$holooProduct->exist : 0,
                    //     ];

                    // }
                    // else{
                    //     continue;
                    // }


                    if(isset($holooProduct->Poshak)){
                        $response=$this->createSingleProduct($param,null,"variable",$holooProduct->Poshak);
                    }
                    else{
                        $response=$this->createSingleProduct($param);
                    }
                    log::info("product insert");
                    log::info(json_encode($response));

                }

            }
            $this->sendResponse('محصول با موفقیت دریافت شدند', Response::HTTP_OK,[]);
        }
    }

    public function holooWebHook1(Request $request){
        // {
        //     "Dbname": "S11216632_holoo1",
        //     "Table": "Article",
        //     "MsgType": "0",
        //     "MsgValue": "0101001,0907057,0914097,0914098,0914099",
        //     "MsgError": "",
        //     "Message": "درج کالا"
        //   }
        // {
        //     "Dbname": "S11216632_holoo1",
        //     "Table": "Article",
        //     "MsgType": "1",
        //     "MsgValue": "0101001,0907057,0914097,0914098,0914099",
        //     "MsgError": "",
        //     "Message": "ویرایش"
        //   }
        log::info($request);
        log::info("webhook resived");

        if(isset($request->Table) && strtolower($request->Table)=="article" && ($request->MsgType==1 or $request->MsgType==0)){
            $Dbname=explode("_",$request->Dbname);
            $HolooUser=$Dbname[0];
            $HolooDb=$Dbname[1];
            $user = User::where(['holooDatabaseName'=>$HolooDb,'holooCustomerID'=>$HolooUser,])
            ->first();
            if (!$user) {
                $this->sendResponse('کاربر مورد نظر یافت نشد', Response::HTTP_NOT_FOUND,[]);
            }

            auth()->login($user);

            $HolooIDs=explode(",",$request->MsgValue);
            $HolooIDs=array_reverse($HolooIDs);
            //array_shift($HolooIDs);

            $config=json_decode($this->getWcConfig());



            if ($request->MsgType==0 && $config->insert_new_product==1) {
                $HolooProds  = $this->fetchCategoryHolloProds($config->product_cat);
            }
            foreach($HolooIDs as $holooID){

                $WCProd=$this->getWcProductWithHolooId($holooID);

                if ($request->MsgType==0 && $WCProd) {    // if ($request->MsgType==1) {


                    //update product

                    $holooProduct=app('App\Http\Controllers\HolooController')->GetSingleProductHoloo($holooID);
                    $holooProduct=json_decode($holooProduct);
                    $WCProd=$this->getWcProductWithHolooId($holooID);
                    $WCProd=$WCProd[0];

                    if(isset($WCProd->meta_data) and count($WCProd->meta_data)>0){
                        $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');
                    }
                    else{
                        $wholesale_customer_wholesale_price=0;
                    }

                    // //return $holooProduct;
                    // $holooProduct=$this->findProduct($holooProduct,$holooID);
                    if(isset($WCProd->id) and $WCProd->id){

                        $param = [
                            'id' => $WCProd->id,
                            'name' =>(isset($config->update_product_name) && $config->update_product_name=="1") ? $this->arabicToPersian($holooProduct->result->a_Name) : $WCProd->name,
                            'regular_price' =>(isset($config->update_product_price) && $config->update_product_price=="1")  ? (string) $this->get_price_type($config->sales_price_field,$holooProduct->result): (int)$WCProd->regular_price,
                            'price' => (isset($config->update_product_price) && $config->update_product_price=="1") ? $this->get_price_type($config->special_price_field,$holooProduct->result) :(int)$WCProd->sale_price ,
                            'sale_price' =>(isset($config->update_product_price) && $config->update_product_price=="1") ? (string) $this->get_price_type($config->special_price_field,$holooProduct->result):(int)$WCProd->sale_price,
                            'wholesale_customer_wholesale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && (isset($wholesale_customer_wholesale_price)) ? $this->get_price_type($config->wholesale_price_field,$holooProduct->result): ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                            'stock_quantity' => (isset($config->update_product_stock) && $config->update_product_stock=="1" && (int) $holooProduct->result->exist>0 and isset($WCProd->stock_quantity)) ? (int) $holooProduct->result->exist : 0
                        ];


                        updateWCSingleProduct::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$param,$holooID)->onQueue("high");
                        // $response = $this->updateWCSingleProduct($param);
                        // log::info("webhook update product");
                        // log::info(json_encode($response));
                    }
                    else{
                        continue;
                    }

                }
                else if ($request->MsgType==0 && $config->insert_new_product==1) {

                    $holooProduct=$this->findProduct($HolooProds,$holooID);
                    //dd($holooProduct);
                    if(!$holooProduct) continue;

                    $param = [
                        "holooCode" => $holooID,
                        "holooName" => $this->arabicToPersian($holooProduct->a_Name),
                        'regular_price' => (string)$this->get_price_type($config->sales_price_field,$holooProduct),
                        'price' => $this->get_price_type($config->special_price_field,$holooProduct),
                        'sale_price' => (string)$this->get_price_type($config->special_price_field,$holooProduct),
                        'wholesale_customer_wholesale_price' => $this->get_price_type($config->wholesale_price_field,$holooProduct),
                        'stock_quantity' => ($holooProduct->exist>0) ? (int)$holooProduct->exist : 0,
                    ];

                    // if ((!isset($config->insert_product_with_zero_inventory) ) || (isset($config->insert_product_with_zero_inventory) && $config->insert_product_with_zero_inventory == "0")) {
                    //     $param = [
                    //         "holooCode" => $holooID,
                    //         "holooName" => $this->arabicToPersian($holooProduct->a_Name),
                    //         'regular_price' => (string)$this->get_price_type($config->sales_price_field,$holooProduct),
                    //         'price' => $this->get_price_type($config->special_price_field,$holooProduct),
                    //         'sale_price' => (string)$this->get_price_type($config->special_price_field,$holooProduct),
                    //         'wholesale_customer_wholesale_price' => $this->get_price_type($config->wholesale_price_field,$holooProduct),
                    //         'stock_quantity' => ($holooProduct->exist>0) ? (int)$holooProduct->exist : 0,
                    //     ];
                    // }
                    // elseif (isset($config->insert_product_with_zero_inventory) && $config->insert_product_with_zero_inventory == "1") {
                    //     $param = [
                    //         "holooCode" => $holooID,
                    //         "holooName" => $this->arabicToPersian($holooProduct->a_Name),
                    //         'regular_price' => (string)$this->get_price_type($config->sales_price_field,$holooProduct),
                    //         'price' => $this->get_price_type($config->special_price_field,$holooProduct),
                    //         'sale_price' => (string)$this->get_price_type($config->special_price_field,$holooProduct),
                    //         'wholesale_customer_wholesale_price' => $this->get_price_type($config->wholesale_price_field,$holooProduct),
                    //         'stock_quantity' => ($holooProduct->exist>0) ? (int)$holooProduct->exist : 0,
                    //     ];

                    // }
                    // else{
                    //     continue;
                    // }


                    if(isset($holooProduct->Poshak)){
                        createSingleProduct::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$param,$holooID,null,"variable",$holooProduct->Poshak)->onQueue("medium");
                        // $response=$this->createSingleProduct($param,null,"variable",$holooProduct->Poshak);
                    }
                    else{
                        createSingleProduct::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$param,$holooID)->onQueue("medium");
                        // $response=$this->createSingleProduct($param);
                    }
                    // log::info("product insert");
                    // log::info(json_encode($response));

                }

            }
            $this->sendResponse('محصول با موفقیت دریافت شدند', Response::HTTP_OK,[]);
        }
    }

    private function getWcProductWithHolooId($meta){
        $user=auth()->user();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products?meta=_holo_sku&value='.$meta,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
        ));

        $response = curl_exec($curl);
        $response=json_decode($response);
        curl_close($curl);
        if (isset($response)) {
            log::info(json_encode($response));
            return $response;
        }
        else{
            return null;
        }



    }

    public function getWcConfig(){
        $user=auth()->user();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wooholo/v1/data',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
        ));

        $response = curl_exec($curl);
        curl_close($curl);


        return $response;


    }

    public function migrate(){
        Artisan::call('migrate');
        return "migrate run";
    }

    public function fresh(){
        Artisan::call('migrate:fresh --seed');
        return "fresh run";
    }

    public function clearCache(){
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        return "Cache is cleared";
    }

    public function get_wc_category(){

        return json_decode($this->getWcConfig(),true)["product_cat"];
    }

    private function variableOptions($clusters){
        $options=[];

        foreach ( $clusters as $key=>$cluster){
            $options[]=$cluster->Name;
        }

        return $options;

    }

    private function AddProductVariation($id,$product,$clusters){
        $user=auth()->user();
        $curl = curl_init();

        // $data = array(
        //     'name' => $product["holooName"],
        //     'type' => $type,
        //     'regular_price' => $product["regular_price"],
        //     'stock_quantity' => $product["stock_quantity"],
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
                'description' => $this->arabicToPersian($cluster->Name),
                'regular_price' => $product["regular_price"],
                'sale_price' => $product["regular_price"],
                'stock_quantity' => $cluster->Few,
                //'status' => 'draft',
                'meta_data' => $meta,


                // 'weight' => $cluster->,
                // 'dimensions' => '<string>',
                //'meta_data' => $meta,
            );
            $data = json_encode($data);

            curl_setopt_array($curl, array(
              CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products/'.$id.'/variations',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_USERAGENT => 'Holoo',
              CURLOPT_POSTFIELDS => $data,
              CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
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


    public function testProductVar(){


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Ticket/RegisterForPartner',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 1000,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_POSTFIELDS => array('Serial' => '10304923','RefreshToken' => 'false','DeleteService' => 'false','MakeService' => 'true','RefreshKey' => 'false'),
            CURLOPT_HTTPHEADER => array(
                'apikey: E5D3A60D3689D3CB8BD8BE91E5E29E934A830C2258B573B5BC28711F3F1D4B70'
            ),
            CURLOPT_HEADER  , true
        ));

        $response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        //dd($response);
        dd($http_status);
        $response = json_decode($response);



        $HolooProd= (object)[
            "Name" => "سطح تست بندي",
            "Few" => 587,
            "fewspd" => 587,
            "fewtak" => 587,
            "BuyPrice" => 245000,
            "LastBuyPrice" => 245000,
            "SellPrice" => 560000,
            "SellPrice2" => 0,
            "SellPrice3" => 0,
            "SellPrice4" => 0,
            "SellPrice5" => 0,
            "SellPrice6" => 0,
            "SellPrice7" => 0,
            "SellPrice8" => 0,
            "SellPrice9" => 0,
            "SellPrice10" => 0,
            "CountInKarton" => 0,
            "CountInBasteh" => 0,
            "MainGroupName" => "سطح بندي اصلي",
            "MainGroupErpCode" => "bBAlfg==",
            "SideGroupName" => "سطح بندي فرعي",
            "SideGroupErpCode" => "bBAlNA1jDg0=",
            "UnitErpCode" => 0,
            "EtebarTakhfifAz" => "          ",
            "EtebarTakhfifTa" => "          ",
            "DiscountPercent" => 0,
            "DiscountPrice" => 0,
            "ErpCode" => "bBAlNA1mckd7UB4O",
            "Poshak" => [
                (object)[
                    "Id" => 4,
                    "Name" => "آبي / کوچک",
                    "Few" => 200,
                    "Min" => 0,
                    "Max" => 0,
                ],
                (object)[
                    "Id" => 5,
                    "Name" => "آبي / متوسط",
                    "Few" => 150,
                    "Min" => 0,
                    "Max" => 0,
                ],
                (object)[
                    "Id" => 6,
                    "Name" => "آبي / بزرگ",
                    "Few" => 120,
                    "Min" => 0,
                    "Max" => 0,
                ],
                (object)[
                    "Id" => 7,
                    "Name" => "سفيد / کوچک",
                    "Few" => 30,
                    "Min" => 0,
                    "Max" => 0,
                ],
                (object)[
                    "Id" => 8,
                    "Name" => "سفيد / متوسط",
                    "Few" => 59,
                    "Min" => 0,
                    "Max" => 0,
                ],
                (object)[
                    "Id" => 9,
                    "Name" => "سفيد / بزرگ",
                    "Few" => 28,
                    "Min" => 0,
                    "Max" => 0,
                ],

            ],
        ];

        if(isset($HolooProd->Poshak)){

            $param = [
                "holooCode" => $HolooProd->ErpCode,
                'holooName' => $HolooProd->Name,
                'regular_price' => (string)$HolooProd->SellPrice ?? 0,
                'price' => (string)$HolooProd->SellPrice ?? 0,
                'sale_price' => (string)$HolooProd->SellPrice ?? 0,
                'wholesale_customer_wholesale_price' => (string)$HolooProd->SellPrice ?? 0,
                'stock_quantity' => (int) $HolooProd->Few ?? 0,
            ];
           //$this->AddProductVariation(3538,$param,$HolooProd->Poshak);
           $this->createSingleProduct($param,null,"variable",$HolooProd->Poshak);
        }
    }

    public function recordLog($event, $user, $comment = null, $type = "info")
    {
        $message = $user . ' ' . $event . ' ' . $comment;
        if ($type == "info") {
            Log::info($message);
        } elseif ($type == "error") {
            Log::error($message);
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

    private function findProduct($products,$holooCode){
        foreach ($products as $product) {
            $product=(object) $product;

            if ($product->a_Code==$holooCode) {
                return $product;
            }
        }
        return null;
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

    public function get_invoice($invoice_id){
        $user=auth()->user();

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/orders/'.$invoice_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        if ($response) {
            $decodedResponse = json_decode($response);
            return $decodedResponse;
        }
        return null;


    }


    public function get_product_holooCode($wcProducts,$wc_product_id){


        foreach ($wcProducts as $WCProd) {
            if ($WCProd->id==$wc_product_id) {
                if (count($WCProd->meta_data)>0) {
                    $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                    return  $wcHolooCode;
                }
            }
        }

        return null;
    }

    public function test(){
        $user=auth()->user();
        $wcHolooCode = "0101012";

        $data=array (
            'id' => 6445,
            'name' => 'استیج 25.5 بدون پایه',
            'regular_price' => 250000,
            'price' => 0,
            'sale_price' => 0,
            'wholesale_customer_wholesale_price' => '',
            'stock_quantity' => 25,
        );
        //$s=dispatch((new UpdateProductsUser($user,$data,$wcHolooCode))->onQueue('high')->onConnection('redis'));
        $s=UpdateProductsUser::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$data,$wcHolooCode)->onQueue("high")->onConnection('redis');
        //$s=$this->queue_update($user,$data,$wcHolooCode);
        dd($s);
        return;
    }

    public function queue_update($user,$param,$flag){
        Log::info('update product for flag ' . $flag);

        $curl = curl_init();
        $meta = array(
            (object)array(
                'key' => 'wholesale_customer_wholesale_price',
                'value' => $param["wholesale_customer_wholesale_price"]
            )
        );
        $data=[
            "regular_price"=>(string)$param['regular_price'],     //problem on update all need to convert to string
            "sale_price"=>((int)$param["sale_price"]==0) ? null:(string)$param['sale_price'],           //problem on update all need to convert to string
            "price" =>$param['price'],
            "stock_quantity"=>(int)$param['stock_quantity'],
            //'wholesale_customer_wholesale_price' => $param['wholesale_customer_wholesale_price'],
            "name"=>$param['name'],
            "meta_data"=>$meta,
        ];
        log::info($data);
        $data = json_encode($data);
        //$data = json_encode($data);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products/'. $param['id'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
              //'Content-Type: multipart/form-data',
              'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);

        log::info(json_encode($response));

        curl_close($curl);
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



}
