<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
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
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://wpdemoo.ir/wordpress/wp-json/wc/v3/products/' . $id . '/variations/' . $id . '?context=view&context=view',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic Y2tfZGIyY2ZiNDIwMTY1ZDc0MGEyNDIxZDUxZWMwN2NlNmI1MzU0ZmRiNjpjc182YzU3ZmRkNmEzMWQ2NzgwYzRhNTEwOTMyYTM2NDgwZTg3YTkyYTNi'
            ),
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
    public function createSingleProduct($param,$categories)
    {
        $category=array(
            (object)array(
                'id' => $categories,
            )
        );
        $meta = array(
            (object)array(
                'key' => '_holo_sku',
                'value' => $param["holooCode"]
            )
        );
        //$json = json_encode($value);
        if ($categories !=null) {
            $data = array(
                'name' => $param["holooName"],
                'type' => 'simple',
                'regular_price' => $param["holooRegularPrice"],
                'stock_quantity' => $param["holooStockQuantity"],
                'status' => 'draft',
                'meta_data' => $meta,
                'categories' => $category
            );
        }
        else{
            $data = array(
                'name' => $param["holooName"],
                'type' => 'simple',
                'regular_price' => $param["holooRegularPrice"],
                'stock_quantity' => $param["holooStockQuantity"],
                'status' => 'draft',
                'meta_data' => $meta,
            );
        }
        $data = json_encode($data);
        //return response($data);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://wpdemoo.ir/wordpress/wp-json/wc/v3/products',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic Y2tfZGIyY2ZiNDIwMTY1ZDc0MGEyNDIxZDUxZWMwN2NlNmI1MzU0ZmRiNjpjc182YzU3ZmRkNmEzMWQ2NzgwYzRhNTEwOTMyYTM2NDgwZTg3YTkyYTNi'
                //'Authorization: Basic '. base64_encode("user:password") ali jan baray basic aut bayad in ra janshin konid
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        if ($response) {
            $decodedResponse = json_decode($response);
        //            curl_close($curl);
            return $this->sendResponse('محصول مورد نظر با موفقیت در سایت ثبت شد.', Response::HTTP_OK, ['response' => $decodedResponse]);
        }

        return $this->sendResponse('مشکل در ارسال و دریافت ریسپانس', Response::HTTP_NOT_ACCEPTABLE, null);

    }


    public function compareProductsFromWoocommerceToHoloo(Request $config){

        Log::error(json_encode($config));

        $callApi = $this->fetchAllWCProds();
        $WCProds = $callApi;

        $callApi = $this->fetchAllHolloProds();
        $HolooProds = $callApi;

        $products = [];
        foreach ($WCProds as $WCProd) {
            //array_push($products,$WCProd->id);

            if (count($WCProd->meta_data)>0) {
                if ($WCProd->meta_data[0]->key == '_holo_sku') {
                    if ($WCProd->meta_data[0]->value!=null) {
                        $messages = [];
                        $messages_code = [];

                        $productFind = false;
                        foreach ($HolooProds->result as $key=>$HolooProd) {

                            //0 "قیمت محصول با هلو منطبق نیست"
                            //1 "نام محصول با هلو منطبق نیست"
                            //2 "مقدار موجودی محصول با هلو منطبق نیست"
                            //3 "کد هلو ثبت شده برای این محصول در نرم افزار هلو یافت نشد"

                            if ($WCProd->meta_data[0]->value == $HolooProd->a_Code) {
                                if ((isset($config->update_product_price) && $config->update_product_price=="1") && $WCProd->regular_price != $HolooProd->sel_Price) {
                                    array_push($messages, 'قیمت محصول با هلو منطبق نیست.');
                                    array_push($messages_code, 0);
                                }
                                if ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->a_Name))) {
                                    //dd($WCProd->name.'-'.trim($this->arabicToPersian($HolooProd->a_Name)));
                                    array_push($messages, 'نام محصول با هلو منطبق نیست.');
                                    array_push($messages_code, 1);

                                }
                                if ((isset($config->update_product_stock) && $config->update_product_stock=="1") && isset($WCProd->stock_quantity) and $WCProd->stock_quantity != $HolooProd->exist_Mandeh) {
                                    array_push($messages, 'مقدار موجودی محصول با هلو منطبق نیست.');
                                    array_push($messages_code, 2);


                                }
                                unset($HolooProds->result[$key]);
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
                                    'holo_code' => $WCProd->meta_data[0]->value,
                                    'woocommerce_product_id' => $WCProd->id,
                                    'msg_code' => $messages_code,

                                ]
                            );
                        }
                    }
                }

            }
        }
        return $this->sendResponse('نتیجه مقایسه', Response::HTTP_OK, ['result' => $products]);
    }

    private function fetchAllHolloProds(){

        $response=app('App\Http\Controllers\HolooController')->fetchAllHolloProds();
        return json_decode($response);
    }

    private function fetchAllWCProds()
    {
        $curl = curl_init();
        $page = 1;
        $products = [];
        $all_products = [];
        do{
          try {
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://wpdemoo.ir/wordpress/wp-json/wc/v3/products?page='.$page.'&per_page=100',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Basic Y2tfZGIyY2ZiNDIwMTY1ZDc0MGEyNDIxZDUxZWMwN2NlNmI1MzU0ZmRiNjpjc182YzU3ZmRkNmEzMWQ2NzgwYzRhNTEwOTMyYTM2NDgwZTg3YTkyYTNi'
                ),
            ));

            $response = curl_exec($curl);
            $products = json_decode($response);

          }
          catch(HttpClientException $e){
            return [];
          }
          $all_products = array_merge($all_products,$products);
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
        $curl = curl_init();
        $data=[
            "regular_price"=>$params['regular_price'],
            "sale_price"=>$params['regular_price'],
            "stock_quantity"=>$params['stock_quantity'],
            "name"=>$params['name'],
        ];
        $url="?";
        $url=($data['name']!=null) ? $url.'name='.$data['name'] : $url;
        $url=($data['regular_price']!=null) ? $url.'&regular_price='.$data['regular_price'] .'&sale_price='.$data['sale_price']: $url;
        $url=($data['stock_quantity']!=null) ? $url.'&stock_quantity='.$data['stock_quantity'] : $url;

        //$data = json_encode($data);
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://wpdemoo.ir/wordpress/wp-json/wc/v3/products/' . $params['id'] .$url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            //CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic Y2tfZGIyY2ZiNDIwMTY1ZDc0MGEyNDIxZDUxZWMwN2NlNmI1MzU0ZmRiNjpjc182YzU3ZmRkNmEzMWQ2NzgwYzRhNTEwOTMyYTM2NDgwZTg3YTkyYTNi'
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
        ini_set('max_execution_time', 120); // 120 (seconds) = 2 Minutes
        $callApi = $this->fetchAllHolloProds();
        $holooProducts = $callApi;

        $callApi = $this->fetchAllWCProds();
        $wcProducts = $callApi;
        $response_product=[];
        if (count($wcProducts)==0 or count($holooProducts->result)==0) {
            return $this->sendResponse('داده در سمت سرور موجود نیست', Response::HTTP_OK,null);
        }
        foreach ($wcProducts as $WCProd) {
            if (isset($WCProd->meta_data[0]->key)) {
                if ($WCProd->meta_data[0]->key == '_holo_sku') {
                    if ($WCProd->meta_data[0]->value!=null) {
                        $productFind = false;
                        foreach ($holooProducts->result as $HolooProd) {
                            if ($WCProd->meta_data[0]->value == $HolooProd->a_Code) {
                                $productFind = true;


                                if (
                                    ((isset($config->update_product_stock) && $config->update_product_stock=="1") && isset($WCProd->stock_quantity) and $WCProd->stock_quantity != $HolooProd->exist_Mandeh) or
                                    ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != $this->arabicToPersian($HolooProd->a_Name)) or
                                    ((isset($config->update_product_price) && $config->update_product_price=="1") && $WCProd->regular_price != $HolooProd->sel_Price)
                                ) {
                                    //dd($WCProd->meta_data[0]->value);
                                    # if product holoo was not same with product hoocomrece
                                    $data = [
                                        'id' => $WCProd->id,
                                        'name' => (isset($config->update_product_name) && $config->update_product_name=="1") && ($WCProd->name != $this->arabicToPersian($HolooProd->a_Name)) ? urlencode($this->arabicToPersian($HolooProd->a_Name)) :null,
                                        'regular_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ($WCProd->regular_price != $HolooProd->sel_Price) ? $HolooProd->sel_Price ?? 0 : null,
                                        'stock_quantity' =>(isset($config->update_product_stock) && $config->update_product_stock=="1") && (isset($WCProd->stock_quantity) and $WCProd->stock_quantity != $HolooProd->exist_Mandeh) ? (int)$HolooProd->exist_Mandeh ?? 0 : null,
                                    ];
                                    dd($data);
                                    dd($this->updateWCSingleProduct($data));

                                    array_push($response_product,$WCProd->meta_data[0]->value);

                                }
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
            if (isset($WCProd->meta_data[0]->key)) {
                if ($WCProd->meta_data[0]->key == '_holo_sku') {
                    if ($WCProd->meta_data[0]->value!=null) {
                        $response_products[]=$WCProd->meta_data[0]->value;
                    }
                }
            }
        }

        return $response_products;
    }

}
