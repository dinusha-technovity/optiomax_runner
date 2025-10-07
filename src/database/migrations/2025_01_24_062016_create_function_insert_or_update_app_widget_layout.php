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
        CREATE OR REPLACE FUNCTION insert_or_update_dashboard_portal_layout_widget(
            p_x DOUBLE PRECISION,
            p_y DOUBLE PRECISION,
            p_w DOUBLE PRECISION,
            p_h DOUBLE PRECISION,
            p_style TEXT,
            p_widget_id BIGINT,
            p_widget_type TEXT,
            p_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            widget_id BIGINT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            return_layout_id BIGINT;
        BEGIN
            IF p_id IS NULL OR p_id = 0 THEN
                -- Check if a matching widget already exists
                SELECT id INTO return_layout_id
                FROM portal_layout_widgets
                WHERE x = p_x AND y = p_y AND w = p_w AND h = p_h AND style = p_style;
        
                IF NOT FOUND THEN
                    -- Insert a new widget if no match is found
                    INSERT INTO portal_layout_widgets 
                    (
                        x, y, w, h, style, widget_id, widget_type, created_at, updated_at
                    ) VALUES 
                    (
                        p_x, p_y, p_w, p_h, p_style, p_widget_id, p_widget_type, NOW(), NOW()
                    ) RETURNING id INTO return_layout_id;
        
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'Widget added successfully'::TEXT AS message,
                        return_layout_id AS widget_id;
                ELSE
                    -- Update the `updated_at` field if a match is found
                    UPDATE portal_layout_widgets
                    SET updated_at = NOW()
                    WHERE id = return_layout_id;
        
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'Widget already exists and was updated'::TEXT AS message,
                        return_layout_id AS widget_id;
                END IF;
            ELSE
                -- Update an existing widget if `p_id` is provided
                UPDATE portal_layout_widgets
                SET 
                    x = p_x,
                    y = p_y,
                    w = p_w,
                    h = p_h,
                    style = p_style,
                    widget_id = p_widget_id,
                    widget_type = p_widget_type,
                    updated_at = NOW()
                WHERE id = p_id RETURNING id INTO return_layout_id;
        
                IF FOUND THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'Widget updated successfully'::TEXT AS message,
                        return_layout_id AS widget_id;
                ELSE
                    -- Insert a new widget if no matching ID is found
                    INSERT INTO portal_layout_widgets 
                    (
                        x, y, w, h, style, widget_id, widget_type, created_at, updated_at
                    ) VALUES 
                    (
                        p_x, p_y, p_w, p_h, p_style, p_widget_id, p_widget_type, NOW(), NOW()
                    ) RETURNING id INTO return_layout_id;
        
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'Widget added successfully'::TEXT AS message,
                        return_layout_id AS widget_id;
                END IF;
            END IF;
        EXCEPTION WHEN OTHERS THEN
            -- Handle exceptions and return an error
            RETURN QUERY SELECT 
                'ERROR'::TEXT AS status,
                SQLERRM::TEXT AS message,
                NULL::BIGINT AS widget_id;
        END;
        $$;
        SQL);
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_dashboard_portal_layout_widget');
    }
};
