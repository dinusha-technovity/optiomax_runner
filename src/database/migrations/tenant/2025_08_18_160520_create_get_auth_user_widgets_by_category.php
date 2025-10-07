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
            DROP FUNCTION IF EXISTS get_auth_user_widgets_by_category(INT, BIGINT);

            CREATE OR REPLACE FUNCTION get_auth_user_widgets_by_category(
                p_user_id INT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                user_exists BOOLEAN;
                result JSONB;
            BEGIN
                -- Validate user_id
                IF p_user_id IS NULL OR p_user_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid user ID provided'::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if user exists
                SELECT EXISTS (
                    SELECT 1 FROM users u
                    WHERE u.id = p_user_id AND u.deleted_at IS NULL
                ) INTO user_exists;

                IF NOT user_exists THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'No matching user found'::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                -- Build JSON grouped by category (deduplicate widgets)
                WITH widget_data AS (
                    SELECT DISTINCT
                        wc.category_name,
                        w.id,
                        jsonb_build_object(
                            'id', w.id,
                            'widget_name', w.widget_type, -- replace with "name" if available
                            'image_path', w.image_path,
                            'design_obj', w.design_obj,
                            'design_component', w.design_component,
                            'settings', rw.settings
                        ) AS widget_info
                    FROM users u
                    INNER JOIN role_user ru ON u.id = ru.user_id
                    INNER JOIN roles r ON ru.role_id = r.id
                    INNER JOIN role_widget rw ON r.id = rw.role_id
                    INNER JOIN app_widgets w ON rw.widget_id = w.id
                    INNER JOIN app_widgets_categories wc ON w.category_id = wc.id
                    WHERE u.id = p_user_id
                    AND rw.is_active = TRUE
                    AND rw.deleted_at IS NULL
                    AND (rw.tenant_id = p_tenant_id OR p_tenant_id IS NULL)
                    AND u.deleted_at IS NULL
                ),
                grouped_data AS (
                    SELECT
                        category_name,
                        jsonb_agg(widget_info) AS category_widgets
                    FROM widget_data
                    GROUP BY category_name
                )
                SELECT jsonb_agg(
                    jsonb_build_object(
                        'category_name', category_name,
                        'widgets', category_widgets
                    )
                )
                INTO result
                FROM grouped_data;

                -- If no widgets found
                IF result IS NULL OR jsonb_array_length(result) = 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'No widgets found for this user'::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                -- Return success
                RETURN QUERY SELECT
                    'SUCCESS'::TEXT,
                    'Widgets fetched successfully'::TEXT,
                    result;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_auth_user_widgets_by_category(INT, BIGINT);");
    }
};