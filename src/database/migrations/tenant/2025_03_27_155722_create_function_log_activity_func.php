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
            CREATE OR REPLACE FUNCTION log_activity(
                p_log_name VARCHAR,
                p_description TEXT,
                p_subject_type VARCHAR DEFAULT NULL,
                p_subject_id BIGINT DEFAULT NULL,
                p_causer_type VARCHAR DEFAULT NULL,
                p_causer_id BIGINT DEFAULT NULL,
                p_properties JSONB DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL
            ) RETURNS BIGINT AS $$
            DECLARE
                v_log_id BIGINT;
            BEGIN
                INSERT INTO activity_log (
                    log_name,
                    description,
                    subject_type,
                    subject_id,
                    causer_type,
                    causer_id,
                    properties,
                    tenant_id,
                    created_at,
                    updated_at
                ) VALUES (
                    p_log_name,
                    p_description,
                    p_subject_type,
                    p_subject_id,
                    p_causer_type,
                    p_causer_id,
                    p_properties,
                    p_tenant_id,
                    NOW(),
                    NOW()
                ) RETURNING id INTO v_log_id;
                
                RETURN v_log_id;
            END;
            $$ LANGUAGE plpgsql;    
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS upsert_item_with_suppliers_func");
    }
};
