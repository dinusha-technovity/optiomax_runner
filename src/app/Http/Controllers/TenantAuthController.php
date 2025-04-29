<?php

namespace App\Http\Controllers;

use App\Helpers\PasswordHelper;
use App\Helpers\UserAuthHelper;
use Illuminate\Http\Request;
use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\TenantRegisterdDataSaveRequest;
use App\Http\Requests\TenantSubUserDataUpdate;
use App\Mail\TenantMails;
use App\Mail\TenantUserPasswordResetMail;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TenantAuthController extends Controller
{
    // public function tenantLoginUser(AuthLoginRequest $request)
    // {
    //     $user = User::where('email', $request->email)->first();
    //     $tenantuser = $user->load('tenants');
    //     $tenant = $tenantuser->tenants;

    //     if ($tenant->is_tenant_blocked) {
    //         return response()->json(['error' => 'Your System is temparary block, please contact optiomax technical team'], 403);
    //     }

    //     // Check if the user exists
    //     if (!$user) {
    //         return response()->json(['error' => 'Invalid credentials.'], 401);
    //     }

    //     // Check if the user is blocked
    //     if ($user->is_user_blocked && $user->blocked_until && now()->lessThan($user->blocked_until)) {
    //         $remainingTime = now()->diffInMinutes($user->blocked_until);
    //         return response()->json(['error' => "Your account is blocked. Try again in $remainingTime minutes."], 403);
    //     }

    //     // Check credentials
    //     if (!Auth::attempt(['email' => $request->email, 'password' => $request->password, 'is_app_user' => true])) {
    //         // Increment failed attempts
    //         $user->failed_attempts += 1;

    //         // Block the user if failed attempts exceed 5
    //         if ($user->failed_attempts >= 5) {
    //             $user->is_user_blocked = true;
    //             $user->blocked_until = now()->addMinutes(15);  // Block the user for 15 minutes
    //             $user->failed_attempts = 0;  // Reset the attempts after blocking
    //         }

    //         $user->save();

    //         return response()->json(['error' => 'Invalid credentials.'], 401);
    //     }

    //     // Reset failed attempts on successful login
    //     $user->failed_attempts = 0;
    //     $user->is_user_blocked = false;
    //     $user->blocked_until = null;
    //     $user->save();

    //     // Check if the user is soft deleted
    //     if ($user->trashed()) {
    //         return response()->json(['message' => 'This account has been deleted.'], 403);
    //     }

    //     if ($user->is_user_permanent_blocked) {
    //         return response()->json(['message' => 'Your account has been permanently blocked. please contact your admin'], 403);
    //     }

    //     $tokensResult = UserAuthHelper::getAuthAccessRefreshToken($request->email, $request->password);
    //     $accessToken = $tokensResult['access_token'];
    //     $refreshToken = $tokensResult['refresh_token'];

    //     $userData = [
    //         'id' => $user->id,
    //         'email' => $user->email,
    //         'tenant_db_host' => $tenant->db_host, 
    //         'tenant_db_name' => $tenant->db_name, 
    //         'tenant_db_user' => $tenant->db_user, 
    //         'tenant_db_password' => $tenant->db_password, 
    //     ];

    //     return response()->json([
    //         'status' => true,
    //         'message' => "User login success!",
    //         'user' => $userData,
    //         'access_token' => $accessToken,
    //         'refresh_token' => $refreshToken,
    //     ], Response::HTTP_OK);

    //     // if (Auth::attempt(['email' => $request->email, 'password' => $request->password, 'is_app_user' => true])) {
    //     //     $user = Auth::user();

    //     //     // Check if the user is soft deleted
    //     //     if ($user->trashed()) {
    //     //         return response()->json(['message' => 'This account has been deleted.'], 403);
    //     //     }

    //     //     $tenantuser = $user->load('tenants');
    //     //     $tenant = $tenantuser->tenants;

    //     //     $tokensResult = UserAuthHelper::getAuthAccessRefreshToken($request->email, $request->password);
    //     //     $accessToken = $tokensResult['access_token'];
    //     //     $refreshToken = $tokensResult['refresh_token'];

    //     //     $userData = [
    //     //         'id' => $user->id,
    //     //         'email' => $user->email,
    //     //         'tenant_db_host' => $tenant->db_host, 
    //     //         'tenant_db_name' => $tenant->db_name, 
    //     //         'tenant_db_user' => $tenant->db_user, 
    //     //         'tenant_db_password' => $tenant->db_password, 
    //     //     ];
 
    //     //     return response()->json([
    //     //         'status' => true,
    //     //         'message' => "User login success!",
    //     //         'user' => $userData,
    //     //         'access_token' => $accessToken,
    //     //         'refresh_token' => $refreshToken,
    //     //     ], Response::HTTP_OK);
    //     // } else {
    //     //     return response()->json(['error' => 'Unauthorized'], 401);
    //     // }
    // }
    public function tenantLoginUser(AuthLoginRequest $request)
    {
        try {
            // Find the user by email
            $user = User::where('email', $request->email)->first();

            // Check if the user exists
            if (!$user) {
                return response()->json(['error' => "Sorry, we couldn't find an account associated with this email."], 403);
            }

            // Load the tenant associated with the user
            $tenantuser = $user->load('tenants');
            $tenant = $tenantuser->tenants;

            // Check if the tenant is blocked
            if ($tenant->is_tenant_blocked) {
                return response()->json([
                    'error' => 'Your system is temporarily blocked, please contact the Optiomax technical team.'
                ], 403);
            }

            // Check if the user is blocked (temporary block)
            if ($user->is_user_blocked && $user->blocked_until && now()->lessThan($user->blocked_until)) {
                $remainingTime = now()->diffInMinutes($user->blocked_until);
                $remaininTimeInSecs =now()->diffInSeconds($user->blocked_until);
                return response()->json([
                    'error' => "Your account is blocked. Try again in $remainingTime minutes.",
                    'remainingTime'=>$remaininTimeInSecs,
                    'isBlocked'=>true,
             ], 403);
            }

            // Check if the user is permanently blocked
            if (!$user->is_user_active) {
                return response()->json(['error' => 'Your account has been permanently blocked. please contact your admin.'], 403);
            }

            // Check if the user is permanently blocked
            if (!$user->is_app_user) {
                return response()->json(['error' => 'Access denied. Unauthorized application user.'], 403);
            }

            // Check password explicitly to distinguish error type
            if (!Hash::check($request->password, $user->password)) {
                // Increment failed attempts
                $user->failed_attempts += 1;

                // Block the user if failed attempts exceed 5
                if ($user->failed_attempts >= 5) {
                    $user->is_user_blocked = true;
                    $user->blocked_until = now()->addMinutes(15);  // Block the user for 15 minutes
                    $user->failed_attempts = 0;  // Reset the attempts after blocking
                }

                $user->save();

                return response()->json(['error' => 'Invalid password.'], 403); // Specific error for invalid password
            }

            // Reset failed attempts on successful login
            $user->failed_attempts = 0;
            $user->is_user_blocked = false;
            $user->blocked_until = null;
            $user->save();

            // Check if the user is soft deleted (trashed)
            if ($user->trashed()) {
                return response()->json(['message' => 'This account has been deleted.'], 403);
            }

            // Check first login
            if ($user->app_first_login === null && !$user->is_email_validate) {
                $firstlogin = true;
            }elseif ($user->app_last_login === null && !$user->is_email_validate) {
                $firstlogin = true;
            }elseif($user->app_first_login === null && $user->is_email_validate){
                $user->app_first_login = Carbon::now();
                $user->app_last_login = Carbon::now();
                $user->save();
                $firstlogin = false;
            }elseif($user->app_first_login !== null && $user->is_email_validate){
                $user->app_last_login = Carbon::now();
                $user->save();
                $firstlogin = false;
            }

            // Generate tokens using UserAuthHelper and handle potential errors from token generation
            try {
                $tokensResult = UserAuthHelper::getAuthAccessRefreshToken($request->email, $request->password);
                $accessToken = $tokensResult['access_token'];
                $refreshToken = $tokensResult['refresh_token'];
            } catch (\Exception $e) {
                // Log the token generation error
                Log::error('Error generating tokens', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'Error generating access tokens. Please try again.'], 500);
            }

            // Prepare user data to be returned
            $userData = [
                'tenant_package' => $tenant->package, 
                'tenant_db_host' => $tenant->db_host, 
                'tenant_db_name' => $tenant->db_name, 
                'tenant_db_user' => $tenant->db_user, 
                'tenant_db_password' => $tenant->db_password, 
            ];

            // Return successful login response with tokens
            return response()->json([
                'status' => true,
                'message' => 'User login successful!',
                'user' => $userData,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'firstlogin' => $firstlogin,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            // Log the unexpected error and return a generic error response
            Log::error('Login error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'An unexpected error occurred. Please try again later.'], 500);
        }
    }

    public function tenantUserRegister(TenantRegisterdDataSaveRequest $request)
    {
        DB::beginTransaction();
        try {
            
            $authUser = Auth::user();
            $validatedUser = $request->validated();
    
            // Check if user already exists
            $user = User::where('email', $validatedUser["email"])->first();
    
            if ($user) {
                if ($user->tenant_id !== $authUser->tenant_id) {
                    return response()->json(['error' => 'User cannot be registered because they are already in another tenant.', "success"=>false], 401);
                }

                if($user->is_app_user === true){
                    return response()->json(['error' => 'User is already is a app user'], 401);
                }

                // If the user is in the same tenant, mark them as an app user and update password
                $user->is_app_user = true;
                $user->password = $validatedUser["password"];
                $user->save();
            } else {

                $tenantuser = $authUser->load('tenants');
                $tenant = $tenantuser->tenants;
     
                User::create($validatedUser);
            }
    
            DB::commit();
    
            return response()->json(['message' => 'User registered successfully'], 201);
            
    
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::debug($th);
    
            return response()->json([
                'status' => false,
                'message' => 'Failed to create user',
                'error' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified resource in storage.
     */ 
    public function updateTenantUserData(TenantSubUserDataUpdate $request)
    {
        try {
            $authUser = Auth::user();
            $tenantuser = $authUser->load('tenants');
            $tenant = $tenantuser->tenants;


            // Fetch user based on tenant_id and email
            $user = User::where('email', $request->useremail)
                    ->where('tenant_id', $request->tenant_id)
                    ->first();

                // Check if user exists
            if(!$user) {
                return response()->json([
                    'message' => 'User Not Found.'
                ], 404);
            }

            //check is the user email is registered in the database 
            if ($user->email !== $request->email) {
                // Check if the new email already exists
                $existingUser = User::where('email', $request->email)->where('id', '!=', $user->id)->exists();

                if ($existingUser) {
                    return response()->json(['error' => 'Email already in use.'], 422);
                }

                $user->email = $request->email;
            }

            // Update user details
            $user->user_name = $request->user_name;
            $user->name = $request->name;
            $user->profile_image = $request->profile_image;
            $user->contact_no = $request->contact_no;
            $user->contact_no_code = $request->contact_no_code;
            $user->user_description = $request->user_description;
            $user->designation_id = $request->designation_id;


            // Save the updated user data
            $user->save();

            // Return Json Response
            return response()->json([
                'message' => "User account status updated successfully.",
                'package' => $tenant->package,
            ],200);
        } catch (\Throwable $th) {
            Log::debug("Tenant user update", $th);
                    return response()->json([
                        'status' => false,
                        'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function tenantUserdestroy(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantuser = $authUser->load('tenants');
            $tenant = $tenantuser->tenants;

            // Fetch user based on tenant_id and email
            $user = User::where('email', $request->useremail)
                    ->where('tenant_id', $request->tenant_id)
                    ->first();

            // Check if user exists
            if(!$user) {
                return response()->json([
                    'message' => 'User Not Found.'
                ], 404);
            }

            // Check if the user is the tenant owner
            // if it is disale his app login
            if ($user->is_owner) {
               
                $user->is_app_user = false;
                $user->save();
            }
            else{
                // Soft delete the user
                $user->delete();
            }

            // Revoke all tokens issued to the user
            $user->tokens()->each(function ($token) {
                $token->revoke();
            });

            // Return Json Response
            return response()->json([
                'message' => "User account deleted successfully.",
                'package' => $tenant->package,
            ],200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Change the specified resource in storage.
     */ 
    public function changeTenantUserStatus(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantuser = $authUser->load('tenants');
            $tenant = $tenantuser->tenants;

            // Fetch user based on tenant_id and email
            $user = User::where('email', $request->useremail)
                    ->where('tenant_id', $request->tenant_id)
                    ->first();

            // Check if user exists
            if(!$user) {
                return response()->json([
                    'message' => 'User Not Found.'
                ], 404);
            }

            // Update user details
            $user->is_user_active = $request->is_user_active;

            // Save the updated user data
            $user->save();

            // Return Json Response
            return response()->json([
                'message' => "User account status updated successfully..",
                'package' => $tenant->package,
            ],200);
        } catch (\Throwable $th) {
                    return response()->json([
                        'status' => false,
                        'message' => $th->getMessage()
                    ], 500);
        }
    }

    /**
     * Change the specified resource in storage.
     */ 
    public function TenantUserPasswordReset(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantuser = $authUser->load('tenants');
            $tenant = $tenantuser->tenants;

            // Fetch user based on tenant_id and email
            $user = User::where('email', $request->useremail)
                    ->where('tenant_id', $request->tenant_id)
                    ->first();

            // Check if user exists
            if(!$user) {
                return response()->json([
                    'message' => 'User Not Found.'
                ], 404);
            }

            // Update user details
            $user->password = $request->password;
            $user->is_email_validate = false;
            $user->app_last_login=null;

            // Save the updated user data
            $user->save();

            // Return Json Response
            return response()->json([
                'message' => "User account status updated successfully..",
                'package' => $tenant->package,
            ],200);
        } catch (\Throwable $th) {
                    return response()->json([
                        'status' => false,
                        'message' => $th->getMessage()
                    ], 500);
        }
    }

    /**
     * Change the specified resource in storage.
     */ 
    public function TenantUserLogout(Request $request)
    {
        try {
            $user = Auth::user();
            $user->token()->revoke();

            // Return Json Response
            return response()->json([
                'message' => "Successfully logged out.."
            ],200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Change the auth user password
     */ 
    public function changeauthuserpassword(Request $request)
    {
        $input = $request->all();

        $user = Auth::user();
        $tenantuser = $user->load('tenants');
        $tenant = $tenantuser->tenants;

        if ($user->is_email_validate) {
            // If the old password doesn't match, return an error response
            return response()->json([
                'message' => 'This user is aready validate.',
            ], 400);
        }
        
        // // Check if the old password matches the user's current password
        if (!Hash::check($input['old_password'], $user->password)) {
            // If the old password doesn't match, return an error response
            return response()->json([
                'message' => 'The Temp password is incorrect.',
            ], 400);
        }

        // Update the user's password and mark password reset as not required
        $user->password = Hash::make($input['password']);
        $user->is_email_validate = true;
        $user->contact_no = $input['contactnumber'];
        $user->save();

        // Return Json Response
        return response()->json([
            'message' => "Your Password reset successful.",
            'package' => $tenant->package,
        ],200);
    }

    /**
     * Change the specified resource in storage.
     */ 
    public function AuthPasswordResetCancle(Request $request)
    {
        try {
            $user = Auth::user();
            $tenantuser = $user->load('tenants');
            $tenant = $tenantuser->tenants;

            if(!$user){
                return response()->json([
                    'message' => 'User does not exit.',
                ], 400);
            }

            $user->app_first_login = null;
            $user->is_email_validate = false;
            $user->save();

            $user->token()->revoke();

            // Return Json Response
            return response()->json([
                'message' => "Your Password reset Cancle successful.",
                'package' => $tenant->package,
            ],200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function tenantForgotPassword(Request $request) {
        try {
            DB::beginTransaction();
    
            // Check if user exists and is an app user
            $user = User::where('email', $request->email)->first();
            if (!$user || !$user->is_app_user) {
                return response()->json([
                    'status' => false,
                    'message' => "User does not exist in our system",
                ]);
            }
    
            // Get tenant details
            $tenant = $user->tenants()->first(); // Ensure single tenant
            if (!$tenant) {
                return response()->json(['status' => false, 'message' => 'Tenant not found']);
            }
    
            // Generate and update password
            $randomPassword = PasswordHelper::generateSecureTempPassword(12);
            $hashedPassword = bcrypt($randomPassword);
            $user->password = $hashedPassword;
            $user->is_email_validate= false;
            $user->app_last_login=null;

            $user->save();
            DB::commit();
    
            // Store for email
            $user_name = $user->user_name;
            $email = $user->email;
            $password = $randomPassword;
    
            // If Enterprise, update password in tenant DB
            if ($tenant->package === "ENTERPRISE") {
                DB::purge('tenant');
                Config::set("database.connections.tenant", [
                    'driver' => 'pgsql',
                    'host' => $tenant->db_host,
                    'port' => env('DB_PORT', '5432'),
                    'database' => $tenant->db_name,
                    'username' => $tenant->db_user,
                    'password' => $tenant->db_password,
                    'charset' => 'utf8',
                    'prefix' => '',
                    'schema' => 'public',
                    'sslmode' => 'prefer',
                ]);
    
                $originalDefaultConnection = Config::get('database.default');
    
                try {
                    DB::connection('tenant')->getPdo();
                    Config::set('database.default', 'tenant');
    
                    DB::connection('tenant')->beginTransaction();
                    DB::connection('tenant')
                        ->table('users')
                        ->where('email', $email)
                        ->update([
                            'password' => $hashedPassword,
                            'is_email_validate' => false,
                            'app_last_login'=>null,

                        ]);
                    DB::connection('tenant')->commit();
                } catch (\Exception $e) {
                    if (DB::connection('tenant')->transactionLevel() > 0) {
                        DB::connection('tenant')->rollBack();
                    }
                    Log::error("Failed to connect/update tenant DB: " . $e->getMessage());
                    Config::set('database.default', $originalDefaultConnection);
                    throw new \Exception("Failed to connect to tenant DB.");
                } finally {
                    Config::set('database.default', $originalDefaultConnection);
                }
            }
    
            // Prepare email
            $application_url = env('APPLICATION_URL');
            $data = json_encode(['name' => $user_name, 'email' => $email, 'password' => $password, 'inviter'=>"by you"]);
            $hashedData = base64_encode($data);

            $signupUrl = $application_url . "continue_login?token={$hashedData}";
            $moreDetailsUrl = $signupUrl;

    
            // Send email safely
            $emailType = "USER_PASSWORD_RESET";

            try {
                Mail::to($email)->send(new TenantMails($user_name, null, null, null, $signupUrl ,$moreDetailsUrl,null, $emailType));
            } catch (\Exception $e) {
                Log::error("Failed to send portal user reset Email: " . $e->getMessage());
                return response()->json([
                    "status" => false,
                    "message" => "Password reset successful, but email could not be sent."
                ]);
            }
            
            $emailType = "USER_PASSWORD_RESET_PASSWORD";

            try {
                Mail::to($email)->send(new TenantMails($user_name, null, null, $password, null,$moreDetailsUrl,null, $emailType));
            } catch (\Exception $e) {
                Log::error("Failed to send User Password Email: " . $e->getMessage());
                return response()->json([
                    "status" => false,
                    "message" => "Password reset successful, but email could not be sent."
                ]);
            }

            return response()->json([
                "status" => true,
                "message" => "Your password reset was successful. Please check your email."
            ]);
    
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
