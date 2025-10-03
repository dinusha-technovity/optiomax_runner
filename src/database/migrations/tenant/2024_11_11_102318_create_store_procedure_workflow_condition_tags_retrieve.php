<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(
            "CREATE OR REPLACE PROCEDURE store_procedure_retrieve_workflow_condition_tags(
                IN p_workflow_condition_tag_id INT DEFAULT NULL
            ) LANGUAGE plpgsql AS
            $$
            BEGIN
                DROP TABLE IF EXISTS workflow_condition_tag_from_store_procedure;
            
                IF p_workflow_condition_tag_id IS NOT NULL AND p_workflow_condition_tag_id <= 0 THEN
                    RAISE EXCEPTION 'Invalid p_workflow_condition_tag_id: %', p_workflow_condition_tag_id;
                END IF;
            
                CREATE TEMP TABLE workflow_condition_tag_from_store_procedure AS
                SELECT
                    wctd.tag_name,
                    wctd.workflow_request_types,
                    wrt.request_type
                FROM workflow_condition_tag_definitions wctd
                INNER JOIN
                    workflow_request_types wrt ON wctd.workflow_request_types = wrt.id
                WHERE (wctd.id = p_workflow_condition_tag_id 
                    OR p_workflow_condition_tag_id IS NULL 
                    OR p_workflow_condition_tag_id = 0)
                    AND wctd.deleted_at IS NULL
                    AND wctd.isactive = TRUE;
            END;
            $$;"
        );        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS store_procedure_retrieve_organization');
    }
};