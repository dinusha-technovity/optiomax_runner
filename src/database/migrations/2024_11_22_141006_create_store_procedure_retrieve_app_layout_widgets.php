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
            "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_RETRIEVE_APP_DASHBOARD_LAYOUT_WIDGETS(
                IN p_layout_id INT DEFAULT NULL
            )
            AS $$
            BEGIN
                DROP TABLE IF EXISTS app_dashboard_layout_from_store_procedure;

                CREATE TEMP TABLE app_dashboard_layout_from_store_procedure AS
                SELECT * FROM
                    app_layout_widgets 
                WHERE
                    app_layout_widgets.id = p_layout_id OR p_layout_id IS NULL OR p_layout_id = 0
                ORDER BY app_layout_widgets.id;
            END;
            $$ LANGUAGE plpgsql;"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS STORE_PROCEDURE_RETRIEVE_APP_DASHBOARD_LAYOUT_WIDGETS');
    }
};
