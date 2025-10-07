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
            CREATE OR REPLACE FUNCTION insert_or_update_procurement_staff(
                IN p_action TEXT,
                IN p_user_id BIGINT,
                IN p_asset_category_id BIGINT,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMPTZ,
                IN p_staff_id BIGINT DEFAULT NULL,
                IN p_causer_id BIGINT DEFAULT NULL,
                IN p_causer_name TEXT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                before_data JSONB,
                after_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                existing_added_user_count INT;
                inserted_row JSONB;
                data_before JSONB;
                data_after JSONB;
                rows_updated INT;
                error_message TEXT;
                v_log_success BOOLEAN;
                user_exists BOOLEAN;
                asset_category_exists BOOLEAN;
            BEGIN
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Tenant ID must be valid', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Validate user_id
                SELECT EXISTS(
                    SELECT 1 FROM users WHERE id = p_user_id AND deleted_at IS NULL AND tenant_id = p_tenant_id
                ) INTO user_exists;

                IF NOT user_exists THEN
                    RETURN QUERY SELECT 'FAILURE', 'Invalid user_id. User not found.', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Validate asset_category_id
                SELECT EXISTS(
                    SELECT 1 FROM asset_categories WHERE id = p_asset_category_id AND deleted_at IS NULL AND tenant_id = p_tenant_id
                ) INTO asset_category_exists;

                IF NOT asset_category_exists THEN
                    RETURN QUERY SELECT 'FAILURE', 'Invalid asset_category_id. Category not found.', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                SELECT COUNT(*) INTO existing_added_user_count
                FROM procurement_staff
                WHERE user_id = p_user_id
                AND asset_category = p_asset_category_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL
                AND (p_action = 'insert' OR id != p_staff_id);

                IF existing_added_user_count > 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'User is already assigned to this asset category', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                IF p_action = 'insert' THEN
                    BEGIN
                        INSERT INTO procurement_staff (
                            user_id, asset_category, tenant_id, created_at, updated_at
                        ) VALUES (
                            p_user_id, p_asset_category_id, p_tenant_id, p_current_time, p_current_time
                        )
                        RETURNING to_jsonb(procurement_staff) INTO inserted_row;

                        BEGIN
                            PERFORM log_activity(
                                'insert_procurement_staff',
                                format('User %s assigned user_id=%s to asset_type_id=%s', p_causer_name, p_user_id, p_asset_category_id),
                                'procurement_staff',
                                (inserted_row->>'id')::BIGINT,
                                'user',
                                p_causer_id,
                                inserted_row,
                                p_tenant_id
                            );
                            v_log_success := TRUE;
                        EXCEPTION WHEN OTHERS THEN
                            v_log_success := FALSE;
                        END;

                        RETURN QUERY SELECT 'SUCCESS', 'User assigned successfully', NULL::JSONB, inserted_row;
                    EXCEPTION WHEN OTHERS THEN
                        error_message := SQLERRM;
                        RETURN QUERY SELECT 'FAILURE', 'Error during insert: ' || error_message, NULL::JSONB, NULL::JSONB;
                    END;

                ELSIF p_action = 'update' THEN
                    SELECT to_jsonb(procurement_staff) INTO data_before
                    FROM procurement_staff
                    WHERE id = p_staff_id AND tenant_id = p_tenant_id AND deleted_at IS NULL
                    LIMIT 1;

                    UPDATE procurement_staff
                    SET user_id = p_user_id,
                        asset_category = p_asset_category_id,
                        updated_at = p_current_time
                    WHERE id = p_staff_id AND tenant_id = p_tenant_id AND deleted_at IS NULL;

                    GET DIAGNOSTICS rows_updated = ROW_COUNT;

                    IF rows_updated > 0 THEN
                        SELECT to_jsonb(procurement_staff) INTO data_after
                        FROM procurement_staff
                        WHERE id = p_staff_id
                        LIMIT 1;

                        BEGIN
                            PERFORM log_activity(
                                'update_procurement_staff',
                                format('User %s updated procurement_staff_id=%s', p_causer_name, p_staff_id),
                                'procurement_staff',
                                p_staff_id,
                                'user',
                                p_causer_id,
                                jsonb_build_object('before', data_before, 'after', data_after),
                                p_tenant_id
                            );
                            v_log_success := TRUE;
                        EXCEPTION WHEN OTHERS THEN
                            v_log_success := FALSE;
                        END;

                        RETURN QUERY SELECT 'SUCCESS', 'Procurement staff updated', data_before, data_after;
                    ELSE
                        RETURN QUERY SELECT 'FAILURE', 'No rows updated', data_before, NULL::JSONB;
                    END IF;
                ELSE
                    RETURN QUERY SELECT 'FAILURE', 'Invalid action. Use INSERT or UPDATE.', NULL::JSONB, NULL::JSONB;
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
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_procurement_staff(TEXT, BIGINT, BIGINT, BIGINT, BIGINT, TIMESTAMPTZ, BIGINT, TEXT);");
    }
};