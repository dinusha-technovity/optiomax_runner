<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Disposal Authorization Users - Manages authorized users for asset disposal operations
     * Implements ISO 55001:2014 standards for asset management disposal controls
     */
    public function up(): void
    {
        Schema::create('disposal_authorization_users', function (Blueprint $table) {
            $table->id();
            
            // Registration & User Association
            $table->string('registration_number', 50)->unique(); // e.g., "DAU-2024-001", globally unique
            $table->unsignedBigInteger('user_id'); // FK to users table (required)
            
            // Authorization Details
            $table->enum('authorization_level', [
                'level-1',      // Basic disposal approval (low value assets)
                'level-2',      // Intermediate approval (medium value assets)
                'level-3',      // Senior approval (high value assets)
                'level-4',      // Executive approval (critical/high-risk assets)
                'unlimited'     // Unrestricted authorization
            ])->default('level-1');
            
            // Authorization Period (ISO 55001 Section 6.2.2 - Authorization validity)
            $table->date('authorized_from'); // Authorization start date (required)
            $table->date('authorized_until')->nullable(); // Authorization end date (null = indefinite)
            
            // Disposal Financial Limits (ISO 55001 Asset Value Controls)
            $table->decimal('max_single_disposal_value', 15, 2)->nullable()
                  ->comment('Maximum value for single asset disposal authorization');
            $table->decimal('max_monthly_disposal_value', 15, 2)->nullable()
                  ->comment('Maximum cumulative monthly disposal value');
            
            // Authorization Scope & Restrictions
            $table->jsonb('authorized_asset_categories')->nullable()
                  ->comment('Array of asset category IDs this user can authorize disposals for');
            $table->jsonb('authorized_disposal_methods')->nullable()
                  ->comment('Array of allowed disposal methods (sale, scrap, donation, etc.)');
            $table->jsonb('location_restrictions')->nullable()
                  ->comment('Array of location IDs where authorization is valid');
            
            // Certification & Training (ISO 55001 Section 7.2 - Competence)
            $table->string('certification_number', 100)->nullable()
                  ->comment('Professional certification or training certificate number');
            $table->date('certification_date')->nullable();
            $table->date('certification_expiry')->nullable();
            $table->text('training_records')->nullable()
                  ->comment('Training completion records for disposal procedures');
            
            // Authorization Status Management
            $table->enum('status', [
                'pending',      // Authorization request submitted
                'active',       // Currently authorized and valid
                'suspended',    // Temporarily suspended
                'expired',      // Authorization period ended
                'revoked',      // Authorization revoked/cancelled
                'inactive'      // Not currently active
            ])->default('pending');
            
            // Authorization Approval & Workflow
            $table->unsignedBigInteger('approved_by')->nullable()
                  ->comment('User ID who approved this authorization');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            
            $table->unsignedBigInteger('revoked_by')->nullable()
                  ->comment('User ID who revoked this authorization');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            
            // Compliance & Documentation
            $table->text('authorization_scope_description')->nullable()
                  ->comment('Detailed description of authorization scope and limitations');
            $table->text('special_conditions')->nullable()
                  ->comment('Any special conditions or restrictions on this authorization');
            $table->jsonb('compliance_requirements')->nullable()
                  ->comment('Specific compliance requirements for this authorizer');
            
            // Usage Statistics (for monitoring and compliance)
            $table->integer('total_disposals_authorized')->default(0)
                  ->comment('Total number of disposals authorized by this user');
            $table->decimal('total_value_authorized', 15, 2)->default(0)
                  ->comment('Total cumulative value of disposals authorized');
            $table->timestamp('last_authorization_at')->nullable()
                  ->comment('Date and time of last disposal authorization');
            
            // Contact & Emergency Information
            $table->string('emergency_contact_name', 200)->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('backup_authorizer_id')->nullable()
                  ->comment('User ID of backup authorizer when this user is unavailable');
            
            // Audit Trail
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Multi-tenant & Soft Delete (Required)
            $table->unsignedBigInteger('tenant_id'); // Required
            $table->timestamp('deleted_at')->nullable(); // Required: Soft delete
            $table->boolean('isactive')->default(true); // Required: Active flag
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('user_id', 'fk_disposal_auth_users_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict'); // Cannot delete user if they have disposal authorizations
            
            $table->foreign('approved_by', 'fk_disposal_auth_users_approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            $table->foreign('revoked_by', 'fk_disposal_auth_users_revoked_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            $table->foreign('created_by', 'fk_disposal_auth_users_created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            $table->foreign('updated_by', 'fk_disposal_auth_users_updated_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Unique Constraints
            $table->unique(['user_id', 'tenant_id', 'deleted_at'], 'uq_disposal_auth_users_user_tenant');
        });

        // -----------------------------------------------------------------------
        // Performance Indexes - Created with IF NOT EXISTS for safe re-execution
        // -----------------------------------------------------------------------
        DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_tenant_active 
                       ON disposal_authorization_users (tenant_id, isactive, deleted_at)');
        
        DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_user_status 
                       ON disposal_authorization_users (user_id, status, deleted_at)');
        
        DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_status 
                       ON disposal_authorization_users (tenant_id, status, deleted_at)');
        
        DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_auth_level 
                       ON disposal_authorization_users (tenant_id, authorization_level, status)');
        
        DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_registration 
                       ON disposal_authorization_users (registration_number)');
        
        DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_auth_period 
                       ON disposal_authorization_users (authorized_from, authorized_until, status)');
        
        DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_cert_expiry 
                       ON disposal_authorization_users (certification_expiry, status) 
                       WHERE certification_expiry IS NOT NULL');
        
        DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_approved_by 
                       ON disposal_authorization_users (approved_by, approved_at)');
        
        DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_deleted_active 
                       ON disposal_authorization_users (deleted_at, isactive)');
        
        // JSONB GIN indexes for efficient querying of array fields (PostgreSQL specific)
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_asset_categories 
                           ON disposal_authorization_users USING gin (authorized_asset_categories)');
            
            DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_disposal_methods 
                           ON disposal_authorization_users USING gin (authorized_disposal_methods)');
            
            DB::statement('CREATE INDEX IF NOT EXISTS idx_disposal_auth_users_locations 
                           ON disposal_authorization_users USING gin (location_restrictions)');
        }
        
        // PostgreSQL-specific: Check constraints and comments
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Date validation: authorized_until must be >= authorized_from
            DB::statement('
                ALTER TABLE disposal_authorization_users 
                ADD CONSTRAINT chk_disposal_auth_users_date_range 
                CHECK (
                    authorized_until IS NULL OR 
                    authorized_until >= authorized_from
                )
            ');
            
            // Certification expiry must be after certification date
            DB::statement('
                ALTER TABLE disposal_authorization_users 
                ADD CONSTRAINT chk_disposal_auth_users_cert_dates 
                CHECK (
                    certification_date IS NULL OR 
                    certification_expiry IS NULL OR 
                    certification_expiry >= certification_date
                )
            ');
            
            // Financial limits must be positive
            DB::statement('
                ALTER TABLE disposal_authorization_users 
                ADD CONSTRAINT chk_disposal_auth_users_financial_limits 
                CHECK (
                    (max_single_disposal_value IS NULL OR max_single_disposal_value > 0) AND
                    (max_monthly_disposal_value IS NULL OR max_monthly_disposal_value > 0)
                )
            ');
            
            // Usage statistics must be non-negative
            DB::statement('
                ALTER TABLE disposal_authorization_users 
                ADD CONSTRAINT chk_disposal_auth_users_usage_stats 
                CHECK (
                    total_disposals_authorized >= 0 AND 
                    total_value_authorized >= 0
                )
            ');
            
            // Status-specific field requirements
            DB::statement('
                ALTER TABLE disposal_authorization_users 
                ADD CONSTRAINT chk_disposal_auth_users_approval_data 
                CHECK (
                    (status != \'active\' OR 
                     (approved_by IS NOT NULL AND approved_at IS NOT NULL))
                )
            ');
            
            DB::statement('
                ALTER TABLE disposal_authorization_users 
                ADD CONSTRAINT chk_disposal_auth_users_revocation_data 
                CHECK (
                    (status != \'revoked\' OR 
                     (revoked_by IS NOT NULL AND revoked_at IS NOT NULL))
                )
            ');
            
            // Table and column documentation
            DB::statement("
                COMMENT ON TABLE disposal_authorization_users IS 
                'ISO 55001:2014 compliant disposal authorization management - tracks authorized users for asset disposal operations with financial limits and competence requirements'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.registration_number IS 
                'Globally unique disposal authorization registration identifier (e.g., DAU-2024-001)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.user_id IS 
                'Foreign key reference to users table - the authorized user'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.authorization_level IS 
                'Hierarchical authorization level determining asset value and risk categories user can approve'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.authorized_from IS 
                'Authorization effective start date (ISO 8601 format)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.authorized_until IS 
                'Authorization expiry date (NULL indicates indefinite authorization)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.max_single_disposal_value IS 
                'ISO 55001 financial control - maximum single asset disposal value this user can authorize'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.max_monthly_disposal_value IS 
                'ISO 55001 financial control - maximum cumulative monthly disposal value'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.authorized_asset_categories IS 
                'JSONB array of asset category IDs this authorization covers'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.authorized_disposal_methods IS 
                'JSONB array of permitted disposal methods (sale, scrap, donation, recycling, transfer, destruction)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.location_restrictions IS 
                'JSONB array of location IDs where this authorization is valid (NULL = all locations)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.certification_number IS 
                'ISO 55001 Section 7.2 - Professional certification or training certificate reference'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.status IS 
                'Authorization lifecycle status: pending → active → expired/revoked/suspended'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.total_disposals_authorized IS 
                'Running count of disposal transactions authorized by this user'
            ");
            
            DB::statement("
                COMMENT ON COLUMN disposal_authorization_users.total_value_authorized IS 
                'Cumulative total value of all disposals authorized by this user'
            ");
        }
        
        // Create sequence for auto-generating registration numbers
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('
                CREATE SEQUENCE IF NOT EXISTS disposal_auth_registration_seq
                START WITH 1
                INCREMENT BY 1
                NO MINVALUE
                NO MAXVALUE
                CACHE 1
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop sequence first
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS disposal_auth_registration_seq CASCADE');
        }
        
        Schema::dropIfExists('disposal_authorization_users');
    }
};
