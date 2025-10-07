<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use App\Models\User;
use App\Models\tenants;
use App\Models\tenant_configuration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\InviteUserMail;
use App\Mail\InvitePotralUserMail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use App\Helpers\PasswordHelper;
use App\Mail\PortalMails;
use App\Mail\TenantMails;

class TenantHelper
{ 
    // public static function generateTenantDbName($registeredUserEmail): String
    // {
    //     $dbSuffix = substr(hash('sha256', $registeredUserEmail), 0, 5);
    //     return 'tenant_' . $dbSuffix;
    // }
    public static function generateTenantDbName($registeredUserEmail): String
    {
        do {
            $dbSuffix = substr(hash('sha256', $registeredUserEmail), 0, 5);
            $databasename = 'tenant_' . $dbSuffix;

            $exists = DB::table('tenants')->where('db_name', $databasename)->exists();

        } while ($exists);

        return $databasename;
    }

    public static function generateTenantDbUserName($registeredUserEmail): String
    {
        do {
            $dbUserSuffix = substr(hash('sha256', $registeredUserEmail), 0, 5);
            $randomString = Str::random(5); 
            $databaseusername = '_' . $randomString . $dbUserSuffix;

            $exists = DB::table('tenants')->where('db_user', $databaseusername)->exists();

        } while ($exists);

        return $databaseusername;
    }

