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
            CREATE OR REPLACE FUNCTION update_asset_details(
                p_asset_id BIGINT,
                p_tenant_id BIGINT,
                p_update_data JSONB,
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
                rows_updated INT;       -- Capture affected rows
                data_before JSONB;      -- Store data before the update
                data_after JSONB;       -- Store data after the update
                query TEXT;             -- Query string to construct dynamic update
                key TEXT;
                value TEXT;
                first BOOLEAN := TRUE;
            BEGIN
                -- Fetch data before the update
                SELECT row_to_json(a.*) INTO data_before
                FROM assets a
                WHERE id = p_asset_id AND tenant_id = p_tenant_id;
                
                -- Start constructing the update query
                query := 'UPDATE assets SET ';
                
                -- Loop through JSONB object keys and dynamically build SET clause
                FOR key, value IN SELECT * FROM jsonb_each_text(p_update_data) LOOP
                    IF NOT first THEN
                        query := query || ', ';
                    ELSE
                        first := FALSE;
                    END IF;
                    query := query || format('%I = %L', key, value);
                END LOOP;
                
                -- Append updated_at field and WHERE condition
                query := query || format(', updated_at = %L WHERE id = %L AND tenant_id = %L', 
                                        p_current_time, p_asset_id, p_tenant_id);
                
                -- Execute the dynamic SQL
                EXECUTE query;
                
                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;
                
                -- Fetch data after the update if rows were updated
                IF rows_updated > 0 THEN
                    SELECT row_to_json(a.*) INTO data_after
                    FROM assets a
                    WHERE id = p_asset_id AND tenant_id = p_tenant_id;
                    
                    -- Return success with before and after data
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Asset updated successfully'::TEXT AS message,
                        data_before,
                        data_after;
                ELSE
                    -- Return failure message with before data and null after data
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Asset not found or tenant mismatch.'::TEXT AS message,
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
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_reading_parameters');
    }
};
