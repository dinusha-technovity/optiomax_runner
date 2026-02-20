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
                    WHERE proname = 'create_or_update_asset_item_schedule_and_assignments'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;


            CREATE OR REPLACE FUNCTION create_or_update_asset_item_schedule_and_assignments(
                IN _asset_item_id BIGINT,
                IN _tenant_id BIGINT,
                IN _current_time TIMESTAMP WITH TIME ZONE,
                IN _is_schedule_available BOOLEAN,
                IN _assigned_users JSON DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                returned_asset_item_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                assigned_user_record JSON;
                user_id_val BIGINT;
                existing_user_ids BIGINT[] := '{}';
                new_user_ids BIGINT[] := '{}';
                user_to_unassign BIGINT;
            BEGIN

                ------------------------------------------------------------------
                -- 1️⃣ Update Schedule Availability
                ------------------------------------------------------------------
                UPDATE asset_items ai
                SET
                    is_schedule_available = _is_schedule_available,
                    updated_at = _current_time
                WHERE ai.id = _asset_item_id
                AND ai.tenant_id = _tenant_id;

                IF NOT FOUND THEN
                    RETURN QUERY SELECT
                        'ERROR',
                        format('Asset item with id %s not found', _asset_item_id),
                        _asset_item_id;
                    RETURN;
                END IF;

                ------------------------------------------------------------------
                -- 2️⃣ If schedule enabled → unassign all users
                ------------------------------------------------------------------
                IF _is_schedule_available = TRUE THEN

                    UPDATE asset_item_assigned_users aiau
                    SET
                        assign_status = 'unassigned',
                        is_active = FALSE,
                        updated_at = _current_time
                    WHERE aiau.asset_item_id = _asset_item_id
                    AND aiau.tenant_id = _tenant_id
                    AND aiau.is_active = TRUE;

                    RETURN QUERY SELECT
                        'SUCCESS',
                        'Schedule enabled and all users unassigned successfully',
                        _asset_item_id;
                    RETURN;

                END IF;

                ------------------------------------------------------------------
                -- 3️⃣ If schedule disabled → manage assignments
                ------------------------------------------------------------------

                IF _assigned_users IS NOT NULL THEN

                    -- Collect new user IDs
                    FOR assigned_user_record IN
                        SELECT * FROM json_array_elements(_assigned_users)
                    LOOP
                        user_id_val := (assigned_user_record->>'user_id')::BIGINT;
                        new_user_ids := array_append(new_user_ids, user_id_val);
                    END LOOP;

                    -- Get existing active user IDs
                    SELECT array_agg(aiau.user_id)
                    INTO existing_user_ids
                    FROM asset_item_assigned_users aiau
                    WHERE aiau.asset_item_id = _asset_item_id
                    AND aiau.tenant_id = _tenant_id
                    AND aiau.is_active = TRUE;

                    existing_user_ids := COALESCE(existing_user_ids, '{}');

                    --------------------------------------------------------------
                    -- Unassign removed users
                    --------------------------------------------------------------
                    FOREACH user_to_unassign IN ARRAY existing_user_ids
                    LOOP
                        IF NOT (user_to_unassign = ANY(new_user_ids)) THEN
                            UPDATE asset_item_assigned_users aiau
                            SET
                                assign_status = 'unassigned',
                                is_active = FALSE,
                                updated_at = _current_time
                            WHERE aiau.asset_item_id = _asset_item_id
                            AND aiau.user_id = user_to_unassign
                            AND aiau.tenant_id = _tenant_id;
                        END IF;
                    END LOOP;

                    --------------------------------------------------------------
                    -- Insert or reactivate new users
                    --------------------------------------------------------------
                    FOREACH user_id_val IN ARRAY new_user_ids
                    LOOP

                        IF EXISTS (
                            SELECT 1
                            FROM asset_item_assigned_users aiau
                            WHERE aiau.asset_item_id = _asset_item_id
                            AND aiau.user_id = user_id_val
                            AND aiau.tenant_id = _tenant_id
                        ) THEN

                            UPDATE asset_item_assigned_users aiau
                            SET
                                assign_status = 'assigned',
                                is_active = TRUE,
                                updated_at = _current_time
                            WHERE aiau.asset_item_id = _asset_item_id
                            AND aiau.user_id = user_id_val
                            AND aiau.tenant_id = _tenant_id;

                        ELSE

                            INSERT INTO asset_item_assigned_users (
                                asset_item_id,
                                user_id,
                                tenant_id,
                                assign_status,
                                is_active,
                                created_at,
                                updated_at
                            )
                            VALUES (
                                _asset_item_id,
                                user_id_val,
                                _tenant_id,
                                'assigned',
                                TRUE,
                                _current_time,
                                _current_time
                            );

                        END IF;

                    END LOOP;

                ELSE
                    ------------------------------------------------------------------
                    -- If no assigned users passed → unassign all
                    ------------------------------------------------------------------
                    UPDATE asset_item_assigned_users aiau
                    SET
                        assign_status = 'unassigned',
                        is_active = FALSE,
                        updated_at = _current_time
                    WHERE aiau.asset_item_id = _asset_item_id
                    AND aiau.tenant_id = _tenant_id
                    AND aiau.is_active = TRUE;
                END IF;

                ------------------------------------------------------------------
                -- 4️⃣ Success Response
                ------------------------------------------------------------------
                RETURN QUERY SELECT
                    'SUCCESS',
                    'Schedule and assignments updated successfully',
                    _asset_item_id;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT
                        'ERROR',
                        format('Error: %s', SQLERRM),
                        _asset_item_id;
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
                    WHERE proname = 'create_or_update_asset_item_schedule_and_assignments'
                LOOP
                    EXECUTE format('DROP ROUTINE %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};