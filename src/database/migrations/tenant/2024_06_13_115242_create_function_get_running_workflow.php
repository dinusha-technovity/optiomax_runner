<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */ 
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE TYPE workflow_request_approver_type AS (
                status TEXT,
                id BIGINT,
                parent_id BIGINT,
                running_parent_id BIGINT,
                request_node_status TEXT,
                behaviourtype TEXT,
                type TEXT,
                data JSONB
            );

            CREATE OR REPLACE FUNCTION get_workflow_request_running_workflow(
                p_workflow_id INT, 
                p_value JSONB
            )
            RETURNS TABLE (
                status TEXT,
                id BIGINT,
                parent_id BIGINT,
                running_parent_id BIGINT,
                request_node_status TEXT,
                behaviourtype TEXT,
                type TEXT,
                data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                record RECORD;
                condition JSONB;
                combined_condition_result BOOLEAN := FALSE;
                first_detail_processed BOOLEAN := FALSE;
                v_designation_id BIGINT;
                designation_name TEXT;
                user_details_according_to_designations JSONB;
                final_object JSONB;
                eval_result JSONB;
                response_data JSONB := '[]'::JSONB;
                extracted_behaviourType TEXT;
            BEGIN
                FOR record IN
                    SELECT wd.id, wd.workflow_detail_parent_id, wd.workflow_detail_running_parent_id, wd.workflow_detail_type_id, wd.workflow_detail_behavior_type_id,
                        wd.workflow_detail_order, wd.workflow_detail_level, wd.workflow_detail_data_object::JSONB,
                        wd.created_at, wd.updated_at, wt.id AS workflow_type_id, wt.workflow_type
                    FROM public.workflow_details wd
                    JOIN public.workflow_types wt ON wd.workflow_detail_type_id = wt.id
                    WHERE wd.workflow_id = p_workflow_id
                    ORDER BY wd.workflow_detail_order
                LOOP
                    IF record.workflow_type_id = 1 THEN
                        IF jsonb_typeof(record.workflow_detail_data_object) = 'array' THEN
                            condition := record.workflow_detail_data_object->0;
                        ELSE
                            condition := record.workflow_detail_data_object;
                        END IF;

                        extracted_behaviourType := condition->>'behaviourType';

                        IF extracted_behaviourType = 'EMPLOYEE' THEN
                            IF (condition->>'condition')::BOOLEAN = combined_condition_result THEN
                                final_object := jsonb_build_object(
                                    'type', condition->>'type',
                                    'designation', designation_name,
                                    'users', (condition->>'users')::jsonb,
                                    'condition', condition->>'condition',
                                    'behaviourType', condition->>'behaviourType',
                                    'isConditionResult', condition->>'isConditionResult'
                                );
                                response_data := response_data || jsonb_build_object(
                                    'status', 'success',
                                    'id', record.id::BIGINT,
                                    'parent_id', record.workflow_detail_parent_id::BIGINT,
                                    'running_parent_id', record.workflow_detail_running_parent_id::BIGINT,
                                    'request_node_status', 'PENDING',
                                    'behaviourtype', extracted_behaviourType,
                                    'type', record.workflow_type,
                                    'data', final_object
                                );
                                CONTINUE;
                            END IF;
                        END IF;

                        IF extracted_behaviourType = 'DESIGNATION' THEN
                            IF condition->>'type' = 'SINGLE' THEN
                                IF (condition->>'condition')::BOOLEAN = combined_condition_result THEN
                                    v_designation_id := (jsonb_array_elements((condition->>'designation')::jsonb)->>'id')::BIGINT;
                                    designation_name := (jsonb_array_elements((condition->>'designation')::jsonb)->>'name')::TEXT;

                                    SELECT jsonb_agg(jsonb_build_object(
                                        'id', u.id,
                                        'name', u.name,
                                        'profile_image', u.profile_image
                                    )) INTO user_details_according_to_designations
                                    FROM public.users u
                                    INNER JOIN public.designations d ON u.designation_id = d.id
                                    WHERE d.id = v_designation_id;

                                    final_object := jsonb_build_object(
                                        'type', condition->>'type',
                                        'designation', designation_name,
                                        'users', user_details_according_to_designations,
                                        'condition', condition->>'condition',
                                        'behaviourType', condition->>'behaviourType',
                                        'isConditionResult', condition->>'isConditionResult'
                                    );

                                    response_data := response_data || jsonb_build_object(
                                        'status', 'success',
                                        'id', record.id::BIGINT,
                                        'parent_id', record.workflow_detail_parent_id::BIGINT,
                                        'running_parent_id', record.workflow_detail_running_parent_id::BIGINT,
                                        'request_node_status', 'PENDING',
                                        'behaviourtype', extracted_behaviourType,
                                        'type', record.workflow_type,
                                        'data', final_object
                                    );
                                    CONTINUE;
                                END IF;
                            ELSIF condition->>'type' = 'POOL' THEN
                                IF (condition->>'condition')::BOOLEAN = combined_condition_result THEN
                                    final_object := jsonb_build_object(
                                        'type', condition->>'type',
                                        'designation', condition->>'designation',
                                        'condition', condition->>'condition',
                                        'behaviourType', condition->>'behaviourType',
                                        'isConditionResult', condition->>'isConditionResult'
                                    );
                                    response_data := response_data || jsonb_build_object(
                                        'status', 'success',
                                        'id', record.id::BIGINT,
                                        'parent_id', record.workflow_detail_parent_id::BIGINT,
                                        'running_parent_id', record.workflow_detail_running_parent_id::BIGINT,
                                        'request_node_status', 'PENDING',
                                        'behaviourtype', extracted_behaviourType,
                                        'type', condition->>'type',
                                        'data', (condition->>'designation')::jsonb
                                    );
                                    CONTINUE;
                                END IF;
                            END IF;
                        END IF;
                    ELSIF record.workflow_type_id = 2 THEN
                        BEGIN
                            SELECT evaluate_workflow_condition(
                                p_value,
                                record.workflow_detail_data_object
                            ) INTO eval_result;

                            combined_condition_result := (eval_result->>'result')::BOOLEAN;
                            extracted_behaviourType := (record.workflow_detail_data_object->'conditions'->0->>'query_tag_name');

                            CONTINUE;
                        EXCEPTION WHEN OTHERS THEN
                            RAISE NOTICE 'Error in condition evaluation: %', SQLERRM;
                        END;
                    ELSIF record.workflow_type_id = 3 THEN
                        response_data := response_data || jsonb_build_object(
                            'status', 'success',
                            'id', record.id::BIGINT,
                            'parent_id', record.workflow_detail_parent_id::BIGINT,
                            'running_parent_id', record.workflow_detail_running_parent_id::BIGINT,
                            'request_node_status', 'PENDING',
                            'behaviourtype', 'APPROVED',
                            'type', 'APPROVED',
                            'data', '[[]]'::JSONB
                        );
                        CONTINUE;
                    END IF;

                    EXIT WHEN first_detail_processed;
                    first_detail_processed := TRUE;
                END LOOP;

                RETURN QUERY 
                SELECT * FROM jsonb_populate_recordset(NULL::workflow_request_approver_type, response_data);
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_workflow_request_running_workflow CASCADE;");
        DB::unprepared("DROP TYPE IF EXISTS workflow_request_approver_type;");
    }
};