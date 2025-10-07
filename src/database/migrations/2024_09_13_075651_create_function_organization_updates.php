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
        // DB::unprepared(
        //     'CREATE OR REPLACE PROCEDURE store_procedure_organization_update(
        //         IN p_organization_id BIGINT,
        //         IN p_parent_node_id BIGINT, 
        //         IN p_level BIGINT, 
        //         IN p_relationship VARCHAR(255),
        //         IN p_data JSONB,
        //         IN p_current_time TIMESTAMP WITH TIME ZONE
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         UPDATE organization
        //         SET 
        //             parent_node_id = p_parent_node_id,
        //             level = p_level,
        //             relationship = p_relationship,
        //             data = p_data,
        //             updated_at = p_current_time
        //         WHERE id = p_organization_id;
        //     END; 
        //     $$;
        // ');
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION update_organization_details(
                p_organization_id BIGINT,
                p_parent_node_id BIGINT,
                p_level BIGINT,
                p_relationship VARCHAR(255),
                p_data JSONB,
                p_contact_no_code BIGINT,
                p_current_time TIMESTAMP WITH TIME ZONE
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
                rows_updated INT; -- Variable to capture affected rows
                data_before JSONB; -- Variable to store data before the update
                data_after JSONB;  -- Variable to store data after the update
            BEGIN
                -- Fetch data before the update
                SELECT jsonb_build_object(
                    'id', id,
                    'parent_node_id', parent_node_id,
                    'level', level,
                    'relationship', relationship,
                    'data', data,
                    'contact_no_code', contact_no_code,
                    'updated_at', updated_at
                ) INTO data_before
                FROM organization
                WHERE id = p_organization_id;
        
                -- Update the organization table
                UPDATE organization
                SET 
                    parent_node_id = p_parent_node_id,
                    level = p_level,
                    relationship = p_relationship,
                    data = p_data,
                    contact_no_code = p_contact_no_code,
                    updated_at = p_current_time
                WHERE id = p_organization_id;
        
                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;
        
                -- Fetch data after the update if rows were updated
                IF rows_updated > 0 THEN
                    SELECT jsonb_build_object(
                        'id', id,
                        'parent_node_id', parent_node_id,
                        'level', level,
                        'relationship', relationship,
                        'data', data,
                        'contact_no_code', contact_no_code,
                        'updated_at', updated_at
                    ) INTO data_after
                    FROM organization
                    WHERE id = p_organization_id;
        
                    -- Return success with before and after data
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Organization details updated successfully'::TEXT AS message,
                        data_before,
                        data_after;
                ELSE
                    -- Return failure message with before data and null after data
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Organization not found.'::TEXT AS message,
                        data_before,
                        NULL::JSONB AS after_data;
                END IF;
            END;
            $$;
        SQL);
    
    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS update_organization_details');
    }
};