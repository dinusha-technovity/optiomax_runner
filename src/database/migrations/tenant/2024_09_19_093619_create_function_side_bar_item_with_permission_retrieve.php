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
        // $procedure = <<<SQL
        //         CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_SIDEBAR_WITH_PERMISSION( 
        //             IN p_user_id INT DEFAULT NULL
        //         )
        //         LANGUAGE plpgsql
        //         AS $$
        //         BEGIN
        //             DROP TABLE IF EXISTS sidebar_item_from_store_procedure;
                
        //             IF p_user_id IS NOT NULL AND p_user_id <= 0 THEN
        //                 RAISE EXCEPTION 'Invalid p_user_id: %', p_user_id;
        //             END IF;
                
        //             CREATE TEMP TABLE sidebar_item_from_store_procedure AS
        //             SELECT
        //                 mn.id,
        //                 mn.permission_id,
        //                 mn.parent_id,
        //                 mn.menuname,
        //                 mn.menulink,
        //                 mn.icon,
        //                 mn.isactive
        //             FROM
        //                 users u
        //             INNER JOIN
        //                 role_user ru ON u.id = ru.user_id
        //             INNER JOIN
        //                 roles r ON ru.role_id = r.id
        //             INNER JOIN
        //                 permission_role pr ON r.id = pr.role_id 
        //             INNER JOIN
        //                 permissions p ON pr.permission_id = p.id
        //             INNER JOIN
        //                 menu_list mn ON mn.permission_id = p.id 
        //             WHERE
        //                 (u.id = p_user_id OR p_user_id IS NULL OR p_user_id = 0)
        //                 AND u.deleted_at IS NULL
        //             GROUP BY
        //             mn.id, mn.permission_id, mn.parent_id, mn.menuname, mn.menulink, mn.icon, mn.isactive;
        //         END;
        //         \$\$;
        // SQL; 
                
        // // Execute the SQL statement
        // DB::unprepared($procedure);
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_sidebar_with_permission(
                p_user_id INT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                permission_id BIGINT,
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
                user_count INT;
            BEGIN
                -- Validate p_user_id
                IF p_user_id IS NULL OR p_user_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid user ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::BIGINT AS permission_id,
                        NULL::BIGINT AS parent_id,
                        NULL::TEXT AS menuname,
                        NULL::TEXT AS menulink,
                        NULL::TEXT AS icon,
                        NULL::BIGINT AS isconfiguration,
                        NULL::BOOLEAN AS menu_order,
                        NULL::BOOLEAN AS isactive;
                    RETURN;
                END IF;
            
                -- Check if the user exists
                SELECT COUNT(*) INTO user_count
                FROM users u
                WHERE u.id = p_user_id
                AND u.deleted_at IS NULL;
            
                IF user_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching user found'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::BIGINT AS permission_id,
                        NULL::BIGINT AS parent_id,
                        NULL::TEXT AS menuname,
                        NULL::TEXT AS menulink,
                        NULL::TEXT AS icon,
                        NULL::BIGINT AS isconfiguration,
                        NULL::BOOLEAN AS menu_order,
                        NULL::BOOLEAN AS isactive;
                    RETURN;
                END IF;
            
                -- Return the sidebar items
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Sidebar items fetched successfully'::TEXT AS message,
                    mn.id,
                    mn.permission_id,
                    mn.parent_id,
                    mn.menuname::TEXT,
                    mn.menulink::TEXT,
                    mn.icon::TEXT,
                    mn.isconfiguration,
                    mn.menu_order,
                    mn.isactive
                FROM
                    users u
                INNER JOIN
                    role_user ru ON u.id = ru.user_id
                INNER JOIN
                    roles r ON ru.role_id = r.id
                INNER JOIN
                    permission_role pr ON r.id = pr.role_id
                INNER JOIN
                    permissions p ON pr.permission_id = p.id
                INNER JOIN
                    menu_list mn ON mn.permission_id = p.id 
                WHERE
                    u.id = p_user_id
                    AND (u.tenant_id = p_tenant_id OR p_tenant_id IS NULL)
                    AND u.deleted_at IS NULL
                GROUP BY
                    mn.id, mn.permission_id, mn.parent_id, mn.menuname, mn.menulink, mn.icon, mn.isactive;
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