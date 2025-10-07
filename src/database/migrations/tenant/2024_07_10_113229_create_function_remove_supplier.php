<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_REMOVE_SUPPLIER(
        //         p_supplier_id bigint
        //     ) LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         IF p_supplier_id IS NULL OR p_supplier_id = 0 THEN
        //             RAISE EXCEPTION 'Supplier ID cannot be null or zero';
        //         END IF;

        //         IF NOT EXISTS (SELECT 1 FROM supplier WHERE id = p_supplier_id) THEN
        //             RAISE EXCEPTION 'supplier ID % does not exist', p_supplier_id;
        //         END IF;

        //         -- DELETE FROM supplier WHERE id = p_supplier_id;

        //         UPDATE suppliers
        //         SET deleted_at = NOW(), isactive = FALSE
        //         WHERE id = p_supplier_id;
        //     END;
        //     $$;"
        // );

        // DB::unprepared(
        //     'CREATE OR REPLACE PROCEDURE store_procedure_remove_supplier(
        //         IN p_supplier_id bigint
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         UPDATE suppliers
        //         SET 
        //             deleted_at = NOW(),
        //             isactive = FALSE
        //         WHERE id = p_supplier_id;
        //     END;  
        //     $$;
        // ');

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_supplier(
                p_supplier_id BIGINT,
                p_tenant_id BIGINT,
                p_deleted_at TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT,
                deleted_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                deleted_data JSONB;      -- Variable to store data before the update
                rows_updated INT;        -- Variable to capture affected rows
            BEGIN
                -- Fetch all column data as JSON before the update
                SELECT row_to_json(s.*) INTO deleted_data
                FROM suppliers s
                WHERE id = p_supplier_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Check if the supplier exists
                IF deleted_data IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows deleted. Supplier not found or already deleted.'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Update the supplier record
                UPDATE suppliers
                SET 
                    deleted_at = p_deleted_at,
                    isactive = FALSE
                WHERE id = p_supplier_id
                AND tenant_id = p_tenant_id;

                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                -- Check if the update was successful
                IF rows_updated > 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Supplier deleted successfully.'::TEXT AS message,
                        deleted_data;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows deleted. Unexpected error.'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                END IF;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_supplier');
    }
};