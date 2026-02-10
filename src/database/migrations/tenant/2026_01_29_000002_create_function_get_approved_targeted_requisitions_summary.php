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
        DB::unprepared(<<<'SQL'
            DO $$
                DECLARE
                    r RECORD;
                BEGIN
                    FOR r IN
                        SELECT oid::regprocedure::text AS func_signature
                        FROM pg_proc
                        WHERE proname = 'get_approved_targeted_requisitions_summary'
                    LOOP
                        EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                    END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_approved_targeted_requisitions_summary(
                p_targeted_user_id BIGINT,
                p_tenant_id BIGINT,
                p_limit INT DEFAULT 5
            )
            RETURNS JSON
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_total_count INT;
                v_result JSON;
            BEGIN
                -- Get total count of approved requisitions targeted to this user
                SELECT COUNT(*)
                INTO v_total_count
                FROM internal_asset_requisitions iar
                WHERE iar.targeted_responsible_person = p_targeted_user_id
                    AND iar.tenant_id = p_tenant_id
                    AND UPPER(iar.requisition_status) = 'APPROVED'
                    AND iar.deleted_at IS NULL
                    AND iar.isactive = true;

                -- Get latest approved requisitions with user details
                SELECT json_build_object(
                    'success', true,
                    'message', 'Approved targeted requisitions summary retrieved successfully',
                    'data', json_build_object(
                        'total_approved_count', v_total_count,
                        'latest_requisitions', COALESCE(
                            (
                                SELECT json_agg(req_data)
                                FROM (
                                    SELECT json_build_object(
                                        'requisition_id', iar.id,
                                        'requisition_identifier', iar.requisition_id,
                                        'requisition_status', iar.requisition_status,
                                        'requested_date', iar.requested_date,
                                        'requisition_by_user_id', iar.requisition_by,
                                        'requisition_by_name', u.name,
                                        'requisition_by_email', u.email,
                                        'requisition_by_profile_image', u.profile_image,
                                        'requisition_by_organization', CASE 
                                            WHEN org.id IS NOT NULL THEN json_build_object(
                                                'id', org.id,
                                                'data', org.data,
                                                'level', org.level
                                            )
                                            ELSE NULL
                                        END,
                                        'items_count', (
                                            SELECT COUNT(*)
                                            FROM internal_asset_requisitions_items iari
                                            WHERE iari.internal_asset_requisition_id = iar.id
                                                AND iari.deleted_at IS NULL
                                                AND iari.isactive = true
                                        ),
                                        'created_at', iar.created_at,
                                        'updated_at', iar.updated_at
                                    ) AS req_data
                                    FROM internal_asset_requisitions iar
                                    LEFT JOIN users u ON iar.requisition_by = u.id
                                    LEFT JOIN organization org ON u.organization = org.id
                                    WHERE iar.targeted_responsible_person = p_targeted_user_id
                                        AND iar.tenant_id = p_tenant_id
                                        AND UPPER(iar.requisition_status) = 'APPROVED'
                                        AND iar.deleted_at IS NULL
                                        AND iar.isactive = true
                                    ORDER BY iar.updated_at DESC
                                    LIMIT p_limit
                                ) AS latest_reqs
                            ),
                            '[]'::JSON
                        )
                    )
                )
                INTO v_result;

                RETURN v_result;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_approved_targeted_requisitions_summary(BIGINT, BIGINT, INT);');
    }
};