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
            // // SQL statement to create the stored procedure
            // $procedure = <<<SQL
            //     CREATE OR REPLACE PROCEDURE submit_asset_requisition(
            //         IN _requisition_id VARCHAR(255),
            //         IN _user_id BIGINT, 
            //         IN _requisition_date DATE,
            //         IN _requisition_status VARCHAR(255),
            //         IN _tenant_id BIGINT,
            //         IN _current_time TIMESTAMP WITH TIME ZONE,
            //         IN _items JSON 
            //     )
            //     LANGUAGE plpgsql
            //     AS $$
            //     DECLARE
            //         asset_requisition_id INTEGER;
            //         item JSON;
            //     BEGIN
            //         -- Check if the asset requisition already exists
            //         SELECT id INTO asset_requisition_id FROM asset_requisitions WHERE requisition_id = _requisition_id;

            //         IF FOUND THEN
            //             -- If the asset requisition exists, update its status
            //             UPDATE asset_requisitions SET requisition_status = _requisition_status WHERE requisition_id = _requisition_id;
            //         ELSE
            //             -- If the asset requisition doesn\'t exist, create it
            //             INSERT INTO asset_requisitions (requisition_id, requisition_by, requisition_date, requisition_status, tenant_id, created_at, updated_at)
            //             VALUES (_requisition_id, _user_id, _requisition_date, _requisition_status, _tenant_id, _current_time, _current_time)
            //             RETURNING id INTO asset_requisition_id;
                    
            //             -- Loop through the items JSON array and insert each item
            //             FOR item IN SELECT * FROM json_array_elements(_items)
            //             LOOP
            //                 INSERT INTO asset_requisitions_items (
            //                     asset_requisition_id, 
            //                     item_name,
            //                     asset_type, 
            //                     quantity, 
            //                     budget, 
            //                     business_purpose,
            //                     upgrade_or_new, 
            //                     period_status,
            //                     period_from,
            //                     period_to,
            //                     period,
            //                     availability_type,
            //                     priority,
            //                     required_date,
            //                     organization,
            //                     reason,
            //                     business_impact,
            //                     suppliers,
            //                     files,
            //                     item_details,
            //                     expected_conditions,
            //                     maintenance_kpi,
            //                     service_support_kpi,
            //                     consumables_kpi,
            //                     tenant_id,
            //                     created_at,
            //                     updated_at
            //                 )
            //                 VALUES (
            //                     asset_requisition_id, 
            //                     item->>'itemName', 
            //                     (item->>'assetType')::BIGINT,
            //                     (item->>'quantity')::INTEGER, 
            //                     (item->>'budget')::NUMERIC, 
            //                     item->>'businessPurpose',
            //                     item->>'upgradeOrNew',  
            //                     (item->>'periodStatus')::BIGINT,
            //                     (item->>'periodFrom')::DATE, 
            //                     (item->>'periodTo')::DATE, 
            //                     item->>'period',
            //                     (item->>'availabilityType')::BIGINT,
            //                     (item->>'priority')::BIGINT,
            //                     (item->>'requiredDate')::DATE, 
            //                     (item->>'organization')::BIGINT,
            //                     item->>'reason', 
            //                     item->>'businessImpact', 
            //                     (item->>'suppliers')::JSON,
            //                     (item->>'files')::JSON,
            //                     (item->>'itemDetails')::JSON,
            //                     (item->>'expected_conditions')::JSON, 
            //                     (item->>'maintenanceKpi')::JSON, 
            //                     (item->>'serviceSupportKpi')::JSON, 
            //                     (item->>'consumablesKPI')::JSON,
            //                     _tenant_id,
            //                     _current_time,
            //                     _current_time
            //                 );
            //             END LOOP;
            //         END IF;
            //     END;
            //     \$\$;
            //     SQL;
                
            // // Execute the SQL statement
            // DB::unprepared($procedure);

            // // SQL statement to create the stored procedure
            // $procedure = <<<SQL
            //     CREATE OR REPLACE PROCEDURE submit_asset_requisition(
            //         IN _requisition_id VARCHAR(255),
            //         IN _user_id BIGINT,
            //         IN _requisition_status VARCHAR(255),
            //         IN _tenant_id BIGINT,
            //         IN _current_time TIMESTAMP WITH TIME ZONE, 
            //         IN _items JSON 
            //     )
            //     LANGUAGE plpgsql
            //     AS $$
            //     DECLARE
            //         asset_requisition_id INTEGER;
            //         item JSON;
            //         error_message TEXT;
            //     BEGIN
            //         -- Create a temporary response table to store the result
            //         DROP TABLE IF EXISTS response;
            //         CREATE TEMP TABLE response (
            //             status TEXT,
            //             message TEXT,
            //             asset_requisition_id BIGINT DEFAULT 0
            //         );

            //         -- Check if the asset requisition already exists
            //         SELECT id INTO asset_requisition_id FROM asset_requisitions WHERE requisition_id = _requisition_id;

            //         IF FOUND THEN
            //             -- If the asset requisition exists, update its status
            //             UPDATE asset_requisitions SET requisition_status = _requisition_status WHERE requisition_id = _requisition_id
            //             RETURNING id INTO asset_requisition_id;
                        
            //             -- Insert success message into the response table
            //             INSERT INTO response (status, message, asset_requisition_id)
            //             VALUES ('SUCCESS', 'asset requisitions inserted successfully', asset_requisition_id);
            //         ELSE
            //             -- If the asset requisition doesn\'t exist, create it
            //             INSERT INTO asset_requisitions (requisition_id, requisition_by, requisition_date, requisition_status, tenant_id, created_at, updated_at)
            //             VALUES (_requisition_id, _user_id, _current_time, _requisition_status, _tenant_id, _current_time, _current_time)
            //             RETURNING id INTO asset_requisition_id;
                    
            //             -- Loop through the items JSON array and insert each item
            //             FOR item IN SELECT * FROM json_array_elements(_items)
            //             LOOP
            //                 INSERT INTO asset_requisitions_items (
            //                     asset_requisition_id, 
            //                     item_name,
            //                     asset_type, 
            //                     quantity, 
            //                     budget, 
            //                     business_purpose,
            //                     upgrade_or_new, 
            //                     period_status,
            //                     period_from,
            //                     period_to,
            //                     period,
            //                     availability_type,
            //                     priority,
            //                     required_date,
            //                     organization,
            //                     reason,
            //                     business_impact,
            //                     suppliers,
            //                     files,
            //                     item_details,
            //                     expected_conditions,
            //                     maintenance_kpi,
            //                     service_support_kpi,
            //                     consumables_kpi,
            //                     tenant_id,
            //                     created_at,
            //                     updated_at
            //                 )
            //                 VALUES (
            //                     asset_requisition_id, 
            //                     item->>'itemName', 
            //                     (item->>'assetType')::BIGINT,
            //                     (item->>'quantity')::INTEGER, 
            //                     (item->>'budget')::NUMERIC, 
            //                     item->>'businessPurpose',
            //                     item->>'upgradeOrNew',  
            //                     (item->>'periodStatus')::BIGINT,
            //                     (item->>'periodFrom')::DATE, 
            //                     (item->>'periodTo')::DATE, 
            //                     item->>'period',
            //                     (item->>'availabilityType')::BIGINT,
            //                     (item->>'priority')::BIGINT,
            //                     (item->>'requiredDate')::DATE, 
            //                     (item->>'organization')::BIGINT,
            //                     item->>'reason', 
            //                     item->>'businessImpact', 
            //                     (item->>'suppliers')::JSON,
            //                     (item->>'files')::JSON,
            //                     (item->>'itemDetails')::JSON,
            //                     (item->>'expected_conditions')::JSON, 
            //                     (item->>'maintenanceKpi')::JSON, 
            //                     (item->>'serviceSupportKpi')::JSON, 
            //                     (item->>'consumablesKPI')::JSON,
            //                     _tenant_id,
            //                     _current_time,
            //                     _current_time
            //                 );
            //             END LOOP;
            //             -- Insert success message into the response table
            //             INSERT INTO response (status, message, asset_requisition_id)
            //             VALUES ('SUCCESS', 'asset requisitions inserted successfully', asset_requisition_id);
            //             END IF;
            //     END;
            //     \$\$;
            //     SQL;
                
            // // Execute the SQL statement
            // DB::unprepared($procedure);
 
            // // SQL statement to create the stored procedure
            // $procedure = <<<SQL
            //     CREATE OR REPLACE PROCEDURE submit_asset_requisition(
            //         IN _user_id BIGINT,
            //         IN _requisition_status VARCHAR(255),
            //         IN _tenant_id BIGINT,
            //         IN _current_time TIMESTAMP WITH TIME ZONE, 
            //         IN _items JSON,
            //         IN _requisition_id VARCHAR(255) DEFAULT NULL
            //     )
            //     LANGUAGE plpgsql
            //     AS $$
            //     DECLARE
            //         curr_val INT;
            //         asset_requisition_id INTEGER;
            //         asset_requisition_register_id TEXT;
            //         return_reg_no VARCHAR(50); 
            //         item JSON;
            //         error_message TEXT;
            //     BEGIN

            //         DROP TABLE IF EXISTS asset_requisition_add_response_from_store_procedure;
            //         CREATE TEMP TABLE asset_requisition_add_response_from_store_procedure (
            //             status TEXT,
            //             message TEXT,
            //             asset_requisition_id BIGINT DEFAULT 0,
            //             asset_requisition_reg_no VARCHAR(50)
            //         );

            //         SELECT nextval('asset_requisition_register_id_seq') INTO curr_val;
            //         asset_requisition_register_id := 'ASSREQU-' || LPAD(curr_val::TEXT, 4, '0');

            //         -- Check if the asset requisition already exists
            //         SELECT id INTO asset_requisition_id FROM asset_requisitions WHERE requisition_id = _requisition_id;

            //         IF FOUND THEN
            //             -- If the asset requisition exists, update its status
            //             UPDATE asset_requisitions SET requisition_status = _requisition_status WHERE requisition_id = _requisition_id
            //             RETURNING id, asset_requisitions.requisition_id INTO asset_requisition_id, return_reg_no;
                        
            //             -- Insert success message into the response table
            //             INSERT INTO asset_requisition_add_response_from_store_procedure (status, message, asset_requisition_id, asset_requisition_reg_no)
            //             VALUES ('SUCCESS', 'asset requisitions update successfully', asset_requisition_id, return_reg_no);
            //         ELSE
            //             -- If the asset requisition doesn\'t exist, create it
            //             INSERT INTO asset_requisitions (requisition_id, requisition_by, requisition_date, requisition_status, tenant_id, created_at, updated_at)
            //             VALUES (asset_requisition_register_id, _user_id, _current_time, _requisition_status, _tenant_id, _current_time, _current_time)
            //             RETURNING id, asset_requisitions.requisition_id INTO asset_requisition_id, return_reg_no;
                    
            //             -- Loop through the items JSON array and insert each item
            //             FOR item IN SELECT * FROM json_array_elements(_items)
            //             LOOP
            //                 INSERT INTO asset_requisitions_items (
            //                     asset_requisition_id, 
            //                     item_name,
            //                     asset_type, 
            //                     quantity, 
            //                     budget, 
            //                     business_purpose,
            //                     upgrade_or_new, 
            //                     period_status,
            //                     period_from,
            //                     period_to,
            //                     period,
            //                     availability_type,
            //                     priority,
            //                     required_date,
            //                     organization,
            //                     reason,
            //                     business_impact,
            //                     suppliers,
            //                     files,
            //                     item_details,
            //                     expected_conditions,
            //                     maintenance_kpi,
            //                     service_support_kpi,
            //                     consumables_kpi,
            //                     tenant_id,
            //                     created_at,
            //                     updated_at
            //                 )
            //                 VALUES (
            //                     asset_requisition_id, 
            //                     item->>'itemName', 
            //                     (item->>'assetType')::BIGINT,
            //                     (item->>'quantity')::INTEGER, 
            //                     (item->>'budget')::NUMERIC, 
            //                     item->>'businessPurpose',
            //                     item->>'upgradeOrNew',  
            //                     (item->>'periodStatus')::BIGINT,
            //                     (item->>'periodFrom')::DATE, 
            //                     (item->>'periodTo')::DATE, 
            //                     item->>'period',
            //                     (item->>'availabilityType')::BIGINT,
            //                     (item->>'priority')::BIGINT,
            //                     (item->>'requiredDate')::DATE, 
            //                     (item->>'organization')::BIGINT,
            //                     item->>'reason', 
            //                     item->>'businessImpact', 
            //                     (item->>'suppliers')::JSON,
            //                     (item->>'files')::JSON,
            //                     (item->>'itemDetails')::JSON,
            //                     (item->>'expected_conditions'), 
            //                     (item->>'maintenanceKpi')::JSON, 
            //                     (item->>'serviceSupportKpi')::JSON, 
            //                     (item->>'consumablesKPI')::JSON,
            //                     _tenant_id,
            //                     _current_time,
            //                     _current_time
            //                 );
            //             END LOOP;
            //             -- Insert success message into the response table
            //             INSERT INTO asset_requisition_add_response_from_store_procedure (status, message, asset_requisition_id, asset_requisition_reg_no)
            //             VALUES ('SUCCESS', 'asset requisitions inserted successfully', asset_requisition_id, return_reg_no);
            //             END IF;
            //     END;
            //     \$\$;
            //     SQL;
                
            // // Execute the SQL statement
            // DB::unprepared($procedure);

        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION submit_asset_requisition(
        //         IN _user_id BIGINT,
        //         IN _requisition_status VARCHAR(255),
        //         IN _tenant_id BIGINT,
        //         IN _current_time TIMESTAMP WITH TIME ZONE, 
        //         IN _items JSON,
        //         IN _requisition_id VARCHAR(255) DEFAULT NULL
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         asset_requisition_id BIGINT,
        //         asset_requisition_reg_no VARCHAR(50)
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         curr_val INT;
        //         asset_requisition_id BIGINT; -- Changed to BIGINT to match the function's return type
        //         asset_requisition_register_id TEXT;
        //         return_reg_no VARCHAR(50); 
        //         item JSON;
        //     BEGIN
        //         -- Initialize the requisition register ID
        //         SELECT nextval('asset_requisition_register_id_seq') INTO curr_val;
        //         asset_requisition_register_id := 'ASSREQU-' || LPAD(curr_val::TEXT, 4, '0');

        //         -- Check if the asset requisition already exists
        //         SELECT id INTO asset_requisition_id FROM asset_requisitions WHERE requisition_id = _requisition_id;

        //         IF FOUND THEN
        //             -- Update existing asset requisition
        //             UPDATE asset_requisitions 
        //             SET requisition_status = _requisition_status 
        //             WHERE requisition_id = _requisition_id
        //             RETURNING id, asset_requisitions.requisition_id INTO asset_requisition_id, return_reg_no;

        //             -- Return success for update
        //             RETURN QUERY SELECT 
        //                 'SUCCESS'::TEXT AS status, 
        //                 'Asset requisitions submitted successfully'::TEXT AS message, 
        //                 asset_requisition_id, 
        //                 return_reg_no;
        //         ELSE
        //             -- Insert new asset requisition
        //             INSERT INTO asset_requisitions (
        //                 requisition_id, 
        //                 requisition_by, 
        //                 requisition_date, 
        //                 requisition_status, 
        //                 tenant_id, 
        //                 created_at, 
        //                 updated_at
        //             )
        //             VALUES (
        //                 asset_requisition_register_id, 
        //                 _user_id, 
        //                 _current_time, 
        //                 _requisition_status, 
        //                 _tenant_id, 
        //                 _current_time, 
        //                 _current_time
        //             )
        //             RETURNING id, asset_requisitions.requisition_id INTO asset_requisition_id, return_reg_no;

        //             -- Insert each item from the JSON array
        //             FOR item IN SELECT * FROM json_array_elements(_items)
        //             LOOP
        //                 INSERT INTO asset_requisitions_items (
        //                     asset_requisition_id, 
        //                     item_name,
        //                     asset_type, 
        //                     quantity, 
        //                     budget, 
        //                     business_purpose,
        //                     upgrade_or_new, 
        //                     period_status,
        //                     period_from,
        //                     period_to,
        //                     period,
        //                     availability_type,
        //                     priority,
        //                     required_date,
        //                     organization,
        //                     reason,
        //                     business_impact,
        //                     suppliers,
        //                     files,
        //                     item_details,
        //                     expected_conditions,
        //                     maintenance_kpi,
        //                     service_support_kpi,
        //                     consumables_kpi,
        //                     tenant_id,
        //                     created_at,
        //                     updated_at
        //                 )
        //                 VALUES (
        //                     asset_requisition_id, 
        //                     item->>'itemName', 
        //                     (item->>'assetType')::BIGINT,
        //                     (item->>'quantity')::INTEGER, 
        //                     (item->>'budget')::NUMERIC, 
        //                     item->>'businessPurpose',
        //                     item->>'upgradeOrNew',  
        //                     (item->>'periodStatus')::BIGINT,
        //                     (item->>'periodFrom')::DATE, 
        //                     (item->>'periodTo')::DATE, 
        //                     item->>'period',
        //                     (item->>'availabilityType')::BIGINT,
        //                     (item->>'priority')::BIGINT,
        //                     (item->>'requiredDate')::DATE, 
        //                     (item->>'organization')::BIGINT,
        //                     item->>'reason', 
        //                     item->>'businessImpact', 
        //                     (item->>'suppliers')::JSON,
        //                     (item->>'files')::JSON,
        //                     (item->>'itemDetails')::JSON,
        //                     (item->>'expected_conditions'), 
        //                     (item->>'maintenanceKpi')::JSON, 
        //                     (item->>'serviceSupportKpi')::JSON, 
        //                     (item->>'consumablesKPI')::JSON,
        //                     _tenant_id,
        //                     _current_time,
        //                     _current_time
        //                 );
        //             END LOOP;

        //             -- Return success for insertion
        //             RETURN QUERY SELECT 
        //                 'SUCCESS'::TEXT AS status, 
        //                 'Asset requisitions saved successfully'::TEXT AS message, 
        //                 asset_requisition_id, 
        //                 return_reg_no;
        //         END IF;
        //     END;
        //     $$;
        // SQL);

        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION submit_asset_requisition(
        //         IN _user_id BIGINT,
        //         IN _requisition_status VARCHAR(255),
        //         IN _tenant_id BIGINT,
        //         IN _current_time TIMESTAMP WITH TIME ZONE, 
        //         IN _items JSON,
        //         IN _requisition_id VARCHAR(255) DEFAULT NULL
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         old_requisition JSONB,
        //         new_requisition JSONB,
        //         requisition_items JSONB
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         curr_val INT;
        //         asset_requisition_id BIGINT;
        //         asset_requisition_register_id TEXT;
        //         return_reg_no VARCHAR(50); 
        //         item JSON;
        //         old_data JSONB;
        //         new_data JSONB;
        //         items_data JSONB;
        //     BEGIN
        //         -- Initialize requisition register ID
        //         SELECT nextval('asset_requisition_register_id_seq') INTO curr_val;
        //         asset_requisition_register_id := 'ASSREQU-' || LPAD(curr_val::TEXT, 4, '0');

        //         -- Check if asset requisition already exists
        //         SELECT id, to_jsonb(asset_requisitions.*) INTO asset_requisition_id, old_data
        //         FROM asset_requisitions WHERE requisition_id = _requisition_id;

        //         IF FOUND THEN
        //             -- Update existing asset requisition
        //             UPDATE asset_requisitions 
        //             SET requisition_status = _requisition_status, updated_at = _current_time
        //             WHERE requisition_id = _requisition_id
        //             RETURNING id, to_jsonb(asset_requisitions.*) INTO asset_requisition_id, new_data;
        //         ELSE
        //             -- Insert new asset requisition
        //             INSERT INTO asset_requisitions (
        //                 requisition_id, requisition_by, requisition_date, requisition_status, tenant_id, created_at, updated_at
        //             )
        //             VALUES (
        //                 asset_requisition_register_id, _user_id, _current_time, _requisition_status, _tenant_id, _current_time, _current_time
        //             )
        //             RETURNING id, to_jsonb(asset_requisitions.*) INTO asset_requisition_id, new_data;
        //         END IF;

        //         -- Insert each item and collect inserted rows
        //         items_data := '[]'::JSONB;
        //         FOR item IN SELECT * FROM json_array_elements(_items)
        //         LOOP
        //             INSERT INTO asset_requisitions_items (
        //                 asset_requisition_id, item_name, asset_type, quantity, budget, business_purpose,
        //                 upgrade_or_new, period_status, period_from, period_to, period,
        //                 availability_type, priority, required_date, organization, reason,
        //                 business_impact, suppliers, files, item_details, expected_conditions,
        //                 maintenance_kpi, service_support_kpi, consumables_kpi, tenant_id, created_at, updated_at
        //             )
        //             VALUES (
        //                 asset_requisition_id, item->>'itemName', (item->>'assetType')::BIGINT, (item->>'quantity')::INTEGER, 
        //                 (item->>'budget')::NUMERIC, item->>'businessPurpose', item->>'upgradeOrNew', (item->>'periodStatus')::BIGINT,
        //                 (item->>'periodFrom')::DATE, (item->>'periodTo')::DATE, item->>'period',
        //                 (item->>'availabilityType')::BIGINT, (item->>'priority')::BIGINT,
        //                 (item->>'requiredDate')::DATE, (item->>'organization')::BIGINT, item->>'reason', 
        //                 item->>'businessImpact', (item->>'suppliers')::JSON, (item->>'files')::JSON, (item->>'itemDetails')::JSON,
        //                 item->>'expected_conditions', (item->>'maintenanceKpi')::JSON, 
        //                 (item->>'serviceSupportKpi')::JSON, (item->>'consumablesKPI')::JSON,
        //                 _tenant_id, _current_time, _current_time
        //             ) RETURNING to_jsonb(asset_requisitions_items.*) INTO items_data;
        //         END LOOP;

        //         -- Return success with JSONB data
        //         RETURN QUERY SELECT 
        //             'SUCCESS'::TEXT AS status, 
        //             CASE WHEN FOUND THEN 'Asset requisition updated successfully' ELSE 'Asset requisition saved successfully' END AS message, 
        //             old_data, new_data, items_data;
        //     END;
        //     $$;
        // SQL);

        DB::unprepared(<<<SQL
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
                        asset_requisition_id, item_name, asset_type, quantity, budget, business_purpose,
                        upgrade_or_new, period_status, period_from, period_to, period,
                        availability_type, priority, required_date, organization, reason,
                        business_impact, suppliers, files, item_details, expected_conditions,
                        maintenance_kpi, service_support_kpi, consumables_kpi, tenant_id, created_at, updated_at
                    )
                    VALUES (
                        v_asset_requisition_id, item->>'itemName', (item->>'assetType')::BIGINT, (item->>'quantity')::INTEGER, 
                        (item->>'budget')::NUMERIC, item->>'businessPurpose', item->>'upgradeOrNew', (item->>'periodStatus')::BIGINT,
                        (item->>'periodFrom')::DATE, (item->>'periodTo')::DATE, item->>'period',
                        (item->>'availabilityType')::BIGINT, (item->>'priority')::BIGINT,
                        (item->>'requiredDate')::DATE, (item->>'organization')::BIGINT, item->>'reason', 
                        item->>'businessImpact', (item->>'suppliers')::JSON, (item->>'files')::JSON, (item->>'itemDetails')::JSON,
                        item->>'expected_conditions', (item->>'maintenanceKpi')::JSON, 
                        (item->>'serviceSupportKpi')::JSON, (item->>'consumablesKPI')::JSON,
                        _tenant_id, _current_time, _current_time
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