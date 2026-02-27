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
        CREATE OR REPLACE FUNCTION get_assets_by_ids(
            p_tenant_id BIGINT,
            p_asset_ids BIGINT[],
            p_sort_by TEXT DEFAULT 'newest'
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_total_records INT := 0;
            v_data JSON := '[]'::JSON;
            v_order_clause TEXT := 'ORDER BY ai.id DESC';
            v_message TEXT := '';
            v_asset_ids_csv TEXT := '';
        BEGIN
            ----------------------------------------------------------------
            -- Validations
            ----------------------------------------------------------------
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid tenant ID provided',
                    'success', FALSE,
                    'data', '[]'::JSON,
                    'total_records', 0
                );
            END IF;

            IF p_asset_ids IS NULL OR array_length(p_asset_ids, 1) IS NULL OR array_length(p_asset_ids, 1) = 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Asset IDs array is required and cannot be empty',
                    'success', FALSE,
                    'data', '[]'::JSON,
                    'total_records', 0
                );
            END IF;

            ----------------------------------------------------------------
            -- Sorting Logic
            ----------------------------------------------------------------
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY ai.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY ai.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY a.name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY a.name DESC NULLS LAST';
                WHEN 'asset_tag_asc' THEN v_order_clause := 'ORDER BY ai.asset_tag ASC NULLS LAST';
                WHEN 'asset_tag_desc' THEN v_order_clause := 'ORDER BY ai.asset_tag DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY ai.id DESC';
            END CASE;

            ----------------------------------------------------------------
            -- Count total matching records
            ----------------------------------------------------------------
            SELECT COUNT(DISTINCT ai.id)
            INTO v_total_records
            FROM asset_items ai
            INNER JOIN assets a ON ai.asset_id = a.id
            INNER JOIN asset_categories ac ON a.category = ac.id
            INNER JOIN assets_types ast ON ac.assets_type = ast.id
            INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
            LEFT JOIN users u ON ai.responsible_person = u.id
            WHERE ai.id = ANY(p_asset_ids)
                AND ai.tenant_id = p_tenant_id
                AND ai.deleted_at IS NULL
                AND ai.isactive = TRUE
                AND a.deleted_at IS NULL
                AND a.isactive = TRUE;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'SUCCESS',
                    'message', 'No matching asset items found for the provided IDs',
                    'success', TRUE,
                    'data', '[]'::JSON,
                    'total_records', 0,
                    'requested_ids', array_to_json(p_asset_ids)
                );
            END IF;

            ----------------------------------------------------------------
            -- Fetch asset details
            ----------------------------------------------------------------
            EXECUTE format($s$
                SELECT COALESCE(json_agg(t.*), '[]'::JSON)
                FROM (
                    SELECT
                        'SUCCESS'::TEXT as status,
                        'Asset details retrieved successfully'::TEXT as message,
                        ai.id,
                        a.id as asset_id,
                        a.name as asset_name,
                        ai.model_number,
                        ai.serial_number,
                        ai.thumbnail_image,
                        ai.qr_code,
                        ai.asset_tag,
                        ai.purchase_cost,
                        ai.item_value,
                        ai.warranty_exparing_at,
                        ai.responsible_person AS responsible_person_id,
                        u.name AS responsible_person_name,
                        ai.booking_availability,
                        ac.assets_type as assets_type_id,
                        ast.name as assets_type_name,
                        a.category as category_id,
                        ac.name as category_name,
                        a.sub_category as sub_category_id,
                        assc.name as sub_category_name,
                        ai.created_at,
                        ai.updated_at
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    LEFT JOIN users u ON ai.responsible_person = u.id
                    WHERE ai.id = ANY($1)
                        AND ai.tenant_id = $2
                        AND ai.deleted_at IS NULL
                        AND ai.isactive = TRUE
                        AND a.deleted_at IS NULL
                        AND a.isactive = TRUE
                    GROUP BY ai.id, a.id, a.name, ai.model_number, ai.serial_number, 
                            ai.thumbnail_image, ai.qr_code, ai.asset_tag, ai.purchase_cost,
                            ai.item_value, ai.warranty_exparing_at,
                            ai.responsible_person, u.name, ai.booking_availability,
                            ac.assets_type, ast.name, a.category, ac.name, 
                            a.sub_category, assc.name, ai.created_at, ai.updated_at
                    %s
                ) t
            $s$, v_order_clause)
            INTO v_data
            USING p_asset_ids, p_tenant_id;

            v_data := COALESCE(v_data, '[]'::JSON);

            ----------------------------------------------------------------
            -- Return final result
            ----------------------------------------------------------------
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', format('Retrieved %s asset item(s) successfully', v_total_records),
                'success', TRUE,
                'data', v_data,
                'total_records', v_total_records,
                'requested_ids', array_to_json(p_asset_ids)
            );

        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_assets_by_ids(BIGINT, BIGINT[], TEXT);');
    }
};
