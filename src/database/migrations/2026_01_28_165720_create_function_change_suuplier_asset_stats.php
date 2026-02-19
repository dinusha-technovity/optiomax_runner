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
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'change_supplier_asset_stats'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION change_supplier_asset_stats(
                p_supplier_id BIGINT,
                p_action TEXT,
                p_tenant_id BIGINT
            )
            RETURNS VOID
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_new_asset_count INT;
                v_current_max_supplier BIGINT;
            BEGIN
                /* ===============================
                VALIDATE ACTION
                ================================ */
                IF p_action NOT IN ('REGISTER', 'DELETE') THEN
                    RAISE EXCEPTION 'Invalid action %. Use REGISTER or DELETE', p_action;
                END IF;

                /* ===============================
                REGISTER ACTION
                ================================ */
                IF p_action = 'REGISTER' THEN

                    IF EXISTS (SELECT 1 FROM supplier_asset_counters WHERE supplier_id = p_supplier_id AND tenant_id = p_tenant_id) THEN
                        UPDATE supplier_asset_counters
                        SET asset_count = asset_count + 1
                        WHERE supplier_id = p_supplier_id AND tenant_id = p_tenant_id
                        RETURNING asset_count INTO v_new_asset_count;
                    ELSE
                        INSERT INTO supplier_asset_counters (supplier_id, asset_count, tenant_id)
                        VALUES (p_supplier_id, 1, p_tenant_id)
                        RETURNING asset_count INTO v_new_asset_count;
                    END IF;

                    -- Insert or update global stats record
                    INSERT INTO supplier_asset_global_stats ( highest_asset_count, highest_supplier_id, tenant_id)
                    VALUES ( v_new_asset_count, p_supplier_id, p_tenant_id)
                    ON CONFLICT (tenant_id)
                    DO UPDATE SET
                        highest_asset_count = v_new_asset_count,
                        highest_supplier_id = p_supplier_id
                    WHERE v_new_asset_count > supplier_asset_global_stats.highest_asset_count;

                /* ===============================
                DELETE ACTION
                ================================ */
                ELSIF p_action = 'DELETE' THEN

                    UPDATE supplier_asset_counters
                    SET asset_count = GREATEST(asset_count - 1, 0)
                    WHERE supplier_id = p_supplier_id
                    RETURNING asset_count
                    INTO v_new_asset_count;

                    -- Check if deleted supplier was the max holder
                    SELECT highest_supplier_id
                    INTO v_current_max_supplier
                    FROM supplier_asset_global_stats
                    WHERE tenant_id = p_tenant_id;

                    IF v_current_max_supplier = p_supplier_id THEN
                        -- Recalculate global max safely
                        UPDATE supplier_asset_global_stats
                        SET
                            highest_asset_count = sub.max_count,
                            highest_supplier_id = sub.supplier_id
                        FROM (
                            SELECT supplier_id, asset_count AS max_count
                            FROM supplier_asset_counters
                            WHERE tenant_id = p_tenant_id
                            ORDER BY asset_count DESC
                            LIMIT 1
                        ) sub
                        WHERE supplier_asset_global_stats.tenant_id = p_tenant_id;
                    END IF;

                END IF;
            END;
            $$;
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'calc_star_rating'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
