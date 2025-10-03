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
            DROP FUNCTION IF EXISTS get_active_work_orders(BIGINT, BIGINT, BIGINT);

            CREATE OR REPLACE FUNCTION get_active_work_orders(
                _tenant_id BIGINT DEFAULT NULL,
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
                expected_duration INTEGER,
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
                deleted_at TIMESTAMP,
                isactive BOOLEAN,
                tenant_id BIGINT,
                user_id BIGINT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Active work orders fetched successfully'::TEXT AS message,
                    wo.id,
                    wo.work_order_number,
                    wo.title,
                    wo.description,
                    wo.asset_item_id,
                    a.name AS asset_name,
                    ai.thumbnail_image AS asset_thumbnail_image,
                    ai.serial_number,
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
                    wo.deleted_at,
                    wo.isactive,
                    wo.tenant_id,
                    wo.user_id,
                    wo.created_at,
                    wo.updated_at
                FROM work_orders wo
                LEFT JOIN asset_items ai ON wo.asset_item_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                WHERE (p_id IS NULL OR wo.id = p_id)
                AND (_tenant_id IS NULL OR wo.tenant_id = _tenant_id)
                AND (p_user_id IS NULL OR wo.user_id = p_user_id)
                AND wo.isactive = true
                AND wo.deleted_at IS NULL;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_active_work_orders');
    }
};