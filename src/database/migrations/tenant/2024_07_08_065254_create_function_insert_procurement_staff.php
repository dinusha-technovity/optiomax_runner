<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {  
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION insert_procurement_staff(
                IN p_user_id BIGINT,
                IN p_asset_type_id BIGINT, 
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                inserted_data JSON
            ) 
            LANGUAGE plpgsql
            AS $$
            DECLARE
                inserted_row JSON;  -- Captures the entire inserted row as JSON
                error_message TEXT; -- Stores any error messages
                existing_added_user_count INT;
            BEGIN
                -- Validate the tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Tenant ID cannot be NULL or less than or equal to 0'::TEXT AS message, 
                        NULL::JSON AS inserted_data;
                END IF;
        
                -- Check if the user is already assigned to the asset type
                SELECT COUNT(*) INTO existing_added_user_count
                FROM procurement_staff
                WHERE user_id = p_user_id
                AND asset_type_id = p_asset_type_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;
        
                -- If references exist, return failure message
                IF existing_added_user_count > 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'This user is already assigned to this asset type'::TEXT AS message,
                        NULL::JSON AS inserted_data;
                END IF;
            
                -- Attempt to insert into the procurement_staff table
                BEGIN
                    INSERT INTO procurement_staff (
                        user_id, 
                        asset_type_id, 
                        tenant_id, 
                        created_at, 
                        updated_at
                    )
                    VALUES (
                        p_user_id, 
                        p_asset_type_id, 
                        p_tenant_id, 
                        p_current_time, 
                        p_current_time
                    )
                    RETURNING row_to_json(procurement_staff) INTO inserted_row;
            
                    -- Return success message with the inserted row as JSON
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'User successfully assigned to asset type'::TEXT AS message, 
                        inserted_row;
                EXCEPTION
                    WHEN OTHERS THEN
                        error_message := SQLERRM;
                        -- Return error message in case of an exception
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status, 
                            ('Error during insert: ' || error_message)::TEXT AS message, 
                            NULL::JSON AS inserted_data;
                END;
            END;
            $$;
        SQL);    
    
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_procurement_staff');
    }
};