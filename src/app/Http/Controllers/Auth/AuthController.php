<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Exception;
use GuzzleHttp\Client;
use App\Http\Requests\UserLoginRequest; 
use App\Http\Requests\AuthUserPasswordResetRequest;
use Illuminate\Support\Facades\Auth; 
use Laravel\Passport\Client as OClient;
use App\Models\UserVerify;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Helpers\UserLoginHelper;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Activity;
use Illuminate\Support\Facades\Hash;
use Mail; 
use App\Http\Requests\PotralForgetPasswordRequest;
use App\Helpers\PasswordHelper;
use App\Mail\PotralUserPasswordResetMail;
use App\Http\Requests\AuthLoginRequest;
use App\Mail\PortalMails;
use App\Mail\TenantMails;

class AuthController extends Controller
{
    public function login(AuthLoginRequest $request) { 

        $credentials = $request->validated();

        // Find user by email
        $user = User::where('email', $request->email)->first();
    
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
            
        // Check if password matches the `portal_password` column
        if (!Hash::check($request->password, $user->portal_password)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
            
        // Check if the user is soft deleted
        if ($user->trashed()) {
            return response()->json(['message' => 'This account has been deleted.'], 403);
        }

        // Check first login
        if ($user->portal_first_login === null && !$user->portal_is_email_validate) {
            $firstlogin = true;
        }elseif(!$user->portal_is_email_validate){
            $firstlogin = true;
        }
        elseif($user->portal_first_login === null && $user->portal_is_email_validate){
            $user->portal_first_login = Carbon::now();
            $user->portal_last_login = Carbon::now();
            $user->save();
            $firstlogin = false;
        }elseif($user->portal_first_login !== null && $user->portal_is_email_validate){
            $user->portal_last_login = Carbon::now();
            $user->save();
            $firstlogin = false;
        }

        // Generate access token for the user
        $accessTokenExpiration = $request->rememberMe ? Carbon::now()->addDays(7) : Carbon::now()->addMinutes(30);

        // Access token with "access" scope
        $accessTokenResult = $user->createToken('Access Token', ['access_portal']);
        $accessToken = $accessTokenResult->token;
        $accessToken->expires_at = $accessTokenExpiration;
        $accessToken->save();

        // Refresh token with "refresh" scope
        $refreshTokenResult = $user->createToken('Refresh Token', ['refresh_portal']);
        $refreshToken = $refreshTokenResult->token;
        $refreshToken->expires_at = Carbon::now()->addDays(30);
        $refreshToken->save();

        $welcome_message = 'Hello'.' '.$user->name;

        // Return the response with user info and tokens
        return response()->json([
            'user' => $user, 
            'access_token' => $accessTokenResult->accessToken,
            'refresh_token' => $refreshTokenResult->accessToken,
            'firstlogin' => $firstlogin,
            'message' => $welcome_message,
        ], 200);
    }

    public function refreshToken(Request $request)
    {
        $authorizationToken = $request->header('resettoken') ?? $request->header('Authorization');

        if (!$authorizationToken) {
            return response()->json(['error' => 'Authorization Token is missing'], 400);
        }
        
        $user = Auth::user();

        // Generate a new access token with "access" scope
        $accessTokenResult = $user->createToken('Access Token', ['access_portal']);
        $accessToken = $accessTokenResult->token;
        $accessToken->expires_at = Carbon::now()->addDays(7);
        $accessToken->save();

        // Refresh token with "refresh" scope
        $refreshTokenResult = $user->createToken('Refresh Token', ['refresh_portal']);
        $refreshToken = $refreshTokenResult->token;
        $refreshToken->expires_at = Carbon::now()->addDays(30);
        $refreshToken->save();

        return response()->json([
            'user' => $user,
            'access_token' => $accessTokenResult->accessToken,
            'refresh_token' => $refreshTokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse($accessToken->expires_at)->toDateTimeString(),
        ]);
    }

    public function resetauthuserpassword(AuthUserPasswordResetRequest $request)
    {
        try {
            $input = $request->validated();

            $Authuser = Auth::user();

            //check user's temp password and od passwor is same
            if (!Hash::check($request->old_password, $Authuser->portal_password)) {
                return response()->json([
                    "error"=>"Your tempory password is incorrect",
                    "status"=>false,
                ],403);
            }

            // Update the user's password and mark password reset as not required
            $Authuser->portal_password = Hash::make($request->password);
            $Authuser->portal_is_email_validate = true;
            $Authuser->portal_contact_no = $request->contactnumber;
            $Authuser->portal_contact_no_code = $request->contactnumberCode;

            $Authuser->save();

            $user_name = $Authuser->name;

            $moreDetailsUrl = 'optiomax.com';

            $emailType ="PORTAL_USER_PASSWORD_CHANGE";
            try {
                Mail::to($Authuser->email)->send(new PortalMails($user_name, null, null, null, null,$moreDetailsUrl,null, $emailType));
            } catch (\Exception $e) {
                Log::error("Failed to send User reset Email: " . $e->getMessage());
            }
            
            // Mail::send('emails.password_changed', [
            //     'user_name' => $user_name,
            //     'successmessage' => $input['password'],
            //     'company_name' => $company_name,
            //     'support_email' => $support_email,
            // ], function($message) use ($Authuser) {
            //     $message->to($Authuser->email)
            //         ->subject('Your account password has been changed');
            // });



            // Return Json Response
            return response()->json([
                'status' => true,
                'message' => 'Your account password has been changed'
            ],200);
        } catch (\Throwable $th) {
                    return response()->json([
                        'status' => false,
                        'message' => $th->getMessage()
                    ], 500);
        }
    }

    public function cancleresetauthuserpassword(Request $request)
    {
        try {
            $Authuser = Auth::user();

            $Authuser->portal_first_login = null;
            $Authuser->portal_is_email_validate = false;
            $Authuser->save();

            // Return Json Response
            return response()->json([
                'status' => true,
                'message' => 'your password reset is cancel'
            ],200);
        } catch (\Throwable $th) {
                    return response()->json([
                        'status' => false,
                        'message' => $th->getMessage()
                    ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = Auth::user();

            $user->token()->revoke();

            return response()->json(['message' => 'Good bye'], 200);
        } catch (\Throwable $th) {
                    return response()->json([
                        'status' => false,
                        'message' => $th->getMessage()
                    ], 500);
        }
    }

    public function updateTheme(Request $request)
    {
        $request->validate([
            'theme' => 'required|in:light,dark,device',
        ]);

        $user = Auth::user();
        if ($user) {
            $user->portal_theme = $request->input('theme');
            $user->save();
            return response()->json(['message' => 'Your theme change successfully. ']);
        }

        return response()->json(['message' => 'Unauthorized.'], 401);
    }

    public function gettheme()
    {
        // Get the authenticated user's tenant_id
        $Authuser = Auth::user();

        $user = Auth::user();
        if ($user) {
            $theme = $user->portal_theme;

            return response()->json(['theme' => $theme]);
        }

        return response()->json(['message' => 'Unauthorized.'], 401);
    }
    
    /**
     * Change the auth user password
     */ 
    public function forgetpassword(PotralForgetPasswordRequest $request)
    {
        $input = $request->validated();

        // Fetch user based on tenant_id and email
        $user = User::where('email', $request->email)->first();

        // Check if user exists
        if(!$user) {
            return response()->json([
                'error' => 'This email address is not associated with an account. Please check your email or sign up.'
            ], 404);
        }

        // Generate a random password for the user
        $randomPassword = PasswordHelper::generateSecureTempPassword(12);

        // Update the user's password and mark password reset as not required
        $user->portal_password = bcrypt($randomPassword);
        $user->portal_is_email_validate = false;
        $user->save();

        $applicationUrl = env('PORTAL_URL');

        $userName = $user->name;
        $email = $user->email;
        
        // Generate a hash for the user details
        $data = json_encode(['name' => $userName, 'email' => $email, 'password' => $randomPassword]);
        $hashedData = base64_encode($data); // Encode data

        // Generate the signup URL
        $signupUrl = $applicationUrl . "password_reset?token={$hashedData}";

        $user_name = $user->name;;
        $email = $user->email;
        $password = $randomPassword;
        $signupUrl = $signupUrl;
        $moreDetailsUrl = $signupUrl;

        $emailType ="RESET_PORTAL_ACC_EMAIL";
        try {
            Mail::to($email)->send(new PortalMails($user_name, null, null, null, $signupUrl,$moreDetailsUrl,null, $emailType));
        } catch (\Exception $e) {
            Log::error("Failed to send User reset Email: " . $e->getMessage());
        }

        $emailType ="RESET_PORTAL_ACC_PASSWORD_EMAIL";
        try {
            Mail::to($email)->send(new PortalMails($user_name, null, null, $password, null,$moreDetailsUrl,null, $emailType));
        } catch (\Exception $e) {
            Log::error("Failed to send User reset Password Email: " . $e->getMessage());
        }

        // Return Json Response
        return response()->json([
            'message' => "Your password reset successful. please check your email"
        ],200);
    }
}