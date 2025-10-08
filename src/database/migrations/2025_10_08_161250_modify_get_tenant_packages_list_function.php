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
                IN p_billing_cycle TEXT DEFAULT NULL,
                IN p_region TEXT DEFAULT NULL,
                IN p_include_addons BOOLEAN DEFAULT TRUE,
                IN p_include_discounts BOOLEAN DEFAULT TRUE
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                slug TEXT,
                type TEXT, -- For backward compatibility (maps to billing_type)
                billing_type TEXT,
                price DECIMAL(10,2), -- Maps to base_price_monthly or base_price_yearly
                discount_price DECIMAL(10,2), -- Calculated discount price
                base_price_monthly DECIMAL(10,2),
                base_price_yearly DECIMAL(10,2),
                description TEXT,
                terms_conditions TEXT,
                charge_immediately_on_signup BOOLEAN,
                -- Legacy fields for backward compatibility
                credits INT,
                workflows INT,
                users INT,
                max_storage_gb INT,
                support BOOLEAN,
                -- New fields
                base_limits JSON,
                allowed_package_types JSON,
                compliance_requirements JSON,
                tax_codes JSON,
                is_recurring BOOLEAN,
                trial_days INT,
                trial_requires_payment_method BOOLEAN,
                setup_fee DECIMAL(10,2),
                cancellation_policy TEXT,
                stripe_price_id_monthly TEXT,
                stripe_price_id_yearly TEXT,
                stripe_product_id TEXT,
                is_popular BOOLEAN,
                is_legacy BOOLEAN,
                sort_order INT,
                legal_version TEXT,
                available_addons JSON,
                applicable_discounts JSON,
                -- Additional fields for payment retry
                max_retry_attempts INT,
                retry_interval_days INT,
                grace_period_days INT
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
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, 
                        NULL::DECIMAL(10,2), NULL::DECIMAL(10,2), NULL::DECIMAL(10,2), NULL::DECIMAL(10,2),
                        NULL::TEXT, NULL::TEXT, NULL::BOOLEAN,
                        NULL::INT, NULL::INT, NULL::INT, NULL::INT, NULL::BOOLEAN,
                        NULL::JSON, NULL::JSON, NULL::JSON, NULL::JSON,
                        NULL::BOOLEAN, NULL::INT, NULL::BOOLEAN, NULL::DECIMAL(10,2), NULL::TEXT,
                        NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::BOOLEAN, NULL::BOOLEAN, 
                        NULL::INT, NULL::TEXT, NULL::JSON, NULL::JSON,
                        NULL::INT, NULL::INT, NULL::INT;
                    RETURN;
                END IF;

                -- Return matching records with enhanced fields and backward compatibility
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'tenant packages fetched successfully'::TEXT AS message,
                    tp.id,
                    tp.name::TEXT,
                    tp.slug::TEXT,
                    CASE -- Map billing_type to legacy 'type' field
                        WHEN p_billing_cycle = 'yearly' THEN 'year'
                        WHEN p_billing_cycle = 'monthly' THEN 'month'
                        WHEN tp.billing_type = 'yearly' THEN 'year'
                        WHEN tp.billing_type = 'monthly' THEN 'month'
                        ELSE 'month'
                    END::TEXT AS type,
                    tp.billing_type::TEXT,
                    CASE -- Map to appropriate price based on billing cycle
                        WHEN p_billing_cycle = 'yearly' OR tp.billing_type = 'yearly' THEN tp.base_price_yearly
                        ELSE tp.base_price_monthly
                    END AS price,
                    NULL::DECIMAL(10,2) AS discount_price, -- Will be calculated by application
                    tp.base_price_monthly,
                    tp.base_price_yearly,
                    tp.description::TEXT,
                    tp.terms_conditions::TEXT,
                    tp.charge_immediately_on_signup,
                    -- Extract legacy fields from base_limits JSON for backward compatibility
                    COALESCE((tp.base_limits->>'credits')::INT, 0) AS credits,
                    COALESCE((tp.base_limits->>'workflows')::INT, 0) AS workflows,
                    COALESCE((tp.base_limits->>'users')::INT, 1) AS users,
                    COALESCE((tp.base_limits->>'storage_gb')::INT, 0) AS max_storage_gb,
                    COALESCE((tp.base_limits->>'support')::BOOLEAN, false) AS support,
                    tp.base_limits::JSON,
                    tp.allowed_package_types::JSON,
                    tp.compliance_requirements::JSON,
                    tp.tax_codes::JSON,
                    tp.is_recurring,
                    tp.trial_days,
                    tp.trial_requires_payment_method,
                    tp.setup_fee,
                    tp.cancellation_policy::TEXT,
                    tp.stripe_price_id_monthly::TEXT,
                    tp.stripe_price_id_yearly::TEXT,
                    tp.stripe_product_id::TEXT,
                    tp.is_popular,
                    tp.is_legacy,
                    tp.sort_order,
                    tp.legal_version::TEXT,
                    CASE 
                        WHEN p_include_addons THEN
                            COALESCE((
                                SELECT json_agg(
                                    json_build_object(
                                        'id', pa.id,
                                        'name', pa.name,
                                        'slug', pa.slug,
                                        'description', pa.description,
                                        'type', pa.addon_type, -- Map addon_type to type for compatibility
                                        'addon_type', pa.addon_type,
                                        'target_feature', pa.target_feature,
                                        'price_monthly', pa.price_monthly,
                                        'price_yearly', pa.price_yearly,
                                        'quantity', COALESCE((pa.boost_values->>'quantity')::INT, 1), -- Legacy quantity field
                                        'boost_values', pa.boost_values,
                                        'is_stackable', pa.is_stackable,
                                        'max_quantity', pa.max_quantity,
                                        'requires_approval', pa.requires_approval,
                                        'is_metered', pa.is_metered,
                                        'metered_rate', pa.metered_rate,
                                        'is_featured', pa.is_featured,
                                        'applicable_packages', pa.applicable_package_slugs, -- Legacy field name
                                        'sort_order', pa.sort_order
                                    )
                                )
                                FROM package_addons pa 
                                WHERE pa.isactive = TRUE
                                AND (pa.applicable_package_slugs IS NULL OR pa.applicable_package_slugs::jsonb ? tp.slug)
                                AND (pa.excluded_package_slugs IS NULL OR NOT pa.excluded_package_slugs::jsonb ? tp.slug)
                                AND (pa.applicable_package_types IS NULL OR EXISTS (
                                    SELECT 1 FROM jsonb_array_elements_text(pa.applicable_package_types::jsonb) apt
                                    WHERE tp.allowed_package_types::jsonb ? apt
                                ))
                                AND (pa.regional_restrictions IS NULL OR p_region IS NULL OR pa.regional_restrictions::jsonb ? p_region)
                                AND (pa.available_from IS NULL OR pa.available_from <= NOW())
                                AND (pa.available_until IS NULL OR pa.available_until >= NOW())
                            ), '[]'::json)
                        ELSE '[]'::json
                    END::JSON AS available_addons,
                    CASE 
                        WHEN p_include_discounts THEN
                            COALESCE((
                                SELECT json_agg(
                                    json_build_object(
                                        'id', pd.id,
                                        'name', pd.name,
                                        'code', pd.code,
                                        'description', pd.description,
                                        'type', pd.type,
                                        'value', pd.value,
                                        'applicable_packages', pd.applicable_package_slugs, -- Legacy field name
                                        'applicable_package_types', pd.applicable_package_types,
                                        'billing_cycles', pd.applicable_billing_cycles, -- Legacy field name
                                        'applicable_billing_cycles', pd.applicable_billing_cycles,
                                        'apply_to_addons', pd.apply_to_addons,
                                        'apply_to_setup_fees', pd.apply_to_setup_fees,
                                        'first_payment_only', pd.first_payment_only,
                                        'is_first_time_only', pd.is_first_time_only,
                                        'minimum_amount', pd.minimum_amount,
                                        'maximum_discount_amount', pd.maximum_discount_amount,
                                        'usage_limit_per_customer', pd.usage_limit_per_customer,
                                        'is_stackable', pd.is_stackable,
                                        'is_public', pd.is_public,
                                        'valid_until', pd.valid_until
                                    )
                                )
                                FROM package_discounts pd 
                                WHERE pd.isactive = TRUE
                                AND pd.approval_status = 'approved'
                                AND (pd.applicable_package_slugs IS NULL OR pd.applicable_package_slugs::jsonb ? tp.slug)
                                AND (pd.excluded_package_slugs IS NULL OR NOT pd.excluded_package_slugs::jsonb ? tp.slug)
                                AND (pd.applicable_package_types IS NULL OR EXISTS (
                                    SELECT 1 FROM jsonb_array_elements_text(pd.applicable_package_types::jsonb) apt
                                    WHERE tp.allowed_package_types::jsonb ? apt
                                ))
                                AND (pd.applicable_regions IS NULL OR p_region IS NULL OR pd.applicable_regions::jsonb ? p_region)
                                AND (pd.valid_from IS NULL OR pd.valid_from <= NOW())
                                AND (pd.valid_until IS NULL OR pd.valid_until >= NOW())
                                AND (p_billing_cycle IS NULL OR pd.applicable_billing_cycles = 'both' 
                                     OR pd.applicable_billing_cycles = p_billing_cycle 
                                     OR (tp.billing_type = 'both' AND pd.applicable_billing_cycles IN ('monthly', 'yearly')))
                            ), '[]'::json)
                        ELSE '[]'::json
                    END::JSON AS applicable_discounts,
                    tp.max_retry_attempts,
                    tp.retry_interval_days,
                    tp.grace_period_days
                FROM tenant_packages tp
                WHERE (p_tenant_packages_id IS NULL OR tp.id = p_tenant_packages_id)
                AND (p_billing_cycle IS NULL OR tp.billing_type = p_billing_cycle OR tp.billing_type = 'both')
                AND (p_package_type IS NULL OR tp.allowed_package_types IS NULL OR tp.allowed_package_types::jsonb ? p_package_type)
                AND (p_region IS NULL OR tp.allowed_regions IS NULL OR tp.allowed_regions::jsonb ? p_region)
                AND tp.deleted_at IS NULL
                AND tp.isactive = TRUE
                AND tp.is_legacy = FALSE
                ORDER BY tp.sort_order, tp.name;

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