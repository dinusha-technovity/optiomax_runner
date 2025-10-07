<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS update_asset_details(
                BIGINT,
                BIGINT,
                JSONB,
                TIMESTAMP WITH TIME ZONE
            );

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
                rows_updated INT;
                data_before JSONB;
                data_after JSONB;
                query TEXT;
                key TEXT;
                value TEXT;
                first BOOLEAN := TRUE;
                new_name TEXT;
                existing_asset_count INTEGER;
            BEGIN
                IF p_update_data ? 'name' THEN
                    new_name := p_update_data->>'name';
                    
                    IF new_name IS NULL OR LENGTH(TRIM(new_name)) = 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT, 
                            'Asset name cannot be empty'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB;
                        RETURN;
                    END IF;
                    
                    SELECT COUNT(*) INTO existing_asset_count
                    FROM assets
                    WHERE LOWER(TRIM(name)) = LOWER(TRIM(new_name))
                    AND tenant_id = p_tenant_id
                    AND id != p_asset_id;

                    IF existing_asset_count > 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT, 
                            'Asset group name already exists'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB;
                        RETURN;
                    END IF;
                END IF;

                SELECT row_to_json(a.*) INTO data_before
                FROM assets a
                WHERE id = p_asset_id AND tenant_id = p_tenant_id;
                
                query := 'UPDATE assets SET ';
                
                FOR key, value IN SELECT * FROM jsonb_each_text(p_update_data) LOOP
                    IF NOT first THEN
                        query := query || ', ';
                    ELSE
                        first := FALSE;
                    END IF;
                    query := query || format('%I = %L', key, value);
                END LOOP;
                
                query := query || format(', updated_at = %L WHERE id = %L AND tenant_id = %L', 
                                        p_current_time, p_asset_id, p_tenant_id);
                
                EXECUTE query;
                
                GET DIAGNOSTICS rows_updated = ROW_COUNT;
                
                IF rows_updated > 0 THEN
                    SELECT row_to_json(a.*) INTO data_after
                    FROM assets a
                    WHERE id = p_asset_id AND tenant_id = p_tenant_id;
                    
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT, 
                        'Asset updated successfully'::TEXT,
                        data_before,
                        data_after;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'No rows updated. Asset not found or tenant mismatch.'::TEXT,
                        data_before,
                        NULL::JSONB;
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
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS update_asset_details(
                BIGINT,
                BIGINT,
                JSONB,
                TIMESTAMP WITH TIME ZONE
            );
        SQL);
    }
};
