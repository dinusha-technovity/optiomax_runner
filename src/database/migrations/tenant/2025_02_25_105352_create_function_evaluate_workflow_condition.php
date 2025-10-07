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
        {
            // DB::unprepared(
            //     "CREATE OR REPLACE FUNCTION evaluate_workflow_condition(
            //         input_data JSONB,
            //         condition JSONB
            //     ) RETURNS JSONB AS $$
            //     DECLARE
            //         logic_op TEXT;
            //         result BOOLEAN := TRUE;
            //         cond JSONB;
            //         single_result BOOLEAN;
            //         field_value TEXT;
            //         cond_value TEXT;
            //         operator TEXT;
            //         query_tag_name TEXT;
            //         dynamic_query TEXT;
            //         query_result TEXT;
            //         query_type TEXT;
            //         param_list JSONB;
            //         eval_details JSONB := '[]'::JSONB;
            //     BEGIN
            //         logic_op := COALESCE(condition->>'logic', 'AND');
        
            //         FOR cond IN SELECT * FROM jsonb_array_elements(condition->'conditions')
            //         LOOP
            //             IF cond ? 'conditions' THEN
            //                 single_result := (evaluate_workflow_condition(input_data, cond)->>'result')::BOOLEAN;
            //             ELSE
            //                 field_value := input_data->>(cond->>'field');
            //                 cond_value := cond->>'value';
            //                 operator := cond->>'operator';
            //                 query_tag_name := cond->>'query_tag_name';
            //                 param_list := cond->'params';
        
            //                 BEGIN
            //                     IF query_tag_name IS NOT NULL THEN
            //                         SELECT query, type INTO dynamic_query, query_type
            //                         FROM query_tag
            //                         WHERE name = query_tag_name;
        
            //                         IF dynamic_query IS NULL THEN
            //                             RAISE NOTICE 'Query tag % not found', query_tag_name;
            //                             single_result := FALSE;
            //                         ELSE
            //                             IF query_type = 'query' THEN
            //                                 EXECUTE dynamic_query
            //                                 INTO query_result
            //                                 USING (SELECT unnest(ARRAY(SELECT jsonb_array_elements_text(param_list))));
        
            //                                 single_result := (
            //                                     CASE operator
            //                                         WHEN '=' THEN query_result = cond_value
            //                                         WHEN '!=' THEN query_result <> cond_value
            //                                         WHEN '>' THEN (query_result)::NUMERIC > (cond_value)::NUMERIC
            //                                         WHEN '<' THEN (query_result)::NUMERIC < (cond_value)::NUMERIC
            //                                         ELSE FALSE
            //                                     END
            //                                 );
            //                             ELSIF query_type = 'function' THEN
            //                                 EXECUTE 'SELECT ' || dynamic_query || '(' || string_agg(quote_literal(p), ',') || ')'
            //                                 INTO query_result
            //                                 FROM jsonb_array_elements_text(param_list) p;
        
            //                                 single_result := (query_result = cond_value);
            //                             ELSIF query_type = 'procedure' THEN
            //                                 EXECUTE dynamic_query USING (SELECT unnest(ARRAY(SELECT jsonb_array_elements_text(param_list))));
            //                                 single_result := TRUE;
            //                             END IF;
            //                         END IF;
            //                     ELSE
            //                         single_result := (
            //                             CASE operator
            //                                 WHEN '>' THEN (field_value)::NUMERIC > (cond_value)::NUMERIC
            //                                 WHEN '>=' THEN (field_value)::NUMERIC >= (cond_value)::NUMERIC
            //                                 WHEN '<' THEN (field_value)::NUMERIC < (cond_value)::NUMERIC
            //                                 WHEN '<=' THEN (field_value)::NUMERIC <= (cond_value)::NUMERIC
            //                                 WHEN '=' THEN field_value = cond_value
            //                                 WHEN '!=' THEN field_value <> cond_value
            //                                 WHEN 'LIKE' THEN field_value LIKE cond_value
            //                                 WHEN 'ILIKE' THEN field_value ILIKE cond_value
            //                                 ELSE FALSE
            //                             END
            //                         );
            //                     END IF;
            //                 EXCEPTION WHEN others THEN
            //                     RAISE NOTICE 'Error evaluating condition: %', SQLERRM;
            //                     single_result := FALSE;
            //                 END;
        
            //                 eval_details := eval_details || jsonb_build_object(
            //                     'field', cond->>'field',
            //                     'operator', operator,
            //                     'value', cond_value,
            //                     'result', single_result
            //                 );
            //             END IF;
        
            //             IF logic_op = 'AND' THEN
            //                 result := result AND single_result;
            //                 IF NOT result THEN
            //                     EXIT;
            //                 END IF;
            //             ELSIF logic_op = 'OR' THEN
            //                 result := result OR single_result;
            //                 IF result THEN
            //                     EXIT;
            //                 END IF;
            //             ELSIF logic_op = 'NOT' THEN
            //                 result := NOT single_result;
            //             END IF;
            //         END LOOP;
        
            //         RETURN jsonb_build_object(
            //             'result', result,
            //             'details', eval_details
            //         );
            //     END;
            //     $$ LANGUAGE plpgsql;");
            // }

            // DB::unprepared(
            //     "CREATE OR REPLACE FUNCTION evaluate_workflow_condition(
            //         input_data JSONB,
            //         condition JSONB
            //     ) RETURNS JSONB AS $$
            //     DECLARE
            //         logic_op TEXT;
            //         result BOOLEAN := TRUE;
            //         cond JSONB;
            //         single_result BOOLEAN;
            //         field_value TEXT;
            //         cond_value TEXT;
            //         operator TEXT;
            //         query_tag_name TEXT;
            //         dynamic_query TEXT;
            //         query_result TEXT;
            //         query_type TEXT;
            //         param_list JSONB;
            //         eval_details JSONB := '[]'::JSONB;
            //         param_array TEXT[];
            //         param TEXT;
            //     BEGIN
            //         logic_op := COALESCE(condition->>'logic', 'AND');

            //         FOR cond IN SELECT * FROM jsonb_array_elements(condition->'conditions')
            //         LOOP
            //             IF cond ? 'conditions' THEN
            //                 -- Recursive call for nested conditions
            //                 single_result := (evaluate_workflow_condition(input_data, cond)->>'result')::BOOLEAN;
            //             ELSE
            //                 -- Extract field, operator, and value
            //                 field_value := input_data->>(cond->>'field');
            //                 cond_value := cond->>'value';
            //                 operator := cond->>'operator';
            //                 query_tag_name := cond->>'query_tag_name';
            //                 param_list := cond->'params';

            //                 BEGIN
            //                     IF query_tag_name IS NOT NULL THEN
            //                         -- Fetch query from query_tag
            //                         SELECT query, type INTO dynamic_query, query_type
            //                         FROM query_tag
            //                         WHERE name = query_tag_name;

            //                         IF dynamic_query IS NULL THEN
            //                             RAISE NOTICE 'Query tag % not found', query_tag_name;
            //                             single_result := FALSE;
            //                         ELSE
            //                             -- Convert params JSONB array to PostgreSQL array
            //                             param_array := ARRAY(SELECT jsonb_array_elements_text(param_list));

            //                             IF query_type = 'query' THEN
            //                                 -- Execute dynamic query with parameters
            //                                 EXECUTE dynamic_query
            //                                 INTO query_result
            //                                 USING param_array[1], param_array[2], param_array[3]; -- Adjust as per expected params

            //                                 single_result := (
            //                                     CASE operator
            //                                         WHEN '=' THEN query_result = cond_value
            //                                         WHEN '!=' THEN query_result <> cond_value
            //                                         WHEN '>' THEN (query_result)::NUMERIC > (cond_value)::NUMERIC
            //                                         WHEN '<' THEN (query_result)::NUMERIC < (cond_value)::NUMERIC
            //                                         ELSE FALSE
            //                                     END
            //                                 );
            //                             ELSIF query_type = 'function' THEN
            //                                 -- Build dynamic function call
            //                                 EXECUTE 'SELECT ' || dynamic_query || '(' || array_to_string(param_array, ',') || ')'
            //                                 INTO query_result;

            //                                 single_result := (query_result = cond_value);
            //                             ELSIF query_type = 'procedure' THEN
            //                                 -- Execute procedure
            //                                 EXECUTE dynamic_query USING param_array[1], param_array[2]; -- Adjust params
            //                                 single_result := TRUE;
            //                             END IF;
            //                         END IF;
            //                     ELSE
            //                         -- Regular field comparison
            //                         single_result := (
            //                             CASE operator
            //                                 WHEN '>' THEN (field_value)::NUMERIC > (cond_value)::NUMERIC
            //                                 WHEN '>=' THEN (field_value)::NUMERIC >= (cond_value)::NUMERIC
            //                                 WHEN '<' THEN (field_value)::NUMERIC < (cond_value)::NUMERIC
            //                                 WHEN '<=' THEN (field_value)::NUMERIC <= (cond_value)::NUMERIC
            //                                 WHEN '=' THEN field_value = cond_value
            //                                 WHEN '!=' THEN field_value <> cond_value
            //                                 WHEN 'LIKE' THEN field_value LIKE cond_value
            //                                 WHEN 'ILIKE' THEN field_value ILIKE cond_value
            //                                 ELSE FALSE
            //                             END
            //                         );
            //                     END IF;
            //                 EXCEPTION WHEN others THEN
            //                     RAISE NOTICE 'Error evaluating condition: %', SQLERRM;
            //                     single_result := FALSE;
            //                 END;

            //                 -- Append evaluation details
            //                 eval_details := eval_details || jsonb_build_object(
            //                     'field', cond->>'field',
            //                     'operator', operator,
            //                     'value', cond_value,
            //                     'result', single_result
            //                 );
            //             END IF;

            //             -- Apply Logic (AND/OR/NOT)
            //             IF logic_op = 'AND' THEN
            //                 result := result AND single_result;
            //                 IF NOT result THEN
            //                     EXIT;
            //                 END IF;
            //             ELSIF logic_op = 'OR' THEN
            //                 result := result OR single_result;
            //                 IF result THEN
            //                     EXIT;
            //                 END IF;
            //             ELSIF logic_op = 'NOT' THEN
            //                 result := NOT single_result;
            //             END IF;
            //         END LOOP;

            //         -- Return JSON with result and details
            //         RETURN jsonb_build_object(
            //             'result', result,
            //             'details', eval_details
            //         );
            //     END;
            //     $$ LANGUAGE plpgsql;");
            // }

            // DB::unprepared(<<<SQL
            //     CREATE OR REPLACE FUNCTION evaluate_workflow_condition(
            //         input_data JSONB,
            //         condition JSONB
            //     ) RETURNS JSONB AS $$
            //     DECLARE
            //         logic_op TEXT;
            //         result BOOLEAN := TRUE;
            //         cond JSONB;
            //         single_result BOOLEAN;
            //         field_value TEXT;
            //         cond_value TEXT;
            //         operator TEXT;
            //         query_tag_name TEXT;
            //         dynamic_query TEXT;
            //         query_result TEXT;
            //         query_type TEXT;
            //         param_list JSONB;
            //         eval_details JSONB := '[]'::JSONB;
            //         param_array TEXT[];
            //         param TEXT;
            //         resolved_param TEXT;
            //     BEGIN
            //         RAISE NOTICE '--- START FUNCTION: evaluate_workflow_condition ---';
            //         RAISE NOTICE 'Input Data: %', input_data;
            //         RAISE NOTICE 'Condition: %', condition;

            //         logic_op := COALESCE(condition->>'logic', 'AND');
            //         RAISE NOTICE 'Logic Operator: %', logic_op;

            //         FOR cond IN SELECT * FROM jsonb_array_elements(condition->'conditions')
            //         LOOP
            //             RAISE NOTICE 'Processing Condition: %', cond;

            //             IF cond ? 'conditions' THEN
            //                 single_result := (evaluate_workflow_condition(input_data, cond)->>'result')::BOOLEAN;
            //             ELSE
            //                 field_value := input_data->>(cond->>'field');
            //                 cond_value := cond->>'value';
            //                 operator := cond->>'operator';
            //                 query_tag_name := cond->>'query_tag_name';
            //                 param_list := cond->'params';

            //                 RAISE NOTICE 'Field: %, Expected Value: %, Operator: %, Query Tag: %', field_value, cond_value, operator, query_tag_name;

            //                 BEGIN
            //                     IF query_tag_name IS NOT NULL THEN
            //                         SELECT query, type INTO dynamic_query, query_type
            //                         FROM query_tag
            //                         WHERE name = query_tag_name;

            //                         IF dynamic_query IS NULL THEN
            //                             RAISE NOTICE 'Query tag % not found', query_tag_name;
            //                             single_result := FALSE;
            //                         ELSE
            //                             RAISE NOTICE 'Query Retrieved: %, Type: %', dynamic_query, query_type;

            //                             -- FIXED PARAMETER RESOLUTION
            //                             param_array := ARRAY[]::TEXT[];
            //                             FOR param IN SELECT jsonb_array_elements_text(param_list)
            //                             LOOP
            //                                 IF param LIKE '{{%}}' THEN
            //                                     resolved_param := input_data->>(trim(both '{}' from param));
            //                                 ELSE
            //                                     resolved_param := param;
            //                                 END IF;

            //                                 IF resolved_param IS NULL THEN
            //                                     RAISE NOTICE 'Warning: Parameter % resolved to NULL!', param;
            //                                 END IF;

            //                                 param_array := param_array || resolved_param;
            //                             END LOOP;

            //                             RAISE NOTICE 'Resolved Parameters: %', param_array;

            //                             IF query_type = 'query' THEN
            //                                 RAISE NOTICE 'Executing Query: %', dynamic_query;
            //                                 EXECUTE dynamic_query INTO query_result USING param_array[1];

            //                                 RAISE NOTICE 'Query Result: %', query_result;

            //                                 single_result := (
            //                                     CASE operator
            //                                         WHEN '=' THEN query_result = cond_value
            //                                         WHEN '!=' THEN query_result <> cond_value
            //                                         ELSE FALSE
            //                                     END
            //                                 );
            //                             END IF;
            //                         END IF;
            //                     END IF;
            //                 EXCEPTION WHEN others THEN
            //                     RAISE NOTICE 'Error evaluating condition: %', SQLERRM;
            //                     single_result := FALSE;
            //                 END;

            //                 eval_details := eval_details || jsonb_build_object(
            //                     'field', cond->>'field',
            //                     'operator', operator,
            //                     'value', cond_value,
            //                     'result', single_result
            //                 );
            //             END IF;

            //             IF logic_op = 'AND' THEN
            //                 result := result AND single_result;
            //                 IF NOT result THEN
            //                     EXIT;
            //                 END IF;
            //             ELSIF logic_op = 'OR' THEN
            //                 result := result OR single_result;
            //                 IF result THEN
            //                     EXIT;
            //                 END IF;
            //             ELSIF logic_op = 'NOT' THEN
            //                 result := NOT single_result;
            //             END IF;
            //         END LOOP;

            //         RETURN jsonb_build_object(
            //             'result', result,
            //             'details', eval_details
            //         );
            //     END;
            //     $$ LANGUAGE plpgsql;
            //     SQL);
            // }

            // DB::unprepared(<<<SQL
            //     CREATE OR REPLACE FUNCTION evaluate_workflow_condition(
            //         input_data JSONB,
            //         condition JSONB
            //     ) RETURNS JSONB AS $$
            //     DECLARE
            //         logic_op TEXT;
            //         result BOOLEAN := TRUE;
            //         cond JSONB;
            //         single_result BOOLEAN;
            //         field_value TEXT;
            //         cond_value TEXT;
            //         operator TEXT;
            //         query_tag_name TEXT;
            //         dynamic_query TEXT;
            //         query_result TEXT;
            //         query_type TEXT;
            //         param_list JSONB;
            //         eval_details JSONB := '[]'::JSONB;
            //         param_array TEXT[];
            //         param TEXT;
            //         resolved_param TEXT;
            //     BEGIN
            //         RAISE NOTICE '--- START FUNCTION: evaluate_workflow_condition ---';
            //         RAISE NOTICE 'Input Data: %', input_data;
            //         RAISE NOTICE 'Condition: %', condition;

            //         logic_op := COALESCE(condition->>'logic', 'AND');
            //         RAISE NOTICE 'Logic Operator: %', logic_op;

            //         FOR cond IN SELECT * FROM jsonb_array_elements(condition->'conditions')
            //         LOOP
            //             RAISE NOTICE 'Processing Condition: %', cond;

            //             IF cond ? 'conditions' THEN
            //                 -- Recursively evaluate nested conditions
            //                 single_result := (evaluate_workflow_condition(input_data, cond)->>'result')::BOOLEAN;
            //             ELSE
            //                 -- Extract values
            //                 field_value := input_data->>(cond->>'field');
            //                 cond_value := cond->>'value';
            //                 operator := cond->>'operator';
            //                 query_tag_name := cond->>'query_tag_name';
            //                 param_list := cond->'params';

            //                 RAISE NOTICE 'Field: %, Expected Value: %, Operator: %, Query Tag: %', field_value, cond_value, operator, query_tag_name;

            //                 BEGIN
            //                     IF query_tag_name IS NOT NULL THEN
            //                         -- Query-based condition evaluation
            //                         SELECT query, type INTO dynamic_query, query_type
            //                         FROM query_tag
            //                         WHERE name = query_tag_name;

            //                         IF dynamic_query IS NULL THEN
            //                             RAISE NOTICE 'Query tag % not found', query_tag_name;
            //                             single_result := FALSE;
            //                         ELSE
            //                             RAISE NOTICE 'Query Retrieved: %, Type: %', dynamic_query, query_type;

            //                             -- Resolve parameters
            //                             param_array := ARRAY[]::TEXT[];
            //                             FOR param IN SELECT jsonb_array_elements_text(param_list)
            //                             LOOP
            //                                 IF param LIKE '{{%}}' THEN
            //                                     resolved_param := input_data->>(trim(both '{}' from param));
            //                                 ELSE
            //                                     resolved_param := param;
            //                                 END IF;

            //                                 param_array := param_array || resolved_param;
            //                             END LOOP;

            //                             RAISE NOTICE 'Resolved Parameters: %', param_array;

            //                             -- Execute the query
            //                             IF query_type = 'query' THEN
            //                                 RAISE NOTICE 'Executing Query: %', dynamic_query;
            //                                 EXECUTE dynamic_query INTO query_result USING param_array[1];

            //                                 RAISE NOTICE 'Query Result: %', query_result;

            //                                 single_result := (
            //                                     CASE operator
            //                                         WHEN '=' THEN query_result = cond_value
            //                                         WHEN '!=' THEN query_result <> cond_value
            //                                         ELSE FALSE
            //                                     END
            //                                 );
            //                             END IF;
            //                         END IF;
            //                     ELSE
            //                         -- Direct field-based condition evaluation
            //                         IF field_value IS NOT NULL THEN
            //                             single_result := (
            //                                 CASE operator
            //                                     WHEN '=' THEN field_value = cond_value
            //                                     WHEN '!=' THEN field_value <> cond_value
            //                                     WHEN '>' THEN field_value::NUMERIC > cond_value::NUMERIC
            //                                     WHEN '>=' THEN field_value::NUMERIC >= cond_value::NUMERIC
            //                                     WHEN '<' THEN field_value::NUMERIC < cond_value::NUMERIC
            //                                     WHEN '<=' THEN field_value::NUMERIC <= cond_value::NUMERIC
            //                                     ELSE FALSE
            //                                 END
            //                             );
            //                         ELSE
            //                             single_result := FALSE; -- If field is missing, fail the condition
            //                         END IF;
            //                     END IF;
            //                 EXCEPTION WHEN others THEN
            //                     RAISE NOTICE 'Error evaluating condition: %', SQLERRM;
            //                     single_result := FALSE;
            //                 END;

            //                 eval_details := eval_details || jsonb_build_object(
            //                     'field', cond->>'field',
            //                     'operator', operator,
            //                     'value', cond_value,
            //                     'result', single_result
            //                 );
            //             END IF;

            //             -- Apply logic (AND, OR, NOT)
            //             IF logic_op = 'AND' THEN
            //                 result := result AND single_result;
            //                 IF NOT result THEN EXIT; END IF;
            //             ELSIF logic_op = 'OR' THEN
            //                 result := result OR single_result;
            //                 IF result THEN EXIT; END IF;
            //             ELSIF logic_op = 'NOT' THEN
            //                 result := NOT single_result;
            //             END IF;
            //         END LOOP;

            //         RETURN jsonb_build_object(
            //             'result', result,
            //             'details', eval_details
            //         );
            //     END;
            //     $$ LANGUAGE plpgsql;
            // SQL);
            
            // DB::unprepared(<<<SQL
            //     CREATE OR REPLACE FUNCTION evaluate_workflow_condition(
            //         input_data JSONB,
            //         condition JSONB
            //     ) RETURNS JSONB AS $$
            //     DECLARE
            //         logic_op TEXT;
            //         result BOOLEAN := TRUE;
            //         cond JSONB;
            //         single_result BOOLEAN;
            //         field_value TEXT;
            //         cond_value TEXT;
            //         operator TEXT;
            //         query_tag_name TEXT;
            //         dynamic_query TEXT;
            //         query_result TEXT;
            //         query_type TEXT;
            //         param_list JSONB;
            //         eval_details JSONB := '[]'::JSONB;
            //         param_array TEXT[];
            //         param TEXT;
            //         resolved_param TEXT;
            //     BEGIN
            //         RAISE NOTICE '--- START FUNCTION: evaluate_workflow_condition ---';
            //         RAISE NOTICE 'Input Data: %', input_data;
            //         RAISE NOTICE 'Condition: %', condition;

            //         logic_op := COALESCE(condition->>'logic', 'AND');
            //         RAISE NOTICE 'Logic Operator: %', logic_op;

            //         FOR cond IN SELECT * FROM jsonb_array_elements(condition->'conditions')
            //         LOOP
            //             RAISE NOTICE 'Processing Condition: %', cond;

            //             IF cond ? 'conditions' THEN
            //                 -- Recursively evaluate nested conditions
            //                 single_result := (evaluate_workflow_condition(input_data, cond)->>'result')::BOOLEAN;
            //             ELSE
            //                 -- Extract values
            //                 field_value := input_data->>(cond->>'field');
            //                 cond_value := cond->>'value';
            //                 operator := cond->>'operator';
            //                 query_tag_name := cond->>'query_tag_name';
            //                 param_list := cond->'params';

            //                 RAISE NOTICE 'Field: %, Expected Value: %, Operator: %, Query Tag: %', field_value, cond_value, operator, query_tag_name;

            //                 BEGIN
            //                     IF query_tag_name IS NOT NULL THEN
            //                         -- Query-based condition evaluation
            //                         SELECT query, type INTO dynamic_query, query_type
            //                         FROM query_tag
            //                         WHERE name = query_tag_name;

            //                         IF dynamic_query IS NULL THEN
            //                             RAISE NOTICE 'Query tag % not found', query_tag_name;
            //                             single_result := FALSE;
            //                         ELSE
            //                             RAISE NOTICE 'Query Retrieved: %, Type: %', dynamic_query, query_type;

            //                             -- Resolve parameters
            //                             param_array := ARRAY[]::TEXT[];
            //                             FOR param IN SELECT jsonb_array_elements_text(param_list)
            //                             LOOP
            //                                 IF param LIKE '{{%}}' THEN
            //                                     resolved_param := input_data->>(trim(both '{}' from param));
            //                                 ELSE
            //                                     resolved_param := param;
            //                                 END IF;
            //                                 param_array := param_array || resolved_param;
            //                             END LOOP;

            //                             RAISE NOTICE 'Resolved Parameters: %', param_array;

            //                             -- Execute the query
            //                             IF query_type = 'query' THEN
            //                                 RAISE NOTICE 'Executing Query: %', dynamic_query;
            //                                 EXECUTE dynamic_query INTO query_result USING param_array[1];

            //                                 RAISE NOTICE 'Query Result: %', query_result;

            //                                 single_result := (
            //                                     CASE operator
            //                                         WHEN '=' THEN query_result = cond_value
            //                                         WHEN '!=' THEN query_result <> cond_value
            //                                         ELSE FALSE
            //                                     END
            //                                 );
            //                             END IF;
            //                         END IF;
            //                     ELSE
            //                         -- Direct field-based condition evaluation
            //                         IF field_value IS NOT NULL THEN
            //                             single_result := (
            //                                 CASE operator
            //                                     WHEN '=' THEN field_value = cond_value
            //                                     WHEN '!=' THEN field_value <> cond_value
            //                                     WHEN '>' THEN field_value::NUMERIC > cond_value::NUMERIC
            //                                     WHEN '>=' THEN field_value::NUMERIC >= cond_value::NUMERIC
            //                                     WHEN '<' THEN field_value::NUMERIC < cond_value::NUMERIC
            //                                     WHEN '<=' THEN field_value::NUMERIC <= cond_value::NUMERIC
            //                                     ELSE FALSE
            //                                 END
            //                             );
            //                         ELSE
            //                             single_result := FALSE; -- If field is missing, fail the condition
            //                         END IF;
            //                     END IF;
            //                 EXCEPTION WHEN others THEN
            //                     RAISE NOTICE 'Error evaluating condition: %', SQLERRM;
            //                     single_result := FALSE;
            //                 END;

            //                 -- FIX: Ensure 'field' is never NULL in details
            //                 eval_details := eval_details || jsonb_build_object(
            //                     'field', COALESCE(cond->>'field', query_tag_name),
            //                     'operator', operator,
            //                     'value', cond_value,
            //                     'result', single_result
            //                 );
            //             END IF;

            //             -- Apply logic (AND, OR, NOT)
            //             IF logic_op = 'AND' THEN
            //                 result := result AND single_result;
            //                 IF NOT result THEN EXIT; END IF;
            //             ELSIF logic_op = 'OR' THEN
            //                 result := result OR single_result;
            //                 IF result THEN EXIT; END IF;
            //             ELSIF logic_op = 'NOT' THEN
            //                 result := NOT single_result;
            //             END IF;
            //         END LOOP;

            //         RETURN jsonb_build_object(
            //             'result', result,
            //             'details', eval_details
            //         );
            //     END;
            //     $$ LANGUAGE plpgsql;
            // SQL);



            DB::unprepared(<<<SQL
                CREATE OR REPLACE FUNCTION evaluate_workflow_condition(
                    input_data JSONB,
                    condition JSONB
                ) RETURNS JSONB AS $$
                DECLARE
                    logic_op TEXT;
                    result BOOLEAN := NULL;
                    cond JSONB;
                    single_result BOOLEAN;
                    field_value TEXT;
                    cond_value TEXT;
                    operator TEXT;
                    query_tag_name TEXT;
                    dynamic_query TEXT;
                    query_result TEXT;
                    query_type TEXT;
                    param_list JSONB;
                    eval_details JSONB := '[]'::JSONB;
                    param_array TEXT[];
                    param TEXT;
                    resolved_param TEXT;
                BEGIN
                    RAISE NOTICE '--- START FUNCTION: evaluate_workflow_condition ---';
                    RAISE NOTICE 'Input Data: %', input_data;
                    RAISE NOTICE 'Condition: %', condition;

                    logic_op := COALESCE(condition->>'logic', 'AND');
                    RAISE NOTICE 'Logic Operator: %', logic_op;

                    -- Iterate through each condition
                    FOR cond IN SELECT * FROM jsonb_array_elements(condition->'conditions')
                    LOOP
                        RAISE NOTICE 'Processing Condition: %', cond;

                        -- **Recursive Evaluation for Nested Conditions**
                        IF cond ? 'conditions' THEN
                            single_result := (evaluate_workflow_condition(input_data, cond)->>'result')::BOOLEAN;
                        ELSE
                            -- **Extract Direct Condition Values**
                            field_value := input_data->>(cond->>'field');
                            cond_value := cond->>'value';
                            operator := cond->>'operator';
                            query_tag_name := cond->>'query_tag_name';
                            param_list := cond->'params';

                            RAISE NOTICE 'Field: %, Expected Value: %, Operator: %, Query Tag: %', field_value, cond_value, operator, query_tag_name;

                            BEGIN
                                -- **Query-Based Condition Evaluation**
                                IF query_tag_name IS NOT NULL THEN
                                    SELECT query, type INTO dynamic_query, query_type
                                    FROM workflow_condition_query_tag
                                    WHERE value = query_tag_name;

                                    IF dynamic_query IS NULL THEN
                                        RAISE NOTICE 'Query tag % not found', query_tag_name;
                                        single_result := FALSE;
                                    ELSE
                                        RAISE NOTICE 'Query Retrieved: %, Type: %', dynamic_query, query_type;

                                        -- **Resolve Parameters**
                                        param_array := ARRAY[]::TEXT[];
                                        FOR param IN SELECT jsonb_array_elements_text(param_list)
                                        LOOP
                                            IF param LIKE '{{%}}' THEN
                                                resolved_param := input_data->>(trim(both '{}' from param));
                                            ELSE
                                                resolved_param := param;
                                            END IF;
                                            param_array := param_array || resolved_param;
                                        END LOOP;

                                        RAISE NOTICE 'Resolved Parameters: %', param_array;

                                        -- **Execute Query**
                                        IF query_type = 'query' THEN
                                            EXECUTE dynamic_query INTO query_result USING param_array[1];

                                            RAISE NOTICE 'Query Result: %', query_result;

                                            single_result := (
                                                CASE operator
                                                    WHEN '=' THEN query_result = cond_value
                                                    WHEN '!=' THEN query_result <> cond_value
                                                    ELSE FALSE
                                                END
                                            );
                                        END IF;
                                    END IF;
                                ELSE
                                    -- **Direct Field-Based Condition Evaluation**
                                    IF field_value IS NOT NULL THEN
                                        single_result := (
                                            CASE operator
                                                WHEN '=' THEN field_value = cond_value
                                                WHEN '!=' THEN field_value <> cond_value
                                                WHEN '>' THEN field_value::NUMERIC > cond_value::NUMERIC
                                                WHEN '>=' THEN field_value::NUMERIC >= cond_value::NUMERIC
                                                WHEN '<' THEN field_value::NUMERIC < cond_value::NUMERIC
                                                WHEN '<=' THEN field_value::NUMERIC <= cond_value::NUMERIC
                                                ELSE FALSE
                                            END
                                        );
                                    ELSE
                                        single_result := FALSE; -- Fail condition if field is missing
                                    END IF;
                                END IF;
                            EXCEPTION WHEN others THEN
                                RAISE NOTICE 'Error evaluating condition: %', SQLERRM;
                                single_result := FALSE;
                            END;

                            -- **Store Evaluation Details**
                            eval_details := eval_details || jsonb_build_object(
                                'field', COALESCE(cond->>'field', query_tag_name),
                                'operator', operator,
                                'value', cond_value,
                                'result', single_result
                            );
                        END IF;

                        -- **Logic Handling**
                        IF logic_op = 'AND' THEN
                            result := COALESCE(result, TRUE) AND single_result;
                            IF NOT result THEN EXIT; END IF; -- Short-circuit if FALSE
                        ELSIF logic_op = 'OR' THEN
                            result := COALESCE(result, FALSE) OR single_result;
                            IF result THEN EXIT; END IF; -- Short-circuit if TRUE
                        ELSIF logic_op = 'NOT' THEN
                            result := NOT single_result;
                        END IF;
                    END LOOP;

                    -- **Return JSON Result**
                    RETURN jsonb_build_object(
                        'result', COALESCE(result, FALSE),
                        'details', eval_details
                    );
                END;
                $$ LANGUAGE plpgsql;
            SQL);



            // DB::unprepared(<<<SQL
            //     CREATE OR REPLACE FUNCTION evaluate_workflow_condition(
            //         input_data JSONB,
            //         condition JSONB
            //     ) RETURNS JSONB AS $$
            //     DECLARE
            //         logic_op TEXT;
            //         result BOOLEAN := NULL;
            //         cond JSONB;
            //         single_result BOOLEAN;
            //         field_value TEXT;
            //         cond_value TEXT;
            //         operator TEXT;
            //         query_tag_name TEXT;
            //         dynamic_query TEXT;
            //         query_result TEXT;
            //         query_type TEXT;
            //         param_list JSONB;
            //         eval_details JSONB := '[]'::JSONB;
            //         param_array TEXT[];
            //         param TEXT;
            //         resolved_param TEXT;
            //     BEGIN
            //         logic_op := COALESCE(condition->>'logic', 'AND');

            //         -- Iterate through each condition
            //         FOR cond IN SELECT * FROM jsonb_array_elements(condition->'conditions')
            //         LOOP
            //             -- If nested conditions exist, evaluate recursively
            //             IF cond ? 'conditions' THEN
            //                 single_result := (evaluate_workflow_condition(input_data, cond)->>'result')::BOOLEAN;
            //             ELSE
            //                 -- Extract condition values
            //                 field_value := input_data->>(cond->>'field');
            //                 cond_value := cond->>'value';
            //                 operator := cond->>'operator';
            //                 query_tag_name := cond->>'query_tag_name';
            //                 param_list := cond->'params';

            //                 -- Evaluate Query-Based Conditions
            //                 IF query_tag_name IS NOT NULL THEN
            //                     SELECT query, type INTO dynamic_query, query_type
            //                     FROM workflow_condition_query_tag
            //                     WHERE value = query_tag_name;

            //                     IF dynamic_query IS NULL THEN
            //                         single_result := FALSE;
            //                     ELSE
            //                         -- Resolve Parameters
            //                         param_array := ARRAY[]::TEXT[];
            //                         FOR param IN SELECT jsonb_array_elements_text(param_list)
            //                         LOOP
            //                             resolved_param := input_data->>(trim(both '{}' from param));
            //                             param_array := param_array || resolved_param;
            //                         END LOOP;

            //                         -- Execute Query
            //                         IF query_type = 'query' THEN
            //                             EXECUTE dynamic_query INTO query_result USING param_array[1];

            //                             single_result := (
            //                                 CASE operator
            //                                     WHEN '=' THEN query_result = cond_value
            //                                     WHEN '!=' THEN query_result <> cond_value
            //                                     ELSE FALSE
            //                                 END
            //                             );
            //                         END IF;
            //                     END IF;
            //                 ELSE
            //                     -- Evaluate Field-Based Conditions
            //                     single_result := (
            //                         CASE operator
            //                             WHEN '=' THEN field_value = cond_value
            //                             WHEN '!=' THEN field_value <> cond_value
            //                             WHEN '>' THEN field_value::NUMERIC > cond_value::NUMERIC
            //                             WHEN '>=' THEN field_value::NUMERIC >= cond_value::NUMERIC
            //                             WHEN '<' THEN field_value::NUMERIC < cond_value::NUMERIC
            //                             WHEN '<=' THEN field_value::NUMERIC <= cond_value::NUMERIC
            //                             ELSE FALSE
            //                         END
            //                     );
            //                 END IF;
            //             END IF;

            //             -- Capture Evaluation Details
            //             eval_details := eval_details || jsonb_build_object(
            //                 'field', cond->>'field',
            //                 'operator', operator,
            //                 'value', cond_value,
            //                 'result', single_result
            //             );

            //             -- Correct Logical Operations Handling
            //             IF logic_op = 'AND' THEN
            //                 result := COALESCE(result, TRUE) AND single_result;
            //                 IF NOT result THEN EXIT; END IF;
            //             ELSIF logic_op = 'OR' THEN
            //                 result := COALESCE(result, FALSE) OR single_result;
            //                 IF result THEN EXIT; END IF;
            //             ELSIF logic_op = 'NOT' THEN
            //                 result := NOT single_result;
            //             END IF;
            //         END LOOP;

            //         -- Return JSON Result
            //         RETURN jsonb_build_object(
            //             'result', COALESCE(result, FALSE),
            //             'details', eval_details
            //         );
            //     END;
            //     $$ LANGUAGE plpgsql;
            // SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_sub_categories_reading_parameters');
    }
};