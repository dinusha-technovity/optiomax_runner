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
            DROP FUNCTION IF EXISTS get_sidebar_with_permission(INT, BIGINT);

            CREATE OR REPLACE FUNCTION get_sidebar_with_permission(
                p_user_id INT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                parent_id BIGINT,
                menuname TEXT,
                menulink TEXT,
                icon TEXT,
                isconfiguration BOOLEAN,
                menu_order BIGINT,
                isactive BOOLEAN
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
                    p.id,
                    p.parent_id,
                    p.name::TEXT AS menuname,
                    p.menulink::TEXT,
                    p.icon::TEXT,
                    p.isconfiguration,
                    p.menu_order,
                    p.isactive
                FROM users u
                INNER JOIN role_user ru ON u.id = ru.user_id
                INNER JOIN roles r ON ru.role_id = r.id
                INNER JOIN permission_role pr ON r.id = pr.role_id
                INNER JOIN permissions p ON pr.permission_id = p.id
                WHERE u.id = p_user_id
                    AND (u.tenant_id = p_tenant_id OR p_tenant_id IS NULL)
                    AND u.deleted_at IS NULL
                    AND p.ismenu_list = TRUE;
            END;
            $$;
        SQL);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_sidebar_with_permission');
    }
};