<?php

namespace App\Http\Controllers;

use App\Helpers\TenantHelper;
use App\Helpers\UserAuthHelper;
use App\Http\Requests\AuthLoginRequest;
use App\Models\User;
use App\Models\tenants;
use App\Http\Requests\UserAuthRequest;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;
use Illuminate\Support\Str;
use Mail;
use Illuminate\Support\Facades\Hash;

class UserAuthenticationController extends Controller
{
    public function registerNewUser(UserAuthRequest $request)
    {
        try {
            // Start transaction
            // DB::beginTransaction();

            $validatedUser = $request->validated();

            // Step 1: Basic Email Validation
            if (!filter_var($validatedUser['email'], FILTER_VALIDATE_EMAIL)) {
                return response()->json(['error' => 'Invalid email format.'], 400);
            }

            // Step 2: MX Record Validation
            $emailDomain = explode('@', $validatedUser['email'])[1];
            if (!checkdnsrr($emailDomain, 'MX')) {
                return response()->json(['error' => 'Invalid email domain.'], 400);
            }
            
            $uncryptedPassword = $validatedUser['password'];
            $validatedUser['portal_password'] = bcrypt($validatedUser['password']);
            $validatedUser['password'] = bcrypt($validatedUser['password']);
            $validatedUser['is_owner'] = true;
            $validatedUser['is_app_user'] = false;
            $validatedUser['portal_is_email_validate'] = true;

            // dd($validatedUser);

            // Create the owner user
            $ownerUser = User::create($validatedUser);


            // if($ownerUser){
            //     $user_name = $ownerUser->name;
            //     $email = $ownerUser->email;
            //     $company_name = 'Optiomax LLC PVT';
            //     $support_email = 'support@optiomax.com';
            //     $login_url = url('http://optiomax.com');
            
            //     Mail::send('emails.userRegisterEmail', [
            //         'user_name' => $user_name,
            //         'email' => $email,
            //         'password' => $uncryptedPassword,
            //         'company_name' => $company_name,
            //         'support_email' => $support_email,
            //         'login_url' => $login_url,
            //     ], function($message) use ($request) {
            //         $message->to($request->email)
            //             ->subject('Welcome to Our Platform');
            //     });
            // }

            // Finalize the tenant setup
            TenantHelper::setupTenantDatabase($ownerUser, $validatedUser['packageType'], $validatedUser['invitedusers'], $validatedUser);

            // Commit transaction
            // DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'User registered successfully'
            ], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            // DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to create user',
                'error' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create an invited user and send a notification email.
     */
    private function createInvitedUser(array $userData, string $ownerEmail, int $ownerId)
    {
        $unencryptedPassword = Str::random(12);

        $userData['password'] = bcrypt($unencryptedPassword);
        $userData['email'] = $userData['app_user_email'];
        $userData['is_owner'] = $userData['accountPerson'];
        $userData['is_app_user'] = $userData['admin'];
        $userData['user_name'] = $userData['name'];

        if ($ownerEmail === $userData['email']) {
            $ownerUser = User::findOrFail($ownerId);
            $ownerUser->is_owner = $userData['is_owner'];
            $ownerUser->is_app_user = $userData['is_app_user'];
            $ownerUser->save();
        } else {
            $createdUser = User::create($userData);

            // Send email notification to the created user
            // Mail::send('emails.userAuthenticationVerifyEmail', [
            //     'password' => $unencryptedPassword,
            // ], function ($message) use ($createdUser) {
            //     $message->to($createdUser->email);
            //     $message->subject('Email Verification Mail');
            // });
        }
    }

    // public function loginUser(AuthLoginRequest $request)
    // {
    //     if (Auth::attempt(['email' => $request->email, 'portal_password' => $request->password, 'is_owner' => true])) {
    //         $user = Auth::user();
            
    //         // Check if the user is soft deleted
    //         if ($user->trashed()) {
    //             return response()->json(['message' => 'This account has been deleted.'], 403);
    //         }

    //         $tokensResult = UserAuthHelper::getAuthAccessRefreshToken($request->email, $request->password);
    //         $accessToken = $tokensResult['access_token'];
    //         $refreshToken = $tokensResult['refresh_token'];

    //         // return response()->json([
    //         //     'status' => true,
    //         //     'user' => $user,
    //         //     'access_token' => $accessToken,
    //         //     'refresh_token' => $refreshToken,
    //         // ], Response::HTTP_OK);

    //         $response = response()->json([
    //             'status' => true,
    //             'access_token' => $accessToken,
    //             'refresh_token' => $refreshToken,
    //             'user' => $user,
    //         ], Response::HTTP_OK);

    //         $response->cookie('accessToken', $accessToken, config('auth.token_lifetime'), '/', null, null, true); // HTTP-only
    //         $response->cookie('refreshToken', $refreshToken, config('auth.token_lifetime'), '/', null, null, true); // HTTP-only

    //         return $response;
    //     } else {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }
    // }
    public function loginUser(AuthLoginRequest $request)
    {
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

        // Generate access and refresh tokens
        $tokensResult = UserAuthHelper::getAuthAccessRefreshToken($user->email, $user->portal_password);
        $accessToken = $tokensResult['access_token'];
        $refreshToken = $tokensResult['refresh_token'];
    
        // Create response with cookies
        $response = response()->json([
            'status' => true,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'user' => $user,
        ], Response::HTTP_OK);
    
        $response->cookie('accessToken', $accessToken, config('auth.token_lifetime'), '/', null, null, true);
        $response->cookie('refreshToken', $refreshToken, config('auth.token_lifetime'), '/', null, null, true);
    
        return $response;
    }

    public function getUserDetails()
    {
        if (Auth::guard('api')->check()) {
            $user = Auth::guard('api')->user();
            return Response(['status' => true, 'data' => $user], 200);
        }
        return Response(['status' => false, 'message' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
    }

    public function userLogout()
    {
        if (Auth::guard('api')->check()) {
            $user = Auth::guard('api')->user();
            $tokens = $user->tokens->pluck('id');
            Token::whereIn('id', $tokens)
                ->update(['revoked' => true]);

            return response()->json([
                'status' => true,
                'message' => 'User logged out successfully'
            ], Response::HTTP_OK);
        }

        return response()->json([
            'status' => false,
            'message' => 'unauthorized'
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function refreshToken(Request $request)
    {
        try {
            if (!$request->header('Authorization')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Authorization header is missing. Please include the authorization header with your request.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $token = $request->header('Authorization');
            $token = str_replace('Bearer ', '', $token);

            $newAccessTokenResult = UserAuthHelper::getAuthRefreshToken($token);
            $accessToken = $newAccessTokenResult['access_token'];
            $refreshToken = $newAccessTokenResult['refresh_token'];

            return response()->json([
                'status' => true,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
            ], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create user',
                'error' => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}