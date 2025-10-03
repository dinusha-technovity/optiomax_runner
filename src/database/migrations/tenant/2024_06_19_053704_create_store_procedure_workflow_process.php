<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // $procedure = <<<SQL
        //         CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_WORKFLOW_PROCESS(
        //             p_workflow_id BIGINT,
        //             p_request_queue_id BIGINT,
        //             p_value INT,
        //             p_designation_user_id BIGINT DEFAULT NULL
        //         )
        //         LANGUAGE plpgsql
        //         AS $$
        //         DECLARE
        //             current_node RECORD;
        //             previous_node RECORD := NULL;

        //             previous_node_id INT;

        //             workflow_node_id_from_request_queue_details BIGINT;

        //             condition JSON;
        //             condition_sql TEXT;
        //             combined_condition_sql TEXT := NULL;
        //             condition_result BOOLEAN;
        //             combined_condition_result BOOLEAN;

        //             users_json JSONB;
        //             user_data JSONB;
        //             updated_users_json JSONB;
        //             user_object JSONB;
        //             user_array JSONB[] := '{}';
        //             updated_workflow_node JSONB;
        //             user_id INT;
        //             existing_user RECORD;

        //             workflow_path_cursor CURSOR FOR
        //                 SELECT *
        //                 FROM public.workflow_details wd
        //                 WHERE wd.workflow_id = p_workflow_id
        //                 ORDER BY wd.created_at;

        //         BEGIN
        //             workflow_node_id_from_request_queue_details := COALESCE(
        //                 (
        //                     SELECT wrqd.workflow_node_id
        //                     FROM public.workflow_request_queue_details wrqd
        //                     JOIN public.workflow_request_queues wrq ON wrqd.request_id = wrq.id
        //                     WHERE wrq.workflow_id = p_workflow_id
        //                     AND wrqd.request_id = p_request_queue_id
        //                     AND wrqd.request_status_from_level = 'APPROVED'
        //                     ORDER BY wrqd.id DESC, wrqd.created_at DESC
        //                     LIMIT 1
        //                 ),
        //                 0
        //             );
                    
        //             OPEN workflow_path_cursor;
        //             LOOP
        //                 FETCH workflow_path_cursor INTO current_node;
        //                 EXIT WHEN NOT FOUND;

        //                 IF previous_node IS NULL THEN
        //                     previous_node_id := COALESCE(
        //                         workflow_node_id_from_request_queue_details, 0
        //                     );
        //                 ELSE
        //                     previous_node_id := previous_node.id;
        //                 END IF;

        //                 IF current_node.workflow_detail_type_id = 1 THEN
        //                     IF jsonb_typeof(current_node.workflow_detail_data_object::jsonb) = 'array' THEN
        //                         condition := current_node.workflow_detail_data_object->0;
        //                     ELSE
        //                         condition := current_node.workflow_detail_data_object;
        //                     END IF;


        //                     IF ((condition->>'condition')::BOOLEAN = combined_condition_result
        //                         AND current_node.workflow_detail_parent_id = previous_node_id)
        //                         OR ((condition->>'isConditionResult')::BOOLEAN = FALSE::BOOLEAN
        //                         AND current_node.workflow_detail_parent_id = previous_node_id)
        //                     THEN
        //                         IF condition->>'behaviourType' = 'EMPLOYEE' THEN
        //                             IF workflow_node_id_from_request_queue_details = 0 AND current_node.workflow_detail_parent_id = previous_node_id THEN
        //                                 IF NOT EXISTS (
        //                                     SELECT 1
        //                                     FROM public.workflow_request_queue_details
        //                                     WHERE request_id = p_request_queue_id AND workflow_node_id = current_node.id
        //                                 ) THEN
        //                                     INSERT INTO public.workflow_request_queue_details (
        //                                         request_id, workflow_node_id, workflow_level, request_status_from_level, 
        //                                         workflow_auth_order, workflow_type, comment_for_action, 
        //                                         created_at, updated_at
        //                                     ) VALUES 
        //                                     ( p_request_queue_id, current_node.id, current_node.workflow_detail_level, 
        //                                         'PENDING', 0, current_node.workflow_detail_type_id, '',  NOW(), NOW()
        //                                     );
        //                                 END IF;
        //                                 RAISE NOTICE 'Workflow Node %', current_node;
        //                                 RETURN;
        //                             ELSEIF NOT workflow_node_id_from_request_queue_details = 0 AND current_node.workflow_detail_parent_id = workflow_node_id_from_request_queue_details THEN
        //                                 IF NOT EXISTS (
        //                                     SELECT 1
        //                                     FROM public.workflow_request_queue_details
        //                                     WHERE request_id = p_request_queue_id AND workflow_node_id = current_node.id
        //                                 ) THEN
        //                                     INSERT INTO public.workflow_request_queue_details (
        //                                         request_id, workflow_node_id, workflow_level, request_status_from_level, 
        //                                         workflow_auth_order, workflow_type, comment_for_action, 
        //                                         created_at, updated_at
        //                                     ) VALUES 
        //                                     ( p_request_queue_id, current_node.id, current_node.workflow_detail_level, 
        //                                         'PENDING', 0, current_node.workflow_detail_type_id, '',  NOW(), NOW()
        //                                     );
        //                                 END IF;
        //                                 RAISE NOTICE 'Workflow Node %', current_node;
        //                                 RETURN;
        //                             ELSEIF current_node.workflow_detail_parent_id = previous_node_id AND current_node.workflow_detail_parent_id = workflow_node_id_from_request_queue_details AND (condition->>'condition')::BOOLEAN = combined_condition_result THEN
        //                                 RAISE NOTICE 'previous_node_id %', previous_node_id;
        //                                 RAISE NOTICE 'current_node.workflow_detail_parent_id %', current_node.workflow_detail_parent_id;
        //                                 RAISE NOTICE 'workflow_node_id_from_request_queue_details %', workflow_node_id_from_request_queue_details;
        //                                 RAISE NOTICE 'Workflow Node %', current_node;
        //                                 RETURN;
        //                             END IF;
        //                         ELSEIF condition->>'behaviourType' = 'DESIGNATION' THEN
        //                             IF workflow_node_id_from_request_queue_details = 0 AND current_node.workflow_detail_parent_id = previous_node_id THEN
        //                                 IF NOT EXISTS (
        //                                     SELECT 1
        //                                     FROM public.workflow_request_queue_details
        //                                     WHERE request_id = p_request_queue_id AND workflow_node_id = current_node.id
        //                                 ) THEN
        //                                     INSERT INTO public.workflow_request_queue_details (
        //                                         request_id, workflow_node_id, workflow_level, request_status_from_level, 
        //                                         workflow_auth_order, workflow_type, approver_user_id, comment_for_action, 
        //                                         created_at, updated_at
        //                                     ) VALUES 
        //                                     ( p_request_queue_id, current_node.id, current_node.workflow_detail_level, 
        //                                         'PENDING', 0, current_node.workflow_detail_type_id, p_designation_user_id, '',  NOW(), NOW()
        //                                     );
        //                                 END IF;
        //                                 RAISE NOTICE 'Workflow Node %', current_node;
        //                                 RETURN;
        //                             ELSEIF NOT workflow_node_id_from_request_queue_details = 0 AND current_node.workflow_detail_parent_id = workflow_node_id_from_request_queue_details THEN
        //                                 IF NOT EXISTS (
        //                                     SELECT 1
        //                                     FROM public.workflow_request_queue_details
        //                                     WHERE request_id = p_request_queue_id AND workflow_node_id = current_node.id
        //                                 ) THEN
        //                                     INSERT INTO public.workflow_request_queue_details (
        //                                         request_id, workflow_node_id, workflow_level, request_status_from_level, 
        //                                         workflow_auth_order, workflow_type, approver_user_id, comment_for_action, 
        //                                         created_at, updated_at
        //                                     ) VALUES 
        //                                     ( p_request_queue_id, current_node.id, current_node.workflow_detail_level, 
        //                                         'PENDING', 0, current_node.workflow_detail_type_id, p_designation_user_id, '',  NOW(), NOW()
        //                                     );
        //                                 END IF;
        //                                 RAISE NOTICE 'Workflow Node %', current_node;
        //                                 RETURN;
        //                             ELSEIF current_node.workflow_detail_parent_id = previous_node_id AND current_node.workflow_detail_parent_id = workflow_node_id_from_request_queue_details AND (condition->>'condition')::BOOLEAN = combined_condition_result THEN
        //                                 RAISE NOTICE 'previous_node_id %', previous_node_id;
        //                                 RAISE NOTICE 'current_node.workflow_detail_parent_id %', current_node.workflow_detail_parent_id;
        //                                 RAISE NOTICE 'workflow_node_id_from_request_queue_details %', workflow_node_id_from_request_queue_details;
        //                                 RAISE NOTICE 'Workflow Node %', current_node;
        //                                 RETURN;
        //                             END IF;
        //                         END IF;
        //                         previous_node := current_node;
        //                     END IF;
        //                 ELSEIF current_node.workflow_detail_type_id = 2 THEN
        //                     combined_condition_sql := NULL;

        //                     FOR condition IN SELECT * FROM json_array_elements(current_node.workflow_detail_data_object->0->'conditions')
        //                     LOOP
        //                         condition_sql := condition::TEXT;
        //                         condition_sql := trim(both '"' from condition_sql);

        //                         condition_sql := replace(condition_sql, 'and', ' AND ');
        //                         condition_sql := replace(condition_sql, 'or', ' OR ');
        //                         condition_sql := replace(condition_sql, '==', '=');
        //                         condition_sql := replace(condition_sql, '''value''', p_value::TEXT);

        //                         IF combined_condition_sql IS NULL THEN
        //                             combined_condition_sql := condition_sql;
        //                         ELSE
        //                             combined_condition_sql := combined_condition_sql || ' AND ' || condition_sql;
        //                         END IF;
        //                     END LOOP;

        //                     EXECUTE 'SELECT (' || combined_condition_sql || ')::BOOLEAN' INTO combined_condition_result;

        //                     previous_node := current_node;
        //                     RAISE INFO 'Condition Node: %', current_node;
        //                     RAISE INFO 'Condition %', combined_condition_result;
        //                 ELSEIF current_node.workflow_detail_type_id = 3 THEN
        //                     IF current_node.workflow_detail_parent_id = previous_node_id THEN
        //                         UPDATE public.workflow_request_queues
        //                         SET workflow_request_status = 'APPROVED'
        //                         WHERE id = p_request_queue_id;
        //                         RAISE INFO 'Approved %', current_node;
        //                         RETURN;
        //                     END IF;
        //                 END IF;

        //             END LOOP;
        //             CLOSE workflow_path_cursor;
        //         END;
        //         \$\$;
        //         SQL;
                
        //     // Execute the SQL statement
        //     DB::unprepared($procedure);


        
        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_WORKFLOW_PROCESS(
        //         p_workflow_id BIGINT,
        //         p_request_queue_id BIGINT,
        //         p_value JSONB,
        //         p_designation_user_id BIGINT DEFAULT NULL
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         current_node RECORD;
        //         previous_node RECORD := NULL;
        //         previous_node_id INT;
        //         workflow_node_id_from_request_queue_details BIGINT;
        //         condition JSONB;
        //         combined_condition_result BOOLEAN := FALSE;
        //         users_json JSONB;
        //         user_data JSONB;
        //         updated_users_json JSONB;
        //         user_object JSONB;
        //         user_array JSONB[] := '{}';
        //         updated_workflow_node JSONB;
        //         user_id INT;
        //         existing_user RECORD;
                
        //         workflow_path_cursor CURSOR FOR
        //             SELECT *
        //             FROM public.workflow_details wd
        //             WHERE wd.workflow_id = p_workflow_id
        //             ORDER BY wd.created_at;

        //     BEGIN
        //         -- Retrieve last approved workflow node ID
        //         workflow_node_id_from_request_queue_details := COALESCE(
        //             (
        //                 SELECT wrqd.workflow_node_id
        //                 FROM public.workflow_request_queue_details wrqd
        //                 JOIN public.workflow_request_queues wrq ON wrqd.request_id = wrq.id
        //                 WHERE wrq.workflow_id = p_workflow_id
        //                 AND wrqd.request_id = p_request_queue_id
        //                 AND wrqd.request_status_from_level = 'APPROVED'
        //                 ORDER BY wrqd.id DESC, wrqd.created_at DESC
        //                 LIMIT 1
        //             ),
        //             0
        //         );

        //         OPEN workflow_path_cursor;
        //         LOOP
        //             FETCH workflow_path_cursor INTO current_node;
        //             EXIT WHEN NOT FOUND;

        //             -- Determine previous node ID
        //             IF previous_node IS NULL THEN
        //                 previous_node_id := COALESCE(workflow_node_id_from_request_queue_details, 0);
        //             ELSE
        //                 previous_node_id := previous_node.id;
        //             END IF;

        //             -- Handle workflow types
        //             IF current_node.workflow_detail_type_id = 1 THEN  -- Employee/Designation Type
        //                 IF jsonb_typeof(current_node.workflow_detail_data_object::jsonb) = 'array' THEN
        //                     condition := current_node.workflow_detail_data_object::jsonb->0;
        //                 ELSE
        //                     condition := current_node.workflow_detail_data_object::jsonb;
        //                 END IF;

        //                 -- Check conditions
        //                 IF ((condition->>'condition')::BOOLEAN = combined_condition_result
        //                     AND current_node.workflow_detail_parent_id = previous_node_id)
        //                     OR ((condition->>'isConditionResult')::BOOLEAN = FALSE
        //                     AND current_node.workflow_detail_parent_id = previous_node_id)
        //                 THEN
        //                     IF condition->>'behaviourType' = 'EMPLOYEE' THEN
        //                         IF NOT EXISTS (
        //                             SELECT 1 FROM public.workflow_request_queue_details
        //                             WHERE request_id = p_request_queue_id AND workflow_node_id = current_node.id
        //                         ) THEN
        //                             INSERT INTO public.workflow_request_queue_details (
        //                                 request_id, workflow_node_id, workflow_level, request_status_from_level, 
        //                                 workflow_auth_order, workflow_type, comment_for_action, 
        //                                 created_at, updated_at
        //                             ) VALUES (
        //                                 p_request_queue_id, current_node.id, current_node.workflow_detail_level, 
        //                                 'PENDING', 0, current_node.workflow_detail_type_id, '', NOW(), NOW()
        //                             );
        //                         END IF;
        //                         RAISE NOTICE 'Workflow Node %', current_node;
        //                         RETURN;
        //                     ELSIF condition->>'behaviourType' = 'DESIGNATION' THEN
        //                         IF NOT EXISTS (
        //                             SELECT 1 FROM public.workflow_request_queue_details
        //                             WHERE request_id = p_request_queue_id AND workflow_node_id = current_node.id
        //                         ) THEN
        //                             INSERT INTO public.workflow_request_queue_details (
        //                                 request_id, workflow_node_id, workflow_level, request_status_from_level, 
        //                                 workflow_auth_order, workflow_type, approver_user_id, comment_for_action, 
        //                                 created_at, updated_at
        //                             ) VALUES (
        //                                 p_request_queue_id, current_node.id, current_node.workflow_detail_level, 
        //                                 'PENDING', 0, current_node.workflow_detail_type_id, p_designation_user_id, '', NOW(), NOW()
        //                             );
        //                         END IF;
        //                         RAISE NOTICE 'Workflow Node %', current_node;
        //                         RETURN;
        //                     END IF;
        //                 END IF;
        //                 previous_node := current_node;

        //             ELSIF current_node.workflow_detail_type_id = 2 THEN  -- Condition Type
        //                 BEGIN
        //                     -- Call function to evaluate condition using JSONB p_value
        //                     SELECT evaluate_workflow_condition(p_value, current_node.workflow_detail_data_object::jsonb)
        //                     INTO combined_condition_result;

        //                     RAISE INFO 'Condition Evaluated Result: %', combined_condition_result;
        //                     previous_node := current_node;
        //                 EXCEPTION WHEN OTHERS THEN
        //                     RAISE NOTICE 'Error in condition evaluation: %', SQLERRM;
        //                 END;

        //             ELSIF current_node.workflow_detail_type_id = 3 THEN  -- Approval Type
        //                 IF current_node.workflow_detail_parent_id = previous_node_id THEN
        //                     UPDATE public.workflow_request_queues
        //                     SET workflow_request_status = 'APPROVED'
        //                     WHERE id = p_request_queue_id;
        //                     RAISE INFO 'Approved %', current_node;
        //                     RETURN;
        //                 END IF;
        //             END IF;
        //         END LOOP;
        //         CLOSE workflow_path_cursor;
        //     END;
        //     $$;
        // SQL);


        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_WORKFLOW_PROCESS(
        //         p_workflow_id BIGINT,
        //         p_request_queue_id BIGINT,
        //         p_value JSONB,
        //         p_designation_user_id BIGINT DEFAULT NULL
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         current_node RECORD;
        //         previous_node RECORD := NULL;
        //         previous_node_id INT;
        //         workflow_node_id_from_request_queue_details BIGINT;
        //         condition JSONB;
        //         combined_condition_result BOOLEAN := FALSE;
        //         users_json JSONB;
        //         user_data JSONB;
        //         updated_users_json JSONB;
        //         user_object JSONB;
        //         user_array JSONB[] := '{}';
        //         updated_workflow_node JSONB;
        //         user_id INT;
        //         existing_user RECORD;
        //         next_node RECORD;
                
        //         workflow_path_cursor CURSOR FOR
        //             SELECT *
        //             FROM public.workflow_details wd
        //             WHERE wd.workflow_id = p_workflow_id
        //             ORDER BY wd.created_at;

        //     BEGIN
        //         -- Retrieve last approved workflow node ID
        //         workflow_node_id_from_request_queue_details := COALESCE(
        //             (
        //                 SELECT wrqd.workflow_node_id
        //                 FROM public.workflow_request_queue_details wrqd
        //                 JOIN public.workflow_request_queues wrq ON wrqd.request_id = wrq.id
        //                 WHERE wrq.workflow_id = p_workflow_id
        //                 AND wrqd.request_id = p_request_queue_id
        //                 AND wrqd.request_status_from_level = 'APPROVED'
        //                 ORDER BY wrqd.id DESC, wrqd.created_at DESC
        //                 LIMIT 1
        //             ),
        //             0
        //         );

        //         OPEN workflow_path_cursor;
        //         LOOP
        //             FETCH workflow_path_cursor INTO current_node;
        //             EXIT WHEN NOT FOUND;

        //             -- Determine previous node ID
        //             IF previous_node IS NULL THEN
        //                 previous_node_id := COALESCE(workflow_node_id_from_request_queue_details, 0);
        //             ELSE
        //                 previous_node_id := previous_node.id;
        //             END IF;

        //             -- Handle workflow types
        //             IF current_node.workflow_detail_type_id = 1 THEN  -- Employee/Designation Type
        //                 IF jsonb_typeof(current_node.workflow_detail_data_object::jsonb) = 'array' THEN
        //                     condition := current_node.workflow_detail_data_object::jsonb->0;
        //                 ELSE
        //                     condition := current_node.workflow_detail_data_object::jsonb;
        //                 END IF;

        //                 -- Check conditions
        //                 IF ((condition->>'condition')::BOOLEAN = combined_condition_result
        //                     AND current_node.workflow_detail_parent_id = previous_node_id)
        //                     OR ((condition->>'isConditionResult')::BOOLEAN = FALSE
        //                     AND current_node.workflow_detail_parent_id = previous_node_id)
        //                 THEN
        //                     IF condition->>'behaviourType' = 'EMPLOYEE' THEN
        //                         IF NOT EXISTS (
        //                             SELECT 1 FROM public.workflow_request_queue_details
        //                             WHERE request_id = p_request_queue_id AND workflow_node_id = current_node.id
        //                         ) THEN
        //                             INSERT INTO public.workflow_request_queue_details (
        //                                 request_id, workflow_node_id, workflow_level, request_status_from_level, 
        //                                 workflow_auth_order, workflow_type, comment_for_action, 
        //                                 created_at, updated_at
        //                             ) VALUES (
        //                                 p_request_queue_id, current_node.id, current_node.workflow_detail_level, 
        //                                 'PENDING', 0, current_node.workflow_detail_type_id, '', NOW(), NOW()
        //                             );
        //                         END IF;
        //                         RAISE NOTICE 'Workflow Node %', current_node;
        //                         RETURN;
        //                     ELSIF condition->>'behaviourType' = 'DESIGNATION' THEN
        //                         IF NOT EXISTS (
        //                             SELECT 1 FROM public.workflow_request_queue_details
        //                             WHERE request_id = p_request_queue_id AND workflow_node_id = current_node.id
        //                         ) THEN
        //                             INSERT INTO public.workflow_request_queue_details (
        //                                 request_id, workflow_node_id, workflow_level, request_status_from_level, 
        //                                 workflow_auth_order, workflow_type, approver_user_id, comment_for_action, 
        //                                 created_at, updated_at
        //                             ) VALUES (
        //                                 p_request_queue_id, current_node.id, current_node.workflow_detail_level, 
        //                                 'PENDING', 0, current_node.workflow_detail_type_id, p_designation_user_id, '', NOW(), NOW()
        //                             );
        //                         END IF;
        //                         RAISE NOTICE 'Workflow Node %', current_node;
        //                         RETURN;
        //                     END IF;
        //                 END IF;
        //                 previous_node := current_node;

        //             ELSIF current_node.workflow_detail_type_id = 2 THEN  -- Condition Type
        //                 BEGIN
        //                     -- Call function to evaluate condition using JSONB p_value
        //                     SELECT evaluate_workflow_condition(p_value, current_node.workflow_detail_data_object::jsonb)
        //                     INTO combined_condition_result;

        //                     RAISE INFO 'Condition Evaluated Result: %', combined_condition_result;
        //                     previous_node := current_node;
                            
        //                     -- Find the next record based on the condition evaluation
        //                     SELECT *
        //                     INTO next_node
        //                     FROM public.workflow_details
        //                     WHERE workflow_id = p_workflow_id
        //                     AND workflow_detail_parent_id = current_node.id
        //                     ORDER BY created_at
        //                     LIMIT 1;

        //                     -- Process next record as in get_workflow_request_running_workflow
        //                     IF next_node.workflow_detail_type_id = 1 THEN
        //                         -- Handle EMPLOYEE or DESIGNATION logic
        //                         condition := next_node.workflow_detail_data_object;
        //                         IF condition->>'behaviourType' = 'EMPLOYEE' THEN
        //                             INSERT INTO public.workflow_request_queue_details (
        //                                 request_id, workflow_node_id, workflow_level, request_status_from_level, 
        //                                 workflow_auth_order, workflow_type, comment_for_action, 
        //                                 created_at, updated_at
        //                             ) VALUES (
        //                                 p_request_queue_id, next_node.id, next_node.workflow_detail_level, 
        //                                 'PENDING', 0, next_node.workflow_detail_type_id, '', NOW(), NOW()
        //                             );
        //                         ELSIF condition->>'behaviourType' = 'DESIGNATION' THEN
        //                             INSERT INTO public.workflow_request_queue_details (
        //                                 request_id, workflow_node_id, workflow_level, request_status_from_level, 
        //                                 workflow_auth_order, workflow_type, approver_user_id, comment_for_action, 
        //                                 created_at, updated_at
        //                             ) VALUES (
        //                                 p_request_queue_id, next_node.id, next_node.workflow_detail_level, 
        //                                 'PENDING', 0, next_node.workflow_detail_type_id, p_designation_user_id, '', NOW(), NOW()
        //                             );
        //                         END IF;
        //                         RETURN;
        //                     END IF;
        //                 EXCEPTION WHEN OTHERS THEN
        //                     RAISE NOTICE 'Error in condition evaluation: %', SQLERRM;
        //                 END;

        //             ELSIF current_node.workflow_detail_type_id = 3 THEN  -- Approval Type
        //                 IF current_node.workflow_detail_parent_id = previous_node_id THEN
        //                     UPDATE public.workflow_request_queues
        //                     SET workflow_request_status = 'APPROVED'
        //                     WHERE id = p_request_queue_id;
        //                     RAISE INFO 'Approved %', current_node;
        //                     RETURN;
        //                 END IF;
        //             END IF;
        //         END LOOP;
        //         CLOSE workflow_path_cursor;
        //     END;
        //     $$;
        // SQL);


        DB::unprepared(<<<SQL
            CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_WORKFLOW_PROCESS(
                p_workflow_id BIGINT,
                p_request_queue_id BIGINT,
                p_value JSONB,
                p_designation_user_id BIGINT DEFAULT NULL
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                current_node RECORD;
                previous_node RECORD := NULL;
                previous_node_id INT;
                workflow_node_id_from_request_queue_details BIGINT;
                condition JSONB;
                combined_condition_result BOOLEAN := FALSE;
                next_node RECORD;

                workflow_path_cursor CURSOR FOR
                    SELECT *
                    FROM public.workflow_details wd
                    WHERE wd.workflow_id = p_workflow_id
                    ORDER BY wd.created_at;

            BEGIN
                -- Retrieve last approved workflow node ID
                workflow_node_id_from_request_queue_details := COALESCE(
                    (
                        SELECT wrqd.workflow_node_id
                        FROM public.workflow_request_queue_details wrqd
                        JOIN public.workflow_request_queues wrq ON wrqd.request_id = wrq.id
                        WHERE wrq.workflow_id = p_workflow_id
                        AND wrqd.request_id = p_request_queue_id
                        AND wrqd.request_status_from_level = 'APPROVED'
                        ORDER BY wrqd.id DESC, wrqd.created_at DESC
                        LIMIT 1
                    ),
                    0
                );

                OPEN workflow_path_cursor;
                LOOP
                    FETCH workflow_path_cursor INTO current_node;
                    EXIT WHEN NOT FOUND;

                    -- Determine previous node ID
                    IF previous_node IS NULL THEN
                        previous_node_id := COALESCE(workflow_node_id_from_request_queue_details, 0);
                    ELSE
                        previous_node_id := previous_node.id;
                    END IF;

                    -- Handle workflow types
                    IF current_node.workflow_detail_type_id = 1 THEN  -- Employee/Designation Type
                        IF jsonb_typeof(current_node.workflow_detail_data_object::jsonb) = 'array' THEN
                            condition := current_node.workflow_detail_data_object::jsonb->0;
                        ELSE
                            condition := current_node.workflow_detail_data_object::jsonb;
                        END IF;

                        -- Check conditions
                        IF ((condition->>'condition')::BOOLEAN = combined_condition_result
                            AND current_node.workflow_detail_parent_id = previous_node_id)
                            OR ((condition->>'isConditionResult')::BOOLEAN = FALSE
                            AND current_node.workflow_detail_parent_id = previous_node_id)
                        THEN
                            IF condition->>'behaviourType' = 'EMPLOYEE' THEN
                                INSERT INTO public.workflow_request_queue_details (
                                    request_id, workflow_node_id, workflow_level, request_status_from_level, 
                                    workflow_auth_order, workflow_type, comment_for_action, 
                                    created_at, updated_at
                                ) VALUES (
                                    p_request_queue_id, current_node.id, current_node.workflow_detail_level, 
                                    'PENDING', 0, current_node.workflow_detail_type_id, '', NOW(), NOW()
                                );
                                RETURN;
                            ELSIF condition->>'behaviourType' = 'DESIGNATION' THEN
                                INSERT INTO public.workflow_request_queue_details (
                                    request_id, workflow_node_id, workflow_level, request_status_from_level, 
                                    workflow_auth_order, workflow_type, approver_user_id, comment_for_action, 
                                    created_at, updated_at
                                ) VALUES (
                                    p_request_queue_id, current_node.id, current_node.workflow_detail_level, 
                                    'PENDING', 0, current_node.workflow_detail_type_id, p_designation_user_id, '', NOW(), NOW()
                                );
                                RETURN;
                            END IF;
                        END IF;
                        previous_node := current_node;

                    ELSIF current_node.workflow_detail_type_id = 2 THEN  -- Condition Type
                        BEGIN
                            -- Extract `result` from evaluate_workflow_condition
                            SELECT (evaluate_workflow_condition(p_value, current_node.workflow_detail_data_object::jsonb)->>'result')::BOOLEAN
                            INTO combined_condition_result;

                            RAISE INFO 'Condition Evaluated Result: %', combined_condition_result;
                            previous_node := current_node;

                            -- Find the next record where the condition result matches
                            SELECT * INTO next_node
                            FROM public.workflow_details
                            WHERE workflow_id = p_workflow_id
                            AND workflow_detail_parent_id = current_node.id
                            AND (
                                workflow_detail_data_object ? 'condition'  -- Ensure 'condition' key exists
                                AND (workflow_detail_data_object->>'condition')::BOOLEAN = combined_condition_result
                            )
                            ORDER BY created_at
                            LIMIT 1;

                            -- Process next record if found
                            IF FOUND THEN
                                RAISE NOTICE 'Next Node Found: %', next_node.id;

                                IF jsonb_typeof(next_node.workflow_detail_data_object::jsonb) = 'array' THEN
                                    condition := next_node.workflow_detail_data_object::jsonb->0;
                                ELSE
                                    condition := next_node.workflow_detail_data_object::jsonb;
                                END IF;

                                IF condition->>'behaviourType' = 'EMPLOYEE' THEN
                                    INSERT INTO public.workflow_request_queue_details (
                                        request_id, workflow_node_id, workflow_level, request_status_from_level, 
                                        workflow_auth_order, workflow_type, comment_for_action, 
                                        created_at, updated_at
                                    ) VALUES (
                                        p_request_queue_id, next_node.id, next_node.workflow_detail_level, 
                                        'PENDING', 0, next_node.workflow_detail_type_id, '', NOW(), NOW()
                                    );
                                    RETURN;
                                ELSIF condition->>'behaviourType' = 'DESIGNATION' THEN
                                    INSERT INTO public.workflow_request_queue_details (
                                        request_id, workflow_node_id, workflow_level, request_status_from_level, 
                                        workflow_auth_order, workflow_type, approver_user_id, comment_for_action, 
                                        created_at, updated_at
                                    ) VALUES (
                                        p_request_queue_id, next_node.id, next_node.workflow_detail_level, 
                                        'PENDING', 0, next_node.workflow_detail_type_id, p_designation_user_id, '', NOW(), NOW()
                                    );
                                    RETURN;
                                END IF;
                            ELSE
                                RAISE NOTICE 'No matching next_node found after condition evaluation';
                            END IF;
                        EXCEPTION WHEN OTHERS THEN
                            RAISE NOTICE 'Error in condition evaluation: %', SQLERRM;
                        END;

                    ELSIF current_node.workflow_detail_type_id = 3 THEN  -- Approval Type
                        IF current_node.workflow_detail_parent_id = previous_node_id THEN
                            UPDATE public.workflow_request_queues
                            SET workflow_request_status = 'APPROVED'
                            WHERE id = p_request_queue_id;
                            RETURN;
                        END IF;
                    END IF;
                END LOOP;
                CLOSE workflow_path_cursor;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS STORE_PROCEDURE_WORKFLOW_PROCESS');
    }
};