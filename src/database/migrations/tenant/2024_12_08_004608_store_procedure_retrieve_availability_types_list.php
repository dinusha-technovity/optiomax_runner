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
        DB::unprepared(<<<SQL
            CREATE OR REPLACE PROCEDURE store_procedure_retrieve_availability_types_list(  
                IN p_availability_type_id INT DEFAULT NULL
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                DROP TABLE IF EXISTS availability_type_list_from_store_procedure;
            
                IF p_availability_type_id IS NOT NULL AND p_availability_type_id <= 0 THEN
                    RAISE EXCEPTION 'Invalid p_availability_type_id: %', p_availability_type_id;
                END IF;
            
                CREATE TEMP TABLE availability_type_list_from_store_procedure AS
                SELECT
                    arat.id,
                    arat.name
                FROM
                    asset_requisition_availability_types arat
                WHERE
                    (arat.id = p_availability_type_id OR p_availability_type_id IS NULL OR p_availability_type_id = 0)
                    AND arat.deleted_at IS NULL
                    AND arat."isactive" = TRUE;
            END;
            $$;
            SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS store_procedure_retrieve_availability_types_list');
    }
};
