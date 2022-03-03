<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationController extends Controller
{
    public function register(Request $request)
    {
//        return \response(['time'=> Carbon::now()]);
        $validations = Validator::make($request->all(), [
            'siteUrl' => 'required|unique:users',
            'holooDatabaseName' => 'required|unique:users',
            'holooCustomerID' => 'required|unique:users',
        ], [
            'siteUrl.required' => 'آدرس سایت الزامی می باشد.',
            'siteUrl.unique' => 'آدرس سایت مورد نظر تکراری می باشد.',
            'holooDatabaseName.required' => 'نام پایگاه داده هلو الزامی می باشد.',
            'holooDatabaseName.unique' => 'نام پایگاه داده هلو تکراری می باشد.',
            'holooCustomerID.required' => 'شناسه یکتای هلو مشتری الزامی می باشد.',
            'holooCustomerID.unique' => 'شناسه یکتای هلو مشتری تکراری می باشد',
        ]);

        if ($validations->fails()) {
            return $this->sendResponse($validations->errors()->first(), Response::HTTP_NOT_ACCEPTABLE, null);
        } else {
            $activeLicense = Str::random(12);
            $user = User::create([
                'siteUrl' => $request->input('siteUrl'),
                'holooDatabaseName' => $request->input('holooDatabaseName'),
                'holooCustomerID' => $request->input('holooCustomerID'),
                'activeLicense' => $activeLicense,
                'expireActiveLicense' => Carbon::now()->addYears(1),
            ]);

            return $this->sendResponse('کاربر مورد نظر با موفقیت ثبت شد', Response::HTTP_OK, ['user' => $user]);
        }
    }

    public function login(Request $request)
    {
        $searchedUser = User::where('siteUrl', '=', $request->input('siteUrl'))
            ->where('activeLicense', $request->input('activeLicense'))
            ->first();
        if ($searchedUser) {
            if ($searchedUser->expireActiveLicense > Carbon::now()) {
                Auth::login($searchedUser);
                $token = $searchedUser->createToken('token')->plainTextToken;
                $searchedUser->update([
                    'wordpressToken' => $token,
//                    'consumerKey' => $request->input('consumerKey'),
//                    'consumerSecret' => $request->input('consumerSecret'),
                ]);
                return $this->sendResponse(null, Response::HTTP_OK, ['user' => Auth::user(), 'token' => $token]);
            }
            return $this->sendResponse('لایسنس شما به پایان رسیده است، لطفا لایسنس جدید تهیه فرمایید.', Response::HTTP_NOT_ACCEPTABLE, null);
        }
        return $this->sendResponse('ورود ناموفق، اطلاعات ورودی خود را مجدد بررسی نمایید.', Response::HTTP_UNAUTHORIZED, null);
    }

    public function user(Request $request)
    {
        return $this->sendResponse(null, Response::HTTP_OK, \auth()->user());
    }

    public function updateWordpressSettings(Request $request)
    {
        $incomeToken = $request->input('token');
        $keys = ['consumerKey' => $request->input('consumerKey'), 'consumerSecret' => $request->input('consumerSecret')];
        if ($keys['consumerKey'] == '' || $keys['consumerSecret'] == '') {
            return $this->sendResponse('مقادیر consumerKey و یا consumerSecret خالی می باشد. لطفا دوباره بررسی فرمایید.', Response::HTTP_NOT_ACCEPTABLE, null);
        }
        $user = User::where('wordpressToken', $incomeToken)->first();
        if ($user) {
            $user->update([
                'consumerKey' => $request->input('consumerKey'),
                'consumerSecret' => $request->input('consumerSecret'),
            ]);
            return $this->sendResponse('کاربر مورد نظر با موفقیت به روز رسانی شد.', Response::HTTP_OK, \auth()->user());
        } else {
            return $this->sendResponse('کاربر مورد نظر یافت نشد. اطلاعات ورودی خود را مجددا بررسی نمایید.', Response::HTTP_NOT_ACCEPTABLE, null);
        }
    }

    public function sendResponse($message, $responseCode, $response)
    {
        return response([
            'message' => $message,
            'responseCode' => $responseCode,
            'response' => $response
        ], $responseCode);
    }
}
