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
            CREATE OR REPLACE FUNCTION get_portal_sidebar_menu_list(
                p_user_id INT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                parent_id BIGINT,
                label TEXT,
                key TEXT,
                icon TEXT,
                href TEXT,
                level INT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                user_exists BOOLEAN;
            BEGIN
                IF p_user_id IS NULL OR p_user_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE', 'Invalid user ID provided',
                        NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;

                SELECT EXISTS (
                    SELECT 1 FROM users u
                    WHERE u.id = p_user_id AND u.deleted_at IS NULL
                ) INTO user_exists;

                IF NOT user_exists THEN
                    RETURN QUERY SELECT 
                        'FAILURE', 'No matching user found',
                        NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;

                RETURN QUERY
                SELECT
                    'SUCCESS',
                    'Sidebar items fetched successfully',
                    sm.id,
                    sm.parent_id,
                    sm.label::text,
                    sm.key::text,
                    sm.icon::text,
                    sm.href::text,
                    sm.level
                FROM portal_sidebar_menu_list sm
                ORDER BY sm.level, sm.parent_id, sm.id;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_portal_sidebar_menu_list;");
    }
};
