<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
        /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('api', ['except' => ['login', 'register']]);
    }
    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request){


        $request->request->add(["password"=>$request->input('activeLicense')]);
        $validator = Validator::make($request->all(), [
            'siteUrl' => 'required|string',
            'activeLicense' => 'required|string|min:6',
            'password'  => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            //return response()->json($validator->errors(), 422);
            return $this->sendResponse('ورود ناموفق موجودیت غیرقابل پردازش', Response::HTTP_UNPROCESSABLE_ENTITY, null);
        }
        //

        $User = User::where(['siteUrl'=> $request->input('siteUrl'),'activeLicense'=> $request->input('activeLicense')])
        ->first();
        //->first(['id','siteUrl','activeLicense','expireActiveLicense',"holooDatabaseName","holooCustomerID"]);

        if (!$User) {
            //return response()->json(['error' => 'Unauthorized'], 401);
            return $this->sendResponse('ورود ناموفق، اطلاعات ورودی خود را مجدد بررسی نمایید.', Response::HTTP_UNAUTHORIZED, null);
        }

        if ($User->expireActiveLicense > Carbon::now()) {

            $token=auth('api')->attempt($validator->validated());
            $response=$this->createNewToken($token);



            return $this->sendResponse("ورود با موفقیت انجام شد", Response::HTTP_OK, $response);
        }
        else{
            return $this->sendResponse('لایسنس شما به پایان رسیده است، لطفا لایسنس جدید تهیه فرمایید.', Response::HTTP_NOT_ACCEPTABLE, null);
        }



    }
    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request) {
        $request->request->add(["password"=>$request->input('activeLicense')]);
        $validator = Validator::make($request->all(), [
            'siteUrl' => 'required|unique:users',
            'holooDatabaseName' => 'required',
            'holooCustomerID' => 'required|unique:users',
            'email'  => 'required|unique:users',
        ], [
            'siteUrl.required' => 'آدرس سایت الزامی می باشد.',
            'siteUrl.unique' => 'آدرس سایت مورد نظر تکراری می باشد.',
            'holooDatabaseName.required' => 'نام پایگاه داده هلو الزامی می باشد.',
            'holooDatabaseName.unique' => 'نام پایگاه داده هلو تکراری می باشد.',
            'holooCustomerID.required' => 'شناسه یکتای هلو مشتری الزامی می باشد.',
            'holooCustomerID.unique' => 'شناسه یکتای هلو مشتری تکراری می باشد',
            'email.required' => 'ادرس ایمیل اجباری می باشد',
            'email.unique' => 'ادرس ایمیل تکراری می باشد',
        ]);

        if ($validator->fails()) {
            return $this->sendResponse($validator->errors()->first(), Response::HTTP_NOT_ACCEPTABLE, null);
        }
        else {
            $activeLicense = Str::random(12);
            $user = User::create([
                'siteUrl' => $request->input('siteUrl'),
                'email' => $request->input('email'),
                'password' => bcrypt($activeLicense),
                'holooDatabaseName' => $request->input('holooDatabaseName'),
                'holooCustomerID' => $request->input('holooCustomerID'),
                'activeLicense' => $activeLicense,
                'expireActiveLicense' => Carbon::now()->addYears(1),
            ]);

            return $this->sendResponse('کاربر مورد نظر با موفقیت ثبت شد', Response::HTTP_CREATED, ['user' => $user]);
        }

    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout() {
        auth('api')->logout();
        return $this->sendResponse('خروج از حساب با موفقیت انجام گردید', Response::HTTP_OK,null);

    }
    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh() {

        return $this->createNewToken(auth('api')->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token){
        return [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 108000,
            'user' => auth()->user()
        ];
    }

    /**
     * Get User profile.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function userProfile() {
        return response()->json(auth('api')->user());
    }

    /**
     * Update wordpress Token
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateWordpressSettings(Request $request){

        $validator = Validator::make($request->all(), [
            'consumerKey' => 'required|string',
            'consumerSecret' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->sendResponse('مقادیر consumerKey و یا consumerSecret خالی می باشد. لطفا دوباره بررسی فرمایید.', Response::HTTP_NOT_ACCEPTABLE, null);
        }

        $user=auth('api')->user();
        $user = User::where('id', $user->id)->first();
        if ($user) {
            $user->update([
                'consumerKey' => $request->input('consumerKey'),
                'consumerSecret' => $request->input('consumerSecret'),
            ]);
            return $this->sendResponse('کاربر مورد نظر با موفقیت به روز رسانی شد.', Response::HTTP_OK, auth('api')->user());
        }
        else {
            return $this->sendResponse('کاربر مورد نظر یافت نشد. اطلاعات ورودی خود را مجددا بررسی نمایید.', Response::HTTP_NOT_ACCEPTABLE, null);
        }
    }

    public function sendResponse($message, $responseCode, $response){
        return response([
            'message' => $message,
            'responseCode' => $responseCode,
            'response' => $response
        ], $responseCode);
    }
}
