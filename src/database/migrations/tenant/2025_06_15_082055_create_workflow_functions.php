<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('
            DROP FUNCTION IF EXISTS get_workflow_alert_data_relevant_approver(bigint);
            DROP FUNCTION IF EXISTS process_employee_approvers(jsonb);
            DROP FUNCTION IF EXISTS process_designation_approvers(jsonb);
            DROP TABLE IF EXISTS workflow_alert_data_temp;
        ');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION process_employee_approvers(
                p_users_json jsonb
            )
            RETURNS jsonb
            LANGUAGE plpgsql
            AS $$
            DECLARE
                user_ids integer[];
                user_details jsonb[];
                user_details_final jsonb;
                user_detail jsonb;
            BEGIN
                SELECT ARRAY(
                    SELECT (jsonb_array_elements(p_users_json)->>'id')::integer
                ) INTO user_ids;

                SELECT ARRAY(
                    SELECT jsonb_build_object(
                        'id', u.id,
                        'user_name', u.user_name,
                        'email', u.email,
                        'name', u.name,
                        'contact_no', u.contact_no,
                        'profile_image', u.profile_image,
                        'website', u.website,
                        'address', u.address,
                        'employee_code', u.employee_code,
                        'user_description', u.user_description,
                        'designation_id', u.designation_id,
                        'designation', d.designation
                    )
                    FROM users u
                    LEFT JOIN designations d ON u.designation_id = d.id
                    WHERE u.id = ANY(user_ids)
                ) INTO user_details;

                user_details_final := '[]'::jsonb;

                FOR i IN 1..array_length(user_details, 1) LOOP
                    user_detail := user_details[i];
                    IF NOT EXISTS (
                        SELECT 1
                        FROM jsonb_array_elements(user_details_final) AS elem
                        WHERE (elem->>'id')::TEXT = (user_detail->>'id')::TEXT
                    ) THEN
                        user_details_final := user_details_final || jsonb_build_array(user_detail);
                    END IF;
                END LOOP;

                RETURN jsonb_agg(user_details_final)->0;
            END;
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION process_designation_approvers(
                p_designation_json jsonb
            )
            RETURNS jsonb
            LANGUAGE plpgsql
            AS $$
            DECLARE
                designation_ids integer[];
                user_details jsonb[];
                user_details_final jsonb;
                user_detail jsonb;
            BEGIN
                SELECT ARRAY(SELECT (jsonb_array_elements(p_designation_json)->>'id')::integer)
                INTO designation_ids;

                SELECT ARRAY(
                    SELECT jsonb_build_object(
                        'id', u.id,
                        'user_name', u.user_name,
                        'email', u.email,
                        'name', u.name,
                        'contact_no', u.contact_no,
                        'profile_image', u.profile_image,
                        'website', u.website,
                        'address', u.address,
                        'employee_code', u.employee_code,
                        'user_description', u.user_description,
                        'designation_id', u.designation_id,
                        'designation', d.designation
                    )
                    FROM users u
                    LEFT JOIN designations d ON u.designation_id = d.id
                    WHERE u.designation_id = ANY(designation_ids)
                )
                INTO user_details;

                user_details_final := '[]'::jsonb;

                FOR i IN 1..array_length(user_details, 1) LOOP
                    user_detail := user_details[i];
                    IF NOT EXISTS (
                        SELECT 1
                        FROM jsonb_array_elements(user_details_final) AS elem
                        WHERE (elem->>'id')::TEXT = (user_detail->>'id')::TEXT
                    ) THEN
                        user_details_final := user_details_final || jsonb_build_array(user_detail);
                    END IF;
                END LOOP;

                RETURN jsonb_agg(user_details_final)->0;
            END;
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION get_workflow_alert_data_relevant_approver(
                p_user_id bigint
            )
            RETURNS TABLE (
                id bigint,
                previous_user_details jsonb,
                requested_user jsonb,
                workflow_request_type text,
                workflow_request_type_id integer,
                workflow_id bigint,
                requested_id bigint,
                pending_workflow_node_id bigint,
                requested_data_obj jsonb,
                request_status text,
                next_approver_behaviour_type text,
                next_approver_type text,
                next_approver_details jsonb
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                workflow_data_record RECORD;
                eval_result jsonb;
                workflow_data_obj jsonb;
                request_value_json jsonb;
                is_relevant boolean;
            BEGIN
                DROP TABLE IF EXISTS workflow_alert_data_temp;
                CREATE TEMP TABLE workflow_alert_data_temp (
                    id BIGSERIAL PRIMARY KEY,
                    previous_user_details JSONB,
                    requested_user JSONB,
                    workflow_request_type TEXT,
                    workflow_request_type_id INT,
                    workflow_id BIGINT,
                    requested_id BIGINT,
                    pending_workflow_node_id BIGINT,
                    requested_data_obj JSONB,
                    request_status TEXT,
                    next_approver_behaviour_type TEXT,
                    next_approver_type TEXT,
                    next_approver_details JSONB
                );

                FOR workflow_data_record IN
                    SELECT 
                        wrq.id AS workflow_request_id,  
                        wrq.user_id, 
                        wrq.workflow_request_type, 
                        wrq.workflow_id, 
                        wrq.requisition_data_object, 
                        wrq.variable_values AS request_value,
                        wrq.workflow_request_status, 
                        wrq.created_at AS request_created_at, 
                        wrq.updated_at AS request_updated_at,
                        wrqd.id AS workflow_request_detail_id, 
                        wrqd.request_id, 
                        wrqd.workflow_node_id, 
                        wrqd.workflow_level, 
                        wrqd.request_status_from_level, 
                        wrqd.workflow_auth_order, 
                        wrqd.workflow_type, 
                        wrqd.approver_user_id, 
                        wrqd.comment_for_action, 
                        wrqd.created_at AS detail_created_at, 
                        wrqd.updated_at AS detail_updated_at,
                        wd.id AS workflow_detail_id,
                        wd.workflow_detail_parent_id, 
                        wd.workflow_id AS wd_workflow_id, 
                        wd.workflow_detail_type_id, 
                        wd.workflow_detail_behavior_type_id, 
                        wd.workflow_detail_order, 
                        wd.workflow_detail_level, 
                        wd.workflow_detail_data_object, 
                        wd.created_at AS wd_created_at, 
                        wd.updated_at AS wd_updated_at
                    FROM public.workflow_request_queues wrq
                    JOIN public.workflow_request_queue_details wrqd ON wrq.id = wrqd.request_id
                    JOIN public.workflow_details wd ON wrqd.workflow_node_id = wd.id
                    WHERE wrqd.request_status_from_level = 'PENDING'
                    ORDER BY wrq.created_at, wrqd.created_at
                LOOP
                    IF workflow_data_record.workflow_detail_behavior_type_id = 1 THEN
                        IF jsonb_typeof(workflow_data_record.workflow_detail_data_object::jsonb) = 'array' THEN
                            workflow_data_obj := workflow_data_record.workflow_detail_data_object->0;
                        ELSE
                            workflow_data_obj := workflow_data_record.workflow_detail_data_object;
                        END IF;

                        DECLARE
                            asset_request_id bigint;
                            requested_user_id bigint;
                            requested_workflow_id bigint;
                            request_data_obj jsonb;
                            workflow_request_type text;
                            workflow_request_type_id integer;
                            pending_workflow_nodeid bigint;
                            previous_user jsonb;
                            requested_user_data jsonb;
                            next_workflow_node RECORD;
                            next_approver_behaviour_type text := NULL;
                            next_approver_type text := NULL;
                            next_approver_user_obj jsonb := NULL;
                            user_ids integer[];
                            designation_ids integer[];
                            user_designation_id integer;
                            designation_data jsonb;
                        BEGIN
                            -- Check if this workflow is relevant to the p_user_id
                            is_relevant := FALSE;
                            
                            -- Check for employee-based approval
                            IF workflow_data_obj->>'behaviourType' = 'EMPLOYEE' THEN
                                user_ids := ARRAY(SELECT (jsonb_array_elements(workflow_data_obj->'users')->>'id')::integer);
                                
                                IF p_user_id = ANY(user_ids) THEN
                                    is_relevant := TRUE;
                                END IF;
                            
                            -- Check for designation-based approval
                            ELSIF workflow_data_obj->>'behaviourType' = 'DESIGNATION' THEN
                                -- For SINGLE designation type
                                IF workflow_data_obj->>'type' = 'SINGLE' THEN
                                    designation_ids := ARRAY(SELECT (jsonb_array_elements(workflow_data_obj->'designation')->>'id')::integer);
                                    
                                    SELECT u.designation_id 
                                    FROM public.users u
                                    WHERE u.id = p_user_id
                                    INTO user_designation_id;
                                    
                                    IF user_designation_id = ANY(designation_ids) THEN
                                        is_relevant := TRUE;
                                    END IF;
                                
                                -- For POOL designation type
                                ELSIF workflow_data_obj->>'type' = 'POOL' THEN
                                    FOR designation_data IN SELECT * FROM jsonb_array_elements(workflow_data_obj->'designation')
                                    LOOP
                                        IF EXISTS (
                                            SELECT 1 FROM public.users 
                                            WHERE designation_id = (designation_data->>'id')::integer 
                                            AND id = p_user_id
                                        ) THEN
                                            is_relevant := TRUE;
                                            EXIT;
                                        END IF;
                                    END LOOP;
                                END IF;
                            END IF;
                            
                            -- Only process if this workflow is relevant to the user
                            IF is_relevant THEN
                                SELECT wrq.id, wrq.user_id, wrq.workflow_id, wrq.requisition_data_object, wrt.request_type, wrt.id
                                FROM public.workflow_request_queues wrq
                                JOIN public.workflow_request_types wrt ON wrq.workflow_request_type = wrt.id
                                WHERE wrq.id = workflow_data_record.workflow_request_id
                                INTO asset_request_id, requested_user_id, requested_workflow_id, request_data_obj, workflow_request_type, workflow_request_type_id
                                LIMIT 1;

                                request_value_json := workflow_data_record.request_value;

                                SELECT workflow_node_id
                                FROM public.workflow_request_queue_details
                                WHERE request_id = asset_request_id
                                AND request_status_from_level = 'PENDING'
                                INTO pending_workflow_nodeid
                                LIMIT 1;

                                -- Get previous approvers
                                SELECT jsonb_agg(
                                    jsonb_build_object(
                                        'user_name', u.user_name,
                                        'email', u.email,
                                        'name', u.name,
                                        'contact_no', u.contact_no,
                                        'profile_image', u.profile_image,
                                        'website', u.website,
                                        'address', u.address,
                                        'employee_code', u.employee_code,
                                        'user_description', u.user_description,
                                        'designation_id', u.designation_id,
                                        'designation', d.designation,
                                        'comment_for_action', wrqd.comment_for_action,
                                        'approved_at', wrqd.updated_at
                                    )
                                )
                                FROM public.workflow_request_queue_details wrqd
                                JOIN public.workflow_request_queues wrq ON wrqd.request_id = wrq.id
                                JOIN public.users u ON wrqd.approver_user_id = u.id
                                LEFT JOIN public.designations d ON u.designation_id = d.id
                                WHERE wrqd.request_id = asset_request_id
                                AND wrqd.request_status_from_level = 'APPROVED'
                                INTO previous_user;

                                -- Get requester details
                                SELECT json_build_object(
                                    'id', u.id,
                                    'user_name', u.user_name,
                                    'email', u.email,
                                    'name', u.name,
                                    'contact_no', u.contact_no,
                                    'profile_image', u.profile_image,
                                    'website', u.website,
                                    'address', u.address,
                                    'employee_code', u.employee_code,
                                    'user_description', u.user_description,
                                    'designation_id', u.designation_id,
                                    'designation', d.designation
                                ) 
                                FROM public.users u
                                LEFT JOIN public.designations d ON u.designation_id = d.id
                                WHERE u.id = requested_user_id
                                INTO requested_user_data;

                                -- Process next approvers in the workflow
                                FOR next_workflow_node IN
                                    SELECT *
                                    FROM public.workflow_details
                                    WHERE workflow_detail_parent_id = workflow_data_record.workflow_detail_id
                                    ORDER BY workflow_detail_order
                                LOOP
                                    IF next_workflow_node.workflow_detail_type_id = 2 THEN
                                        SELECT evaluate_workflow_condition(
                                            request_value_json,
                                            next_workflow_node.workflow_detail_data_object::jsonb
                                        ) INTO eval_result;

                                        FOR next_workflow_node IN
                                            SELECT *
                                            FROM public.workflow_details
                                            WHERE workflow_detail_parent_id = next_workflow_node.id
                                            ORDER BY workflow_detail_order
                                        LOOP
                                            IF jsonb_typeof(next_workflow_node.workflow_detail_data_object::jsonb) = 'array' THEN
                                                workflow_data_obj := next_workflow_node.workflow_detail_data_object->0;
                                            ELSE
                                                workflow_data_obj := next_workflow_node.workflow_detail_data_object;
                                            END IF;

                                            IF (workflow_data_obj->>'condition')::BOOLEAN = (eval_result->>'result')::BOOLEAN OR
                                            (workflow_data_obj->>'isConditionResult')::BOOLEAN = FALSE THEN

                                                next_approver_behaviour_type := workflow_data_obj->>'behaviourType';
                                                next_approver_type := workflow_data_obj->>'type';

                                                IF workflow_data_obj->>'behaviourType' = 'EMPLOYEE' THEN
                                                    SELECT process_employee_approvers((workflow_data_obj->>'users')::JSONB) 
                                                    INTO next_approver_user_obj;
                                                ELSIF workflow_data_obj->>'behaviourType' = 'DESIGNATION' THEN
                                                    SELECT process_designation_approvers((workflow_data_obj->>'designation')::JSONB) 
                                                    INTO next_approver_user_obj;
                                                END IF;
                                            END IF;
                                        END LOOP;

                                    ELSIF next_workflow_node.workflow_detail_type_id = 1 THEN
                                        IF jsonb_typeof(next_workflow_node.workflow_detail_data_object::jsonb) = 'array' THEN
                                            workflow_data_obj := next_workflow_node.workflow_detail_data_object->0;
                                        ELSE
                                            workflow_data_obj := next_workflow_node.workflow_detail_data_object;
                                        END IF;

                                        next_approver_behaviour_type := workflow_data_obj->>'behaviourType';
                                        next_approver_type := workflow_data_obj->>'type';

                                        IF workflow_data_obj->>'behaviourType' = 'EMPLOYEE' THEN
                                            SELECT process_employee_approvers((workflow_data_obj->>'users')::JSONB) 
                                            INTO next_approver_user_obj;
                                        ELSIF workflow_data_obj->>'behaviourType' = 'DESIGNATION' THEN
                                            SELECT process_designation_approvers((workflow_data_obj->>'designation')::JSONB) 
                                            INTO next_approver_user_obj;
                                        END IF;
                                    END IF;
                                END LOOP;

                                INSERT INTO workflow_alert_data_temp (
                                    requested_user,
                                    workflow_request_type,
                                    workflow_request_type_id,
                                    workflow_id,
                                    requested_id,
                                    pending_workflow_node_id,
                                    previous_user_details,
                                    requested_data_obj,
                                    request_status,
                                    next_approver_behaviour_type,
                                    next_approver_type,
                                    next_approver_details
                                ) VALUES (
                                    requested_user_data::JSONB,
                                    workflow_request_type::TEXT,
                                    workflow_request_type_id::INT,
                                    requested_workflow_id::BIGINT,
                                    asset_request_id::BIGINT,
                                    pending_workflow_nodeid::BIGINT,
                                    previous_user,
                                    request_data_obj::JSONB,
                                    'PENDING',
                                    next_approver_behaviour_type::TEXT,
                                    next_approver_type::TEXT,
                                    next_approver_user_obj::JSONB
                                );
                            END IF;
                        END;
                    END IF;
                END LOOP;

                RETURN QUERY SELECT * FROM workflow_alert_data_temp;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_workflow_alert_data_relevant_approver(bigint)');
        DB::unprepared('DROP FUNCTION IF EXISTS process_employee_approvers(jsonb)');
        DB::unprepared('DROP FUNCTION IF EXISTS process_designation_approvers(jsonb)');
        DB::unprepared('DROP TABLE IF EXISTS workflow_alert_data_temp');
    }
};