<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_active_work_order_technicians(
            _tenant_id BIGINT DEFAULT NULL,
            p_id BIGINT DEFAULT NULL,
            p_available_only BOOLEAN DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            name VARCHAR,
            email VARCHAR,
            phone VARCHAR,
            mobile VARCHAR,
            address TEXT,
            employee_id VARCHAR,
            job_title VARCHAR,
            specialization VARCHAR,
            certifications JSON,
            hourly_rate DECIMAL(10,2),
            is_contractor BOOLEAN,
            is_available BOOLEAN,
            unavailable_reason TEXT,
            deleted_at TIMESTAMP,
            isactive BOOLEAN,
            tenant_id BIGINT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Return all active records matching the criteria
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT AS status,
                'Active technicians fetched successfully'::TEXT AS message,
                wot.id,
                wot.name::VARCHAR,
                wot.email::VARCHAR,
                wot.phone::VARCHAR,
                wot.mobile::VARCHAR,
                wot.address::TEXT,
                wot.employee_id::VARCHAR,
                wot.job_title::VARCHAR,
                wot.specialization::VARCHAR,
                wot.certifications::JSON,
                wot.hourly_rate,
                wot.is_contractor,
                wot.is_available,
                wot.unavailable_reason::TEXT,
                wot.deleted_at,
                wot.isactive,
                wot.tenant_id,
                wot.created_at,
                wot.updated_at
            FROM work_order_technicians wot
            WHERE (p_id IS NULL OR wot.id = p_id)
            AND (_tenant_id IS NULL OR wot.tenant_id = _tenant_id)
            AND wot.isactive = true
            AND wot.deleted_at IS NULL
            AND (p_available_only IS NULL OR wot.is_available = p_available_only);
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_active_work_order_technicians');
        
    }
};
