<?php

namespace App\Jobs;

use App\Models\RegistrationDebug;
use App\Models\User;
use App\Models\tenants;
use App\Models\tenant_configuration;
use App\Helpers\PasswordHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CreateTenantUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $registrationDebugId;
    public $timeout = 120;
    public $tries = 2;

    public function __construct($registrationDebugId)
    {
        $this->registrationDebugId = (int) $registrationDebugId; // Ensure it's serializable
        $this->onQueue('tenant-registration');
    }

    public function handle()
    {
        Log::info("CreateTenantUsersJob started for registration ID: {$this->registrationDebugId}");
        
        $reg = RegistrationDebug::find($this->registrationDebugId);
        if (!$reg) throw new \Exception('Registration record not found');

        $tenant = tenants::find($reg->validated_user['tenant_id']);
        if (!$tenant) throw new \Exception('Tenant not found');

        $tenantUser = User::find($reg->owner_user_id);
        $invitedUsers = $reg->invited_users;

        Log::info("Creating tenant users for registration ID: {$reg->id}, Tenant ID: {$tenant->id}");

        try {
            $userDetails = $this->createTenantUsers($tenant, $tenantUser, $invitedUsers);
            
            Cache::put("tenant_users_{$reg->id}", $userDetails, now()->addHours(2));
            Log::info("Cached user details for registration ID: {$reg->id}. Cache key: tenant_users_{$reg->id}");
            Log::info("User details cached: " . json_encode($userDetails));
            
            $reg->update(['status' => 'users_created']);
            
            Log::info("Tenant users created successfully for registration ID: {$reg->id}. Moving to email job...");
            
        } catch (\Exception $e) {
            Log::error("Failed to create tenant users for registration ID: {$reg->id} - " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }

        Log::info("CreateTenantUsersJob completed successfully for registration ID: {$this->registrationDebugId}");
    }

    private function createTenantUsers($tenant, $tenantUser, $invitedUsers)
    {
        $originalDefaultConnection = Config::get('database.default');
        
        // Check if tenant has separate database (Enterprise package)
        $isEnterpriseWithSeparateDB = ($tenant->package === 'ENTERPRISE' && 
                                     $tenant->db_name !== env('DB_DATABASE'));
        
        if ($isEnterpriseWithSeparateDB) {
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
            Config::set('database.default', 'tenant');
        }

        try {
            $invitedUserDetails = [];

            foreach ($invitedUsers as $user) {
                $randomPassword = PasswordHelper::generateSecureTempPassword(12);
                $user['password'] = $randomPassword;

                // Create user in tenant database if Enterprise with separate DB
                if ($user['admin'] && $isEnterpriseWithSeparateDB) {
                    $userData = [
                        'user_name' => $user['name'],
                        'email' => $user['app_user_email'],
                        'name' => $user['name'],
                        'contact_no' => $user['contact_no'] ?? null,
                        'contact_no_code' => $user['contact_no_code'] ?? null,
                        'website' => $user['website'] ?? null,
                        'address' => $user['address'] ?? null,
                        'password' => bcrypt($randomPassword),
                        'is_owner' => $user['accountPerson'],
                        'is_app_user' => $user['admin'],
                        'tenant_id' => $tenant->id,
                        'designation_id' => 1,
                    ];
                    User::create($userData);
                }

                $portalPassword = null;
                if ($user['accountPerson']) {
                    $portalPassword = PasswordHelper::generateSecureTempPassword(12);
                }

                $invitedUserDetails[] = [
                    'user_name' => $user['name'],
                    'email' => $user['app_user_email'],
                    'name' => $user['name'],
                    'contact_no' => $user['contact_no'] ?? null,
                    'contact_no_code' => $user['contact_no_code'] ?? null,
                    'website' => $user['website'] ?? null,
                    'address' => $user['address'] ?? null,
                    'password' => $randomPassword,
                    'portal_password' => $portalPassword,
                    'is_owner' => $user['accountPerson'],
                    'is_app_user' => $user['admin'],
                    'tenant_id' => $tenant->id,
                ];
            }

            // Create system user if Enterprise with separate DB
            if ($isEnterpriseWithSeparateDB) {
                $systemUserName = 'tenant' . $tenant->id . Str::random(11);
                $systemUserEmail = $systemUserName . '@gmail.com';
                $systemPassword = Str::random(11);

                User::create([
                    'user_name' => $systemUserName,
                    'email' => $systemUserEmail,
                    'name' => $systemUserName,
                    'contact_no' => $tenant->contact_no,
                    'website' => $tenant->website,
                    'address' => $tenant->address,
                    'password' => $systemPassword,
                    'tenant_id' => $tenant->id,
                    'is_system_user' => true,
                    'system_user_expires_at' => Carbon::now()->addDays(30),
                ]);

                app()->singleton('selectedTenantId', fn() => $tenant->id);
                Artisan::call('db:seed', ['--class' => 'TenantModelHasRolesSeeder']);

                // Prepare tenant personal data as object
                $configurationDetails = [
                    'tenant_name' => $tenant->tenant_name,
                    'address' => $tenant->address,
                    'contact_no' => $tenant->contact_no,
                    'contact_no_code' => $tenant->contact_no_code,
                    'email' => $tenant->email,
                    'website' => $tenant->website,
                    'zip_code' => $tenant->zip_code,
                    'city' => $tenant->city,
                    'country' => $tenant->country,
                ];

                // Create tenant_configuration in main database
                tenant_configuration::create([
                    'system_user_email' => $systemUserEmail,
                    'system_user_password' => $systemPassword,
                    'tenant_id' => $tenant->id,
                    'configuration_details' => $configurationDetails
                ]);
                
                // Switch back to main database
                Config::set('database.default', $originalDefaultConnection);

                // Create duplicate system user in main database
                User::create([
                    'user_name' => $systemUserName,
                    'email' => $systemUserEmail,
                    'name' => $systemUserName,
                    'contact_no' => $tenant->contact_no,
                    'contact_no_code' => $tenant->contact_no_code,
                    'website' => $tenant->website,
                    'address' => $tenant->address,
                    'password' => $systemPassword,
                    'tenant_id' => $tenant->id,
                    'is_system_user' => true,
                    'system_user_expires_at' => Carbon::now()->addDays(30),
                ]);
            }

            // Create/update users in main database
            foreach ($invitedUserDetails as &$user) {
                if ($tenantUser->email === $user['email']) {
                    $ownerUser = User::findOrFail($tenantUser->id);
                    $ownerUser->password = bcrypt($user['password']);
                    $ownerUser->is_owner = $user['is_owner'];
                    $ownerUser->is_app_user = $user['is_app_user'];
                    $ownerUser->tenant_id = $tenant->id;
                    $ownerUser->save();
                } else {
                    $randomPortalPassword = $user['portal_password'] ?? PasswordHelper::generateSecureTempPassword(12);
                    
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
                    User::create($userData);
                    
                    $user['portal_password'] = $randomPortalPassword;
                }
            }

            $tenantUser->tenant_id = $tenant->id;
            $tenantUser->save();

            return [
                'invited_users' => $invitedUserDetails,
                'tenant_owner' => $tenantUser->toArray(),
                'system_user' => $isEnterpriseWithSeparateDB ? [
                    'email' => $systemUserEmail,
                    'password' => $systemPassword
                ] : null
            ];

        } catch (\Exception $e) {
            Log::error("Error in createTenantUsers: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        } finally {
            Config::set('database.default', $originalDefaultConnection);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("CreateTenantUsersJob failed permanently for ID: {$this->registrationDebugId} - " . $exception->getMessage());
        Log::error("Stack trace: " . $exception->getTraceAsString());
        
        $reg = RegistrationDebug::find($this->registrationDebugId);
        if ($reg) {
            $reg->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
    }
}
