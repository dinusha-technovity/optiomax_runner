<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS get_active_work_orders(BIGINT, BIGINT);
            DROP FUNCTION IF EXISTS get_active_work_orders(BIGINT, BIGINT, BIGINT);

            CREATE FUNCTION get_active_work_orders( 
                p_tenant_id BIGINT,
                p_id BIGINT DEFAULT NULL,
                p_user_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                work_order_number VARCHAR,
                title VARCHAR,
                description TEXT,
                asset_item_id BIGINT,
                asset_name VARCHAR,
                asset_thumbnail_image JSONB,
                asset_item_serial_no VARCHAR,
                asset_description TEXT,
                technician_id BIGINT,
                maintenance_type_id BIGINT,
                budget_code_id BIGINT,
                approved_supervisor_id BIGINT,
                type VARCHAR,
                priority VARCHAR,
                status_column VARCHAR,
                job_title VARCHAR,
                job_title_description TEXT,
                scope_of_work TEXT,
                skills_certifications TEXT,
                risk_assessment TEXT,
                safety_instruction TEXT,
                compliance_note TEXT,
                work_order_start TIMESTAMP,
                work_order_end TIMESTAMP,
                expected_duration NUMERIC(10,2),
                expected_duration_unit VARCHAR,
                labour_hours DECIMAL,
                est_cost DECIMAL,
                permit_documents JSON,
                work_order_materials JSON,
                work_order_equipments JSON,
                actual_work_order_start TIMESTAMP,
                actual_work_order_end TIMESTAMP,
                completion_note TEXT,
                actual_used_materials JSON,
                technician_comment TEXT,
                completion_images JSON,
                asset_responsible_person TEXT,
                asset_responsible_person_note TEXT,
                requested_items JSON,
                deleted_at TIMESTAMP,
                isactive BOOLEAN, 
                tenant_id BIGINT,
                user_id BIGINT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                work_order_count INT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE', 'Invalid tenant ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- Validate user ID
                IF p_user_id IS NULL OR p_user_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE', 'Invalid user ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- Check if any matching work orders exist
                SELECT COUNT(*) INTO work_order_count
                FROM work_orders wo
                WHERE (p_id IS NULL OR wo.id = p_id)
                AND wo.tenant_id = p_tenant_id
                AND wo.user_id = p_user_id
                AND wo.isactive = TRUE
                AND wo.deleted_at IS NULL;

                IF work_order_count = 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE', 'No matching active work orders found',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- Return matching work orders
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'Active work orders fetched successfully'::TEXT,
                    wo.id,
                    wo.work_order_number,
                    wo.title,
                    wo.description,
                    wo.asset_item_id,
                    a.name AS asset_name,
                    ai.thumbnail_image AS asset_thumbnail_image,
                    ai.serial_number AS asset_item_serial_no,
                    a.asset_description,
                    wo.technician_id,
                    wo.maintenance_type_id,
                    wo.budget_code_id,
                    wo.approved_supervisor_id,
                    wo.type,
                    wo.priority,
                    wo.status AS status_column,
                    wo.job_title,
                    wo.job_title_description,
                    wo.scope_of_work,
                    wo.skills_certifications,
                    wo.risk_assessment,
                    wo.safety_instruction,
                    wo.compliance_note,
                    wo.work_order_start,
                    wo.work_order_end,
                    wo.expected_duration,
                    wo.expected_duration_unit,
                    wo.labour_hours,
                    wo.est_cost,
                    wo.permit_documents,
                    wo.work_order_materials,
                    wo.work_order_equipments,
                    wo.actual_work_order_start,
                    wo.actual_work_order_end,
                    wo.completion_note,
                    wo.actual_used_materials,
                    wo.technician_comment,
                    wo.completion_images,
                    u.name::TEXT AS asset_responsible_person,
                    wo.asset_responsible_person_note,
                    COALESCE(
                        (
                            SELECT json_agg(json_build_object(
                                'id', rri.id,
                                'item_id', rri.item_id,
                                'item_name', it.item_name,
                                'requested_qty', rri.requested_qty
                            ))
                            FROM work_orders_related_requested_item rri
                            LEFT JOIN items it ON rri.item_id = it.id
                            WHERE rri.work_order_id = wo.id
                            AND rri.isactive = TRUE
                            AND rri.deleted_at IS NULL
                        ),
                        '[]'::JSON
                    ) AS requested_items,
                    wo.deleted_at,
                    wo.isactive,
                    wo.tenant_id,
                    wo.user_id,
                    wo.created_at,
                    wo.updated_at
                FROM work_orders wo
                LEFT JOIN asset_items ai ON wo.asset_item_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN users u ON wo.asset_responsible_person = u.id
                WHERE (p_id IS NULL OR wo.id = p_id)
                AND wo.tenant_id = p_tenant_id
                AND wo.user_id = p_user_id
                AND wo.isactive = TRUE
                AND wo.deleted_at IS NULL;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_active_work_orders(BIGINT, BIGINT, BIGINT);');
    }
};