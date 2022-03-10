<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WCController;
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
Route::get('cashClear', [WCController::class, 'clearCache']);



