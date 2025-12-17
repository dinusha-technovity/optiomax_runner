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
        DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'insert_asset_item_readings_insert'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

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
                v_is_group BOOLEAN := FALSE;
                v_is_single BOOLEAN := FALSE;
                v_employee_id BIGINT := NULL;
                v_schedule_id BIGINT := NULL;
                v_employee_count INT := 0;
                v_log_success BOOLEAN := FALSE;
                v_error_message TEXT;
                v_reading_id BIGINT;
                v_log_data JSONB;
            BEGIN

                -- Validate critical inputs
                IF _readings IS NULL OR json_array_length(_readings) = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No readings provided for insertion'::TEXT AS message, 
                        NULL::JSON AS inserted_data;
                    RETURN;
                END IF;

                -- Check if there's an employee assignment for this asset at the current time
                -- First, check if there's a schedule in the current time period
                SELECT eas.id INTO v_schedule_id
                FROM employee_asset_scheduling eas
                WHERE eas.asset_id = _asset_item_id
                AND eas.tenant_id = _tenant_id
                AND eas.deleted_at IS NULL
                AND eas.is_active = TRUE
                AND _current_time >= eas.start_datetime
                AND _current_time < eas.end_datetime
                LIMIT 1;

                -- If there's a schedule, check how many employees are assigned to it
                IF v_schedule_id IS NOT NULL THEN
                    SELECT 
                        COUNT(DISTINCT asre.employee_id),
                        MIN(asre.employee_id)
                    INTO 
                        v_employee_count,
                        v_employee_id
                    FROM asset_schedule_related_employees asre
                    WHERE asre.asset_schedule_id = v_schedule_id;

                    -- Determine group/single status based on employee count
                    IF v_employee_count > 1 THEN
                        -- Group assignment: multiple employees
                        v_is_group := TRUE;
                        v_is_single := FALSE;
                        v_employee_id := NULL;  -- No single employee for group
                    ELSIF v_employee_count = 1 THEN
                        -- Individual assignment: single employee
                        v_is_single := TRUE;
                        v_is_group := FALSE;
                        -- v_employee_id is already set by MIN() above
                    END IF;
                ELSE
                    -- No schedule found in current time period
                    v_employee_count := 0;
                    v_is_group := FALSE;
                    v_is_single := FALSE;
                    v_employee_id := NULL;
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
                        is_group,
                        is_single,
                        employee,
                        tenant_id,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        _asset_item_id,
                        item->>'parameterName', 
                        item->>'value', 
                        _record_by_user_id,
                        v_is_group,
                        v_is_single,
                        v_employee_id,
                        _tenant_id,
                        _current_time,
                        _current_time
                    )
                    RETURNING id, row_to_json(asset_items_readings) INTO v_reading_id, inserted_row;

                    -- Prepare log data
                    v_log_data := jsonb_build_object(
                        'reading_id', v_reading_id,
                        'asset_item_id', _asset_item_id,
                        'parameter', item->>'parameterName',
                        'value', item->>'value',
                        'is_group', v_is_group,
                        'is_single', v_is_single,
                        'employee_id', v_employee_id,
                        'action', 'create'
                    );

                    -- Log the activity (with error handling)
                    BEGIN
                        PERFORM log_activity(
                            'asset_item_reading.created',
                            'Asset item reading created for parameter: ' || (item->>'parameterName'),
                            'asset_items_readings',
                            v_reading_id,
                            'user',
                            _record_by_user_id,
                            v_log_data,
                            _tenant_id
                        );
                        v_log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                        v_log_success := FALSE;
                        v_error_message := 'Logging failed: ' || SQLERRM;
                    END;

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