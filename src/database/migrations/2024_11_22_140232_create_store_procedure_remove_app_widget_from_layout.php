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
        DB::unprepared(
            "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_REMOVE_APP_DASHBOARD_LAYOUT_WIDGET(
                p_id BIGINT DEFAULT NULL
            ) LANGUAGE plpgsql
            AS $$
            BEGIN 
                IF p_id IS NULL OR p_id = 0 THEN
                    RAISE EXCEPTION 'Layout Widget ID cannot be null or zero';
                END IF;

                IF NOT EXISTS (SELECT 1 FROM app_layout_widgets WHERE id = p_id) THEN
                    RAISE EXCEPTION 'Layout Widget ID % does not exist', p_id;
                END IF;

                DELETE FROM app_layout_widgets WHERE id = p_id;
            END;
            $$;"
        );
    } 

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS STORE_PROCEDURE_REMOVE_APP_DASHBOARD_LAYOUT_WIDGET');
    }
};
