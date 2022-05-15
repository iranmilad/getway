<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Str;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
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
        $this->middleware('auth:api', ['except' => ['login']]);
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
            $token = JWTAuth::getToken();
            if (!$token) {
                $token=Auth::attempt($validator->validated());
                if (!$token) {

                    $token = auth()->login($User);
                    $response=$this->createNewToken($token);
                    return $this->sendResponse("ورود با موفقیت انجام شد", Response::HTTP_OK,$response);
                }

            }
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
            'serial' => 'required|unique:users',
            'email'  => 'required|unique:users',
        ], [
            'siteUrl.required' => 'آدرس سایت الزامی می باشد.',
            'siteUrl.unique' => 'آدرس سایت مورد نظر تکراری می باشد.',
            'holooDatabaseName.required' => 'نام پایگاه داده هلو الزامی می باشد.',
            'holooDatabaseName.unique' => 'نام پایگاه داده هلو تکراری می باشد.',
            'holooCustomerID.required' => 'شناسه یکتای هلو مشتری الزامی می باشد.',
            'holooCustomerID.unique' => 'شناسه یکتای هلو مشتری تکراری می باشد',
            'serial.required' => 'شناسه یکتای هلو مشتری الزامی می باشد.',
            'serial.unique' => 'شناسه یکتای هلو مشتری تکراری می باشد',
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
                'serial' => $request->input('serial'),
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

        auth()->logout();
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://myholoo.ir/api/Ticket/RegisterForPartner',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('Serial' => '10304923','RefreshToken' => 'false','DeleteService' => 'true','MakeService' => 'false'),
        CURLOPT_HTTPHEADER => array(
        'apikey: E5D3A60D3689D3CB8BD8BE91E5E29E934A830C2258B573B5BC28711F3F1D4B70',
        'Authorization: Bearer eyJhbGciOiJodHRwOi8vd3d3LnczLm9yZy8yMDAxLzA0L3htbGRzaWctbW9yZSNobWFjLXNoYTI1NiIsInR5cCI6IkpXVCJ9.eyJodHRwOi8vc2NoZW1hcy54bWxzb2FwLm9yZy93cy8yMDA1LzA1L2lkZW50aXR5L2NsYWltcy9uYW1laWRlbnRpZmllciI6IjE0Mjc5IiwiVGl0bGUiOiJTYW5kQm94IiwiTGljZW5zZSI6IjEwMzA0OTIzIiwibmJmIjoxNjUyMTM4NDUyLCJleHAiOjE2NTIzMTEyNTIsImlzcyI6Imh0dHA6Ly9jbG91ZC5ob2xvby5jb20iLCJhdWQiOiJodHRwOi8vY2xvdWQuaG9sb28uY29tIn0.u0d9Lre7XXCUmWa-H2WEnrNtSagGzaQbA2DplMSwMV8'
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);


        return $this->sendResponse('خروج از حساب با موفقیت انجام گردید', Response::HTTP_OK,$response);

    }
    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh() {
        $response=$this->createNewToken(auth()->refresh());
        return $this->sendResponse("توکن به روز گردید", Response::HTTP_OK,$response);
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
            'expires_in' => auth()->factory()->getTTL() * 108000,
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

        return $this->sendResponse("فعال", Response::HTTP_OK, auth()->user());

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

        $user=auth()->user();
        $user = User::where('id', $user->id)->first();
        if ($user) {
            $user->update([
                'consumerKey' => $request->input('consumerKey'),
                'consumerSecret' => $request->input('consumerSecret'),
            ]);
            return $this->sendResponse('کاربر مورد نظر با موفقیت به روز رسانی شد.', Response::HTTP_OK, auth()->user());
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
