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
            CREATE OR REPLACE FUNCTION get_goods_received_notes(
                p_tenant_id BIGINT,
                p_grn_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                grn_number TEXT,
                purchasing_number TEXT,
                supplier_id BIGINT,
                supplier_name TEXT,
                receipt_date DATE,
                grn_status TEXT,
                total_amount NUMERIC(15,2),
                line_items JSON
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                grn_count INT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE',
                        'Invalid tenant ID provided',
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::DATE,
                        NULL::TEXT,
                        NULL::NUMERIC(15,2),
                        NULL::JSON;
                    RETURN;
                END IF;

                -- Validate GRN ID if provided
                IF p_grn_id IS NOT NULL AND p_grn_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE',
                        'Invalid GRN ID provided',
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::DATE,
                        NULL::TEXT,
                        NULL::NUMERIC(15,2),
                        NULL::JSON;
                    RETURN;
                END IF;

                -- Check record existence
                SELECT COUNT(*) INTO grn_count
                FROM goods_received_note g
                WHERE (p_grn_id IS NULL OR g.id = p_grn_id)
                AND g.tenant_id = p_tenant_id
                AND g.deleted_at IS NULL
                AND g.isactive = TRUE;

                IF grn_count = 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE',
                        'No matching Goods Received Notes found',
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::DATE,
                        NULL::TEXT,
                        NULL::NUMERIC(15,2),
                        NULL::JSON;
                    RETURN;
                END IF;

                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Goods Received Notes fetched successfully'::TEXT AS message,
                    g.id,
                    g.grn_number::TEXT,
                    g.purchasing_number::TEXT,
                    g.supplier_id,
                    s.name::TEXT AS supplier_name,
                    g.receipt_date,
                    g.status::TEXT AS grn_status,
                    g.total_amount,
                    (
                        SELECT json_agg(
                            json_build_object(
                                'grn_line_id', gl.id,
                                'item_id', gl.item_id,
                                'item_name', i.item_name,
                                'received_qty', gl.received_qty,
                                'unit_price', gl.unit_price,
                                'currency_id', gl.currency_id,
                                'line_total', gl.line_total,
                                'lot_number', gl.lot_number,
                                'expiration_date', gl.expiration_date
                            )
                        )
                        FROM grn_lines gl
                        LEFT JOIN items i ON i.id = gl.item_id
                        WHERE gl.grn_id = g.id
                        AND gl.deleted_at IS NULL
                        AND gl.isactive = TRUE
                    ) AS line_items
                FROM goods_received_note g
                JOIN suppliers s ON s.id = g.supplier_id
                WHERE (p_grn_id IS NULL OR g.id = p_grn_id)
                AND g.tenant_id = p_tenant_id
                AND g.deleted_at IS NULL
                AND g.isactive = TRUE;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('function_get_goods_received_notes');
    }
};
