<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WCController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HolooController;
use App\Http\Controllers\AuthenticationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/








Route::group([
    'middleware' => 'api',

], function ($router) {


    //holoo webhook event
    Route::post('/webhook', [WCController::class, 'holooWebHook']);
    Route::post('/webhook2', [WCController::class, 'holooWebHook1']);

    //login and user group
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/user-profile', [AuthController::class, 'userProfile']);
    Route::get('/logout', [AuthController::class, 'logout']);

    Route::post('/updateUser', [AuthController::class, 'updateWordpressSettings']);

   // Route::get('/test', [WCController::class, 'testProductVar']);
    Route::get('/test', [WCController::class, 'test']);


});


Route::group([
    'middleware' => 'auth:api',
   // 'middleware' => 'api',

], function ($router) {
    Route::get('/wcall', [WCController::class, 'fetchAllWCProducts']);
    Route::get('/wc/{id}', [WCController::class, 'fetchSingleProduct']);
    Route::post('/wcadd', [WCController::class, 'createSingleProduct']);
    Route::post('/getProductConflict', [WCController::class, 'compareProductsFromWoocommerceToHoloo']);
    Route::post('/getProductConflict2', [WCController::class, 'compareProductsFromWoocommerceToHoloo2']);
    Route::post('/getProductConflict3', [WCController::class, 'compareProductsFromWoocommerceToHoloo3']);

    Route::get('/selfConfig', [WCController::class, 'self_config']);
    Route::post('/updateConfig', [WCController::class, 'updateConfig']);

    Route::post('/updateWCSingleProduct', [WCController::class, 'updateWCSingleProduct']);
    Route::post('/updateAllProductFromHolooToWC', [WCController::class, 'updateAllProductFromHolooToWC3']);
    Route::post('/updateAllProductFromHolooToWC2', [WCController::class, 'updateAllProductFromHolooToWC2'])->name("updateAllProductFromHolooToWC2");   //test
    Route::post('/updateAllProductFromHolooToWC3', [WCController::class, 'updateAllProductFromHolooToWC3'])->name("updateAllProductFromHolooToWC3");   //test
    Route::post('/updateAllProductVariationFromHolooToWC', [WCController::class, 'updateAllProductVariationFromHolooToWC'])->name("updateAllProductVariationFromHolooToWC");   //test

    Route::post('/wcSingleProductUpdate', [HolooController::class, 'wcSingleProductUpdate']);
    Route::post('/wcAddAllHolooProductsCategory', [HolooController::class, 'wcAddAllHolooProductsCategory']);
    Route::post('/wcAddAllHolooProductsCategory2', [HolooController::class, 'wcAddAllHolooProductsCategory2']);

    Route::post('/GetBankAccount', [HolooController::class, 'getAccountBank']);
    Route::post('/GetCashAccount', [HolooController::class, 'getAccountCash']);
    Route::post('/GetAllAccount', [HolooController::class, 'get_all_accounts']);
    //Route::post('/GetShippingAccount', [HolooController::class, 'get_shipping_accounts']);
    Route::post('/GetShippingAccount', [HolooController::class, 'get_shipping_accounts_by_product']);
    Route::post('/GetAllCustomerAccount', [HolooController::class, 'GetAllCustomerAccount']);


    Route::post('/getProductCategory', [HolooController::class, 'getProductCategory']);
    Route::get('/getAllHolooProducts', [HolooController::class, 'getAllHolooProducts']);



    //woocomrece webhook event
    Route::post('/wcInvoiceRegistration', [HolooController::class, 'wcInvoiceRegistration']);
    Route::post('/wcInvoicePayed', [HolooController::class, 'wcInvoicePayed']);
    Route::post('/addToCart', [HolooController::class, 'addToCart']);
    Route::post('/changeProduct', [HolooController::class, 'changeProduct']);


});

Route::middleware(['auth:api','cors'])->group(function () {
    Route::post('/wcGetExcelProducts', [HolooController::class, 'wcGetExcelProducts']);
    Route::get('/wcGetExcelProducts', [HolooController::class, 'wcGetExcelProducts']);
});


// php artisan queue:work --queue=high,medium,low,default
