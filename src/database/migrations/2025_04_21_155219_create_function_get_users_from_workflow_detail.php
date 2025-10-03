<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{ 
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_users_from_workflow_detail(workflow_id_alias BIGINT)
            RETURNS TABLE (
                id BIGINT,
                name TEXT,
                email TEXT,
                designation_id BIGINT,
                profile_image TEXT
            ) AS $$
            DECLARE
                json_data JSON;
                user_ids BIGINT[] := '{}';
                designation_ids BIGINT[] := '{}';
                elem JSON;
                u JSON;
                d JSON;
            BEGIN
                SELECT workflow_detail_data_object INTO json_data
                FROM workflow_details
                WHERE workflow_details.id = workflow_id_alias;

                IF json_data IS NULL THEN
                    RETURN;
                END IF;

                FOR elem IN SELECT * FROM json_array_elements(json_data)
                LOOP
                    IF elem ->> 'behaviourType' = 'EMPLOYEE' THEN
                        FOR u IN SELECT * FROM json_array_elements(elem -> 'users')
                        LOOP
                            user_ids := array_append(user_ids, (u ->> 'id')::BIGINT);
                        END LOOP;
                    ELSIF elem ->> 'behaviourType' = 'DESIGNATION' THEN
                        FOR d IN SELECT * FROM json_array_elements(elem -> 'designation')
                        LOOP
                            designation_ids := array_append(designation_ids, (d ->> 'id')::BIGINT);
                        END LOOP;
                    END IF;
                END LOOP;

                RETURN QUERY
                SELECT 
                    u.id,
                    u.name::TEXT,
                    u.email::TEXT,
                    u.designation_id,
                    u.profile_image::TEXT
                FROM users u
                WHERE u.id = ANY(user_ids)
                OR u.designation_id = ANY(designation_ids);
            END;
            $$ LANGUAGE plpgsql;
        SQL);        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_users_from_workflow_detail');
    }
};