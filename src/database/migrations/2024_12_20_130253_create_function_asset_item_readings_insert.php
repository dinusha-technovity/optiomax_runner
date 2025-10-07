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
            CREATE OR REPLACE FUNCTION insert_asset_item_readings_insert( 
                IN _readings JSON,
                IN _asset_item_id BIGINT,
                IN _record_by_user_id BIGINT,
                IN _tenant_id BIGINT,
                IN _current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                inserted_data JSON
            ) 
            LANGUAGE plpgsql
            AS $$
            DECLARE
                item JSON;                 -- Iterates over each item in the input JSON array
                inserted_row JSON;         -- Captures the inserted row as a JSON object
                inserted_data_array JSON[] := '{}'; -- Array to store all inserted rows as JSON objects
            BEGIN

                -- Validate critical inputs
                IF _readings IS NULL OR json_array_length(_readings) = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No readings provided for insertion'::TEXT AS message, 
                        NULL::JSON AS inserted_data;
                    RETURN;
                END IF;

                -- Loop through the items JSON array and insert each item
                FOR item IN SELECT * FROM json_array_elements(_readings)
                LOOP
                    -- Insert item into asset_items_readings table and get the generated row as JSON
                    INSERT INTO asset_items_readings (
                        asset_item, 
                        parameter,
                        value, 
                        record_by,
                        tenant_id,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        _asset_item_id,
                        item->>'parameterName', 
                        item->>'value', 
                        _record_by_user_id,
                        _tenant_id,
                        _current_time,
                        _current_time
                    )
                    RETURNING row_to_json(asset_items_readings) INTO inserted_row;

                    -- Append the JSON row to the array
                    inserted_data_array := array_append(inserted_data_array, inserted_row);
                END LOOP;

                -- Return the concatenated JSON array and success message
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status,
                    'All readings inserted successfully'::TEXT AS message,
                    json_agg(unnested_row) AS inserted_data
                FROM unnest(inserted_data_array) unnested_row;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_asset_item_readings_insert');
    }
};