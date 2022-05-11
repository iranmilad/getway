<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Jobs\UpdateProductsUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Client\HttpClientException;
use phpDocumentor\Reflection\Types\This;

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
        //Log::error(json_encode($config));

        $callApi = $this->fetchAllWCProds(true);
        $WCProds = $callApi;


        $callApi = $this->fetchCategoryHolloProds($config->product_cat);
        $HolooProds = $callApi;
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
                        $HolooProd=(object) $HolooProd;
                        //0 "قیمت محصول با هلو منطبق نیست"
                        //1 "نام محصول با هلو منطبق نیست"
                        //2 "مقدار موجودی محصول با هلو منطبق نیست"
                        //3 "کد هلو ثبت شده برای این محصول در نرم افزار هلو یافت نشد"

                        if ($wcHolooCode == $HolooProd->a_Code) {
                            if (
                            isset($config->update_product_price) && $config->update_product_price=="1" &&
                            (
                            (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                            (isset($config->special_price_field) && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                            (isset($config->wholesale_price_field) && (int)$WCProd->wholesale_price_field  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
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
                            if ((isset($config->update_product_stock) && $config->update_product_stock=="1") && isset($WCProd->stock_quantity) and $WCProd->stock_quantity != (int)$HolooProd->exist) {
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

    private function fetchAllHolloProds(){

        $response=app('App\Http\Controllers\HolooController')->fetchAllHolloProds();
        return json_decode($response);
    }
    private function fetchCategoryHolloProds($cat){

        $response=app('App\Http\Controllers\HolooController')->fetchCategoryHolloProds($cat);
        return $response;
    }

    private function fetchAllWCProds($published=false)
    {
        $user=auth()->user();
        if($published){
            $status= "status=publish&" ;
        }
        else{
            $status= "";
        }
        $curl = curl_init();
        $page = 1;
        $products = [];
        $all_products = [];
        do{
          try {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products?'.$status.'page='.$page.'&per_page=100',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
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
            "regular_price"=>$params['regular_price'],
            "price"=>$params['price'],
            "sale_price"=>$params['regular_price'],
            //"wholesale_customer_wholesale_price"=>$params['wholesale_customer_wholesale_price'],
            "stock_quantity"=>$params['stock_quantity'],
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
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
              //'Content-Type: multipart/form-data',
              'Content-Type: application/json',
            ),
        ));
        $response = curl_exec($curl);


        $response = json_decode($response);

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
        //$callApi = $this->fetchAllHolloProds();
        $callApi = $this->fetchCategoryHolloProds($config->product_cat);
        $holooProducts = $callApi;

        $callApi = $this->fetchAllWCProds();
        $wcProducts = $callApi;
        $response_product=[];
        if (count($wcProducts)==0 or count($holooProducts)==0) {
            return $this->sendResponse('داده در سمت سرور موجود نیست', Response::HTTP_OK,null);
        }
        foreach ($wcProducts as $WCProd) {
            if (count($WCProd->meta_data)>0) {
                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                if ($wcHolooCode) {

                    $productFind = false;
                    foreach ($holooProducts as $HolooProd) {
                        $HolooProd=(object) $HolooProd;
                        if ($wcHolooCode == $HolooProd->a_Code) {
                            $productFind = true;

                            if (
                                ((isset($config->update_product_stock) && $config->update_product_stock=="1") && (int) $HolooProd->exist>0 and isset($WCProd->stock_quantity) and  $WCProd->stock_quantity !=(int) $HolooProd->exist) or
                                ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->a_Name))) or
                                ((isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd))) or
                                ((isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ) or
                                ((isset($config->update_product_price) && $config->update_product_price=="1") && isset($WCProd->wholesale_price_field) && ((int)$WCProd->wholesale_price_field != $this->get_price_type($config->wholesale_price_field,$HolooProd)) )
                            ) {

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
                                    'wholesale_customer_wholesale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && (isset($WCProd->wholesale_price_field) && (int)$WCProd->wholesale_price_field != $this->get_price_type($config->wholesale_price_field,$HolooProd)) ? $this->get_price_type($config->wholesale_price_field,$HolooProd)  : ((isset($WCProd->wholesale_price_field)) ? (int)$WCProd->wholesale_price_field : null),
                                    'stock_quantity' => (int) $HolooProd->exist ?? 0,
                                ];

                                //$data=[(int)$WCProd->sale_price  ,$this->get_price_type($config->special_price_field,$HolooProd),((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)),$config->special_price_field];
                                //return $this->sendResponse('همه محصولات به روز رسانی شدند.', Response::HTTP_OK, $data);
                                $s=UpdateProductsUser::dispatch($user,$data,$wcHolooCode)->onConnection('redis');
                                //dispatch((new UpdateProductsUser($user,$data,$WCProd->meta_data[0]->value))->onConnection('queue')->onQueue('high'));

                                array_push($response_product,$wcHolooCode);

                            }
                        }

                    }


                }
            }
        }
        if (count($response_product)>0) {
            return $this->sendResponse('همه محصولات به روز رسانی شدند.', Response::HTTP_OK, ["result"=>["msg_code"=>1,"count"=>count($response_product),"products_cods"=>$response_product]]);
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

        if(isset($request->Table) && strtolower($request->Table)=="article" && ($request->MsgType==1 or $request->MsgType==0)){
            $Dbname=explode("_",$request->Dbname);
            $HolooUser=$Dbname[0];
            $HolooDb=$Dbname[1];
            $user = User::where(['holooDatabaseName'=>$HolooDb,'holooCustomerID'=>$HolooUser,])
            ->first();
            auth()->login($user);
            $HolooIDs=explode(",",$request->MsgValue);
            $config=json_decode($this->getWcConfig());
            foreach($HolooIDs as $holooID){
                $holooProduct=app('App\Http\Controllers\HolooController')->GetSingleProductHoloo($holooID);
                $holooProduct=json_decode($holooProduct);

                if ($request->MsgType==1) {

                    //update product
                    $wcProduct=$this->getWcProductWithHolooId($holooID);
                    // //return $holooProduct;
                    // $holooProduct=$this->findProduct($holooProduct,$holooID);
                    if($wcProduct){
                        $param = [
                            'id' => $wcProduct->id,
                            'name' =>$this->arabicToPersian($holooProduct->result->a_Name),
                            'regular_price' => $this->get_price_type($config->sales_price_field,$holooProduct->result),
                            'price' => $this->get_price_type($config->special_price_field,$holooProduct->result),
                            'sale_price' => $this->get_price_type($config->special_price_field,$holooProduct->result),
                            'wholesale_customer_wholesale_price' => $this->get_price_type($config->wholesale_price_field,$holooProduct->result),
                            'stock_quantity' => (int) $holooProduct->result->exist>0 ,
                        ];
                        $response = $this->updateWCSingleProduct($param);
                        log::info(json_decode($response));
                    }
                    else{
                        continue;
                    }

                }
                else if ($request->MsgType==0 && $config->insert_new_product==1) {



                    // $holooProduct==$this->findProduct($holooProduct,$holooID);
                    $param = [
                        "holooCode" => $holooID,
                        "holooName" => $this->arabicToPersian($holooProduct->result->a_Name),
                        'regular_price' => $this->get_price_type($config->sales_price_field,$holooProduct->result),
                        'price' => $this->get_price_type($config->special_price_field,$holooProduct->result),
                        'sale_price' => $this->get_price_type($config->special_price_field,$holooProduct->result),
                        'wholesale_customer_wholesale_price' => $this->get_price_type($config->wholesale_price_field,$holooProduct->result),
                        'stock_quantity' => (int) $holooProduct->result->exist>0 ?? 0,
                    ];
                    //$category=['id' => $data[$category->m_groupcode], "name" => ""];

                    if(isset($holooProduct->Poshak)){
                        $response=$this->createSingleProduct($param,null,"variable",$holooProduct->Poshak);
                    }
                    else{
                        $response=$this->createSingleProduct($param);
                    }


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
        curl_close($curl);
        if ($response) {
            $response=json_decode($response)[0];
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
            return (int)(float) $HolooProd->sel_Price;
        }
        else{
            return (int)(float) $HolooProd->{"sel_Price".$price_field};
        }
    }

    private function findProduct($products,$holooCode){
        foreach ($products as $product) {
            dd($product);
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
}
