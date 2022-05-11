<?php

namespace App\Jobs;

use App\Jobs\AddProductsUser;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class FindProductInCategory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    protected $category;
    protected $token;
    protected $wcHolooExistCode;
    protected $request;
    public $flag;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$category,$token,$wcHolooExistCode,$request,$flag)
    {
        $this->user=$user;
        $this->category=$category;
        $this->flag=$flag;
        $this->token=$token;
        $this->wcHolooExistCode=$wcHolooExistCode;
        $this->request=$request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(){
        $curl = curl_init();
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
                'm_groupcode: ' . $this->category->m_groupcode,
                's_groupcode: ' . $this->category->s_groupcode,
                'isArticle: true',
                'access_token: ' . $this->user->apiKey,
                'Authorization: Bearer ' .$this->token,
            ),
        ));
        $response = curl_exec($curl);
        $HolooProds = json_decode($response);
        // log::info($HolooProds);
        // log::info($this->category->m_groupcode);
        // log::info($this->category->s_groupcode);

        foreach ($HolooProds as $HolooProd) {
            if (!in_array($HolooProd->a_Code, $this->wcHolooExistCode)) {

                $param = [
                    "holooCode" => $HolooProd->a_Code,
                    'name' => $this->arabicToPersian($HolooProd->a_Name),
                    'regular_price' => $this->get_price_type($this->request["sales_price_field"],$HolooProd),
                    'price' => $this->get_price_type($this->request["special_price_field"],$HolooProd),
                    'sale_price' => $this->get_price_type($this->request["special_price_field"],$HolooProd),
                    'wholesale_customer_wholesale_price' => $this->get_price_type($this->request["wholesale_price_field"],$HolooProd),
                    'stock_quantity' => (int) $HolooProd->exist ?? 0,
                ];
                if ((!isset($this->request["insert_product_with_zero_inventory"]) && $HolooProd->exist > 0) || (isset($this->request["insert_product_with_zero_inventory"]) && $this->request["insert_product_with_zero_inventory"] == "0" && $HolooProd->exist > 0)) {

                    if (isset($HolooProd->Poshak)) {
                        AddProductsUser::dispatch($this->user, $param, ['id' => $this->request["product_cat"][$this->category->m_groupcode."-".$this->category->s_groupcode], "name" => ""], $HolooProd->a_Code,"variable",$HolooProd->Poshak);
                    }
                    else{
                        //Log::info(['id' => $this->request["product_cat"][$this->category->m_groupcode."-".$this->category->s_groupcode], "name" => ""]);
                        AddProductsUser::dispatch($this->user, $param, ['id' => $this->request["product_cat"][$this->category->m_groupcode."-".$this->category->s_groupcode], "name" => ""], $HolooProd->a_Code);
                    }
                }
                elseif (isset($this->request["insert_product_with_zero_inventory"]) && $this->request["insert_product_with_zero_inventory"] == "1") {

                    if (isset($HolooProd->Poshak)) {
                        AddProductsUser::dispatch($this->user, $param, ['id' => $this->request["product_cat"][$this->category->m_groupcode."-".$this->category->s_groupcode], "name" => ""], $HolooProd->a_Code,"variable",$HolooProd->Poshak);
                    }
                    else{
                        AddProductsUser::dispatch($this->user, $param, ['id' => $this->request["product_cat"][$this->category->m_groupcode."-".$this->category->s_groupcode], "name" => ""], $HolooProd->a_Code);
                    }

                }
            }

        }
    }

    private function get_price_type($price_field,$HolooProd){

        if($price_field==1){
            return (string)$HolooProd->sel_Price;
        }
        else{
            return (string)$HolooProd->{"sel_Price".$price_field};
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

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->user->id.'_'.$this->flag;
    }

}
