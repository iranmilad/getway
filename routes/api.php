<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WCController;
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

Route::post('register', [AuthenticationController::class, 'register']);
Route::post('login', [AuthenticationController::class, 'login']);
Route::get('wcall', [WCController::class, 'fetchAllWCProducts']);
Route::get('wc/{id}', [WCController::class, 'fetchSingleProduct']);
Route::post('wcadd', [WCController::class, 'createSingleProduct']);
Route::post('getProductConflict', [WCController::class, 'compareProductsFromWoocommerceToHoloo']);

Route::post('updateWCSingleProduct', [WCController::class, 'updateWCSingleProduct']);
Route::post('updateAllProductFromHolooToWC', [WCController::class, 'updateAllProductFromHolooToWC']);
Route::post('wcSingleProductUpdate', [HolooController::class, 'wcSingleProductUpdate']);
Route::post('wcAddAllHolooProductsCategory', [HolooController::class, 'wcAddAllHolooProductsCategory']);
Route::post('wcGetExcelProducts', [HolooController::class, 'wcGetExcelProducts']);
Route::get('wcGetExcelProducts', [HolooController::class, 'wcGetExcelProducts']);
Route::post('wcGetBankAccount', [HolooController::class, 'getAccountBank']);
Route::post('wcGetCashAccount', [HolooController::class, 'getAccountCash']);

Route::post('getProductCategory', [HolooController::class, 'getProductCategory']);
Route::get('getAllHolooProducts', [HolooController::class, 'getAllHolooProducts']);

//woocomrece webhook event
Route::post('wcInvoiceRegistration', [HolooController::class, 'wcInvoiceRegistration']);
Route::post('wcInvoicePayed', [HolooController::class, 'wcInvoicePayed']);
Route::post('addToCart', [HolooController::class, 'addToCart']);

//holoo webhook event
Route::post('webhook', [WCController::class, 'holooWebHook']);

//assistent
Route::get('migrate', [WCController::class, 'migrate']);
Route::get('cashClear', [WCController::class, 'clearCache']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthenticationController::class, 'user']);
    Route::post('updateUser', [AuthenticationController::class, 'updateWordpressSettings']);
//    Route::post('logout', [AuthenticationController::class, 'logout']);
});

//Route::get()

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});