    public static function sendPostRequest($email, $dbname)
    {
        try {
            $response = Http::post('http://213.199.44.42:8001/api/v1/write-tenant-env-file', [
                'file_name' => $email,
                'db_name' => $dbname,
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $response->body()
                ], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public static function setupTenantDatabase($tenantUser, $packageType, $invitedusers, $validatedUser): void
    {
        DB::beginTransaction(); // Ensure transaction safety
        try {
            if ($packageType == "ENTERPRISE" && $tenantUser) { 
                $tenantDbHost = env('DB_HOST');
                $tenantDbName = self::generateTenantDbName($validatedUser['companyemail']);
                $tenantDbUserName = self::generateTenantDbUserName($validatedUser['companyemail']);
                
                // Generate a more robust password without special characters
                $tenantDbUserPassword = bin2hex(random_bytes(8));

                if (!$tenantDbHost || !$tenantDbName || !$tenantDbUserName || !$tenantDbUserPassword) {
                    throw new \Exception("Missing required database information.");
                }
    
                if ($tenantDbHost && $tenantDbName && $tenantDbUserName && $tenantDbUserPassword) {
    
                    // 1. Create tenant user and database
                    DB::statement("CREATE USER \"$tenantDbUserName\" WITH PASSWORD '$tenantDbUserPassword';");
                    DB::statement("CREATE DATABASE \"$tenantDbName\" OWNER \"$tenantDbUserName\";");
                    DB::statement("GRANT ALL PRIVILEGES ON DATABASE \"$tenantDbName\" TO \"$tenantDbUserName\";");
    
                    // Store tenant information
                    $tenant = tenants::create([
                        'tenant_name' => $validatedUser['companyname'],
                        'address' => $validatedUser['companyaddress'],
                        'contact_no' => $validatedUser['companycontact_no'],
                        'contact_no_code' => $validatedUser['companycontact_no_code'],
                        'zip_code' => $validatedUser['companyzip_code'],
                        'city' => $validatedUser['companycity'],
                        'country'=>$validatedUser['companycountry'],
                        'email' => $validatedUser['companyemail'],
                        'website' => $validatedUser['companywebsite'],
                        'owner_user' => $tenantUser->id,
                        'activation_code' => null,
                        'package' => $packageType,
                        'db_host' => $tenantDbHost,
                        'db_name' => $tenantDbName,
                        'db_user' => $tenantDbUserName,
                        'db_password' => $tenantDbUserPassword,
                    ]);
    
                    // 2. Dynamically configure tenant connection
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

                    // 3. Test tenant DB connection
                    try {
                        DB::connection('tenant')->getPdo();
                        \Log::info("Connection to tenant DB successful.");
                    } catch (\Exception $e) {
                        throw new \Exception("Failed to connect to tenant DB: " . $e->getMessage());
                    }
    
                    // 4. Temporarily set the tenant connection as the default
                    $originalDefaultConnection = Config::get('database.default');
                    Config::set('database.default', 'tenant');
    
                    try {
                        Artisan::call('migrate', [
                            '--path' => 'database/migrations/tenant',
                            '--force' => true,
                        ]);
                        Artisan::call('passport:install');
                    } catch (\Exception $e) {
                        throw new \Exception("Failed to run migrations or install Passport: " . $e->getMessage());
                    }

                    // 5. Create the tenant's user
                    // Generate and process invited users
                    $invitedUserDetails = []; // To store plaintext passwords for invited users

                    foreach ($invitedusers as $user) {
                        // Generate a random password for the user
                        $randomPassword = PasswordHelper::generateSecureTempPassword(12);
                        $user['password'] = $randomPassword; // Add 'password' key to the user array

                        // Only create the user if 'admin' is true
                        if ($user['admin']) {
                            $userData = [
                                'user_name' => $user['name'],
                                'email' => $user['app_user_email'],
                                'name' => $user['name'],
                                'contact_no' => $user['contact_no'] ?? null,
                                'contact_no_code' => $user['contact_no_code'] ?? null,
                                'website' => $user['website'] ?? null,
                                'address' => $user['address'] ?? null,
                                'password' => bcrypt($randomPassword), // Encrypt the password
                                'is_owner' => $user['accountPerson'],
                                'is_app_user' => $user['admin'],
                                'tenant_id' => $tenant->id,
                            ];
                            $createdUser = User::create($userData);

                            //send email for invite to the app 
                            $applicationUrl = env('APPLICATION_URL');

                            $userName = $user['name'];
                            $email = $user['app_user_email'];
                            $inviterName = $tenantUser->name;
                            
                            // Generate a hash for the user details
                            $data = json_encode(['name' => $userName, 'email' => $email, 'password' => $randomPassword, 'inviter' => $inviterName]);
                            $hashedData = base64_encode($data); // Encode data
    
                            // Generate the signup URL
                            $signupUrl = $applicationUrl . "/continune_first_sign_up?token={$hashedData}";
    
                            $user_name = $user['name'];
                            $email = $user['app_user_email'];
                            $inviterName = $tenantUser->name;
                            $password = $randomPassword;
                            $signupUrl = $signupUrl;
                            $moreDetailsUrl = $signupUrl;

                            $emailType = "USER_INVITATION";

                            try {
                                Mail::to($email)->send(new TenantMails($user_name, $inviterName, null, null, $signupUrl,$moreDetailsUrl,null, $emailType));
                            } catch (\Exception $e) {
                                Log::error("Failed to send User Invitation: " . $e->getMessage());
                            }

                            $emailType = "USER_INVITATION_PASSWORD";

                            try {
                                Mail::to($email)->send(new TenantMails($user_name, null, null, $password, null,$moreDetailsUrl,null, $emailType));
                            } catch (\Exception $e) {
                                Log::error("Failed to send User Password Email: " . $e->getMessage());
                            }

                        }

                        // Store plaintext password for reference or further processing
                        $invitedUserDetails[] = [
                            'user_name' => $user['name'],
                            'email' => $user['app_user_email'],
                            'name' => $user['name'],
                            'contact_no' => $user['contact_no'] ?? null,
                            'contact_no_code'=>$user['contact_no_code']?? null,
                            'website' => $user['website'] ?? null,
                            'address' => $user['address'] ?? null,
                            'password' => $randomPassword, // Store plaintext password here
                            'is_owner' => $user['accountPerson'],
                            'is_app_user' => $user['admin'],
                            'tenant_id' => $tenant->id,
                        ];

                        // $applicationUrl = env('APPLICATION_URL');

                        // $userName = $user['name'];
                        // $email = $user['app_user_email'];
                        // $inviterName = $tenantUser->name;
                        
                        // // Generate a hash for the user details
                        // $data = json_encode(['name' => $userName, 'email' => $email, 'password' => $randomPassword, 'inviter' => $inviterName]);
                        // $hashedData = base64_encode($data); // Encode data

                        // // Generate the signup URL
                        // $signupUrl = $applicationUrl . "/continune_first_sign_up?token={$hashedData}";

                        // $user_name = $user['name'];
                        // $email = $user['app_user_email'];
                        // $inviterName = $tenantUser->name;
                        // $password = $randomPassword;
                        // $signupUrl = $signupUrl;
                        // $moreDetailsUrl = $signupUrl;
                    
                        // Mail::to($email)->send(new InviteUserMail($user_name, $inviterName, $email, $password, $signupUrl, $moreDetailsUrl));

                    }

                    $systemUserName = 'tenant'.$tenant->id.Str::random(11);
                    $systemUserEmail = $systemUserName.'@gmail.com';
                    $password = Str::random(11);

                    User::create([
                        'user_name' => $systemUserName,
                        'email' => $systemUserEmail,
                        'name' => $systemUserName,
                        'contact_no' => $tenant->contact_no,
                        'website' => $tenant->website,
                        'address' => $tenant->address,
                        'password' => $password,
                        'tenant_id' => $tenant->id,
                        'is_system_user' => true,
                        'system_user_expires_at' => Carbon::now()->addDays(30), 
                    ]);

                    tenant_configuration::create([
                        'system_user_email' => $systemUserEmail,
                        'system_user_password' => $password,
                        'tenant_id' => $tenant->id
                    ]);

                    $selectedTenantId = $tenant->id;
                    
                    // Pass the selected user name to the service container
                    app()->singleton('selectedTenantId', function () use ($selectedTenantId) {
                        return $selectedTenantId;
                    });

                    Artisan::call('db:seed', [
                        '--class' => 'TenantDBSeeder',
                    ]);

                    // 7. Revert back to the original default connection
                    Config::set('database.default', $originalDefaultConnection);

                    $emailExists = false;

                    // Update tenant owner and invited users
                    foreach ($invitedUserDetails as $user) {
                        if ($tenantUser->email === $user['email']) {
                            // Update the tenant owner
                            $ownerUser = User::findOrFail($tenantUser->id);
                            $ownerUser->password = bcrypt($user['password']);
                            $ownerUser->is_owner = $user['is_owner'];
                            $ownerUser->is_app_user = $user['is_app_user'];
                            $ownerUser->tenant_id = $tenant->id;
                            $ownerUser->save();
                            $emailExists = true;
                        } else {
                            // Generate a random password for the user
                            $randomPortalPassword = PasswordHelper::generateSecureTempPassword(12);
                            
                            // Create invited users with persisted data
                            $userData = [
                                'user_name' => $user['name'],
                                'email' => $user['email'],
                                'name' => $user['name'],
                                'contact_no' => $user['contact_no'] ?? null,
                                'contact_no_code' => $user['contact_no_code'] ?? null,
                                'website' => $user['website'] ?? null,
                                'address' => $user['address'] ?? null,
                                'portal_password' => bcrypt($randomPortalPassword),
                                'password' => bcrypt($user['password']),
                                'is_owner' => $user['is_owner'],
                                'is_app_user' => $user['is_app_user'],
                                'tenant_id' => $tenant->id,
                            ];
                            $createdUser = User::create($userData);

                            if ($user['is_owner']) {
                                $applicationUrl = env('PORTAL_URL');

                                $userName = $user['name'];
                                $email = $user['email'];
                                $inviterName = $tenantUser->name;
                                
                                // Generate a hash for the user details
                                $data = json_encode(['name' => $userName, 'email' => $email, 'password' => $randomPortalPassword, 'inviter' => $inviterName]);
                                $hashedData = base64_encode($data); // Encode data
        
                                // Generate the signup URL
                                $signupUrl = $applicationUrl . "/continune_first_sign_up?token={$hashedData}";

                                $user_name = $user['name'];
                                $email = $user['email'];
                                $inviterName = $tenantUser->name;
                                $password = $randomPortalPassword;
                                $signupUrl = $signupUrl;
                                $moreDetailsUrl = $signupUrl;

                                $emailType= "PORTAL_USER_INVITATION";
                            
                                // Mail::to($email)->send(new InvitePotralUserMail($user_name, $inviterName, $email, $password, $signupUrl, $moreDetailsUrl));
                                try {
                                    Mail::to($email)->send(new PortalMails($user_name, $inviterName, null, null, $signupUrl,$moreDetailsUrl,null, $emailType));
                                } catch (\Exception $e) {
                                    Log::error("Failed to send User Password Email: " . $e->getMessage());
                                }

                                $emailType= "PORTAL_USER_INVITATION_PASSWORD";

                                try {
                                    Mail::to($email)->send(new PortalMails($user_name, null, null, $password, null,$moreDetailsUrl,null, $emailType));
                                } catch (\Exception $e) {
                                    Log::error("Failed to send User Password Email: " . $e->getMessage());
                                }
                            }
                        }
                    }

                    // if (!$emailExists) {
                    //     $applicationUrl = env('APPLICATION_URL');

                    //     $userName = $user['name'];
                    //     $email = $user['email'];
                    //     $inviterName = $tenantUser->name;
                        
                    //     // Generate a hash for the user details
                    //     $data = json_encode(['name' => $userName, 'email' => $email, 'password' => $randomPassword, 'inviter' => $inviterName]);
                    //     $hashedData = base64_encode($data); // Encode data

                    //     // Generate the signup URL
                    //     $signupUrl = $applicationUrl . "/continune_first_sign_up?token={$hashedData}";

                    //     $user_name = $user['name'];
                    //     $email = $user['email'];
                    //     $inviterName = $tenantUser->name;
                    //     $password = $randomPortalPassword;
                    //     $signupUrl = $signupUrl;
                    //     $moreDetailsUrl = $signupUrl;
                    
                    //     Mail::to($email)->send(new InvitePotralUserMail($user_name, $inviterName, $email, $password, $signupUrl, $moreDetailsUrl));
                    // }

                    //update tenent user's tenant id 

                    $tenantUser = User::where('email', $tenantUser->email)->first();
                        if ($tenantUser) {
                            $tenantUser->tenant_id = $tenant->id;
                            $tenantUser->save();
                        }

                    User::create([
                        'user_name' => $systemUserName,
                        'email' => $systemUserEmail,
                        'name' => $systemUserName,
                        'contact_no' => $tenant->contact_no,
                        'contact_no_code'=>$tenant->contact_no_code,
                        'website' => $tenant->website,
                        'address' => $tenant->address,
                        'password' => $password,
                        'tenant_id' => $tenant->id,
                        'is_system_user' => true,
                        'system_user_expires_at' => Carbon::now()->addDays(30), 
                    ]);

                    // Commit transaction if everything is successful
                    DB::commit();
                }
            } elseif ($packageType == "INDIVIDUAL" && $tenantUser) {
                $tenantDbHost = env('DB_HOST');
                $tenantDbName = env('DB_DATABASE');
                $tenantDbUserName = env('DB_USERNAME');
                $tenantDbUserPassword = env('DB_PASSWORD');

                if (!$tenantDbHost || !$tenantDbName || !$tenantDbUserName || !$tenantDbUserPassword) {
                    throw new \Exception("Missing required database credentials for individual package.");
                }

                if ($tenantDbHost && $tenantDbName && $tenantDbUserName && $tenantDbUserPassword) {
                    // Store tenant information
                    $tenant = tenants::create([
                        'tenant_name' => $validatedUser['companyname'],
                        'address' => $validatedUser['companyaddress'],
                        'contact_no' => $validatedUser['companycontact_no'],
                        'contact_no_code' => $validatedUser['companycontact_no_code'],
                        'email' => $validatedUser['companyemail'],
                        'zip_code' => $validatedUser['companyzip_code'],
                        'city' => $validatedUser['companycity'],
                        'country'=>$validatedUser['companycountry'],
                        'website' => $validatedUser['companywebsite'],
                        'owner_user' => $tenantUser->id,
                        'activation_code' => null,
                        'package' => $packageType,
                        'db_host' => $tenantDbHost,
                        'db_name' => $tenantDbName,
                        'db_user' => $tenantDbUserName,
                        'db_password' => $tenantDbUserPassword,
                    ]);

                    foreach ($invitedusers as $user) {
                        if ($tenantUser->email === $user['app_user_email']) {
                            $randomAppPassword = PasswordHelper::generateSecureTempPassword(12);
                            $user['password'] = $randomAppPassword;
                            // Update the tenant owner
                            $ownerUser = User::findOrFail($tenantUser->id);
                            $ownerUser->is_owner = $user['accountPerson'];
                            $ownerUser->is_app_user = $user['admin'];
                            $ownerUser->password = bcrypt($user['password']);
                            $ownerUser->tenant_id = $tenant->id;
                            $ownerUser->save();

                            $applicationUrl = env('APPLICATION_URL');
                            
                            $userName = $user['name'];
                            $email = $user['app_user_email'];
                            $inviterName = $tenantUser->name;
                            
                            // Generate a hash for the user details
                            $data = json_encode(['name' => $userName, 'email' => $email, 'password' => $randomAppPassword, 'inviter' => $inviterName]);
                            $hashedData = base64_encode($data); // Encode data
    
                            // Generate the signup URL
                            $signupUrl = $applicationUrl . "/continune_first_sign_up?token={$hashedData}";

                            $user_name = $user['name'];
                            $email = $user['app_user_email'];
                            $inviterName = $tenantUser->name;
                            $password = $randomAppPassword;
                            $signupUrl = $signupUrl;
                            $moreDetailsUrl = $signupUrl;

                            $emailType = "USER_INVITATION";

                            try {
                                Mail::to($email)->send(new TenantMails($user_name, $inviterName, null, null, $signupUrl,$moreDetailsUrl,null, $emailType));
                            } catch (\Exception $e) {
                                Log::error("Failed to send User Invitation: " . $e->getMessage());
                            }

                            $emailType = "USER_INVITATION_PASSWORD";

                            try {
                                Mail::to($email)->send(new TenantMails($user_name, null, null, $password, null,$moreDetailsUrl,null, $emailType));
                            } catch (\Exception $e) {
                                Log::error("Failed to send User Password Email: " . $e->getMessage());
                            }

                            // Mail::to($email)->send(new InviteUserMail($user_name, $inviterName, $email, $password, $signupUrl, $moreDetailsUrl));
                        } else {
                            // Generate a random password for the user
                            $randomPortalPassword = PasswordHelper::generateSecureTempPassword(12);
                            $randomAppPassword = PasswordHelper::generateSecureTempPassword(12);
                            $user['portal_password'] = $randomPortalPassword;
                            $user['password'] = $randomAppPassword;

                            // Create invited users with persisted data
                            $userData = [
                                'user_name' => $user['name'],
                                'email' => $user['app_user_email'],
                                'name' => $user['name'],
                                'contact_no' => $user['contact_no'] ?? null,
                                'contact_no_code' => $user['contact_no_code'] ?? null,
                                'website' => $user['website'] ?? null,
                                'address' => $user['address'] ?? null,
                                'portal_password' => bcrypt($user['portal_password']),
                                'password' => bcrypt($user['password']), // Use the already generated password
                                'is_owner' => $user['accountPerson'],
                                'is_app_user' => $user['admin'],
                                'tenant_id' => $tenant->id,
                            ];
                            $createdUser = User::create($userData);

                            $applicationUrl = env('APPLICATION_URL');

                            $userName = $user['name'];
                            $email = $user['app_user_email'];
                            $inviterName = $tenantUser->name;
                            
                            // Generate a hash for the user details
                            $data = json_encode(['name' => $userName, 'email' => $email, 'password' => $randomAppPassword, 'inviter' => $inviterName]);
                            $hashedData = base64_encode($data); // Encode data
    
                            // Generate the signup URL
                            $signupUrl = $applicationUrl . "/continune_first_sign_up?token={$hashedData}";
    
                            $user_name = $user['name'];
                            $email = $user['app_user_email'];
                            $inviterName = $tenantUser->name;
                            $password = $randomAppPassword;
                            $signupUrl = $signupUrl;
                            $moreDetailsUrl = $signupUrl;

                            $emailType = "USER_INVITATION";

                            try {
                                Mail::to($email)->send(new TenantMails($user_name, $inviterName, null, null, $signupUrl,$moreDetailsUrl,null, $emailType));
                            } catch (\Exception $e) {
                                Log::error("Failed to send User Invitation: " . $e->getMessage());
                            }

                            $emailType = "USER_INVITATION_PASSWORD";

                            try {
                                Mail::to($email)->send(new TenantMails($user_name, null, null, $password, null,$moreDetailsUrl,null, $emailType));
                            } catch (\Exception $e) {
                                Log::error("Failed to send User Password Email: " . $e->getMessage());
                            }
                        
                            // Mail::to($email)->send(new InviteUserMail($user_name, $inviterName, $email, $password, $signupUrl, $moreDetailsUrl));

                            if ($user['accountPerson']) {
                                $applicationUrl = env('PORTAL_URL');

                                $userName = $user['name'];
                                $email = $user['app_user_email'];
                                $inviterName = $tenantUser->name;
                                
                                // Generate a hash for the user details
                                $data = json_encode(['name' => $userName, 'email' => $email, 'password' => $randomPortalPassword, 'inviter' => $inviterName]);
                                $hashedData = base64_encode($data); // Encode data
        
                                // Generate the signup URL
                                $signupUrl = $applicationUrl . "/continune_first_sign_up?token={$hashedData}";

                                $user_name = $user['name'];
                                $email = $user['app_user_email'];
                                $inviterName = $tenantUser->name;
                                $password = $randomPortalPassword;
                                $signupUrl = $signupUrl;
                                $moreDetailsUrl = $signupUrl;

                                //sending emails

                                $emailType= "PORTAL_USER_INVITATION";
                            
                                try {
                                    Mail::to($email)->send(new PortalMails($user_name, $inviterName, null, null, $signupUrl,$moreDetailsUrl,null, $emailType));
                                } catch (\Exception $e) {
                                    Log::error("Failed to send User Password Email: " . $e->getMessage());
                                }

                                $emailType= "PORTAL_USER_INVITATION_PASSWORD";

                                try {
                                    Mail::to($email)->send(new PortalMails($user_name, null, null, $password, null,$moreDetailsUrl,null, $emailType));
                                } catch (\Exception $e) {
                                    Log::error("Failed to send User Password Email: " . $e->getMessage());
                                }
                            
                                // Mail::to($email)->send(new InvitePotralUserMail($user_name, $inviterName, $email, $password, $signupUrl, $moreDetailsUrl));
                            }
                        }
                    }

                    $systemUserName = 'tenant'.$tenant->id.Str::random(11);
                    $systemUserEmail = $systemUserName.'@gmail.com';
                    $password = Str::random(11);

                    User::create([
                        'user_name' => $systemUserName,
                        'email' => $systemUserEmail,
                        'name' => $systemUserName,
                        'contact_no' => $tenant->contact_no,
                        'contact_no_code' => $tenant->contact_no_code,
                        'website' => $tenant->website,
                        'address' => $tenant->address,
                        'password' => $password,
                        'tenant_id' => $tenant->id,
                        'is_system_user' => true,
                        'system_user_expires_at' => Carbon::now()->addDays(30), 
                    ]);

                    tenant_configuration::create([
                        'system_user_email' => $systemUserEmail,
                        'system_user_password' => $password,
                        'tenant_id' => $tenant->id
                    ]);

                    $selectedTenantId = $tenant->id;

                    app()->singleton('selectedTenantId', function () use ($selectedTenantId) {
                        return $selectedTenantId;
                    });

                    Artisan::call('db:seed', [
                        '--class' => 'TenantIndividualDBSeeder',
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            \Log::error("Error setting up tenant database: " . $e->getMessage());
            throw $e;
        }
        DB::commit(); // Commit transaction if successful
    }

    private static function rollbackChanges($tenantDbName): void
    {
        try {
            DB::statement("DROP DATABASE IF EXISTS $tenantDbName");
        } catch (\Exception $e) {
            throw $e;
        }
    }
}