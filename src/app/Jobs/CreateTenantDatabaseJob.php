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
        $this->registrationDebugId = $registrationDebugId;
    }

    public function handle()
    {
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
            // Create database and user outside transaction
            DB::statement("CREATE USER \"$tenantDbUserName\" WITH PASSWORD '$tenantDbUserPassword';");
            DB::statement("CREATE DATABASE \"$tenantDbName\" OWNER \"$tenantDbUserName\";");
            DB::statement("GRANT ALL PRIVILEGES ON DATABASE \"$tenantDbName\" TO \"$tenantDbUserName\";");

            return [
                'db_host' => $tenantDbHost,
                'db_name' => $tenantDbName,
                'db_user' => $tenantDbUserName,
                'db_password' => $tenantDbUserPassword
            ];
        } catch (\Exception $e) {
            // If database creation fails, try to clean up user
            try {
                DB::statement("DROP USER IF EXISTS \"$tenantDbUserName\";");
            } catch (\Exception $cleanupException) {
                Log::warning("Failed to cleanup user after database creation failure: " . $cleanupException->getMessage());
            }
            throw $e;
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

        // Switch to tenant connection for migrations
        $originalDefaultConnection = Config::get('database.default');
        Config::set('database.default', 'tenant');

        try {
            Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
            Artisan::call('passport:install', ['--force' => true]); 

            app()->singleton('selectedTenantId', fn() => $tenant->id);
            
            // First run seeders that don't depend on users
            Artisan::call('db:seed', ['--class' => 'TenantDBSeeder']);
            
            Log::info("Tenant database setup completed for: {$tenant->db_name}");
        } catch (\Exception $e) {
            throw new \Exception("Failed to setup tenant database: " . $e->getMessage());
        } finally {
            // Always restore original connection
            Config::set('database.default', $originalDefaultConnection);
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
        $reg = RegistrationDebug::find($this->registrationDebugId);
        if ($reg) {
            $reg->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
        Log::error("CreateTenantDatabaseJob failed permanently for ID: {$this->registrationDebugId} - " . $exception->getMessage());
    }
}