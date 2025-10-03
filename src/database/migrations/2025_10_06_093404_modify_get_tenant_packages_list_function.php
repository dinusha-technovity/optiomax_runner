<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_tenant_packages_list'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_tenant_packages_list(
                IN p_tenant_packages_id INT DEFAULT NULL,
                IN p_package_type TEXT DEFAULT NULL,
                IN p_billing_cycle TEXT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                type TEXT,
                price DECIMAL(10,2),
                discount_price DECIMAL(10,2),
                description TEXT,
                credits INT,
                workflows INT,
                users INT,
                max_storage_gb INT,
                support BOOLEAN,
                allowed_package_types JSON,
                features JSON,
                is_recurring BOOLEAN,
                trial_days INT,
                setup_fee DECIMAL(10,2),
                max_retry_attempts INT,
                retry_interval_days INT,
                grace_period_days INT,
                stripe_price_id_monthly TEXT,
                stripe_price_id_yearly TEXT,
                stripe_product_id TEXT,
                is_popular BOOLEAN,
                sort_order INT
            )
            LANGUAGE plpgsql
            AS \$\$
            BEGIN
                -- Validate package ID if provided
                IF p_tenant_packages_id IS NOT NULL AND p_tenant_packages_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant package ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS type,
                        NULL::DECIMAL(10,2) AS price,
                        NULL::DECIMAL(10,2) AS discount_price,
                        NULL::TEXT AS description,
                        NULL::INT AS credits,
                        NULL::INT AS workflows,
                        NULL::INT AS users,
                        NULL::INT AS max_storage_gb,
                        NULL::BOOLEAN AS support,
                        NULL::JSON AS allowed_package_types,
                        NULL::JSON AS features,
                        NULL::BOOLEAN AS is_recurring,
                        NULL::INT AS trial_days,
                        NULL::DECIMAL(10,2) AS setup_fee,
                        NULL::INT AS max_retry_attempts,
                        NULL::INT AS retry_interval_days,
                        NULL::INT AS grace_period_days,
                        NULL::TEXT AS stripe_price_id_monthly,
                        NULL::TEXT AS stripe_price_id_yearly,
                        NULL::TEXT AS stripe_product_id,
                        NULL::BOOLEAN AS is_popular,
                        NULL::INT AS sort_order;
                    RETURN;
                END IF;

                -- Return matching records with enhanced fields
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'tenant packages fetched successfully'::TEXT AS message,
                    tp.id,
                    tp.name::TEXT,
                    tp.type::TEXT,
                    tp.price,
                    tp.discount_price,
                    tp.description::TEXT,
                    tp.credits,
                    tp.workflows,
                    tp.users,
                    tp.max_storage_gb,
                    tp.support,
                    tp.allowed_package_types::JSON,
                    tp.features::JSON,
                    tp.is_recurring,
                    tp.trial_days,
                    tp.setup_fee,
                    tp.max_retry_attempts,
                    tp.retry_interval_days,
                    tp.grace_period_days,
                    tp.stripe_price_id_monthly::TEXT,
                    tp.stripe_price_id_yearly::TEXT,
                    tp.stripe_product_id::TEXT,
                    tp.is_popular,
                    tp.sort_order
                FROM tenant_packages tp
                WHERE (p_tenant_packages_id IS NULL OR tp.id = p_tenant_packages_id)
                AND (p_billing_cycle IS NULL OR tp.type = p_billing_cycle)
                AND (p_package_type IS NULL OR tp.allowed_package_types IS NULL OR tp.allowed_package_types::jsonb ? p_package_type)
                AND tp.deleted_at IS NULL
                AND tp.isactive = TRUE
                ORDER BY tp.sort_order, tp.name, tp.type;

            END;
            \$\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_tenant_packages_list');
    }
};