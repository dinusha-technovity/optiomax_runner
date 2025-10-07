<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION insert_or_update_procurement(
                IN p_procurement_request_user INT,
                IN p_date DATE,
                IN p_selected_items JSON,
                IN p_selected_suppliers JSON,
                IN p_rpf_document JSON,
                IN p_attachment JSON,
                IN p_required_date DATE,
                IN p_comment VARCHAR(191),
                IN p_procurement_status VARCHAR(191),
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE,
                IN p_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                before_data JSONB,
                after_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                inserted_row JSONB;
                before_update_data JSONB;
                after_update_data JSONB;
                updated_rows INT;
                procurement_id TEXT;
                curr_val BIGINT;
            BEGIN
                -- Check if it's an insert or update
                IF p_id IS NULL OR p_id = 0 THEN
                    -- Generate the procurement ID using a sequence
                    SELECT nextval('procurement_request_id_seq') INTO curr_val;
                    procurement_id := 'PROCU-' || LPAD(curr_val::TEXT, 4, '0');
            
                    -- Insert operation
                    INSERT INTO procurements (
                        request_id, procurement_by, date, selected_items, selected_suppliers, rpf_document,
                        attachment, required_date, comment, procurement_status, tenant_id,
                        created_at, updated_at
                    ) VALUES (
                        procurement_id, p_procurement_request_user, p_date, p_selected_items, p_selected_suppliers, p_rpf_document,
                        p_attachment, p_required_date, p_comment, p_procurement_status, p_tenant_id,
                        p_current_time, p_current_time
                    )
                    RETURNING row_to_json(procurements) INTO inserted_row;
            
                    -- Return success with inserted row data
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Procurement added successfully'::TEXT AS message, 
                        NULL::JSONB AS before_data,
                        inserted_row;
                ELSE
                    -- Fetch data before update
                    SELECT to_jsonb(procurements) INTO before_update_data
                    FROM procurements
                    WHERE id = p_id AND tenant_id = p_tenant_id;
            
                    -- Check if row exists before proceeding with the update
                    IF before_update_data IS NULL THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Procurement not found for the given ID'::TEXT AS message,
                            NULL::JSONB AS before_data,
                            NULL::JSONB AS after_data;
                    END IF;
            
                    -- Perform the update
                    UPDATE procurements
                    SET 
                        procurement_by = p_procurement_request_user,
                        date = p_date,
                        selected_items = p_selected_items,
                        selected_suppliers = p_selected_suppliers,
                        rpf_document = p_rpf_document,
                        attachment = p_attachment,
                        required_date = p_required_date,
                        comment = p_comment,
                        procurement_status = p_procurement_status,
                        updated_at = p_current_time
                    WHERE id = p_id AND tenant_id = p_tenant_id
                    RETURNING row_to_json(procurements) INTO after_update_data;
            
                    -- Capture affected rows
                    GET DIAGNOSTICS updated_rows = ROW_COUNT;
            
                    -- If rows are updated, return success
                    IF updated_rows > 0 THEN
                        RETURN QUERY SELECT 
                            'SUCCESS'::TEXT AS status,
                            'Procurement updated successfully'::TEXT AS message,
                            before_update_data,
                            after_update_data;
                    ELSE
                        -- No rows updated
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'No rows updated. Procurement not found or no changes made.'::TEXT AS message,
                            before_update_data,
                            NULL::JSONB AS after_data;
                    END IF;
                END IF;
            EXCEPTION
                WHEN OTHERS THEN
                    -- Return error message
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        ('Error: ' || SQLERRM)::TEXT AS message,
                        NULL::JSONB AS before_data,
                        NULL::JSONB AS after_data;
            END;
            $$;
        SQL);
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_procurement');
    }
};
