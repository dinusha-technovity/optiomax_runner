<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
    DROP FUNCTION IF EXISTS submit_asset_requisition(
            IN _user_id BIGINT,
            IN _requisition_status VARCHAR(255),
            IN _tenant_id BIGINT,
            IN _current_time TIMESTAMP WITH TIME ZONE, 
            IN _items JSON,
            IN _requisition_id VARCHAR(255)
        );
        CREATE OR REPLACE FUNCTION submit_asset_requisition(
            IN _user_id BIGINT,
            IN _requisition_status VARCHAR(255),
            IN _tenant_id BIGINT,
            IN _current_time TIMESTAMP WITH TIME ZONE, 
            IN _items JSON,
            IN _requisition_id VARCHAR(255) DEFAULT NULL
        )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                old_requisition JSONB,
                new_requisition JSONB,
                requisition_items JSONB
            )
            LANGUAGE plpgsql
            AS $$
            
            DECLARE
            curr_val INT;
            v_asset_requisition_id BIGINT;
            asset_requisition_register_id TEXT;
            item JSON;
            old_data JSONB;
            new_data JSONB;
            items_data JSONB;
        BEGIN
            -- Initialize requisition register ID
            SELECT nextval('asset_requisition_register_id_seq') INTO curr_val;
            asset_requisition_register_id := 'ASSREQU-' || LPAD(curr_val::TEXT, 4, '0');

            -- Check if asset requisition already exists
            SELECT id, to_jsonb(asset_requisitions.*) INTO v_asset_requisition_id, old_data
            FROM asset_requisitions WHERE requisition_id = _requisition_id;

            IF FOUND THEN
                -- Update existing asset requisition
                UPDATE asset_requisitions 
                SET requisition_status = _requisition_status, updated_at = _current_time
                WHERE requisition_id = _requisition_id
                RETURNING id, to_jsonb(asset_requisitions.*) INTO v_asset_requisition_id, new_data;
            ELSE
                -- Insert new asset requisition
                INSERT INTO asset_requisitions (
                    requisition_id, requisition_by, requisition_date, requisition_status, tenant_id, created_at, updated_at
                )
                VALUES (
                    asset_requisition_register_id, _user_id, _current_time, _requisition_status, _tenant_id, _current_time, _current_time
                )
                RETURNING id, to_jsonb(asset_requisitions.*) INTO v_asset_requisition_id, new_data;
            END IF;

            -- Insert each item
            FOR item IN SELECT * FROM json_array_elements(_items)
            LOOP
                INSERT INTO asset_requisitions_items (
                    asset_requisition_id, 
                    item_name, 
                    quantity, 
                    budget, 
                    budget_currency,
                    business_purpose,
                    acquisition_type,
                    period_status, 
                    period_from, 
                    period_to, 
                    period,
                    availability_type, 
                    priority, 
                    required_date, 
                    organization, 
                    reason,
                    business_impact, 
                    suppliers, 
                    files, 
                    item_details, 
                    expected_conditions,
                    maintenance_kpi, 
                    service_support_kpi, 
                    consumables_kpi,
                    asset_item_id,
                    asset_category,
                    asset_sub_category,
                    description,
                    expected_depreciation_value,
                    tenant_id, 
                    created_at, 
                    updated_at
                )
                VALUES (
                    v_asset_requisition_id, 
                    COALESCE(item->>'item_name', 'Unknown'), -- Ensure item_name is not null
                    
                    NULLIF(item->>'quantity', '')::INTEGER, 
                    NULLIF(item->>'budget', '')::NUMERIC, 
                    NULLIF(item->>'budget_currency', '')::BIGINT,
                    item->>'business_purpose',
                    NULLIF(item->>'asset_acquisition_type', '')::BIGINT,
                    NULLIF(item->>'period_status', '')::BIGINT,
                    NULLIF(item->>'period_from', '')::DATE, 
                    NULLIF(item->>'period_to', '')::DATE, 
                    item->>'period',
                    NULLIF(item->>'availability_type', '')::BIGINT, 
                    NULLIF(item->>'priority', '')::BIGINT,
                    NULLIF(item->>'required_date', '')::DATE, 
                    NULLIF(item->>'organization', '')::BIGINT, 
                    item->>'reason', 
                    item->>'business_impact',
                    NULLIF(item->>'suppliers', '[]')::JSON, 
                    NULLIF(item->>'files', '[]')::JSON, 
                    NULLIF(item->>'item_details', '[]')::JSON,
                    item->>'expected_conditions', 
                    NULLIF(item->>'maintenance_kpi', '[]')::JSON, 
                    NULLIF(item->>'service_support_kpi', '[]')::JSON, 
                    NULLIF(item->>'consumables_kpi', '[]')::JSON,
                    NULLIF(item->>'asset_item_id', '')::BIGINT,
                    (item->>'asset_category')::BIGINT,
                    (item->>'asset_sub_category')::BIGINT,
                    item->>'description',
                    NULLIF(item->>'expected_depreciation_value', '')::INTEGER,
                    _tenant_id, 
                    _current_time, 
                    _current_time
                );
            END LOOP;

            -- Fetch all requisition items related to this requisition
            SELECT jsonb_agg(to_jsonb(ari.*)) INTO items_data
            FROM asset_requisitions_items ari
            WHERE ari.asset_requisition_id = v_asset_requisition_id;

            -- Return final result
            RETURN QUERY SELECT 
                'SUCCESS'::TEXT AS status, 
                CASE WHEN old_data IS NOT NULL THEN 'Asset requisition updated successfully' ELSE 'Asset requisition saved successfully' END AS message, 
                old_data, new_data, items_data;
        END;
    
        $$;
    SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS submit_asset_requisition');
        
    }
};
