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
                    procurement_staff BIGINT
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
                            NULL::BIGINT AS procurement_staff;

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

                    SELECT COUNT(*) INTO v_procurement_staff 
                    FROM procurement_staff 
                    WHERE isactive = true AND deleted_at IS NULL AND tenant_id = p_tenant_id;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'Data retrieved successfully'::TEXT AS message,
                        v_suppliers AS suppliers,
                        v_categories AS categories,
                        v_subcategories AS subcategories,
                        v_workflows AS workflows,
                        v_organizations AS organizations,
                        v_item_master AS item_master,
                        v_procurement_staff AS procurement_staff;
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