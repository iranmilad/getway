<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WCController;
use App\Http\Controllers\ViewController;
use App\Http\Controllers\DownloadController;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/unauthenticated', function () {
    return response([
        'message' => "دسترسی به سرویس مسدود است لطفا ابتدا وارد شوید",
        'responseCode' => Response::HTTP_UNAUTHORIZED,
        'response' => null
    ], Response::HTTP_UNAUTHORIZED);
})->name("unauthenticated");


//assistent
Route::get('migrate', [WCController::class, 'migrate']);
Route::get('external/fresh', [WCController::class, 'fresh']);
Route::get('cashClear', [WCController::class, 'clearCache']);



Route::get('/liveWcGetExcelProducts/{user_id}', [DownloadController::class, 'index'])->name("liveWcGetExcelProducts");
Route::get('/liveWcGetExcelProducts2/{user_id}', [DownloadController::class, 'index2'])->name("liveWcGetExcelProducts2");
Route::get('/conf/{user_id}/{token}', [ViewController::class, 'index'])->name("conf");

Route::get('/sendUpdate/{user_id}', [DownloadController::class, 'sendUpdate'])->name("sendUpdate");
