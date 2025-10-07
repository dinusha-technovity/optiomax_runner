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
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_app_dashboard_layout_widget(
                p_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate input: Layout Widget ID must not be NULL or zero
                IF p_id IS NULL OR p_id = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Layout Widget ID cannot be null or zero'::TEXT AS message;
                    RETURN;
                END IF;
            
                -- Check if the Layout Widget ID exists
                IF NOT EXISTS (SELECT 1 FROM portal_layout_widgets WHERE id = p_id) THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        format('Layout Widget ID % does not exist', p_id)::TEXT AS message;
                    RETURN;
                END IF;
            
                -- Perform the deletion
                DELETE FROM portal_layout_widgets WHERE id = p_id;
            
                -- Return success message
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status, 
                    'Layout Widget deleted successfully'::TEXT AS message;
            END;
            $$;
        SQL);        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_app_dashboard_layout_widget');
    }
};
