<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS get_all_resources_count(
                    IN p_tenant_id BIGINT
                );
            CREATE OR REPLACE FUNCTION get_all_resources_count(
                    IN p_tenant_id BIGINT DEFAULT NULL
                )
                RETURNS TABLE(
                    status TEXT,
                    message TEXT,
                    suppliers BIGINT,
                    categories BIGINT,
                    subcategories BIGINT,
                    workflows BIGINT,
                    organizations BIGINT,
                    item_master BIGINT,
                    procurement_staff BIGINT,
                    user_roles BIGINT,
                    user_accounts BIGINT,
                    asset_groups BIGINT,
                    asset_master BIGINT,
                    maintenance_team BIGINT,
                    asset_availability_term_types BIGINT,
                    customers BIGINT
                )
                LANGUAGE plpgsql
                AS $$
                DECLARE
                    v_suppliers BIGINT;
                    v_categories BIGINT;
                    v_subcategories BIGINT;
                    v_workflows BIGINT;
                    v_organizations BIGINT;
                    v_item_master BIGINT;
                    v_procurement_staff BIGINT;
                    v_user_roles BIGINT;
                    v_user_accounts BIGINT;
                    v_asset_groups BIGINT;
                    v_asset_master BIGINT;
                    v_maintenance_team BIGINT;
                    v_asset_availability_term_types BIGINT;
                    v_customers BIGINT;
                BEGIN
                    IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Invalid tenant ID provided'::TEXT AS message,
                            NULL::BIGINT AS suppliers,
                            NULL::BIGINT AS categories,
                            NULL::BIGINT AS subcategories,
                            NULL::BIGINT AS workflows,
                            NULL::BIGINT AS organizations,
                            NULL::BIGINT AS item_master,
                            NULL::BIGINT AS procurement_staff,
                            NULL::BIGINT AS user_roles,
                            NULL::BIGINT AS user_accounts,
                            NULL::BIGINT AS asset_groups,
                            NULL::BIGINT AS asset_master,
                            NULL::BIGINT AS maintenance_team,
                            NULL::BIGINT AS asset_availability_term_types,
                            NULL::BIGINT AS customers;
                        RETURN;
                    END IF;

                    -- Get counts for each table where isactive=true and deleted_at is null
                    SELECT COUNT(*) INTO v_suppliers
                    FROM suppliers
                    WHERE isactive = true
                    AND deleted_at IS NULL
                    AND supplier_reg_status = 'APPROVED'
                    AND tenant_id = p_tenant_id;
                
                    SELECT COUNT(*) INTO v_categories 
                    FROM asset_categories 
                    WHERE isactive = true AND deleted_at IS NULL AND tenant_id = p_tenant_id;
                    
                    SELECT COUNT(*) INTO v_subcategories 
                    FROM asset_sub_categories 
                    WHERE isactive = true AND deleted_at IS NULL AND tenant_id = p_tenant_id;
                    
                    SELECT COUNT(*) INTO v_workflows 
                    FROM workflows 
                    WHERE deleted_at IS NULL AND tenant_id = p_tenant_id;
                    
                    SELECT COUNT(*) INTO v_organizations 
                    FROM organization 
                    WHERE isactive = true AND deleted_at IS NULL AND tenant_id = p_tenant_id;
                    
                    SELECT COUNT(*) INTO v_item_master 
                    FROM items 
                    WHERE isactive = true AND deleted_at IS NULL AND tenant_id = p_tenant_id;

                    SELECT COUNT(DISTINCT ps.user_id)
                    INTO v_procurement_staff
                    FROM procurement_staff ps
                    WHERE ps.isactive = true
                    AND ps.deleted_at IS NULL
                    AND ps.tenant_id = p_tenant_id;

                    SELECT COUNT(*) INTO v_user_roles 
                    FROM roles 
                    WHERE deleted_at IS NULL AND tenant_id = p_tenant_id;

                    SELECT COUNT(*) INTO v_user_accounts 
                    FROM users 
                    WHERE is_system_user = false AND deleted_at IS NULL AND tenant_id = p_tenant_id;

                    SELECT COUNT(*) INTO v_asset_groups 
                    FROM assets 
                    WHERE isactive = true AND deleted_at IS NULL AND tenant_id = p_tenant_id;

                    SELECT COUNT(*) INTO v_asset_master 
                    FROM asset_items 
                    WHERE isactive = true AND deleted_at IS NULL AND tenant_id = p_tenant_id;

                    SELECT COUNT(*) INTO v_maintenance_team
                    FROM maintenance_teams
                    WHERE isactive = true AND deleted_at IS NULL AND tenant_id = p_tenant_id;

                    SELECT COUNT(*) INTO v_asset_availability_term_types
                    FROM asset_availability_term_types
                    WHERE isactive = true AND deleted_at IS NULL AND tenant_id = p_tenant_id;

                    SELECT COUNT(*) INTO v_customers
                    FROM customers
                    WHERE is_active = true AND deleted_at IS NULL AND tenant_id = p_tenant_id;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'Data retrieved successfully'::TEXT AS message,
                        v_suppliers AS suppliers,
                        v_categories AS categories,
                        v_subcategories AS subcategories,
                        v_workflows AS workflows,
                        v_organizations AS organizations,
                        v_item_master AS item_master,
                        v_procurement_staff AS procurement_staff,
                        v_user_roles AS user_roles,
                        v_user_accounts AS user_accounts,
                        v_asset_groups AS asset_groups,
                        v_asset_master AS asset_master,
                        v_maintenance_team AS maintenance_team,
                        v_asset_availability_term_types AS asset_availability_term_types,
                        v_customers AS customers;
                END;
                $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_resources_count');
    }
};