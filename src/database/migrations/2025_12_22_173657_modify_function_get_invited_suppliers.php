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
            CREATE OR REPLACE FUNCTION get_invited_suppliers(
                p_tenant_id BIGINT,
                p_supplier_id INT DEFAULT NULL,
                p_current_time TIMESTAMP DEFAULT now()
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                supplier_reg_no TEXT,
                supplier_reg_status TEXT,
                email TEXT,
                invite_id BIGINT,
                expires_at TIMESTAMP,
                invite_status TEXT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                supplier_count INT;
            BEGIN
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE', 'Invalid tenant ID provided', NULL, NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;

                IF p_supplier_id IS NOT NULL AND p_supplier_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE', 'Invalid supplier ID provided', NULL, NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;

                UPDATE supplier_invites si
                SET status = 'EXPIRED'
                WHERE si.expires_at <= p_current_time
                AND si.status = 'pending'
                AND si.deleted_at IS NULL
                AND si.isactive = TRUE;

                SELECT COUNT(*) INTO supplier_count
                FROM suppliers
                WHERE (p_supplier_id IS NULL OR suppliers.id = p_supplier_id)
                AND suppliers.tenant_id = p_tenant_id
                AND suppliers.deleted_at IS NULL
                AND suppliers.isactive = TRUE;

                IF supplier_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE', 'No matching suppliers found', NULL, NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;

                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Suppliers fetched successfully'::TEXT AS message,
                    s.id,
                    si.invite_reg_number::TEXT,
                    s.supplier_reg_status::TEXT,
                    s.email::TEXT,
                    si.id,
                    si.expires_at,
                    si.status::TEXT
                FROM suppliers s
                JOIN LATERAL (
                    SELECT si.*
                    FROM supplier_invites si
                    WHERE si.suppliers_id = s.id
                    AND si.deleted_at IS NULL
                    AND si.isactive = TRUE
                    ORDER BY si.created_at DESC
                    LIMIT 1
                ) si ON TRUE
                WHERE 
                    (p_supplier_id IS NULL OR s.id = p_supplier_id)
                    AND s.tenant_id = p_tenant_id
                    AND s.supplier_reg_status = 'INVITED'
                    AND s.deleted_at IS NULL
                    AND s.isactive = TRUE;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_invited_suppliers');
    }
};
