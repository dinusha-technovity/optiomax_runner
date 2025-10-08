<?php

namespace App\Jobs;

use App\Models\RegistrationDebug;
use App\Models\User;
use App\Models\tenants;
use App\Models\TenantPackage;
use App\Models\TenantSubscription;
use App\Helpers\TenantHelper;
use App\Services\StripePaymentService;
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
 
class CreateTenantDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $registrationDebugId;
    public $timeout = 120;

    public function __construct($registrationDebugId)
    {
        $this->registrationDebugId = (int) $registrationDebugId; // Ensure it's serializable
        $this->onQueue('tenant-registration');
    }

    public function handle()
    {
        Log::info("CreateTenantDatabaseJob started for registration ID: {$this->registrationDebugId}");
        
        $reg = RegistrationDebug::find($this->registrationDebugId);
        if (!$reg) throw new \Exception('Registration record not found');

        $tenantUser = User::find($reg->owner_user_id);
        $validatedUser = $reg->validated_user;
        $packageType = $reg->package_type;
        $selectedPackageId = $reg->selected_package_id;

        Log::info("Creating tenant database for registration ID: {$reg->id}, selected package ID: {$selectedPackageId}");

        try {
            // Get package details using selected_package_id
            $package = DB::table('tenant_packages')
                ->where('id', $selectedPackageId)
                ->where('isactive', true)
                ->whereNull('deleted_at')
                ->first();

            if (!$package) {
                throw new \Exception("Package with ID '{$selectedPackageId}' not found");
            }

            Log::info("Using package: {$package->name} (ID: {$package->id}), Type: {$package->type}");

            if ($packageType == "ENTERPRISE") {
                $dbCredentials = $this->createEnterpriseDatabase($validatedUser);
                DB::beginTransaction();
                $tenant = $this->createEnterpriseTenant($tenantUser, $validatedUser, $dbCredentials, $package);
                $this->setupTenantDatabase($tenant);
                DB::commit();
            } else {
                DB::beginTransaction();
                $tenant = $this->createIndividualTenant($tenantUser, $validatedUser, $package);
                DB::commit();
            }

            // Store both tenant_id and selected_package_id for payment processing
            $validatedUserWithTenant = array_merge($validatedUser, [
                'tenant_id' => $tenant->id,
                'selected_package_id' => $selectedPackageId,
                'package_name' => $package->name,
                'package_type_billing' => $package->type
            ]);
            
            $reg->update([
                'validated_user' => $validatedUserWithTenant,
                'status' => 'database_created'
            ]);
            
            Log::info("Tenant database created successfully for registration ID: {$reg->id}, Tenant ID: {$tenant->id}");
            Log::info("CreateTenantDatabaseJob completed successfully for registration ID: {$this->registrationDebugId}");
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($packageType == "ENTERPRISE" && isset($dbCredentials)) {
                $this->cleanupEnterpriseDatabase($dbCredentials);
            }
            
            Log::error("Failed to create tenant database for registration ID: {$reg->id} - " . $e->getMessage());
            throw $e;
        }
    }

    private function createEnterpriseDatabase($validatedUser)
    {
        $tenantDbHost = env('DB_HOST');
        $tenantDbName = TenantHelper::generateTenantDbName($validatedUser['companyemail']);
        $tenantDbUserName = TenantHelper::generateTenantDbUserName($validatedUser['companyemail']);
        $tenantDbUserPassword = bin2hex(random_bytes(8));

        try {
            // Check if database already exists
            $databaseExists = $this->checkDatabaseExists($tenantDbName);
            $userExists = $this->checkUserExists($tenantDbUserName);
            
            Log::info("Database existence check: DB '{$tenantDbName}' exists: " . ($databaseExists ? 'yes' : 'no') . ", User '{$tenantDbUserName}' exists: " . ($userExists ? 'yes' : 'no'));
            
            if (!$userExists) {
                Log::info("Creating database user: {$tenantDbUserName}");
                DB::statement("CREATE USER \"$tenantDbUserName\" WITH PASSWORD '$tenantDbUserPassword';");
            } else {
                Log::info("Database user already exists, updating password: {$tenantDbUserName}");
                DB::statement("ALTER USER \"$tenantDbUserName\" WITH PASSWORD '$tenantDbUserPassword';");
            }
            
            if (!$databaseExists) {
                Log::info("Creating database: {$tenantDbName}");
                DB::statement("CREATE DATABASE \"$tenantDbName\" OWNER \"$tenantDbUserName\";");
            } else {
                Log::info("Database already exists, ensuring ownership: {$tenantDbName}");
                DB::statement("ALTER DATABASE \"$tenantDbName\" OWNER TO \"$tenantDbUserName\";");
            }
            
            // Always grant privileges regardless of whether database/user existed
            DB::statement("GRANT ALL PRIVILEGES ON DATABASE \"$tenantDbName\" TO \"$tenantDbUserName\";");

            return [
                'db_host' => $tenantDbHost,
                'db_name' => $tenantDbName,
                'db_user' => $tenantDbUserName,
                'db_password' => $tenantDbUserPassword,
                'database_existed' => $databaseExists,
                'user_existed' => $userExists
            ];
            
        } catch (\Exception $e) {
            Log::error("Database creation/check failed: " . $e->getMessage());
            
            // If database creation fails and we created a user, try to clean up
            if (!$userExists && $this->checkUserExists($tenantDbUserName)) {
                try {
                    DB::statement("DROP USER IF EXISTS \"$tenantDbUserName\";");
                    Log::info("Cleaned up user after database creation failure: {$tenantDbUserName}");
                } catch (\Exception $cleanupException) {
                    Log::warning("Failed to cleanup user after database creation failure: " . $cleanupException->getMessage());
                }
            }
            throw $e;
        }
    }

    private function checkDatabaseExists($databaseName): bool
    {
        try {
            $result = DB::select("SELECT 1 FROM pg_database WHERE datname = ?", [$databaseName]);
            return !empty($result);
        } catch (\Exception $e) {
            Log::warning("Failed to check database existence: " . $e->getMessage());
            return false;
        }
    }

    private function checkUserExists($userName): bool
    {
        try {
            $result = DB::select("SELECT 1 FROM pg_roles WHERE rolname = ?", [$userName]);
            return !empty($result);
        } catch (\Exception $e) {
            Log::warning("Failed to check user existence: " . $e->getMessage());
            return false;
        }
    }

    private function createEnterpriseTenant($tenantUser, $validatedUser, $dbCredentials, $package)
    {
        return tenants::create([
            'tenant_name' => $validatedUser['companyname'],
            'address' => $validatedUser['companyaddress'],
            'contact_no' => $validatedUser['companycontact_no'],
            'contact_no_code' => $validatedUser['companycontact_no_code'],
            'zip_code' => $validatedUser['companyzip_code'] ?? null,
            'city' => $validatedUser['companycity'] ?? null,
            'country' => $validatedUser['companycountry'] ?? null,
            'email' => $validatedUser['companyemail'],
            'website' => $validatedUser['companywebsite'],
            'owner_user' => $tenantUser->id,
            'package' => $validatedUser['packageType'],
            'db_host' => $dbCredentials['db_host'],
            'db_name' => $dbCredentials['db_name'],
            'db_user' => $dbCredentials['db_user'],
            'db_password' => $dbCredentials['db_password'],
            'stripe_customer_id' => $validatedUser['customerId'] ?? null,
            'stripe_subscription_id' => null, // Will be set when Stripe subscription is created
            'stripe_payment_method_id' => $validatedUser['paymentMethodId'] ?? null,
            'payment_status' => 'active',
        ]);
    }

    private function createIndividualTenant($tenantUser, $validatedUser, $package)
    {
        return tenants::create([
            'tenant_name' => $validatedUser['companyname'],
            'address' => $validatedUser['companyaddress'],
            'contact_no' => $validatedUser['companycontact_no'],
            'contact_no_code' => $validatedUser['companycontact_no_code'],
            'email' => $validatedUser['companyemail'],
            'zip_code' => $validatedUser['companyzip_code'] ?? null,
            'city' => $validatedUser['companycity'] ?? null,
            'country' => $validatedUser['companycountry'] ?? null,
            'website' => $validatedUser['companywebsite'],
            'owner_user' => $tenantUser->id,
            'package' => $validatedUser['packageType'],
            'db_host' => env('DB_HOST'),
            'db_name' => env('DB_DATABASE'),
            'db_user' => env('DB_USERNAME'),
            'db_password' => env('DB_PASSWORD'),
            'stripe_customer_id' => $validatedUser['customerId'] ?? null,
            'stripe_subscription_id' => null,
            'stripe_payment_method_id' => $validatedUser['paymentMethodId'] ?? null,
            'payment_status' => 'active',
        ]);
    }

    private function setupTenantDatabase($tenant)
    {
        // Configure tenant connection
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

        // Test connection
        try {
            DB::connection('tenant')->getPdo();
            Log::info("Tenant database connection successful for: {$tenant->db_name}");
        } catch (\Exception $e) {
            throw new \Exception("Failed to connect to tenant database: " . $e->getMessage());
        }

        // Ensure storage permissions before running migrations
        $this->ensureStoragePermissions();

        // Check if migrations have already been run
        $migrationTableExists = $this->checkMigrationTableExists();
        
        // Switch to tenant connection for migrations
        $originalDefaultConnection = Config::get('database.default');
        Config::set('database.default', 'tenant');

        try {
            if (!$migrationTableExists) {
                Log::info("Running fresh migrations for tenant database: {$tenant->db_name}");
                Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
                
                // Install Passport with proper error handling
                $this->installPassportSafely($tenant);

                app()->singleton('selectedTenantId', fn() => $tenant->id);
                
                // First run seeders that don't depend on users
                Artisan::call('db:seed', ['--class' => 'TenantDBSeeder']);
                
                Log::info("Fresh tenant database setup completed for: {$tenant->db_name}");
            } else {
                Log::info("Migrations already exist, running any pending migrations for: {$tenant->db_name}");
                Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
                
                // Check if passport keys exist, if not install
                if (!$this->checkPassportKeysExist()) {
                    $this->installPassportSafely($tenant);
                    Log::info("Passport keys installed for existing database: {$tenant->db_name}");
                }
                
                Log::info("Existing tenant database updated for: {$tenant->db_name}");
            }
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to setup tenant database: " . $e->getMessage());
        } finally {
            // Always restore original connection
            Config::set('database.default', $originalDefaultConnection);
        }
    }

    private function ensureStoragePermissions(): void
    {
        try {
            Log::info("Ensuring storage permissions for Passport key creation");
            
            // Create storage directory if it doesn't exist
            $storagePath = storage_path();
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0777, true);
            }
            
            // Ensure storage directory is writable
            if (!is_writable($storagePath)) {
                chmod($storagePath, 0777);
            }
            
            // Check if we can write to storage
            $testFile = $storagePath . '/test_write_permission.tmp';
            if (file_put_contents($testFile, 'test') === false) {
                throw new \Exception("Cannot write to storage directory: {$storagePath}");
            }
            unlink($testFile);
            
            Log::info("Storage permissions verified successfully");
            
        } catch (\Exception $e) {
            Log::warning("Storage permission check failed: " . $e->getMessage());
            // Continue anyway, let Passport installation handle the error
        }
    }

    private function installPassportSafely($tenant): void
    {
        try {
            Log::info("Installing Passport for tenant database: {$tenant->db_name}");
            
            // First, ensure the oauth tables exist by running migrations
            $oauthTablesExist = $this->checkOauthTablesExist();
            
            if (!$oauthTablesExist) {
                Log::info("OAuth tables don't exist, running Passport migrations");
                Artisan::call('passport:install', ['--force' => true]);
            } else {
                Log::info("OAuth tables exist, checking for keys");
                
                // Check if keys already exist in storage
                $publicKeyExists = file_exists(storage_path('oauth-public.key'));
                $privateKeyExists = file_exists(storage_path('oauth-private.key'));
                
                if (!$publicKeyExists || !$privateKeyExists) {
                    Log::info("OAuth keys missing, generating new keys");
                    try {
                        // Try to generate keys manually with better error handling
                        Artisan::call('passport:keys', ['--force' => true]);
                    } catch (\Exception $keyException) {
                        Log::warning("Failed to generate keys with passport:keys, trying passport:install: " . $keyException->getMessage());
                        Artisan::call('passport:install', ['--force' => true]);
                    }
                } else {
                    Log::info("OAuth keys already exist, skipping key generation");
                }
                
                // Ensure clients exist
                $clientsExist = $this->checkPassportClientsExist();
                if (!$clientsExist) {
                    Log::info("Creating Passport clients");
                    Artisan::call('passport:client', [
                        '--personal' => true,
                        '--name' => "Tenant {$tenant->id} Personal Access Client"
                    ]);
                }
            }
            
            Log::info("Passport installation completed successfully for: {$tenant->db_name}");
            
        } catch (\Exception $e) {
            Log::error("Passport installation failed for {$tenant->db_name}: " . $e->getMessage());
            
            // Try alternative approach - create minimal OAuth structure without keys
            try {
                Log::info("Attempting minimal OAuth setup without file-based keys");
                $this->createMinimalOauthStructure();
                Log::info("Minimal OAuth structure created successfully");
            } catch (\Exception $fallbackException) {
                Log::error("Fallback OAuth setup also failed: " . $fallbackException->getMessage());
                // Don't throw here - continue without Passport if necessary
                Log::warning("Continuing without Passport OAuth setup for tenant: {$tenant->db_name}");
            }
        }
    }

    private function checkOauthTablesExist(): bool
    {
        try {
            $result = DB::connection('tenant')->select("
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_name IN ('oauth_clients', 'oauth_access_tokens', 'oauth_refresh_tokens', 'oauth_auth_codes', 'oauth_personal_access_clients')
            ");
            return $result[0]->count >= 5;
        } catch (\Exception $e) {
            Log::info("OAuth tables check failed: " . $e->getMessage());
            return false;
        }
    }

    private function checkPassportClientsExist(): bool
    {
        try {
            $result = DB::connection('tenant')->select("SELECT COUNT(*) as count FROM oauth_clients");
            return $result[0]->count > 0;
        } catch (\Exception $e) {
            Log::info("Passport clients check failed: " . $e->getMessage());
            return false;
        }
    }

    private function createMinimalOauthStructure(): void
    {
        try {
            // Create basic OAuth client records without relying on file-based keys
            DB::connection('tenant')->table('oauth_clients')->insertOrIgnore([
                'id' => 1,
                'name' => 'Laravel Personal Access Client',
                'secret' => hash('sha256', 'tenant_' . time() . '_secret'),
                'provider' => null,
                'redirect' => 'http://localhost',
                'personal_access_client' => true,
                'password_client' => false,
                'revoked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('tenant')->table('oauth_personal_access_clients')->insertOrIgnore([
                'id' => 1,
                'client_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Log::info("Minimal OAuth structure created successfully");
        } catch (\Exception $e) {
            throw new \Exception("Failed to create minimal OAuth structure: " . $e->getMessage());
        }
    }

    private function checkPassportKeysExist(): bool
    {
        try {
            // Check both database clients and file-based keys
            $clientsExist = $this->checkPassportClientsExist();
            $keysExist = file_exists(storage_path('oauth-public.key')) && file_exists(storage_path('oauth-private.key'));
            
            return $clientsExist && $keysExist;
        } catch (\Exception $e) {
            Log::info("Passport keys check failed: " . $e->getMessage());
            return false;
        }
    }

    private function checkMigrationTableExists(): bool
    {
        try {
            $result = DB::connection('tenant')->select("SELECT 1 FROM information_schema.tables WHERE table_name = 'migrations'");
            return !empty($result);
        } catch (\Exception $e) {
            Log::warning("Failed to check migration table existence: " . $e->getMessage());
            return false;
        }
    }

    private function cleanupEnterpriseDatabase($dbCredentials)
    {
        try {
            Log::info("Cleaning up failed database creation: {$dbCredentials['db_name']}");
            DB::statement("DROP DATABASE IF EXISTS \"{$dbCredentials['db_name']}\";");
            DB::statement("DROP USER IF EXISTS \"{$dbCredentials['db_user']}\";");
        } catch (\Exception $e) {
            Log::error("Failed to cleanup database: " . $e->getMessage());
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("CreateTenantDatabaseJob failed permanently for ID: {$this->registrationDebugId} - " . $exception->getMessage());
        Log::error("Stack trace: " . $exception->getTraceAsString());
        
        $reg = RegistrationDebug::find($this->registrationDebugId);
        if ($reg) {
            $reg->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
    }
}