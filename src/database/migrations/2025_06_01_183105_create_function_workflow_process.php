<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION workflow_process(
        //             p_workflow_id BIGINT,
        //             p_request_queue_id BIGINT,
        //             p_value JSONB,
        //             p_designation_user_id BIGINT DEFAULT NULL
        //         ) RETURNS TABLE (
        //             status TEXT,
        //             message TEXT,
        //             data JSONB
        //         )
        //         LANGUAGE plpgsql
        //         AS \$\$
        //         DECLARE
        //             current_node RECORD;
        //             previous_node RECORD := NULL;
        //             previous_node_id INT;
        //             workflow_node_id_from_request_queue_details BIGINT;
        //             condition JSONB;
        //             combined_condition_result BOOLEAN := FALSE;
        //             next_node RECORD;
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

        //             FOR current_node IN
        //                 SELECT *
        //                 FROM public.workflow_details wd
        //                 WHERE wd.workflow_id = p_workflow_id
        //                 ORDER BY wd.created_at
        //             LOOP
        //                 IF previous_node IS NULL THEN
        //                     previous_node_id := COALESCE(workflow_node_id_from_request_queue_details, 0);
        //                 ELSE
        //                     previous_node_id := previous_node.id;
        //                 END IF;

        //                 IF current_node.workflow_detail_type_id = 1 THEN
        //                     IF jsonb_typeof(current_node.workflow_detail_data_object::jsonb) = 'array' THEN
        //                         condition := current_node.workflow_detail_data_object::jsonb->0;
        //                     ELSE
        //                         condition := current_node.workflow_detail_data_object::jsonb;
        //                     END IF;

        //                     IF ((condition->>'condition')::BOOLEAN = combined_condition_result
        //                         AND current_node.workflow_detail_parent_id = previous_node_id)
        //                         OR ((condition->>'isConditionResult')::BOOLEAN = FALSE
        //                         AND current_node.workflow_detail_parent_id = previous_node_id) THEN

        //                         IF condition->>'behaviourType' = 'EMPLOYEE' THEN
        //                             INSERT INTO public.workflow_request_queue_details (
        //                                 request_id, workflow_node_id, workflow_level, request_status_from_level,
        //                                 workflow_auth_order, workflow_type, comment_for_action,
        //                                 created_at, updated_at
        //                             ) VALUES (
        //                                 p_request_queue_id, current_node.id, current_node.workflow_detail_level,
        //                                 'PENDING', 0, current_node.workflow_detail_type_id, '', NOW(), NOW()
        //                             );
        //                             RETURN QUERY SELECT 'SUCCESS', 'Inserted EMPLOYEE node', to_jsonb(current_node);
        //                             RETURN;
        //                         ELSIF condition->>'behaviourType' = 'DESIGNATION' THEN
        //                             INSERT INTO public.workflow_request_queue_details (
        //                                 request_id, workflow_node_id, workflow_level, request_status_from_level,
        //                                 workflow_auth_order, workflow_type, approver_user_id, comment_for_action,
        //                                 created_at, updated_at
        //                             ) VALUES (
        //                                 p_request_queue_id, current_node.id, current_node.workflow_detail_level,
        //                                 'PENDING', 0, current_node.workflow_detail_type_id, p_designation_user_id, '', NOW(), NOW()
        //                             );
        //                             RETURN QUERY SELECT 'SUCCESS', 'Inserted DESIGNATION node', to_jsonb(current_node);
        //                             RETURN;
        //                         END IF;
        //                     END IF;
        //                     previous_node := current_node;

        //                 ELSIF current_node.workflow_detail_type_id = 2 THEN
        //                     BEGIN
        //                         SELECT (evaluate_workflow_condition(p_value, current_node.workflow_detail_data_object::jsonb)->>'result')::BOOLEAN
        //                         INTO combined_condition_result;

        //                         previous_node := current_node;

        //                         SELECT * INTO next_node
        //                         FROM public.workflow_details
        //                         WHERE workflow_id = p_workflow_id
        //                         AND workflow_detail_parent_id = current_node.id
        //                         AND (workflow_detail_data_object ? 'condition'
        //                             AND (workflow_detail_data_object->>'condition')::BOOLEAN = combined_condition_result)
        //                         ORDER BY created_at
        //                         LIMIT 1;

        //                         IF FOUND THEN
        //                             IF jsonb_typeof(next_node.workflow_detail_data_object::jsonb) = 'array' THEN
        //                                 condition := next_node.workflow_detail_data_object::jsonb->0;
        //                             ELSE
        //                                 condition := next_node.workflow_detail_data_object::jsonb;
        //                             END IF;

        //                             IF condition->>'behaviourType' = 'EMPLOYEE' THEN
        //                                 INSERT INTO public.workflow_request_queue_details (
        //                                     request_id, workflow_node_id, workflow_level, request_status_from_level,
        //                                     workflow_auth_order, workflow_type, comment_for_action,
        //                                     created_at, updated_at
        //                                 ) VALUES (
        //                                     p_request_queue_id, next_node.id, next_node.workflow_detail_level,
        //                                     'PENDING', 0, next_node.workflow_detail_type_id, '', NOW(), NOW()
        //                                 );
        //                                 RETURN QUERY SELECT 'SUCCESS', 'Condition path EMPLOYEE inserted', to_jsonb(next_node);
        //                                 RETURN;
        //                             ELSIF condition->>'behaviourType' = 'DESIGNATION' THEN
        //                                 INSERT INTO public.workflow_request_queue_details (
        //                                     request_id, workflow_node_id, workflow_level, request_status_from_level,
        //                                     workflow_auth_order, workflow_type, approver_user_id, comment_for_action,
        //                                     created_at, updated_at
        //                                 ) VALUES (
        //                                     p_request_queue_id, next_node.id, next_node.workflow_detail_level,
        //                                     'PENDING', 0, next_node.workflow_detail_type_id, p_designation_user_id, '', NOW(), NOW()
        //                                 );
        //                                 RETURN QUERY SELECT 'SUCCESS', 'Condition path DESIGNATION inserted', to_jsonb(next_node);
        //                                 RETURN;
        //                             END IF;
        //                         ELSE
        //                             RETURN QUERY SELECT 'FAILURE', 'No matching next node after condition evaluation', NULL;
        //                             RETURN;
        //                         END IF;
        //                     EXCEPTION WHEN OTHERS THEN
        //                         RETURN QUERY SELECT 'ERROR', SQLERRM, NULL;
        //                         RETURN;
        //                     END;

        //                 ELSIF current_node.workflow_detail_type_id = 3 THEN
        //                     IF current_node.workflow_detail_parent_id = previous_node_id THEN
        //                         UPDATE public.workflow_request_queues
        //                         SET workflow_request_status = 'APPROVED'
        //                         WHERE id = p_request_queue_id;
        //                         RETURN QUERY SELECT 'SUCCESS', 'Workflow approved', to_jsonb(current_node);
        //                         RETURN;
        //                     END IF;
        //                 END IF;
        //             END LOOP;

        //             RETURN QUERY SELECT 'FAILURE', 'No matching workflow path executed', NULL;
        //         END;
        //     \$\$;
        // SQL);

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION workflow_process(
                p_workflow_id BIGINT,
                p_request_queue_id BIGINT,
                p_value JSONB,
                p_designation_user_id BIGINT DEFAULT NULL
            ) RETURNS TABLE (
                status TEXT,
                message TEXT,
                data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                record RECORD;
                condition JSONB;
                combined_condition_result BOOLEAN := FALSE;
                previous_node_id BIGINT;
                workflow_node_id_from_request_queue_details BIGINT;
                eval_result JSONB;
            BEGIN
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
                previous_node_id := workflow_node_id_from_request_queue_details;

                FOR record IN
                    SELECT *
                    FROM public.workflow_details wd
                    WHERE wd.workflow_id = p_workflow_id
                    ORDER BY wd.workflow_detail_order
                LOOP
                    IF record.workflow_detail_type_id = 1 THEN
                        IF jsonb_typeof(record.workflow_detail_data_object::jsonb) = 'array' THEN
                            condition := record.workflow_detail_data_object::jsonb->0;
                        ELSE
                            condition := record.workflow_detail_data_object::jsonb;
                        END IF;

                        IF ((condition->>'condition')::BOOLEAN = combined_condition_result
                            AND record.workflow_detail_parent_id = previous_node_id)
                            OR ((condition->>'isConditionResult')::BOOLEAN = FALSE
                            AND record.workflow_detail_parent_id = previous_node_id) THEN

                            IF condition->>'behaviourType' = 'EMPLOYEE' THEN
                                INSERT INTO public.workflow_request_queue_details (
                                    request_id, workflow_node_id, workflow_level, request_status_from_level, 
                                    workflow_auth_order, workflow_type, comment_for_action, 
                                    created_at, updated_at
                                ) VALUES (
                                    p_request_queue_id, record.id, record.workflow_detail_level, 
                                    'PENDING', 0, record.workflow_detail_type_id, '', NOW(), NOW()
                                );
                                RETURN QUERY SELECT 'SUCCESS', 'EMPLOYEE node inserted', to_jsonb(record);
                                RETURN;
                            ELSIF condition->>'behaviourType' = 'DESIGNATION' THEN
                                INSERT INTO public.workflow_request_queue_details (
                                    request_id, workflow_node_id, workflow_level, request_status_from_level, 
                                    workflow_auth_order, workflow_type, approver_user_id, comment_for_action, 
                                    created_at, updated_at
                                ) VALUES (
                                    p_request_queue_id, record.id, record.workflow_detail_level, 
                                    'PENDING', 0, record.workflow_detail_type_id, p_designation_user_id, '', NOW(), NOW()
                                );
                                RETURN QUERY SELECT 'SUCCESS', 'DESIGNATION node inserted', to_jsonb(record);
                                RETURN;
                            END IF;
                        END IF;

                    ELSIF record.workflow_detail_type_id = 2 THEN
                        BEGIN
                            SELECT evaluate_workflow_condition(p_value, record.workflow_detail_data_object::jsonb)
                            INTO eval_result;

                            combined_condition_result := (eval_result->>'result')::BOOLEAN;
                            previous_node_id := record.id;
                        EXCEPTION WHEN OTHERS THEN
                            RETURN QUERY SELECT 'ERROR', SQLERRM, NULL::JSONB;
                            RETURN;
                        END;

                    ELSIF record.workflow_detail_type_id = 3 THEN
                        IF record.workflow_detail_parent_id = previous_node_id THEN
                            UPDATE public.workflow_request_queues
                            SET workflow_request_status = 'APPROVED'
                            WHERE id = p_request_queue_id;
                            RETURN QUERY SELECT 'SUCCESS', 'Workflow approved', to_jsonb(record);
                            RETURN;
                        END IF;
                    END IF;
                END LOOP;

                RETURN QUERY SELECT 'FAILURE', 'No matching workflow path executed', NULL::JSONB;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS workflow_process, JSONB, BIGINT);');
    }
};