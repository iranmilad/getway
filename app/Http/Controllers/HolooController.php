<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpParser\Node\Stmt\TryCatch;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class HolooController extends Controller
{
    private function getNewToken():string{

        $userSerial="10304923";
        $userApiKey="E5D3A60D3689D3CB8BD8BE91E5E29E934A830C2258B573B5BC28711F3F1D4B70";
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://sandbox.myholoo.ir/api/Ticket/RegisterForPartner',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array('Serial' => $userSerial,'RefreshToken' => 'false','DeleteService' => 'false','MakeService' => 'true','RefreshKey' => 'false'),
          CURLOPT_HTTPHEADER => array(
            'apikey: '.$userApiKey,
            'Content-Type: multipart/form-data',
          ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $response = json_decode($response);

        return $response->result->apikey;
    }

    private function getAllCategory(){
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://sandbox.myholoo.ir/api/Service/M_Group/Holoo1',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array(
            'serial: 10304923',
            'Authorization: Bearer '.$this->getNewToken()
        ),
        ));

        $response = curl_exec($curl);
        $decodedResponse = json_decode($response);
        curl_close($curl);
        return $decodedResponse;
    }

    public function getProductCategory(){

        $response=$this->getAllCategory();
        if ($response) {
            $category = [];


            foreach ($response->result as $row) {
                array_push($category, array("m_groupcode"=>$row->m_groupcode,"m_groupname"=>$this->arabicToPersian($row->m_groupname)));
            }
            return $this->sendResponse('دریافت گروه بندی محصولات', Response::HTTP_OK, ['result' => $category]);
        }
        return $this->sendResponse('مشکل در دریافت گروه بندی محصولات', Response::HTTP_NOT_ACCEPTABLE, null);
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
        return str_replace(array_keys($characters), array_values($characters),$string);
    }

    public function getAllHolooProducts()
    {
        return $this->sendResponse('لیست تمامی محصولات هلو', Response::HTTP_OK, $this->fetchAllHolloProds());
    }

    public function fetchAllHolloProds()
    {


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://sandbox.myholoo.ir/api/Service/article/Holoo1',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: 10304923',
                'database: Holoo1',

                'Authorization: Bearer '.$this->getNewToken()
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    private function updateWCSingleProduct($data){

        $response=app('App\Http\Controllers\WCController')->updateWCSingleProduct($data);
        return $response;
    }

    public function wcInvoiceRegistration(Request $orderInvoice)
    {
        $oreder=array (
            'id' => 727,
            'parent_id' => 0,
            'number' => '727',
            'order_key' => 'wc_order_58d2d042d1d',
            'created_via' => 'rest-api',
            'version' => '3.0.0',
            'status' => 'processing',
            'currency' => 'USD',
            'date_created' => '2017-03-22T16:28:02',
            'date_created_gmt' => '2017-03-22T19:28:02',
            'date_modified' => '2017-03-22T16:28:08',
            'date_modified_gmt' => '2017-03-22T19:28:08',
            'discount_total' => '0.00',
            'discount_tax' => '0.00',
            'shipping_total' => '10.00',
            'shipping_tax' => '0.00',
            'cart_tax' => '1.35',
            'total' => '29.35',
            'total_tax' => '1.35',
            'prices_include_tax' => false,
            'customer_id' => 0,
            'customer_ip_address' => '',
            'customer_user_agent' => '',
            'customer_note' => '',
            'billing' =>
            array (
              'first_name' => 'John',
              'last_name' => 'Doe',
              'company' => '',
              'address_1' => '969 Market',
              'address_2' => '',
              'city' => 'San Francisco',
              'state' => 'CA',
              'postcode' => '94103',
              'country' => 'US',
              'email' => 'john.doe@example.com',
              'phone' => '(555) 555-5555',
            ),
            'shipping' =>
            array (
              'first_name' => 'John',
              'last_name' => 'Doe',
              'company' => '',
              'address_1' => '969 Market',
              'address_2' => '',
              'city' => 'San Francisco',
              'state' => 'CA',
              'postcode' => '94103',
              'country' => 'US',
            ),
            'payment_method' => 'bacs',
            'payment_method_title' => 'Direct Bank Transfer',
            'transaction_id' => '',
            'date_paid' => '2017-03-22T16:28:08',
            'date_paid_gmt' => '2017-03-22T19:28:08',
            'date_completed' => NULL,
            'date_completed_gmt' => NULL,
            'cart_hash' => '',
            'meta_data' =>
            array (
              0 =>
              array (
                'id' => 13106,
                'key' => '_download_permissions_granted',
                'value' => 'yes',
              ),
            ),
            'line_items' =>
            array (
              0 =>
              array (
                'id' => 315,
                'name' => 'Woo Single #1',
                'product_id' => 93,
                'variation_id' => 0,
                'quantity' => 2,
                'tax_class' => '',
                'subtotal' => '6.00',
                'subtotal_tax' => '0.45',
                'total' => '6.00',
                'total_tax' => '0.45',
                'taxes' =>
                array (
                  0 =>
                  array (
                    'id' => 75,
                    'total' => '0.45',
                    'subtotal' => '0.45',
                  ),
                ),
                'meta_data' =>
                array (
                ),
                'sku' => '',
                'price' => 3,
              ),
              1 =>
              array (
                'id' => 316,
                'name' => 'Ship Your Idea &ndash; Color: Black, Size: M Test',
                'product_id' => 22,
                'variation_id' => 23,
                'quantity' => 1,
                'tax_class' => '',
                'subtotal' => '12.00',
                'subtotal_tax' => '0.90',
                'total' => '12.00',
                'total_tax' => '0.90',
                'taxes' =>
                array (
                  0 =>
                  array (
                    'id' => 75,
                    'total' => '0.9',
                    'subtotal' => '0.9',
                  ),
                ),
                'meta_data' =>
                array (
                  0 =>
                  array (
                    'id' => 2095,
                    'key' => 'pa_color',
                    'value' => 'black',
                  ),
                  1 =>
                  array (
                    'id' => 2096,
                    'key' => 'size',
                    'value' => 'M Test',
                  ),
                ),
                'sku' => 'Bar3',
                'price' => 12,
              ),
            ),
            'tax_lines' =>
            array (
              0 =>
              array (
                'id' => 318,
                'rate_code' => 'US-CA-STATE TAX',
                'rate_id' => 75,
                'label' => 'State Tax',
                'compound' => false,
                'tax_total' => '1.35',
                'shipping_tax_total' => '0.00',
                'meta_data' =>
                array (
                ),
              ),
            ),
            'shipping_lines' =>
            array (
              0 =>
              array (
                'id' => 317,
                'method_title' => 'Flat Rate',
                'method_id' => 'flat_rate',
                'total' => '10.00',
                'total_tax' => '0.00',
                'taxes' =>
                array (
                ),
                'meta_data' =>
                array (
                ),
              ),
            ),
            'fee_lines' =>
            array (
            ),
            'coupon_lines' =>
            array (
            ),
            'refunds' =>
            array (
            ),
            '_links' =>
            array (
              'self' =>
              array (
                0 =>
                array (
                  'href' => 'https://example.com/wp-json/wc/v3/orders/727',
                ),
              ),
              'collection' =>
              array (
                0 =>
                array (
                  'href' => 'https://example.com/wp-json/wc/v3/orders',
                ),
              ),
            ),
        );

        $data=array (
            'generalinfo' =>
            array (
              'apiname' => 'InvoicePost',
              'dto' =>
              array (
                'invoiceinfo' =>
                array (
                  'id' => 2104120,
                  'Type' => 1,
                  'Date' => '2021-11-03',
                  'Time' => '00:00:00 ',
                  'Kind' => 4,
                  'Discount' => '1',
                  'Bank' => 999,
                  'BankSarfasl' => '10200010001',
                  'custid' => '00003',
                  'detailinfo' =>
                  array (
                    0 =>
                    array (
                      'id' => '0201001',
                      'Productid' => '0201001',
                      'few' => 1,
                      'price' => 1000,
                      'discount' => '0',
                      'levy' => 0,
                      'scot' => 0,
                    ),
                  ),
                ),
              ),
            ),
        );
        return $this->sendResponse('ثبت فاکتور فروش انجام شد', Response::HTTP_OK, ["result"=>["msg_code"=>1]]);
    }

    public function wcInvoicePayed(Request $orderInvoice){


        $oreder=array (
            'id' => 727,
            'parent_id' => 0,
            'number' => '727',
            'order_key' => 'wc_order_58d2d042d1d',
            'created_via' => 'rest-api',
            'version' => '3.0.0',
            'status' => 'processing',
            'currency' => 'USD',
            'date_created' => '2017-03-22T16:28:02',
            'date_created_gmt' => '2017-03-22T19:28:02',
            'date_modified' => '2017-03-22T16:28:08',
            'date_modified_gmt' => '2017-03-22T19:28:08',
            'discount_total' => '0.00',
            'discount_tax' => '0.00',
            'shipping_total' => '10.00',
            'shipping_tax' => '0.00',
            'cart_tax' => '1.35',
            'total' => '29.35',
            'total_tax' => '1.35',
            'prices_include_tax' => false,
            'customer_id' => 0,
            'customer_ip_address' => '',
            'customer_user_agent' => '',
            'customer_note' => '',
            'billing' =>
            array (
              'first_name' => 'John',
              'last_name' => 'Doe',
              'company' => '',
              'address_1' => '969 Market',
              'address_2' => '',
              'city' => 'San Francisco',
              'state' => 'CA',
              'postcode' => '94103',
              'country' => 'US',
              'email' => 'john.doe@example.com',
              'phone' => '(555) 555-5555',
            ),
            'shipping' =>
            array (
              'first_name' => 'John',
              'last_name' => 'Doe',
              'company' => '',
              'address_1' => '969 Market',
              'address_2' => '',
              'city' => 'San Francisco',
              'state' => 'CA',
              'postcode' => '94103',
              'country' => 'US',
            ),
            'payment_method' => 'bacs',
            'payment_method_title' => 'Direct Bank Transfer',
            'transaction_id' => '',
            'date_paid' => '2017-03-22T16:28:08',
            'date_paid_gmt' => '2017-03-22T19:28:08',
            'date_completed' => NULL,
            'date_completed_gmt' => NULL,
            'cart_hash' => '',
            'meta_data' =>
            array (
              0 =>
              array (
                'id' => 13106,
                'key' => '_download_permissions_granted',
                'value' => 'yes',
              ),
            ),
            'line_items' =>
            array (
              0 =>
              array (
                'id' => 315,
                'name' => 'Woo Single #1',
                'product_id' => 93,
                'variation_id' => 0,
                'quantity' => 2,
                'tax_class' => '',
                'subtotal' => '6.00',
                'subtotal_tax' => '0.45',
                'total' => '6.00',
                'total_tax' => '0.45',
                'taxes' =>
                array (
                  0 =>
                  array (
                    'id' => 75,
                    'total' => '0.45',
                    'subtotal' => '0.45',
                  ),
                ),
                'meta_data' =>
                array (
                ),
                'sku' => '',
                'price' => 3,
              ),
              1 =>
              array (
                'id' => 316,
                'name' => 'Ship Your Idea &ndash; Color: Black, Size: M Test',
                'product_id' => 22,
                'variation_id' => 23,
                'quantity' => 1,
                'tax_class' => '',
                'subtotal' => '12.00',
                'subtotal_tax' => '0.90',
                'total' => '12.00',
                'total_tax' => '0.90',
                'taxes' =>
                array (
                  0 =>
                  array (
                    'id' => 75,
                    'total' => '0.9',
                    'subtotal' => '0.9',
                  ),
                ),
                'meta_data' =>
                array (
                  0 =>
                  array (
                    'id' => 2095,
                    'key' => 'pa_color',
                    'value' => 'black',
                  ),
                  1 =>
                  array (
                    'id' => 2096,
                    'key' => 'size',
                    'value' => 'M Test',
                  ),
                ),
                'sku' => 'Bar3',
                'price' => 12,
              ),
            ),
            'tax_lines' =>
            array (
              0 =>
              array (
                'id' => 318,
                'rate_code' => 'US-CA-STATE TAX',
                'rate_id' => 75,
                'label' => 'State Tax',
                'compound' => false,
                'tax_total' => '1.35',
                'shipping_tax_total' => '0.00',
                'meta_data' =>
                array (
                ),
              ),
            ),
            'shipping_lines' =>
            array (
              0 =>
              array (
                'id' => 317,
                'method_title' => 'Flat Rate',
                'method_id' => 'flat_rate',
                'total' => '10.00',
                'total_tax' => '0.00',
                'taxes' =>
                array (
                ),
                'meta_data' =>
                array (
                ),
              ),
            ),
            'fee_lines' =>
            array (
            ),
            'coupon_lines' =>
            array (
            ),
            'refunds' =>
            array (
            ),
            '_links' =>
            array (
              'self' =>
              array (
                0 =>
                array (
                  'href' => 'https://example.com/wp-json/wc/v3/orders/727',
                ),
              ),
              'collection' =>
              array (
                0 =>
                array (
                  'href' => 'https://example.com/wp-json/wc/v3/orders',
                ),
              ),
            ),
        );

        $DateString=Carbon::parse($oreder["date_paid_gmt"],'UTC');
        $DateString->setTimezone('Asia/Tehran');
        //return $DateString->format('Y-m-d');

        if(isset($orderInvoice->invoicePaid) && $orderInvoice->invoicePaid=="paid"){
            $type=1;
        }
        else if(isset($orderInvoice->invoicePaid) && $orderInvoice->invoicePaid=="prePaid"){
            $type=2;
        }
        else if(isset($orderInvoice->invoicePaid) && $orderInvoice->invoicePaid=="order"){
            $type=3;
        }
        else {
            return $this->sendResponse('ثبت فاکتور انجام نشد', Response::HTTP_OK, ["result"=>["msg_code"=>0]]);
        }

        $data=array (
            'generalinfo' =>
            array (
              'apiname' => 'InvoicePost',
              'dto' =>
                    array (
                        'invoiceinfo' =>
                            array (
                            'id' => $oreder["id"],          //$oreder->id
                            'Type' => $type,              //1 faktor frosh 2 pish factor
                            'Date' => $DateString->format('Y-m-d'),
                            'Time' => $DateString->format('H:i:s'),
                            'Bank' => 999,
                            'BankSarfasl' => '10200010001',
                            'Cash' => '10200010001',
                            'CashSarfas' => '10200010001',
                            'Nesiyeh' => '',
                            'custid' => '00003',
                            'detailinfo' =>
                                array (
                                    0 =>
                                        array (
                                            'id' => '0201001',
                                            'Productid' => '0201001',
                                            'few' => 1,
                                            'price' => 1000,
                                            'discount' => '0',
                                            'levy' => 0,
                                            'scot' => 0,
                                        ),
                                ),
                            ),
                    ),
            ),
        );


        return $this->sendResponse('ثبت سفارش فروش انجام شد', Response::HTTP_OK, ["result"=>["msg_code"=>1]]);
    }

    public function wcSingleProductUpdate(Request $request){
        ini_set('max_execution_time', 120); // 120 (seconds) = 2 Minutes
        $holoo_product_id=$request->holoo_id;
        $wp_product_id=$request->product_id;

        $userSerial="10304923";
        $userApiKey="E5D3A60D3689D3CB8BD8BE91E5E29E934A830C2258B573B5BC28711F3F1D4B70";

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://sandbox.myholoo.ir/api/Service/article/Holoo1/'.$holoo_product_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'serial: '.$userSerial,
            'access_token: '.$userApiKey,
            'Authorization: Bearer '.$this->getNewToken()
        ),
        ));


        $response = curl_exec($curl);
        $HolooProd=json_decode($response)->result;

        $param = [
            'id' => $wp_product_id,
            'name' => urlencode($this->arabicToPersian($HolooProd->a_Name)),
            'regular_price' => $HolooProd->sel_Price ?? 0,
            'stock_quantity' => (int)$HolooProd->exist_Mandeh ?? 0,
        ];

        $response= $this->updateWCSingleProduct($param);


        return $this->sendResponse('محصول به روز شد', Response::HTTP_OK, ['code'=>1,'result' => $response]);
    }


    public function wcAddAllHolooProductsCategory(Request $request){
        //ini_set('max_execution_time', 120); // 120 (seconds) = 2 Minutes
        $token=$this->getNewToken();
        $curl = curl_init();
        $userSerial="10304923";
        $userApiKey="E5D3A60D3689D3CB8BD8BE91E5E29E934A830C2258B573B5BC28711F3F1D4B70";
        $data=$request->all();
        //dd($data);
        $counter=1;
        $categories=$this->getAllCategory();
        $wcHolooExistCode=app('App\Http\Controllers\WCController')->get_all_holoo_code_exist();
        $allRespose=[];
        foreach ($categories->result as $key => $category) {
            if(array_key_exists("product_cat_".$category->m_groupcode,$data)){

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://sandbox.myholoo.ir/api/Article/SearchArticles?from.date=2020',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: '.$userSerial,
                        'database: Holoo1',
                        'm_groupcode: '.$category->m_groupcode,
                        'isArticle: true',
                        'access_token: '.$userApiKey,
                        'Authorization: Bearer '.$token
                    ),
                ));
                $response = curl_exec($curl);
                $HolooProds=json_decode($response);


                foreach($HolooProds as $HolooProd){

                    if (!in_array($HolooProd->a_Code, $wcHolooExistCode) && $HolooProd->exist_Mandeh>0) { //&& $HolooProd->exist_Mandeh>0

                        $param=[
                            "holooCode"=>$HolooProd->a_Code,
                            "holooName"=>$this->arabicToPersian($HolooProd->a_Name),
                            "holooRegularPrice"=>(string) $HolooProd->sel_Price ?? 0,
                            "holooStockQuantity" => (string) $HolooProd->exist_Mandeh ?? 0,
                        ];


                        $allRespose[]=app('App\Http\Controllers\WCController')->createSingleProduct($param,$data["product_cat_".$category->m_groupcode]);

                    }

                }


            }
        }
        curl_close($curl);


        return $this->sendResponse('محصولات جدید با موفقیت اضافه گردیدند', Response::HTTP_OK, ["result"=>["msg_code"=>1,"respose"=>$allRespose]]);
    }



    public function wcGetExcelProducts(Request $orderInvoice){
        //PDF file is stored under project/public/download/info.pdf
        $file= url('/')."/download/file.csv";

        return $this->sendResponse('ادرس فایل دانلود', Response::HTTP_OK, ["result"=>["url"=>$file]]);

    }


    public function addToCart(Request $orderInvoice){
        return $this->sendResponse('ثبت سفارش فروش انجام شد', Response::HTTP_OK, ["result"=>["msg_code"=>1]]);
    }

}
