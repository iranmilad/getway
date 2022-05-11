<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Jobs\AddProductsUser;
use App\Jobs\FindProductInCategory;
use App\Models\ProductRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

class HolooController extends Controller
{
    private function getNewToken(): string
    {
        $user = auth()->user();

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
                User::where(['id' => $user->id])
                ->update([
                    'cloudTokenExDate' => Carbon::now()->addDay(1),
                    'cloudToken' => $response->result->apikey,
                ]);

                return $response->result->apikey;
            }
            else {
                dd("توکن دریافت نشد", $response);

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

        $response = $this->getAllCategory();
        if ($response) {
            $category = [];

            foreach ($response->result as $row) {
                array_push($category, array("m_groupcode" => $row->m_groupcode."-".$row->s_groupcode, "m_groupname" => $this->arabicToPersian($row->s_groupname)));
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
            'response' => $response,
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

    public function getAllHolooProducts()
    {
        return $this->sendResponse('لیست تمامی محصولات هلو', Response::HTTP_OK, $this->fetchAllHolloProds());
    }

    public function fetchAllHolloProds()
    {
        $user = auth()->user();
        $curl = curl_init();

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
                    $totalProduct=array_merge(json_decode($response, true)->result,$totalProduct);
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

        $order = array(
            "id" => 4656,
            "parent_id" => 0,
            "status" => "cancelled",
            "currency" => "USD",
            "version" => "6.2.0",
            "prices_include_tax" => false,
            "date_created" => array(
                "date" => "2022-03-06 19:20:35.000000",
                "timezone_type" => 3,
                "timezone" => "Atlantic/Azores",
            ),
            "date_modified" => array(
                "date" => "2022-03-06 20:38:39.000000",
                "timezone_type" => 3,
                "timezone" => "Atlantic/Azores",
            ),
            "discount_total" => "0",
            "discount_tax" => "0",
            "shipping_total" => "0",
            "shipping_tax" => "0",
            "cart_tax" => "0",
            "total" => "8000.00",
            "total_tax" => "0",
            "customer_id" => 2,
            "order_key" => "wc_order_9ukFS1c8klNMe",
            "billing" => array(
                "first_name" => "milad",
                "last_name" => "kazemi",
                "company" => "ثث",
                "address_1" => "تهران",
                "address_2" => "ثقثق",
                "city" => "تهران",
                "state" => "THR",
                "postcode" => "1937933613",
                "country" => "IR",
                "email" => "kazemi.milad@gmail.com",
                "phone" => "09189997745",
            ),
            "shipping" => array(
                "first_name" => "",
                "last_name" => "",
                "company" => "",
                "address_1" => "",
                "address_2" => "",
                "city" => "",
                "state" => "",
                "postcode" => "",
                "country" => "",
                "phone" => "",
            ),
            "payment_method" => "bankmellat",
            "payment_method_title" => "بانک ملت",
            "transaction_id" => "",
            "customer_ip_address" => "5.112.133.167",
            "customer_user_agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            "created_via" => "checkout",
            "customer_note" => "",
            "date_completed" => null,
            "date_paid" => null,
            "cart_hash" => "7fd3c40c67e5797ec4482b019a058dbb",
            "number" => "4656",
            "meta_data" => array(
                array(
                    "id" => 83643,
                    "key" => "is_vat_exempt",
                    "value" => "no",
                ),
                array(
                    "id" => 83644,
                    "key" => "holo_status",
                    "value" => "ثبت فاکتور فروش انجام شد",
                ),

            ),
            "line_items" => array(
                0 => array(
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
                    'taxes' => array(
                        0 => array(
                            'id' => 75,
                            'total' => '0.45',
                            'subtotal' => '0.45',
                        ),
                    ),
                    'meta_data' => array(
                    ),
                    'sku' => '',
                    'price' => 3,
                ),
                1 => array(
                    'id' => 316,
                    'name' => 'Ship Your Idea &ndash; Color: Black, Size: M Test',
                    'product_id' => 22,
                    'variation_id' => 23,
                    'quantity' => 2,
                    'tax_class' => '',
                    'subtotal' => '12.00',
                    'subtotal_tax' => '0.90',
                    'total' => '24.00',
                    'total_tax' => '0.90',
                    'taxes' => array(
                        0 => array(
                            'id' => 75,
                            'total' => '0.9',
                            'subtotal' => '0.9',
                        ),
                    ),
                    'meta_data' => array(
                        0 => array(
                            'id' => 2095,
                            'key' => 'pa_color',
                            'value' => 'black',
                        ),
                        1 => array(
                            'id' => 2096,
                            'key' => 'size',
                            'value' => 'M Test',
                        ),
                        array(
                            "id" => 83644,
                            "key" => "_holo_sku",
                            "value" => "0101004",
                        ),
                    ),
                    'sku' => 'Bar3',
                    'price' => 12,
                ),
            ),
            "tax_lines" => array(),
            "shipping_lines" => array(),
            "fee_lines" => array(),
            "coupon_lines" => array(),
            "token" => "35|hhpYiw8fz53470WHlCa1D8w9EAAlKyx7ZS6KXYZ9",
            "licence_status" => "active",
            "licence_key" => "pMEufCKJAj3N",
            "woo_holo_change" => "update_product",
            "holo_status" => "ok",
            "holo_token" => "35|hhpYiw8fz53470WHlCa1D8w9EAAlKyx7ZS6KXYZ9",
            "payment" => array(
                "cod" => array(
                    "number" => "10100010001",
                    "fee" => "",
                    "vat" => "1",
                ),
                "bankmellat" => array(
                    "number" => "10200010001",
                    "fee" => "",
                    "vat" => "1",
                ),
                "WC_payping" => array(
                    "number" => "10200010001",
                    "fee" => "",
                    "vat" => "1",
                ),
                "WC_ZPal" => array(
                    "number" => "10200010001",
                    "fee" => "",
                    "vat" => "1",
                ),
                "wallet" => array(
                    "number" => "10200010001",
                    "fee" => "",
                    "vat" => "1",
                ),
            ),
            "holo_categories" => array(
                array(
                    "m_groupcode" => "01",
                    "m_groupname" => "خدمات",
                ),
                array(
                    "m_groupcode" => "02",
                    "m_groupname" => "ایده نگار",
                ),
                array(
                    "m_groupcode" => "03",
                    "m_groupname" => "سخت افزار",
                ),
                array(
                    "m_groupcode" => "04",
                    "m_groupname" => "اینترنت",
                ),
                array(
                    "m_groupcode" => "05",
                    "m_groupname" => "دخان",
                ),
                array(
                    "m_groupcode" => "06",
                    "m_groupname" => "محصولات اولیه",
                ),
                array(
                    "m_groupcode" => "07",
                    "m_groupname" => "بهداشتی",
                ),
                array(
                    "m_groupcode" => "08",
                    "m_groupname" => "سی دی",
                ),
            ),
            "product_cat" => array(
                "01" => "",
                "02" => "",
                "03" => "",
                "04" => "",
                "05" => "",
                "06" => "",
                "07" => "",
                "08" => "",
            ),
            "update_product_price" => "0",
            "update_product_stock" => "0",
            "update_product_name" => "0",
            "insert_new_product" => "0",
            "status_place_payment" => "cash",
            "sales_price_field" => "1",
            "product_stock_field" => "1",
            "save_sale_invoice" => "1",
            "special_price_field" => "2",
            "wholesale_price_field" => "3",
            "save_pre_sale_invoice" => "0",
            "insert_product_with_zero_inventory" => "0",
            "invoice_items_no_holo_code" => "0",
        );

        $orderInvoice->request->add($order);

        if ($orderInvoice->save_sale_invoice) {
            $_data = (object) $orderInvoice->input("date_created");
            $DateString = Carbon::parse($_data->date ?? now(), $_data->timezone);
            $DateString->setTimezone('Asia/Tehran');
            //return $DateString->format('Y-m-d');

            // if (isset($orderInvoice->invoicePaid) && $orderInvoice->invoicePaid == "paid") {
            //     $type = 1;
            // } else if (isset($orderInvoice->invoicePaid) && $orderInvoice->invoicePaid == "prePaid") {
            //     $type = 2;
            // } else if (isset($orderInvoice->invoicePaid) && $orderInvoice->invoicePaid == "order") {
            //     $type = 3;
            // } else {
            //     return $this->sendResponse('ثبت فاکتور انجام نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            // }
            if (!$orderInvoice->save_sale_invoice || $orderInvoice->save_sale_invoice == 0) {
                return $this->sendResponse('ثبت فاکتور انجام نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            } else {
                $type = $orderInvoice->save_sale_invoice;
            }

            $custid = $this->getHolooCustomerID($orderInvoice->billing, $orderInvoice->customer_id);
            if (!$custid) {
                return $this->sendResponse("ثبت فاکتور انجام نشد", Response::HTTP_INTERNAL_SERVER_ERROR, ["result" => ["msg_code" => 0]]);
            }

            $items = array();
            $sum_total = 0;
            $payment_methos = $orderInvoice->payment_method;
            if (is_string($orderInvoice->payment)) {
                $payment = json_decode($orderInvoice->payment);
            } elseif (is_array($orderInvoice->payment)) {
                $payment = (object) $orderInvoice->payment;
            }

            $payment = (object) $payment->$payment_methos;

            foreach ($orderInvoice->line_items as $item) {
                if (is_array($item)) {
                    $item = (object) $item;
                }

                if (isset($item->meta_data) && is_array($item->meta_data)) {
                    foreach ($item->meta_data as $meta) {

                        if (is_array($meta)) {
                            $meta = (object) $meta;
                        }
                        if ($meta->key == "_holo_sku") {
                            $total = $this->getAmount($item->total, $orderInvoice->currency);
                            $lazy = 0;
                            $scot = 0;
                            if ($payment->vat) {
                                $lazy = $total * 6 / 100;
                                $scot = $total * 3 / 100;
                            }
                            $items[] = array(
                                'id' => $meta->value,
                                'Productid' => $meta->value,
                                'few' => $item->quantity,
                                'price' => $item->price,
                                'discount' => '0',
                                'levy' => $lazy,
                                'scot' => $scot,
                            );
                            $sum_total += $total;
                        }

                    }
                }

            }

            if ($orderInvoice->product_shipping) {
                $shipping_lines = $orderInvoice->shipping_lines[0] ?? null;
                if ($shipping_lines) {
                    if (is_array($shipping_lines)) {
                        $shipping_lines = (object) $shipping_lines;
                    }

                    $total = $this->getAmount($shipping_lines->total, $orderInvoice->currency);
                    $scot = $this->getAmount($shipping_lines->total_tax, $orderInvoice->currency);
                    $items[] = array(
                        'id' => $orderInvoice->product_shipping,
                        'Productid' => $orderInvoice->product_shipping,
                        'few' => 1,
                        'price' => $total,
                        'discount' => 0,
                        'levy' => 0,
                        'scot' => $scot,
                    );

                    $sum_total += $total;
                }

            }

            if (sizeof($items) > 0) {
                $payment_type = "bank";
                if ($orderInvoice->status_place_payment == "Installment") {
                    $payment_type = "nesiyeh";
                } else if (substr($payment->number, 0, 3) == "101") {
                    $payment_type = "cash";
                }
                $data = array(
                    'generalinfo' => array(
                        'apiname' => 'InvoicePost',
                        'dto' => array(
                            'invoiceinfo' => array(
                                'id' => $orderInvoice->input("id"), //$oreder->id
                                'Type' => 2, //1 faktor frosh 2 pish factor,
                                'kind' => $type,
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
                    $data["generalinfo"]["dto"]["invoiceinfo"]["CashSarfas"] = $payment->number;
                } else {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["nesiyeh"] = $sum_total;
                }

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
                if ($response->success) {
                    $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration finish succsessfuly");
                    return $this->sendResponse('ثبت سفارش فروش انجام شد', Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
                } else {
                    $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration finish wrong", "error");
                    $this->recordLog("Invoice Registration", $user->siteUrl, $response, "error");
                }

                return $this->sendResponse($response->message, Response::HTTP_INTERNAL_SERVER_ERROR, ["result" => ["msg_code" => 0]]);
            }

        }
    }

    public function wcInvoicePayed(Request $orderInvoice)
    {
        $user = auth()->user();
        $this->recordLog("Invoice Payed", $user->siteUrl, "Invoice Payed receive");

        // return response()->json($this->genericFee("#102564#25000%3", 25000));

        $order = array(
            "id" => 4656,
            "parent_id" => 0,
            "status" => "cancelled",
            "currency" => "USD",
            "version" => "6.2.0",
            "prices_include_tax" => false,
            "date_created" => array(
                "date" => "2022-03-06 19:20:35.000000",
                "timezone_type" => 3,
                "timezone" => "Atlantic/Azores",
            ),
            "date_modified" => array(
                "date" => "2022-03-06 20:38:39.000000",
                "timezone_type" => 3,
                "timezone" => "Atlantic/Azores",
            ),
            "discount_total" => "0",
            "discount_tax" => "0",
            "shipping_total" => "0",
            "shipping_tax" => "0",
            "cart_tax" => "0",
            "total" => "8000.00",
            "total_tax" => "0",
            "customer_id" => 12,
            "order_key" => "wc_order_9ukFS1c8klNMe",
            "billing" => array(
                "first_name" => "milad",
                "last_name" => "kazemi",
                "company" => "ثث",
                "address_1" => "تهران",
                "address_2" => "ثقثق",
                "city" => "تهران",
                "state" => "THR",
                "postcode" => "1937933613",
                "country" => "IR",
                "email" => "kazemi.milad@gmail.com",
                "phone" => "09189997740",
            ),
            "shipping" => array(
                "first_name" => "",
                "last_name" => "",
                "company" => "",
                "address_1" => "",
                "address_2" => "",
                "city" => "",
                "state" => "",
                "postcode" => "",
                "country" => "",
                "phone" => "",
            ),
            "payment_method" => "bankmellat",
            "payment_method_title" => "بانک ملت",
            "transaction_id" => "",
            "customer_ip_address" => "5.112.133.167",
            "customer_user_agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            "created_via" => "checkout",
            "customer_note" => "",
            "date_completed" => null,
            "date_paid" => array(
                "date" => "2022-03-06 20:38:39.000000",
                "timezone_type" => 3,
                "timezone" => "Atlantic/Azores",
            ),
            "cart_hash" => "7fd3c40c67e5797ec4482b019a058dbb",
            "number" => "4656",
            "meta_data" => array(
                array(
                    "id" => 83643,
                    "key" => "is_vat_exempt",
                    "value" => "no",
                ),
                array(
                    "id" => 83644,
                    "key" => "holo_status",
                    "value" => "ثبت فاکتور فروش انجام شد",
                ),

            ),
            "line_items" => array(
                0 => array(
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
                    'taxes' => array(
                        0 => array(
                            'id' => 75,
                            'total' => '0.45',
                            'subtotal' => '0.45',
                        ),
                    ),
                    'meta_data' => array(
                    ),
                    'sku' => '',
                    'price' => 3,
                ),
                1 => array(
                    'id' => 316,
                    'name' => 'Ship Your Idea &ndash; Color: Black, Size: M Test',
                    'product_id' => 22,
                    'variation_id' => 23,
                    'quantity' => 2,
                    'tax_class' => '',
                    'subtotal' => '12.00',
                    'subtotal_tax' => '0.90',
                    'total' => '24.00',
                    'total_tax' => '0.90',
                    'taxes' => array(
                        0 => array(
                            'id' => 75,
                            'total' => '0.9',
                            'subtotal' => '0.9',
                        ),
                    ),
                    'meta_data' => array(
                        0 => array(
                            'id' => 2095,
                            'key' => 'pa_color',
                            'value' => 'black',
                        ),
                        1 => array(
                            'id' => 2096,
                            'key' => 'size',
                            'value' => 'M Test',
                        ),
                        array(
                            "id" => 83644,
                            "key" => "_holo_sku",
                            "value" => "0101004",
                        ),
                    ),
                    'sku' => 'Bar3',
                    'price' => 12,
                ),
            ),
            "tax_lines" => array(),
            "shipping_lines" => array(),
            "fee_lines" => array(),
            "coupon_lines" => array(),
            "token" => "35|hhpYiw8fz53470WHlCa1D8w9EAAlKyx7ZS6KXYZ9",
            "licence_status" => "active",
            "licence_key" => "pMEufCKJAj3N",
            "woo_holo_change" => "update_product",
            "holo_status" => "ok",
            "holo_token" => "35|hhpYiw8fz53470WHlCa1D8w9EAAlKyx7ZS6KXYZ9",
            "payment" => array(
                "cod" => array(
                    "number" => "10100010001",
                    "fee" => "",
                    "vat" => "1",
                ),
                "bankmellat" => array(
                    "number" => "10200010001",
                    "fee" => "#102564#25000%3",
                    "vat" => "1",
                ),
                "WC_payping" => array(
                    "number" => "10200010001",
                    "fee" => "",
                    "vat" => "1",
                ),
                "WC_ZPal" => array(
                    "number" => "10200010001",
                    "fee" => "",
                    "vat" => "1",
                ),
                "wallet" => array(
                    "number" => "10200010001",
                    "fee" => "",
                    "vat" => "1",
                ),
            ),
            "holo_categories" => array(
                array(
                    "m_groupcode" => "01",
                    "m_groupname" => "خدمات",
                ),
                array(
                    "m_groupcode" => "02",
                    "m_groupname" => "ایده نگار",
                ),
                array(
                    "m_groupcode" => "03",
                    "m_groupname" => "سخت افزار",
                ),
                array(
                    "m_groupcode" => "04",
                    "m_groupname" => "اینترنت",
                ),
                array(
                    "m_groupcode" => "05",
                    "m_groupname" => "دخان",
                ),
                array(
                    "m_groupcode" => "06",
                    "m_groupname" => "محصولات اولیه",
                ),
                array(
                    "m_groupcode" => "07",
                    "m_groupname" => "بهداشتی",
                ),
                array(
                    "m_groupcode" => "08",
                    "m_groupname" => "سی دی",
                ),
            ),
            "product_cat" => array(
                "01" => "",
                "02" => "",
                "03" => "",
                "04" => "",
                "05" => "",
                "06" => "",
                "07" => "",
                "08" => "",
            ),
            "update_product_price" => "0",
            "update_product_stock" => "0",
            "update_product_name" => "0",
            "insert_new_product" => "0",
            "status_place_payment" => "cash",
            "sales_price_field" => "1",
            "product_stock_field" => "1",
            "save_sale_invoice" => "1",
            "special_price_field" => "2",
            "wholesale_price_field" => "3",
            "save_pre_sale_invoice" => "0",
            "insert_product_with_zero_inventory" => "0",
            "invoice_items_no_holo_code" => "0",
        );

        //$orderInvoice->request->add($order);

        if ($orderInvoice->save_sale_invoice) {
            $_data = (object) $orderInvoice->input("date_paid");
            $DateString = Carbon::parse($_data->date ?? now(), $_data->timezone);

            $DateString->setTimezone('Asia/Tehran');
            // return $DateString;
            //return $DateString->format('Y-m-d');

            // if (isset($orderInvoice->invoicePaid) && $orderInvoice->invoicePaid == "paid") {
            //     $type = 1;
            // } else if (isset($orderInvoice->invoicePaid) && $orderInvoice->invoicePaid == "prePaid") {
            //     $type = 2;
            // } else if (isset($orderInvoice->invoicePaid) && $orderInvoice->invoicePaid == "order") {
            //     $type = 3;
            // } else {
            //     return $this->sendResponse('ثبت فاکتور انجام نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            // }
            if (!$orderInvoice->save_sale_invoice || $orderInvoice->save_sale_invoice == 0) {
                return $this->sendResponse('ثبت فاکتور انجام نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            } else {
                $type = $orderInvoice->save_sale_invoice;
            }

            $custid = $this->getHolooCustomerID($orderInvoice->billing, $orderInvoice->customer_id);

            if (!$custid) {
                return $this->sendResponse("ثبت فاکتوdsd انجام نشد", Response::HTTP_INTERNAL_SERVER_ERROR, ["result" => ["msg_code" => 0]]);
            }

            $items = array();
            $sum_total = 0;
            $payment_methos = $orderInvoice->payment_method;
            if (is_string($orderInvoice->payment)) {
                $payment = json_decode($orderInvoice->payment);
            } elseif (is_array($orderInvoice->payment)) {
                $payment = (object) $orderInvoice->payment;
            }

            $payment = (object) $payment->$payment_methos;

            foreach ($orderInvoice->line_items as $item) {
                if (is_array($item)) {
                    $item = (object) $item;
                }

                if (isset($item->meta_data) && is_array($item->meta_data)) {
                    foreach ($item->meta_data as $meta) {

                        if (is_array($meta)) {
                            $meta = (object) $meta;
                        }
                        if ($meta->key == "_holo_sku") {
                            $total = $this->getAmount($item->total, $orderInvoice->currency);
                            $lazy = 0;
                            $scot = 0;
                            if ($payment->vat) {
                                $lazy = $total * 6 / 100;
                                $scot = $total * 3 / 100;
                            }
                            $items[] = array(
                                'id' => $meta->value,
                                'Productid' => $meta->value,
                                'few' => $item->quantity,
                                'price' => $item->price,
                                'discount' => '0',
                                'levy' => $lazy,
                                'scot' => $scot,
                            );
                            $sum_total += $total;
                        }

                    }
                }

            }

            if ($orderInvoice->product_shipping) {
                $shipping_lines = $orderInvoice->shipping_lines[0] ?? null;
                if ($shipping_lines) {
                    if (is_array($shipping_lines)) {
                        $shipping_lines = (object) $shipping_lines;
                    }

                    $total = $this->getAmount($shipping_lines->total, $orderInvoice->currency);
                    $scot = $this->getAmount($shipping_lines->total_tax, $orderInvoice->currency);
                    $items[] = array(
                        'id' => $orderInvoice->product_shipping,
                        'Productid' => $orderInvoice->product_shipping,
                        'few' => 1,
                        'price' => $total,
                        'discount' => 0,
                        'levy' => 0,
                        'scot' => $scot,
                    );

                    $sum_total += $total;
                }

            }

            if (sizeof($items) > 0) {
                $payment_type = "bank";
                if ($orderInvoice->status_place_payment == "Installment") {
                    $payment_type = "nesiyeh";
                } else if (substr($payment->number, 0, 3) == "101") {
                    $payment_type = "cash";
                }
                $data = array(
                    'generalinfo' => array(
                        'apiname' => 'InvoicePost',
                        'dto' => array(
                            'invoiceinfo' => array(
                                'id' => $orderInvoice->input("id"), //$oreder->id
                                'Type' => 1, //1 faktor frosh 2 pish factor,
                                'kind' => $type,
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
                    $data["generalinfo"]["dto"]["invoiceinfo"]["CashSarfas"] = $payment->number;
                } else {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["nesiyeh"] = $sum_total;
                }

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
                if ($response->success) {

                    if ($payment->fee && $payment->fee != "") {
                        $fee = $this->genericFee($payment->fee, $sum_total);
                        if ($fee && $fee->amount > 0) {
                            $this->wcInvoiceBank($orderInvoice, $fee, $custid, $DateString, $type);
                        }
                    }

                    $this->recordLog("Invoice Payed", $user->siteUrl, "Invoice Payed finish succsessfuly");
                    return $this->sendResponse('ثبت سفارش فروش انجام شد', Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
                } else {
                    $this->recordLog("Invoice Payed", $user->siteUrl, "Invoice Payed finish wrong", "error");
                    $this->recordLog("Invoice Payed", $user->siteUrl, $response, "error");
                }
                return $this->sendResponse($response->message, Response::HTTP_INTERNAL_SERVER_ERROR, ["result" => ["msg_code" => 0]]);
            }

        }

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
        if ($currency == "toman") {
            return $amount * 10;
        }

        return $amount;
    }

    public function wcSingleProductUpdate(Request $request)
    {
        ini_set('max_execution_time', 120); // 120 (seconds) = 2 Minutes
        $holoo_product_id = $request->holoo_id;
        $wp_product_id = $request->product_id;
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
            'name' => $this->arabicToPersian($HolooProd->a_Name),
            'regular_price' => $this->get_price_type($request->sales_price_field,$HolooProd),
            'price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'sale_price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'wholesale_customer_wholesale_price' => $this->get_price_type($request->wholesale_price_field,$HolooProd),
            'stock_quantity' => (int) $HolooProd->exist>0 ?? 0,
        ];

        $response = $this->updateWCSingleProduct($param);
        return $this->sendResponse("محصول با موفقیت به روز شد.", Response::HTTP_OK, ["result" => ["msg_code" => $response]]);
        return $response;
    }

    public function GetSingleProductHoloo($holoo_id)
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

    public function wcAddAllHolooProductsCategory(Request $request)
    {
        $user = auth()->user();
        $user_id = $user->id;
        $counter = 0;
        if (ProductRequest::where(['user_id' => $user_id])->exists()) {
            //return $this->sendResponse('شما یک درخواست ثبت محصول در ۲۴ ساعت گذشته ارسال کرده اید لطفا منتظر بمانید تا عملیات قبلی شما تکمیل گردد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
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
                FindProductInCategory::dispatch($user,$category,$token,$wcHolooExistCode,$param,$category->m_groupcode.'-'.$category->s_groupcode);

                // curl_setopt_array($curl, array(
                //     CURLOPT_URL => 'https://myholoo.ir/api/Article/SearchArticles?from.date=2022',
                //     CURLOPT_RETURNTRANSFER => true,
                //     CURLOPT_ENCODING => '',
                //     CURLOPT_MAXREDIRS => 10,
                //     CURLOPT_TIMEOUT => 0,
                //     CURLOPT_FOLLOWLOCATION => true,
                //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                //     CURLOPT_CUSTOMREQUEST => 'GET',
                //     CURLOPT_HTTPHEADER => array(
                //         'serial: ' . $user->serial,
                //         'database: ' . $user->holooDatabaseName,
                //         'm_groupcode: ' . $category->s_groupcode,
                //         'isArticle: true',
                //         'access_token: ' . $user->apiKey,
                //         'Authorization: Bearer ' . $token,
                //     ),
                // ));
                // $response = curl_exec($curl);
                // $HolooProds = json_decode($response);

                // foreach ($HolooProds as $HolooProd) {
                //     if (!in_array($HolooProd->a_Code, $wcHolooExistCode)
                //     ) { //&& $HolooProd->exist>0


                //         // $param = [
                //         //     "holooCode" => $HolooProd->a_Code,
                //         //     "holooName" => $this->arabicToPersian($HolooProd->a_Name),
                //         //     "holooRegularPrice" => (string) $HolooProd->sel_Price ?? 0,
                //         //     "holooStockQuantity" => (string) $HolooProd->exist ?? 0,
                //         // ];
                //         $param = [
                //             "holooCode" => $HolooProd->a_Code,
                //             'name' => $this->arabicToPersian($HolooProd->a_Name),
                //             'regular_price' => $this->get_price_type($request->sales_price_field,$HolooProd),
                //             'price' => $this->get_price_type($request->special_price_field,$HolooProd),
                //             'sale_price' => $this->get_price_type($request->special_price_field,$HolooProd),
                //             'wholesale_customer_wholesale_price' => $this->get_price_type($request->wholesale_price_field,$HolooProd),
                //             'stock_quantity' => (int) $HolooProd->exist ?? 0,
                //         ];
                //         if ((!isset($request->insert_product_with_zero_inventory) && $HolooProd->exist > 0) || (isset($request->insert_product_with_zero_inventory) && $request->insert_product_with_zero_inventory == "0" && $HolooProd->exist > 0)) {
                //             //$allRespose[]=app('App\Http\Controllers\WCController')->createSingleProduct($param,['id' => $category->s_groupcode,"name" => $category->m_groupname]);
                //             $counter = $counter + 1;
                //             if (isset($HolooProd->Poshak)) {
                //                 AddProductsUser::dispatch($user, $param, ['id' => $data[$category->s_groupcode], "name" => ""], $HolooProd->a_Code,"variable",$HolooProd->Poshak);
                //             }
                //             else{
                //                 AddProductsUser::dispatch($user, $param, ['id' => $data[$category->s_groupcode], "name" => ""], $HolooProd->a_Code);
                //             }
                //         }
                //         elseif (isset($request->insert_product_with_zero_inventory) && $request->insert_product_with_zero_inventory == "1") {
                //             //$allRespose[]=app('App\Http\Controllers\WCController')->createSingleProduct($param,['id' => $category->s_groupcode,"name" => $category->m_groupname]);
                //             $counter = $counter + 1;
                //             if (isset($HolooProd->Poshak)) {
                //                 AddProductsUser::dispatch($user, $param, ['id' => $data[$category->s_groupcode], "name" => ""], $HolooProd->a_Code,"variable",$HolooProd->Poshak);
                //             }
                //             else{
                //                 AddProductsUser::dispatch($user, $param, ['id' => $data[$category->s_groupcode], "name" => ""], $HolooProd->a_Code);
                //             }
                //             //dd($data[$category->s_groupcode]);
                //         }
                //     }

                // }
            }
        }

        curl_close($curl);

        // if ($counter == 0) {
        //     return $this->sendResponse("تمامی محصولات به روز هستند", Response::HTTP_OK, ["result" => ["msg_code" => 2]]);
        // }

        $productRequest = new ProductRequest;
        $productRequest->user_id = $user_id;
        $productRequest->request_time = Carbon::now();
        $productRequest->save();

        return $this->sendResponse(" درخواست ثبت محصولات جدید با موفقیت ثبت گردید. ", Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
    }

    public function wcGetExcelProducts()
    {

        $counter = 0;
        $user = auth()->user();
        $user_id = $user->id;
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();

        $productCategory = app('App\Http\Controllers\WCController')->get_wc_category();

        $data = $productCategory;
        //$data = ['02' => 12];
        //dd($data);

        $categories = $this->getAllCategory();

        $wcHolooExistCode = app('App\Http\Controllers\WCController')->get_all_holoo_code_exist();
        $allRespose = [];
        $sheetes = [];
        foreach ($categories->result as $key => $category) {
            if (array_key_exists($category->m_groupcode.'-'.$category->s_groupcode, $data)) {
                $sheetes[$category->m_groupname] = array();

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
                        'serial: ' . $userSerial,
                        'database: ' . $user->holooDatabaseName,
                        // 'm_groupcode: ' . $category->s_groupcode,
                        'isArticle: true',
                        'access_token: ' . $userApiKey,
                        'Authorization: Bearer ' . $token,
                    ),
                ));
                $response = curl_exec($curl);
                $HolooProds = json_decode($response);

                foreach ($HolooProds as $HolooProd) {

                    if (!in_array($HolooProd->a_Code, $wcHolooExistCode)) {

                        $param = [
                            "holooCode" => $HolooProd->a_Code,
                            "holooName" => $this->arabicToPersian($HolooProd->a_Name),
                            "holooRegularPrice" => (string) $HolooProd->sel_Price ?? 0,
                            "holooStockQuantity" => (string) $HolooProd->exist ?? 0,
                        ];

                        $sheetes[$category->m_groupname][] = $param;

                    }

                }
            }
        }

        curl_close($curl);
        if (count($sheetes) != 0) {
            $excel = new ReportExport($sheetes);
            $filename = $user_id;
            $file = "download/" . $filename . ".xls";
            Excel::store($excel, $file, "asset");
            return $this->sendResponse('ادرس فایل دانلود', Response::HTTP_OK, ["result" => ["url" => asset($file)]]);
        } else {
            return $this->sendResponse('محصولی جهت تولید فایل خروجی یافت نشد', Response::HTTP_OK, ["result" => ["url" => "#"]]);
        }

    }

    public function addToCart(Request $orderInvoice)
    {
        return $this->sendResponse('ثبت سفارش فروش انجام شد', Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
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
        return $this->sendResponse('لیست حسابهای بانکی', Response::HTTP_OK, ["result" => $response->data]);

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
        return $this->sendResponse('لیست حسابهای نقدی', Response::HTTP_OK, ["result" => $response->data]);

    }

    private function getHolooCustomerID($customer, $customerId)
    {
        if (is_array($customer)) {
            $customer = (object) $customer;
        }
        $holooCustomers = $this->getHolooDataTable();

        // dd($holooCustomers);
        foreach ($holooCustomers->result as $holloCustomer) {
            if ($holloCustomer->c_Mobile == $customer->phone) {
                return $holloCustomer->c_Code;
            }
        }

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
        $data = [
            "generalinfo" => [
                "apiname" => "CustomerPost",
                "dto" => [
                    "custinfo" => [
                        [
                            "code" => $customerId,
                            "id" => $customerId,
                            "name" => $customer->first_name . ' ' . $customer->last_name,
                            "ispurchaser" => true,
                            "isseller" => false,
                            "custtype" => 0,
                            "Kind" => 2,
                            "tel" => "",
                            "mobile" => $customer->phone,
                            "city" => $customer->city,
                            "ostan" => $customer->state,
                            "email" => $customer->email,
                            "zipcode" => $customer->postcode,
                            "address" => $customer->address_1,
                        ],
                    ],
                ],
            ],
        ];

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
                "Authorization: Bearer $token",
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        if ($response->success) {
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
        return $this->sendResponse('لیست حسابهای', Response::HTTP_OK,  $obj);
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
        // return $this->sendResponse('لیست حسابهای', Response::HTTP_OK,  $response2);
        $obj = (object)array_merge_recursive((array)$response2->data , (array)$response->data);
        curl_close($curl);
        return $this->sendResponse('لیست حسابهای', Response::HTTP_OK,  $obj);
    }

}
