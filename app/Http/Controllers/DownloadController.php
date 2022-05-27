<?php

namespace App\Http\Controllers;


use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use App\Exports\ReportExport;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;


class DownloadController extends Controller
{
    public function index($user_id){
        $counter = 0;

        $user = User::where(["id"=>$user_id])->first();
        Auth::login($user);



        $user_id = $user->id;
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        ini_set('max_execution_time', 0);
        set_time_limit(0);

        log::info('products file not found try for make new for user: ' . $user->id);
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();

        // $productCategory = app('App\Http\Controllers\WCController')->get_wc_category();

        // $data = $productCategory;
        //$data = ['02' => 12];

        $categories = $this->getAllCategory();
        //dd($categories);


        $allRespose = [];
        $sheetes = [];
        foreach ($categories->result as $key => $category) {

            //if (array_key_exists($category->m_groupcode.'-'.$category->s_groupcode, $data)) {
                // if ($data[$category->m_groupcode.'-'.$category->s_groupcode]==""){
                //     continue;
                // }
                $sheetes[$category->m_groupcode.'-'.$category->s_groupcode] = array();

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
                        'm_groupcode: ' . $category->m_groupcode,
                        's_groupcode: ' . $category->s_groupcode,
                        'isArticle: true',
                        'access_token: ' . $userApiKey,
                        'Authorization: Bearer ' . $token,
                    ),
                ));
                $response = curl_exec($curl);
                $HolooProds = json_decode($response);

                foreach ($HolooProds as $HolooProd) {

                   // if (!in_array($HolooProd->a_Code, $wcHolooExistCode)) {

                        $param = [
                            "holooCode" => $HolooProd->a_Code,
                            "holooName" => $this->arabicToPersian($HolooProd->a_Name),
                            "holooRegularPrice" => (string) $HolooProd->sel_Price ?? 0,
                            "holooStockQuantity" => (string) $HolooProd->exist ?? 0,
                            "holooCustomerCode" => ($HolooProd->a_Code_C) ?? "",
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
            $headers = array(
            'Content-Type: application/xls',
            );
            return response()->download($file, $filename . ".xls", $headers);
        }
        else {
            return "فایل خروجی ساخته نشد";
        }

    }


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
                log::info("take new token request and response");
                log::info(json_encode($response));
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

}
