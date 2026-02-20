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
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_asset_item_schedule_and_assignments'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_asset_item_schedule_and_assignments(
                IN _asset_item_id BIGINT,
                IN _tenant_id BIGINT
            )
            RETURNS TABLE (
                asset_item_id BIGINT,
                is_schedule_available BOOLEAN,
                assignee_type_id BIGINT,
                assigned_users JSON
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    ai.id AS asset_item_id,
                    ai.is_schedule_available,
                    ai.assignee_type_id,

                    -- Get assigned active users as JSON array
                    COALESCE(
                        (
                            SELECT json_agg(
                                json_build_object(
                                    'user_id', aiau.user_id
                                )
                            )
                            FROM asset_item_assigned_users aiau
                            WHERE aiau.asset_item_id = ai.id
                            AND aiau.tenant_id = _tenant_id
                            AND aiau.is_active = true
                        ),
                        '[]'::JSON
                    ) AS assigned_users

                FROM asset_items ai
                WHERE ai.id = _asset_item_id
                AND ai.tenant_id = _tenant_id;

            END;
            $$;

            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_items_public_data(BIGINT, BIGINT);');
    }
};
