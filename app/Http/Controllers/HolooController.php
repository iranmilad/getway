<?php

namespace App\Http\Controllers;

use stdClass;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use App\Exports\ReportExport;
use App\Jobs\AddProductsUser;
use App\Jobs\CreateProductFind;
use App\Models\ProductRequest;
use App\Jobs\FindProductInCategory;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\HttpFoundation\Response;
use App\Jobs\UpdateProductsVariationUser;


class HolooController extends Controller
{
    private function getNewToken($force=false): string
    {

        $user = auth()->user();

        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        if ($user->cloudTokenExDate > Carbon::now() and $force == false) {

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

            if ($response and isset($response->success) and $response->success == true) {
                log::info("take new token request and response");
                log::info(json_encode($response));
                User::where(['id' => $user->id])
                ->update([
                    'cloudTokenExDate' => Carbon::now()->addHour(4),
                    'cloudToken' => $response->result->apikey,
                ]);

                return $response->result->apikey;
            }
            else {
                log::alert("get take is problem");
                log::alert(json_encode($response));
                dd("???????? ???????????? ??????", $response);

            }
        }
    }

    private function getAllCategory()
    {
        $user = auth()->user();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/S_Group/' . $user->holooDatabaseName,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $decodedResponse = json_decode($response);
        curl_close($curl);
        return $decodedResponse;
    }

    public function getProductCategory()
    {
        //return $this->sendResponse('???????? ???? ???????????? ???????? ???????? ??????????????', Response::HTTP_NOT_ACCEPTABLE, null);
        $response = $this->getAllCategory();
        if ($response) {
            $category = [];

            foreach ($response->result as $row) {
                array_push($category, array("m_groupcode" => $row->m_groupcode."-".$row->s_groupcode, "m_groupname" => $this->arabicToPersian($row->s_groupname)));
            }
            return $this->sendResponse('???????????? ???????? ???????? ??????????????', Response::HTTP_OK, ['result' => $category]);
        }
        return $this->sendResponse('???????? ???? ???????????? ???????? ???????? ??????????????', Response::HTTP_NOT_ACCEPTABLE, null);
    }

    public function sendResponse($message, $responseCode, $response)
    {
        return response([
            'message' => $message,
            'responseCode' => $responseCode,
            'response' => $response,
        ], $responseCode);
    }

    public static function arabicToPersian($string)
    {

        $characters = [
            '??' => '??',
            '????' => '??',
            '????' => '??',
            '????' => '??',
            '????' => '??',
            '????' => '??',
            '????' => '??',
            '??' => '??',
            '??' => '??',
            '??' => '??',
            '??' => '??',
            '??' => '??',
            '??' => '??',
            '??' => '??',
            '??' => '??',
            '??' => '??',
            '??' => '??',
            '??' => '??',
            '??' => '??',
        ];
        return str_replace(array_keys($characters), array_values($characters), $string);
    }

    public function getAllHolooProducts()
    {
        return $this->sendResponse('???????? ?????????? ?????????????? ??????', Response::HTTP_OK, $this->fetchAllHolloProds());
    }

    public function fetchAllHolloProdsOld()
    {
        $user = auth()->user();
        $curl = curl_init();
        // log::info("yes");
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/article/' . $user->holooDatabaseName,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,

                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
    public function fetchAllHolloProds()
    {
        $user = auth()->user();
        $curl = curl_init();
        // log::info("yes");
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,

                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpcode == 401) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'serial: ' . $user->serial,
                    'database: ' . $user->holooDatabaseName,
                    'Authorization: Bearer ' . $this->getNewToken(true),
                ),
            ));
            $response = curl_exec($curl);
        }

        curl_close($curl);
        return $response;
    }

    public function fetchCategoryHolloProdsOld($categorys)
    {
        $totalProduct=[];

        $user = auth()->user();
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
                        'serial: ' . $user->serial,
                        'database: ' . $user->holooDatabaseName,
                        'Authorization: Bearer ' . $this->getNewToken(),
                        'm_groupcode: ' . $m_groupcode,
                        's_groupcode: ' . $s_groupcode,
                        'isArticle: true',
                        'access_token: ' .$user->apiKey
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

    public function fetchCategoryHolloProds($categorys)
    {
        $totalProduct=[];

        $user = auth()->user();
        $curl = curl_init();
        foreach ($categorys as $category_key=>$category_value) {
            if ($category_value != "") {
                $m_groupcode=explode("-",$category_key)[0];
                $s_groupcode=explode("-",$category_key)[1];

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?sidegroupcode='.$s_groupcode.'&maingroupcode='.$m_groupcode,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: ' . $user->serial,
                        'database: ' . $user->holooDatabaseName,
                        'Authorization: Bearer ' . $this->getNewToken(),
                    ),
                ));
                $response = curl_exec($curl);

                if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["product"])){
                    $totalProduct=array_merge(json_decode($response, true)["data"]["product"] ??[],$totalProduct??[]);
                }

            }


        }


        return $totalProduct;
    }

    private function updateWCSingleProduct($data)
    {

        $response = app('App\Http\Controllers\WCController')->updateWCSingleProduct($data);
        return $response;
    }

    public function wcInvoiceRegistration(Request $orderInvoice)
    {
        $user = auth()->user();
        $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration receive");

        //log::info("order: ".json_encode($orderInvoice->request->all()));
        //$orderInvoice->request->add($order);
        //return $this->sendResponse('test', Response::HTTP_OK, $orderInvoice);
        $allStatus=['processing', 'pending','completed','on-hold','pws-shipping','cancelled','refunded','failed','trash'];
        if (!in_array($orderInvoice->status, $allStatus)){
            Log::alert("Invoice Payed status not valid");
            Log::alert("Invoice status is".$orderInvoice->status);
            return $this->sendResponse('?????????? ???????????? ?????????? ????????', Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
        }

        $invoice = new Invoice();
        $invoice->invoice = json_encode($orderInvoice->request->all());
        $invoice->user_id = $user->id;
        $invoice->invoiceId = isset($orderInvoice->id) ? $orderInvoice->id : null;
        $invoice->invoiceStatus = isset($orderInvoice->status) ? $orderInvoice->status : null;
        $invoice->save();

        if (isset($orderInvoice->save_pre_sale_invoice) and $orderInvoice->save_pre_sale_invoice=="3") {

            $_data = (object) $orderInvoice->input("date_created");
            $DateString = Carbon::parse($_data->date ?? now(), $_data->timezone);
            $DateString->setTimezone('Asia/Tehran');

            if (!$orderInvoice->save_pre_sale_invoice || $orderInvoice->save_pre_sale_invoice == 0) {
                $this->InvoiceChangeStatus($invoice->id, '?????? ?????? ???????????? ?????????? ??????');
                return $this->sendResponse('?????? ?????? ???????????? ?????????? ??????', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }
            else {
                $type = $orderInvoice->save_pre_sale_invoice;
            }

            $custid = $this->getHolooCustomerID($orderInvoice->billing, $orderInvoice->customer_id);

            if (!$custid) {
                log::info("???? ?????????? ???????? ??????");
                $this->InvoiceChangeStatus($invoice->id, "?????? ?????? ???????????? ?????????? ??????");
                return $this->sendResponse("?????? ?????? ???????????? ?????????? ??????", Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
            }

            $items = array();
            $sum_total = 0;
            if (is_string($orderInvoice->payment)) {
                $payment = json_decode($orderInvoice->payment);
            }
            elseif (is_array($orderInvoice->payment)) {
                $payment = (object) $orderInvoice->payment;
            }
            log::info("payment: ".json_encode($payment));
            if (!(array)$payment) {
                $this->InvoiceChangeStatus($invoice->id, '?????? ?????? ???????????? ?????????? ??????.?????? ???????????? ??????????????');
                return $this->sendResponse('?????? ?????? ???????????? ?????????? ??????.?????? ???????????? ??????????????', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }
            $payment =(object) $payment->{$orderInvoice->payment_method};
            $orderInvoiceFull=app('App\Http\Controllers\WCController')->get_invoice($orderInvoice->id);
            //$fetchAllWCProds=app('App\Http\Controllers\WCController')->fetchAllWCProds(true);
            if(!is_object($orderInvoiceFull)){
                $this->InvoiceChangeStatus($invoice->id, '?????? ?????? ???????????? ?????????? ?????? ???????? ?????????? ??????');
                return $this->sendResponse('?????? ?????? ???????????? ?????????? ?????? ???????? ?????????? ??????', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }

            $numberOfItem=0;

            foreach ($orderInvoiceFull->line_items as $item) {
                $numberOfItem=+1;
                if (is_array($item)) {
                    $item = (object) $item;

                }
                //$HoloID=app('App\Http\Controllers\WCController')->get_product_holooCode($fetchAllWCProds,$item->product_id);


                if (isset($item->meta_data)) {
                    $HoloID=$this->findKey($item->meta_data,'_holo_sku');
                    // if($item->total==0){
                    //     continue;
                    // }
                    $total = $this->getAmount($item->total, $orderInvoiceFull->currency);
                    $lazy = 0;
                    $scot = 0;
                    if ($payment->vat) {
                        $lazy = $total * 6 / 100;
                        $scot = $total * 3 / 100;
                    }
                    $items[] = array(
                        'id' => (int)$HoloID,
                        'Productid' => $HoloID,
                        'few' => $item->quantity,
                        'price' => $this->getAmount($item->price, $orderInvoiceFull->currency),
                        'discount' => '0',
                        'levy' => $lazy,
                        'scot' => $scot,
                    );
                    $sum_total += $total;

                }
                elseif($orderInvoice->invoice_items_no_holo_code){
                    $this->InvoiceChangeStatus($invoice->id, '?????? ?????? ???????????? ?????????? ???????? ???????? ???? ?????? ?????????? ??????');
                    return $this->sendResponse('?????? ?????? ???????????? ?????????? ???????? ???????? ???? ?????? ?????????? ??????', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
                }

            }

            //hazineh haml be sorat kala azafe shavad
            if ($orderInvoice->product_shipping) {
                $shipping_lines = $orderInvoiceFull->shipping_lines[0] ?? null;
                if ($shipping_lines) {

                    if (is_array($shipping_lines)) {
                        $shipping_lines = (object) $shipping_lines;
                    }
                    $total = $this->getAmount($shipping_lines->total, $orderInvoiceFull->currency);
                    if ($total>0){
                        $scot = $this->getAmount($shipping_lines->total_tax, $orderInvoiceFull->currency);
                        $items[] = array(
                            'id' => (int)$orderInvoice->product_shipping,
                            'Productid' => $orderInvoice->product_shipping,
                            'few' => $numberOfItem,
                            'price' => $total-$scot,
                            'discount' => 0,
                            'levy' => 0,
                            'scot' => $scot,
                        );

                        $sum_total += $total;

                    }
                }

            }




            if (sizeof($items) > 0) {
                $payment_type = "bank";
                if ($orderInvoice->status_place_payment == "Installment") {
                    $payment_type = "nesiyeh";
                }
                else if (substr($payment->number, 0, 3) == "101") {
                    $payment_type = "cash";
                }
                $data = array(
                    'generalinfo' => array(
                        'apiname' => 'InvoicePost',
                        'dto' => array(
                            'invoiceinfo' => array(
                                'id' => $orderInvoice->input("id"), //$oreder->id
                                'Type' => 1, //1 faktor frosh 2 pish factor, 3 sefaresh =>$type
                                'kind' => 4,
                                'Date' => $DateString->format('Y-m-d'),
                                'Time' => $DateString->format('H:i:s'),
                                'custid' => $custid,
                                'detailinfo' => $items,
                            ),
                        ),
                    ),
                );


                if ($payment_type == "bank") {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["Bank"] = $sum_total;
                    $data["generalinfo"]["dto"]["invoiceinfo"]["BankSarfasl"] = $payment->number;
                }
                elseif ($payment_type == "cash") {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["Cash"] = $sum_total;
                    $data["generalinfo"]["dto"]["invoiceinfo"]["CashSarfasl"] = $payment->number;
                }
                else {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["nesiyeh"] = $sum_total;
                }

                ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/CallApi/InvoicePost',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => array('data' => json_encode($data)),
                    CURLOPT_HTTPHEADER => array(
                        "serial: ".$user->serial,
                        'database: ' . $user->holooDatabaseName,
                        "Authorization: Bearer ".$this->getNewToken(),
                        'access_token:' . $user->apiKey,
                    ),
                ));
                $response = curl_exec($curl);
                $response = json_decode($response);
                log::info(json_encode($data));
                curl_close($curl);
                log::info(json_encode($response));
                if (isset($response->success) and $response->success) {
                    $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration finish succsessfuly");
                    $this->InvoiceChangeStatus($invoice->id, '?????? ?????????? ???????? ?????????? ????');
                    return $this->sendResponse('?????? ?????????? ???????? ?????????? ????', Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
                }
                else {
                    $this->InvoiceChangeStatus($invoice->id, '?????? ???? ?????? ??????????');
                    $invoice = new Invoice();
                    $invoice->invoice = json_encode(['data' => $data]);
                    $invoice->user_id = $user->id;
                    $invoice->save();
                    //return $this->sendResponse('test', Response::HTTP_OK,$response);
                    $this->recordLog("Invoice Registration", $user->siteUrl, json_encode(['data' => $data]), "error");
                    $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration finish wrong", "error");
                    $this->recordLog("Invoice Registration", $user->siteUrl, json_encode($response), "error");
                }

                return $this->sendResponse($response->message, Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }
            $this->InvoiceChangeStatus($invoice->id, '?????????? ?????????? ???????? ??????');
            return $this->sendResponse('?????????? ?????????? ???????? ??????', Response::HTTP_OK, ["result" => ["msg_code" => 0,"item"=>$orderInvoiceFull]]);
        }
        $this->InvoiceChangeStatus($invoice->id, '?????? ?????? ???????????? ?????????? ??????');
        return $this->sendResponse('?????? ?????? ???????????? ?????????? ??????', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
    }

    public function wcInvoicePayed(Request $orderInvoice)
    {
        $user = auth()->user();
        $this->recordLog("Invoice Payed", $user->siteUrl, "Invoice Payed receive");

        $invoice = Invoice::where(['invoiceId'=>$orderInvoice->id,"invoiceStatus"=>"processing","status"=>'["\u062b\u0628\u062a \u0633\u0641\u0627\u0631\u0634 \u0641\u0631\u0648\u0634 \u0627\u0646\u062c\u0627\u0645 \u0634\u062f"]'])
        ->first();

        $allStatus=['processing', 'pending','completed','on-hold','pws-shipping','cancelled','refunded','failed','trash'];
        if (!in_array($orderInvoice->status, $allStatus)){
            Log::alert("Invoice Payed status not valid");
            Log::alert("Invoice status is".$orderInvoice->status);
            return $this->sendResponse('?????????? ???????????? ?????????? ????????', Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
        }

        if($invoice){
            $invoice = new Invoice();
            $invoice->invoice = json_encode($orderInvoice->request->all());
            $invoice->user_id = $user->id;
            $invoice->invoiceId = isset($orderInvoice->id) ? $orderInvoice->id : null;
            $invoice->invoiceStatus = isset($orderInvoice->status) ? $orderInvoice->status : null;
            $invoice->save();
            //$this->InvoiceChangeStatus($invoice->id, '?????? ?????????? ???????? ???? ?????? ?????????? ?????? ??????');
            return $this->sendResponse('?????? ?????????? ???????? ?????????? ????', Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
        }

        $invoice = new Invoice();
        $invoice->invoice = json_encode($orderInvoice->request->all());
        $invoice->user_id = $user->id;
        $invoice->invoiceId = isset($orderInvoice->id) ? $orderInvoice->id : null;
        $invoice->invoiceStatus = isset($orderInvoice->status) ? $orderInvoice->status : null;
        $invoice->save();

        if (isset($orderInvoice->save_sale_invoice) and $orderInvoice->save_sale_invoice != "0") {

            $_data = (object) $orderInvoice->input("date_created");
            $DateString = Carbon::parse($_data->date ?? now(), $_data->timezone);
            $DateString->setTimezone('Asia/Tehran');

            if (!$orderInvoice->save_sale_invoice || $orderInvoice->save_sale_invoice == 0) {
                $this->InvoiceChangeStatus($invoice->id, '?????? ???????????? ?????????????? ??????');
                return $this->sendResponse('?????? ???????????? ?????????????? ??????', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }
            else {
                $type = $orderInvoice->save_sale_invoice;
            }

            $custid = $this->getHolooCustomerID($orderInvoice->billing, $orderInvoice->customer_id);

            if (!$custid) {
                log::info("???? ?????????? ???????? ??????");
                $this->InvoiceChangeStatus($invoice->id, " ?????? ???????????? ?????????? ?????? ???? ?????????? ???????? ??????");
                return $this->sendResponse(" ?????? ???????????? ?????????? ?????? ???? ?????????? ???????? ??????", Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
            }

            $items = array();
            $sum_total = 0;

            if (is_string($orderInvoice->payment)) {
                $payment = json_decode($orderInvoice->payment);
            }
            elseif (is_array($orderInvoice->payment)) {
                $payment = (object) $orderInvoice->payment;
            }
            // log::info("payment: ".json_encode($payment));
            // log::info("payment: ".json_encode($orderInvoice->payment_method));
            if (!(array)$payment) {
                $this->InvoiceChangeStatus($invoice->id, '?????? ???????????? ?????????? ??????.?????? ???????????? ??????????????');
                return $this->sendResponse('?????? ???????????? ?????????? ??????.?????? ???????????? ??????????????', Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
            }
            $payment =(object) $payment->{$orderInvoice->payment_method};
            //log::info("payment: ".json_encode($payment));
            $orderInvoiceFull=app('App\Http\Controllers\WCController')->get_invoice($orderInvoice->id);
            //$fetchAllWCProds=app('App\Http\Controllers\WCController')->fetchAllWCProds(true);


            if(!is_object($orderInvoiceFull)){

                log::alert(json_encode($orderInvoiceFull));
                $this->InvoiceChangeStatus($invoice->id, '?????? ???????????? ?????????? ?????? ???????? ?????????? ??????');
                return $this->sendResponse('?????? ???????????? ?????????? ?????? ???????? ?????????? ??????', Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
            }
            $cate=[];
            foreach ($orderInvoiceFull->line_items as $item) {
                if (is_array($item)) {
                    $item = (object) $item;
                }



                if (isset($item->meta_data)) {
                    $HoloID=$this->findKey($item->meta_data,'_holo_sku');
                    if($HoloID){

                        // if($item->total==0){
                        //     continue;
                        // }
                        //$totalfactor=$item->total ?? $item->subtotal;
                        $total = $this->getAmount($item->total, $orderInvoiceFull->currency);
                        $lazy = 0;
                        $scot = 0;
                        if ($payment->vat) {
                            $lazy = $total * 6 / 100;
                            $scot = $total * 3 / 100;
                        }
                        $items[] = array(
                            'id' => (int)$HoloID,
                            'Productid' => $HoloID,
                            'few' => $item->quantity,
                            'price' => $this->getAmount($item->price, $orderInvoiceFull->currency),
                            'discount' => '0',
                            'levy' => $lazy,
                            'scot' => $scot,
                        );
                        $sum_total += $total;
                    }
                    elseif($orderInvoice->invoice_items_no_holo_code){
                        $this->InvoiceChangeStatus($invoice->id, '?????? ???????????? ?????????? ???????? ???????? ???? ?????? ?????????? ??????');
                        return $this->sendResponse('?????? ???????????? ?????????? ???????? ???????? ???? ?????? ?????????? ??????', Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
                    }
                    else{
                        continue;
                    }

                }
                elseif($orderInvoice->invoice_items_no_holo_code){
                    $this->InvoiceChangeStatus($invoice->id, '?????? ???????????? ?????????? ???????? ???????? ???? ?????? ?????????? ??????');
                    return $this->sendResponse('?????? ???????????? ?????????? ???????? ???????? ???? ?????? ?????????? ??????', Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
                }

            }

            //hazineh haml be sorat kala azafe shavad
            if ($orderInvoice->product_shipping) {
                $shipping_lines = $orderInvoiceFull->shipping_lines[0] ?? null;
                if ($shipping_lines) {

                    if (is_array($shipping_lines)) {
                        $shipping_lines = (object) $shipping_lines;
                    }
                    $total = $this->getAmount($shipping_lines->total, $orderInvoiceFull->currency);
                    if ($total>0){

                        $scot = $this->getAmount($shipping_lines->total_tax, $orderInvoiceFull->currency);
                        $items[] = array(
                            'id' => (int)$orderInvoice->product_shipping,
                            'Productid' => $orderInvoice->product_shipping,
                            'few' => 1,
                            'price' => $total-$scot,
                            'discount' => 0,
                            'levy' => 0,
                            'scot' => $scot,
                        );

                        $sum_total += $total;
                    }
                }

            }




            if (sizeof($items) > 0) {
                $payment_type = "bank";
                if ($orderInvoice->status_place_payment == "Installment") {
                    $payment_type = "nesiyeh";
                }
                else if (substr($payment->number, 0, 3) == "101") {
                    $payment_type = "cash";
                }
                $data = array(
                    'generalinfo' => array(
                        'apiname' => 'InvoicePost',
                        'dto' => array(
                            'invoiceinfo' => array(
                                'id' => (int)$orderInvoice->input("id"), //$oreder->id
                                'Type' => 1, //1 faktor frosh 2 pish factor, 3 sefaresh =>$type
                                'kind' => 4,
                                'Date' => $DateString->format('Y-m-d'),
                                'Time' => $DateString->format('H:i:s'),
                                'custid' => $custid,
                                'detailinfo' => $items,
                            ),
                        ),
                    ),
                );


                if ($payment_type == "bank") {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["Bank"] = $sum_total;
                    $data["generalinfo"]["dto"]["invoiceinfo"]["BankSarfasl"] = $payment->number;
                } elseif ($payment_type == "cash") {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["Cash"] = $sum_total;
                    $data["generalinfo"]["dto"]["invoiceinfo"]["CashSarfasl"] = $payment->number;
                } else {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["nesiyeh"] = $sum_total;
                }

                ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/CallApi/InvoicePost',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => array('data' => json_encode($data)),
                    CURLOPT_HTTPHEADER => array(
                        "serial: ".$user->serial,
                        'database: ' . $user->holooDatabaseName,
                        "Authorization: Bearer ".$this->getNewToken(),
                        'access_token:' . $user->apiKey,
                    ),
                ));

                $response = curl_exec($curl);
                $response = json_decode($response);
                log::info("invoice Package");
                log::info(json_encode($data));
                log::info(json_encode($response));
                curl_close($curl);
                if (isset($response->success) and $response->success) {
                    $this->InvoiceChangeStatus($invoice->id, '?????? ?????????? ???????? ?????????? ????');
                    Invoice::where(['id'=>$invoice->id])
                    ->update([
                    'holooInvoice' => $data,
                    ]);
                    $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration finish succsessfuly");
                    return $this->sendResponse('?????? ?????????? ???????? ?????????? ????', Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
                }
                else {
                    $this->InvoiceChangeStatus($invoice->id, json_encode([$response->message]));
                    Invoice::where(['id'=>$invoice->id])
                    ->update([
                        'holooInvoice' => $data
                    ]);
                    //return $this->sendResponse('test', Response::HTTP_OK,$response);
                    $this->recordLog("Invoice Registration", $user->siteUrl, json_encode(['data' => $data]), "error");
                    $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration finish wrong", "error");
                    $this->recordLog("Invoice Registration", $user->siteUrl, json_encode($response), "error");
                }

                return $this->sendResponse($response->message, Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
            }
            $this->InvoiceChangeStatus($invoice->id, '?????????? ?????????? ???????? ??????');
            return $this->sendResponse('?????????? ?????????? ???????? ??????', Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0,"item"=>$orderInvoiceFull]]);
        }
        $this->InvoiceChangeStatus($invoice->id, '?????? ???????????? ?????????? ??????');
        return $this->sendResponse('?????? ???????????? ?????????? ??????', Response::HTTP_OK, ["result" => ["msg_code" => 0,"param"=>$orderInvoice->save_sale_invoice]]);
    }

    private function wcInvoiceBank($orderInvoice, $fee, $custid, $DateString, $kind)
    {
        $user = auth()->user();
        $sarfasl=$fee->sarfasl;
        $total = $this->getAmount($fee->amount, $orderInvoice->currency);
        $items[] = array(
            'id' => $sarfasl,
            'Productid' => $sarfasl,
            'few' => 1,
            'price' => $total,
            'discount' => 0,
            'levy' => 0,
            'scot' => 0,
        );

        $data = array(
            'generalinfo' => array(
                'apiname' => 'InvoicePost',
                'dto' => array(
                    'invoiceinfo' => array(
                        'id' => $orderInvoice->input("id"), //$oreder->id
                        'Type' => 1, //1 faktor frosh 2 pish factor,
                        'kind' => $kind,
                        'Date' => $DateString->format('Y-m-d'),
                        'Time' => $DateString->format('H:i:s'),
                        'custid' => $custid,
                        'detailinfo' => $items,
                    ),
                ),
            ),
        );

        // if ($payment_type == "bank") {
            $data["generalinfo"]["dto"]["invoiceinfo"]["Bank"] = $total;
            $data["generalinfo"]["dto"]["invoiceinfo"]["BankSarfasl"] = $sarfasl;
        // } elseif ($payment_type == "cash") {
        //     $data["generalinfo"]["dto"]["invoiceinfo"]["Cash"] = $total;
        //     $data["generalinfo"]["dto"]["invoiceinfo"]["CashSarfas"] = $sarfasl;
        // } else {
        //     $data["generalinfo"]["dto"]["invoiceinfo"]["nesiyeh"] = $total;
        // }

        ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();
        $userSerial = $user->serial;
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/CallApi/InvoicePost',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('data' => json_encode($data)),
            CURLOPT_HTTPHEADER => array(
                "serial: $userSerial",
                'database: ' . $user->holooDatabaseName,
                "Authorization: Bearer $token",
            ),
        ));
        $response = curl_exec($curl);
        $response = json_decode($response);
        curl_close($curl);

    }

    private function getAmount($amount, $currency)
    {
        //"IRT"
        $zarib=$this->get_tabdel_vahed();
        $zarib=1/$zarib;
        // if ($currency == "toman") {
        //     return (int)$amount * 10;
        // }

        return (int)$amount*$zarib;
    }
    public function wcSingleProductUpdateOld(Request $request)
    {
        ini_set('max_execution_time', 120); // 120 (seconds) = 2 Minutes
        $holoo_product_id = $request->holoo_id;
        $wp_product_id = $request->product_id;
        if(count( explode(":", $wp_product_id))>1){
            $wp_product_id=explode(":", $wp_product_id);
            //product is variant
            $this->wcSingleVariantProductUpdate($wp_product_id,$holoo_product_id,$request);
            return $this->sendResponse("?????????? ???? ???????????? ???? ?????? ????.", Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
        }
        $user = auth()->user();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/article/' . $user->holooDatabaseName . '/' . $holoo_product_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $HolooProd = json_decode($response)->result;


        $param = [
            'id' => $wp_product_id,
            'name' => $this->arabicToPersian($HolooProd->name),
            'regular_price' => $this->get_price_type($request->sales_price_field,$HolooProd),
            'price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'sale_price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'wholesale_customer_wholesale_price' => $this->get_price_type($request->wholesale_price_field,$HolooProd),
            'stock_quantity' => $this->get_exist_type($request->product_stock_field,$HolooProd),
        ];

        $response = $this->updateWCSingleProduct($param);
        return $this->sendResponse("?????????? ???? ???????????? ???? ?????? ????.", Response::HTTP_OK, ["result" => ["msg_code" => $response]]);
        return $response;
    }
    public function wcSingleProductUpdate(Request $request)
    {
        ini_set('max_execution_time', 120); // 120 (seconds) = 2 Minutes
        $holoo_product_id = $request->holoo_id;
        $wp_product_id = $request->product_id;
        if(count( explode(":", $wp_product_id))>1){
            $wp_product_id=explode(":", $wp_product_id);
            //product is variant
            $this->wcSingleVariantProductUpdate($wp_product_id,$holoo_product_id,$request);
            return $this->sendResponse("?????????? ???? ???????????? ???? ?????? ????.", Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
        }
        $user = auth()->user();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?a_Code=' . $holoo_product_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        //$HolooProd = json_decode($response)->result;
        $HolooProd = json_decode($response)->data->product;
        $HolooProd =$HolooProd[0];
        //dd($HolooProd);
        $param = [
            'id' => $wp_product_id,
            'name' => $this->arabicToPersian($HolooProd->name),
            'regular_price' => $this->get_price_type($request->sales_price_field,$HolooProd),
            'price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'sale_price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'wholesale_customer_wholesale_price' => $this->get_price_type($request->wholesale_price_field,$HolooProd),
            'stock_quantity' => $this->get_exist_type($request->product_stock_field,$HolooProd),
        ];

        $response = $this->updateWCSingleProduct($param);
        return $this->sendResponse("?????????? ???? ???????????? ???? ?????? ????.", Response::HTTP_OK, ["result" => ["msg_code" => $response]]);
        return $response;
    }
    public function wcSingleVariantProductUpdateOld(array $wp_product_variant_id,$holoo_product_id,$request)
    {
        ini_set('max_execution_time', 120); // 120 (seconds) = 2 Minutes

        $wp_product_id = $wp_product_variant_id[0];
        $wp_variant_id = $wp_product_variant_id[1];

        $user = auth()->user();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/article/' . $user->holooDatabaseName . '/' . $holoo_product_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $HolooProd = json_decode($response)->result;


        $data = [
            'id' => $wp_product_id,
            'variation_id' => $wp_variant_id,
            'name' => $this->arabicToPersian($HolooProd->a_Name),
            'regular_price' => $this->get_price_type($request->sales_price_field,$HolooProd),
            'price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'sale_price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'wholesale_customer_wholesale_price' => $this->get_price_type($request->wholesale_price_field,$HolooProd),
            'stock_quantity' => $this->get_exist_type($request->product_stock_field,$HolooProd),
        ];
        $wcHolooCode=$HolooProd->a_Code;
        UpdateProductsVariationUser::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$data,$wcHolooCode)->onQueue("high");
        return $this->sendResponse("?????????? ???? ???????????? ???? ?????? ????.", Response::HTTP_OK, ["result" => ["msg_code" => $response]]);
        return $response;
    }
    public function wcSingleVariantProductUpdate(array $wp_product_variant_id,$holoo_product_id,$request)
    {
        ini_set('max_execution_time', 120); // 120 (seconds) = 2 Minutes

        $wp_product_id = $wp_product_variant_id[0];
        $wp_variant_id = $wp_product_variant_id[1];

        $user = auth()->user();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?a_Code=' . $holoo_product_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        //$HolooProd = json_decode($response)->result;
        $HolooProd = json_decode($response)->data->product;
        $HolooProd =$HolooProd[0];

        $data = [
            'id' => $wp_product_id,
            'variation_id' => $wp_variant_id,
            'name' => $this->arabicToPersian($HolooProd->name),
            'regular_price' => $this->get_price_type($request->sales_price_field,$HolooProd),
            'price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'sale_price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'wholesale_customer_wholesale_price' => $this->get_price_type($request->wholesale_price_field,$HolooProd),
            'stock_quantity' => $this->get_exist_type($request->product_stock_field,$HolooProd),
        ];

        $wcHolooCode=$HolooProd->a_Code;
        UpdateProductsVariationUser::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$data,$wcHolooCode)->onQueue("high");
        return $this->sendResponse("?????????? ???? ???????????? ???? ?????? ????.", Response::HTTP_OK, ["result" => ["msg_code" => $response]]);
        return $response;
    }

    public function GetSingleProductHolooOld($holoo_id)
    {
        $user = auth()->user();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/article/' . $user->holooDatabaseName . '/' . $holoo_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;

    }
    public function GetSingleProductHoloo($holoo_id)
    {
        $user = auth()->user();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?a_Code=' . $holoo_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));



        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        log::info("get http code ".$httpcode."  for get single product from cloud for holoo product id: ".$holoo_id);
        curl_close($curl);
        return $response;

    }
    public function wcAddAllHolooProductsCategory(Request $request)
    {
        $user = auth()->user();
        $user_id = $user->id;
        $counter = 0;
        log::info('add new all product resive for user: ' . $user->id);
        if(!$user->allow_insert_product)
            return $this->sendResponse('?????? ?????????? ???????? ???????????? ?????? ???????????? ?????? ?????? ???????? ??????', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);

        if (ProductRequest::where(['user_id' => $user_id])->exists()) {
            return $this->sendResponse('?????? ???? ?????????????? ?????? ?????????? ???? ???? ???????? ?????????? ?????????? ???????? ?????? ???????? ?????????? ???????????? ???? ???????????? ???????? ?????? ?????????? ????????', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
        }


        ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();

        $data = $request->product_cat;
        //dd($data);

        $categories = $this->getAllCategory();
        //return $categories;
        $wcHolooExistCode = app('App\Http\Controllers\WCController')->get_all_holoo_code_exist();
        $param = [
            'sales_price_field' => $request->sales_price_field,
            'special_price_field' => $request->special_price_field,
            'special_price_field' => $request->special_price_field,
            'wholesale_price_field' => $request->wholesale_price_field,
            'insert_product_with_zero_inventory' =>$request->insert_product_with_zero_inventory,
            'product_cat' => $request->product_cat
        ];

        foreach ($categories->result as $key => $category) {
            if (array_key_exists($category->m_groupcode.'-'.$category->s_groupcode, $data) && $data[$category->m_groupcode.'-'.$category->s_groupcode]!="") {
                FindProductInCategory::dispatch((object)[
                    "id"=>$user->id,
                    "siteUrl"=>$user->siteUrl,
                    "consumerKey"=>$user->consumerKey,
                    "consumerSecret"=>$user->consumerSecret,
                    "serial"=>$user->serial,
                    "holooDatabaseName"=>$user->holooDatabaseName,
                    "apiKey"=>$user->apiKey,
                    "token"=>$user->token,
                ],
                    $category,$token,$wcHolooExistCode,$param,$category->m_groupcode.'-'.$category->s_groupcode)->onQueue("low");

            }
        }

        curl_close($curl);

        // if ($counter == 0) {
        //     return $this->sendResponse("?????????? ?????????????? ???? ?????? ??????????", Response::HTTP_OK, ["result" => ["msg_code" => 2]]);
        // }

        $productRequest = new ProductRequest;
        $productRequest->user_id = $user_id;
        $productRequest->request_time = Carbon::now();
        $productRequest->save();

        return $this->sendResponse(" ?????????????? ?????? ?????????????? ???????? ???? ???????????? ?????? ??????????. ", Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
    }

    public function wcAddAllHolooProductsCategory2(Request $request)
    {
        $user = auth()->user();
        $user_id = $user->id;
        $counter = 0;
        if (ProductRequest::where(['user_id' => $user_id])->exists()) {
            //return $this->sendResponse('?????? ???? ?????????????? ?????? ?????????? ???? ???? ???????? ?????????? ?????????? ???????? ?????? ???????? ?????????? ???????????? ???? ???????????? ???????? ?????? ?????????? ????????', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
        }


        ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();

        $data = $request->product_cat;
        //dd($data);

       CreateProductFind::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"serial"=>$user->serial,"apiKey"=>$user->apiKey,"holooDatabaseName"=>$user->holooDatabaseName,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret,"cloudTokenExDate"=>$user->cloudTokenExDate,"cloudToken"=>$user->cloudToken],$data,(object)$request->all(),$token,1)->onQueue("low");

        return $this->sendResponse(" ?????????????? ?????? ?????????????? ???????? ???? ???????????? ?????? ??????????. ", Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
    }

    public function wcGetExcelProducts()
    {
        $counter = 0;
        $user = auth()->user();
        // if($user->user_traffic!="light"){
        //   $this->wcGetExcelProducts2();
        // }

        $user_id = $user->id;
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        ini_set('max_execution_time', 0);
        set_time_limit(0);

        log::info('request resive download file for user: ' . $user->id);
        $file=public_path("download/$user_id.xls");
        $yesdate = strtotime("-1 days");
        // if (File::exists($file) and filemtime($file) < $yesdate ) {
        //     $filename = $user_id;
        //     $file = "download/" . $filename . ".xls";
        //     return $this->sendResponse('???????? ???????? ????????????', Response::HTTP_OK, ["result" => ["url" => asset($file)]]);
        // }
        log::info('products file not found try for make new for user: ' . $user->id);
        return $this->sendResponse('???????? ???????? ????????????', Response::HTTP_OK, ["result" => ["url" => route("liveWcGetExcelProducts", ["user_id" => $user->id])]]);

        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();

        // $productCategory = app('App\Http\Controllers\WCController')->get_wc_category();

        // $data = $productCategory;
        //$data = ['02' => 12];

        $categories = $this->getAllCategory();
        //dd($categories);

        //$wcHolooExistCode = app('App\Http\Controllers\WCController')->get_all_holoo_code_exist();
        $allRespose = [];
        $sheetes = [];
        foreach ($categories->result as $key => $category) {

            //if (array_key_exists($category->m_groupcode.'-'.$category->s_groupcode, $data)) {
                // if ($data[$category->m_groupcode.'-'.$category->s_groupcode]==""){
                //     continue;
                // }
                $sheetes[$category->m_groupcode.'-'.$category->s_groupcode] = array();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?sidegroupcode='.$category->s_groupcode.'&maingroupcode='.$category->m_groupcode,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: ' . $user->serial,
                        'database: ' . $user->holooDatabaseName,
                        'Authorization: Bearer ' . $this->getNewToken(),
                    ),
                ));
                $response = curl_exec($curl);

                $HolooProds = json_decode($response)->data->product;

                foreach ($HolooProds as $HolooProd) {

                   // if (!in_array($HolooProd->a_Code, $wcHolooExistCode)) {

                        $param = [
                            "holooCode" => $HolooProd->a_Code,
                            "holooName" => $this->arabicToPersian($HolooProd->name),
                            "holooRegularPrice" => (string) $HolooProd->sellPrice ?? 0,
                            "holooStockQuantity" => (string) $HolooProd->few ?? 0,
                            "holooCustomerCode" => ($HolooProd->code) ?? "",
                        ];

                        $sheetes[$category->m_groupcode.'-'.$category->s_groupcode][] = $param;

                   //}

                }
            //}
        }

        curl_close($curl);
        if (count($sheetes) != 0) {
            $excel = new ReportExport($sheetes);
            $filename = $user_id;
            $file = "download/" . $filename . ".xls";
            Excel::store($excel, $file, "asset");
            return $this->sendResponse('???????? ???????? ????????????', Response::HTTP_OK, ["result" => ["url" => asset($file)]]);
        }
        else {
            return $this->sendResponse('???????????? ?????? ?????????? ???????? ?????????? ???????? ??????', Response::HTTP_OK, ["result" => ["url" => "#"]]);
        }

    }

    public function wcGetExcelProducts2()
    {

        $counter = 0;
        $user = auth()->user();
        $user_id = $user->id;
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        ini_set('max_execution_time', 0);
        set_time_limit(0);

        log::info('request resive download file for user: ' . $user->id);
        // $file=public_path("download/$user_id.xls");
        // $yesdate = strtotime("-1 days");
        // if (File::exists($file) and filemtime($file) < $yesdate ) {
        //     $filename = $user_id;
        //     $file = "download/" . $filename . ".xls";
        //     return $this->sendResponse('???????? ???????? ????????????', Response::HTTP_OK, ["result" => ["url" => asset($file)]]);
        // }
        log::info('products file not found try for make new for user: ' . $user->id);
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();

        // $productCategory = app('App\Http\Controllers\WCController')->get_wc_category();

        // $data = $productCategory;
        //$data = ['02' => 12];

        //$categories = $this->getAllCategory();
        //dd($categories);

        //$wcHolooExistCode = app('App\Http\Controllers\WCController')->get_all_holoo_code_exist();
        $allRespose = [];
        $sheetes = [];
        // foreach ($categories->result as $key => $category) {

            //if (array_key_exists($category->m_groupcode.'-'.$category->s_groupcode, $data)) {
                // if ($data[$category->m_groupcode.'-'.$category->s_groupcode]==""){
                //     continue;
                // }
                $sheetes["kala"] = array();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: ' . $user->serial,
                        'database: ' . $user->holooDatabaseName,
                        'Authorization: Bearer ' . $this->getNewToken(),
                    ),
                ));
                $response = curl_exec($curl);
                $HolooProds = json_decode($response)->data->product;

                foreach ($HolooProds as $HolooProd) {

                   // if (!in_array($HolooProd->a_Code, $wcHolooExistCode)) {

                        $param = [
                            "holooCode" => $HolooProd->a_Code,
                            "holooName" => $this->arabicToPersian($HolooProd->name),
                            "holooRegularPrice" => (string) $HolooProd->sellPrice ?? 0,
                            "holooStockQuantity" => (string) $HolooProd->few ?? 0,
                            "holooCustomerCode" => ($HolooProd->code) ?? "",
                        ];

                        $sheetes["kala"][] = $param;

                   //}

                }
            //}
        //}

        curl_close($curl);
        if (count($sheetes) != 0) {
            $excel = new ReportExport($sheetes);
            $filename = $user_id;
            $file = "download/" . $filename . ".xls";
            Excel::store($excel, $file, "asset");
            return $this->sendResponse('???????? ???????? ????????????', Response::HTTP_OK, ["result" => ["url" => asset($file)]]);
        }
        else {
            return $this->sendResponse('???????????? ?????? ?????????? ???????? ?????????? ???????? ??????', Response::HTTP_OK, ["result" => ["url" => "#"]]);
        }

    }


    public function addToCart(Request $orderInvoice)
    {
        return $this->sendResponse('?????? ?????????? ???????? ?????????? ????', Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
    }

    public function getAccountBank(Request $config)
    {
        $user = auth()->user();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Bank/GetBank',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        curl_close($curl);
        return $this->sendResponse('???????? ?????????????? ??????????', Response::HTTP_OK, ["result" => $response->data]);

    }

    public function getAccountCash(Request $config)
    {
        $user = auth()->user();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Cash/GetCash',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        curl_close($curl);
        return $this->sendResponse('???????? ?????????????? ????????', Response::HTTP_OK, ["result" => $response->data]);

    }

    private function getHolooCustomerID($customer, $customerId)
    {
        if (is_array($customer)) {
            $customer = (object) $customer;
        }
        $holooCustomers = $this->getHolooDataTable();
        if(!is_object($holooCustomers) or !isset($holooCustomers->result)){
            log::alert("holooCustomers is not any response in cloud");
            log::alert($holooCustomers);
        }
        else{
            foreach ($holooCustomers->result as $holloCustomer) {
                if ($holloCustomer->c_Mobile == $customer->phone) {
                    log::info("finded customer: ".$holloCustomer->c_Code_C);
                    log::info("customer holoo mobile: ".$holloCustomer->c_Mobile);
                    log::info("customer holoo name: ".$holloCustomer->c_Name);
                    return $holloCustomer->c_Code_C;
                }
            }

        }
        log::info("customer for your mobile number not found i want to create new customer to holoo for mobile ".$customer->phone);
        return $this->createHolooCustomer($customer, $customerId);

    }

    private function getHolooDataTable($table = "customer")
    {
        $user = auth()->user();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://myholoo.ir/api/Service/" . $table . "/" . $user->holooDatabaseName,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        return $response;
    }

    private function createHolooCustomer($customer, $customerId)
    {
        $user = auth()->user();
        $curl = curl_init();
        $customer_account=1030001;


        $data = [
            "generalinfo" => [
                "apiname" => "CustomerPost",
                "dto" => [
                    "custinfo" => [
                        [

                            "id" => rand(100000, 999999),
                            "bedsarfasl" => $customer_account,
                            "name" => $customer->first_name . ' ' . $customer->last_name.' - '.$customer->phone,
                            "ispurchaser" => true,
                            "isseller" => false,
                            "custtype" => 0,
                            "kind" => 3,
                            "tel" => "",
                            "mobile" => $customer->phone,
                            //"city" => $customer->city,
                            //"ostan" => $customer->state,
                            "email" => $customer->email,
                            //"zipcode" => $customer->postcode,
                            //"address" => $customer->address_1,
                        ],
                    ],
                ],
            ],
        ];

        log::info("customer data: ".json_encode($data));
        $token = $this->getNewToken();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://myholoo.ir/api/CallApi/CustomerPost",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array("data" => json_encode($data)),
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'access_token:' . $user->apiKey,
                "Authorization: Bearer $token",
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        log::info("customer: ".json_encode($response));
        if (isset($response->success) and $response->success) {
            sleep(20);
            return $this->getHolooCustomerID($customer, $customerId);
        }

        return false;
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

    private function genericFee($fee, $total)
    {

        try {
            $arr = explode("#", $fee);
            if (sizeof($arr) < 3) {
                return false;
            }

            $sarfasl = $arr[1];
            $pr = $arr[2];
            if (strlen($pr) > 0) {
                $pr = explode('*', $pr);
                foreach ($pr as $p) {
                    if (str_contains($p, '%')) {
                        $a = explode("%", $p);
                        if (sizeof($a) < 2) {
                            return false;
                        }
                        if ($a[0] <= $total) {
                            $amount = $total * $a[1] / 100;
                        }

                    } elseif (str_contains($p, ':')) {
                        $a = explode(":", $p);
                        if (sizeof($a) < 2) {
                            return false;
                        }
                        if ($a[0] <= $total) {
                            $amount = $a[1];
                        }
                    } else {
                        if (is_numeric($p)) {
                            $amount = $p;
                        } else {
                            return false;
                        }
                    }
                }
            } else {
                return false;
            }

            $res = new stdClass();
            $res->amount = $amount ?? 0;
            $res->sarfasl = $sarfasl;

            return $res;

        } catch (\Exception$ex) {
            return false;
        }

    }

    private function get_price_type($price_field,$HolooProd){
        // "sales_price_field": "1",
        // "special_price_field": "2",
        // "wholesale_price_field": "3",

        // "sellPrice": 12000,
        // "sellPrice2": 0,
        // "sellPrice3": 0,
        // "sellPrice4": 0,
        // "sellPrice5": 0,
        // "sellPrice6": 0,
        // "sellPrice7": 0,
        // "sellPrice8": 0,
        // "sellPrice9": 0,
        // "sellPrice10": 0,


        if((int)$price_field==1){
            return (int)(float) $HolooProd->sellPrice*$this->get_tabdel_vahed();
        }
        else{
            return (int)(float) $HolooProd->{"sellPrice".$price_field}*$this->get_tabdel_vahed();
        }
    }

    public function get_all_accounts(){
        $user = auth()->user();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Bank/GetBank',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);


        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Cash/GetCash',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response2 = curl_exec($curl);
        $response2 = json_decode($response2);

        $obj = (object)array_merge_recursive((array)$response2->data , (array)$response->data);
        curl_close($curl);
        return $this->sendResponse('???????? ??????????????', Response::HTTP_OK,  $obj);
    }

    public function get_shipping_accounts(){
        $user = auth()->user();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Bank/GetBank',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);


        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Cash/GetCash',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response2 = curl_exec($curl);
        $response2 = json_decode($response2);
        // return $this->sendResponse('???????? ??????????????', Response::HTTP_OK,  $response2);
        $obj = (object)array_merge_recursive((array)$response2->data , (array)$response->data);
        curl_close($curl);
        return $this->sendResponse('???????? ??????????????', Response::HTTP_OK,  $obj);
    }

    public function get_shipping_accounts_by_product(){
        $obj=$this->get_all_wc_products_code();
        return $this->sendResponse('???????? ??????????????', Response::HTTP_OK,  $obj);
    }


    public function get_all_wc_products_code(){
        $user=auth()->user();

        $status= "";

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

        $response_products=[];
        $json=[
            "sarfasl_Code"=> "",
            "sarfasl_Name"=> "??????????????",
        ];
        $response_products[]=(object) $json;
        foreach ($all_products as $WCProd) {
            if (count($WCProd->meta_data)>0) {
                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                if ($wcHolooCode) {
                    $json=[
                        "sarfasl_Code"=> $wcHolooCode,
                        "sarfasl_Name"=> $WCProd->name,
                    ];
                    $response_products[]=(object) $json;
                }
            }
        }


        return (object)$response_products;
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


    public function GetAllCustomerAccount(){
        $user=auth()->user();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Customer/GetCustomerGroup',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        $response = $response->data->bedGroup;
        if(count($response)==0){
            $response[] =(object)[
                "sarfasl_Code"=> "1030001",
                "sarfasl_Name"=> "?????? ??????"
            ];
        }

        curl_close($curl);
        return $this->sendResponse('???????? ??????????????', Response::HTTP_OK,  $response);
    }

    public function changeProduct(Request $config){
        // log::info($config->id);
        // log::info($config->meta_data);
        return $this->sendResponse("???????????? ?????????? ???????????? ????.", Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
    }

    public function InvoiceChangeStatus($id,$input){
        if (!is_array($input)){
            $input = [$input];
        }
        $input = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
            return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
        }, $input);
        Invoice::where(['id'=>$id,])
        ->update([
        'status' =>$input ,
        ]);
    }

    private function get_exist_type($exist_field,$HolooProd){
        // "sales_price_field": "1",
        // "special_price_field": "2",
        // "wholesale_price_field": "3",


        if((int)$exist_field==1){
            return (int)(float) $HolooProd->few;
        }
        elseif((int)$exist_field==2){
            return (int)(float) $HolooProd->fewspd;
        }
        elseif((int)$exist_field==3){
            return (int)(float) $HolooProd->fewtak;
        }
    }
}
