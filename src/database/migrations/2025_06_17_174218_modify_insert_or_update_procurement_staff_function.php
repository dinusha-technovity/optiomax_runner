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
            DROP FUNCTION IF EXISTS insert_or_update_procurement_staff(
                TEXT, BIGINT, JSONB, BIGINT, TIMESTAMPTZ, BIGINT, BIGINT, TEXT
            );

            CREATE OR REPLACE FUNCTION insert_or_update_procurement_staff(
                IN p_action TEXT,
                IN p_user_id BIGINT,
                IN p_asset_categories JSONB,
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
                item JSONB;
                cat_id BIGINT;
                existing_added_user_count INT;
                inserted_row JSONB;
                deleted_row JSONB;
                rows_updated INT;
                error_message TEXT;
                v_log_success BOOLEAN;
                user_exists BOOLEAN;
                asset_category_exists BOOLEAN;
                result_after JSONB := '[]';
                result_before JSONB := '[]';
                new_category_ids BIGINT[] := ARRAY(
                    SELECT (elem->>'id')::BIGINT
                    FROM jsonb_array_elements(p_asset_categories) elem
                );
                existing_category_ids BIGINT[];
                to_insert BIGINT[];
                to_delete BIGINT[];
            BEGIN
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Tenant ID must be valid', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                SELECT EXISTS(
                    SELECT 1 FROM users WHERE id = p_user_id AND deleted_at IS NULL AND tenant_id = p_tenant_id
                ) INTO user_exists;

                IF NOT user_exists THEN
                    RETURN QUERY SELECT 'FAILURE', 'Invalid user_id. User not found.', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                IF p_action = 'insert' THEN
                    FOR item IN SELECT * FROM jsonb_array_elements(p_asset_categories) LOOP
                        cat_id := (item->>'id')::BIGINT;

                        SELECT EXISTS(
                            SELECT 1 FROM asset_categories WHERE id = cat_id AND deleted_at IS NULL AND tenant_id = p_tenant_id
                        ) INTO asset_category_exists;

                        IF NOT asset_category_exists THEN CONTINUE; END IF;

                        SELECT COUNT(*) INTO existing_added_user_count
                        FROM procurement_staff
                        WHERE user_id = p_user_id AND asset_category = cat_id AND tenant_id = p_tenant_id AND deleted_at IS NULL;

                        IF existing_added_user_count > 0 THEN
                            RETURN QUERY SELECT 'FAILURE', format('This user is already assigned to the selected asset category in the procurement staff.', p_user_id, cat_id), NULL::JSONB, NULL::JSONB;
                            RETURN;
                        END IF;

                        BEGIN
                            INSERT INTO procurement_staff (
                                user_id, asset_category, tenant_id, created_at, updated_at
                            ) VALUES (
                                p_user_id, cat_id, p_tenant_id, p_current_time, p_current_time
                            ) RETURNING to_jsonb(procurement_staff) INTO inserted_row;

                            BEGIN
                                PERFORM log_activity(
                                    'insert_procurement_staff',
                                    format('User %s assigned user_id=%s to asset_type_id=%s', p_causer_name, p_user_id, cat_id),
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

                            result_after := result_after || jsonb_build_array(inserted_row);
                        EXCEPTION WHEN OTHERS THEN
                            error_message := SQLERRM;
                            RETURN QUERY SELECT 'FAILURE', 'Error during insert: ' || error_message, NULL::JSONB, NULL::JSONB;
                            RETURN;
                        END;
                    END LOOP;

                ELSIF p_action = 'update' THEN
                    SELECT ARRAY_AGG(asset_category)
                    INTO existing_category_ids
                    FROM procurement_staff
                    WHERE user_id = p_user_id AND tenant_id = p_tenant_id AND deleted_at IS NULL;

                    to_insert := ARRAY(SELECT unnest(new_category_ids) EXCEPT SELECT unnest(existing_category_ids));
                    to_delete := ARRAY(SELECT unnest(existing_category_ids) EXCEPT SELECT unnest(new_category_ids));

                    FOREACH cat_id IN ARRAY to_insert LOOP
                        SELECT EXISTS(
                            SELECT 1 FROM asset_categories WHERE id = cat_id AND deleted_at IS NULL AND tenant_id = p_tenant_id
                        ) INTO asset_category_exists;

                        IF NOT asset_category_exists THEN CONTINUE; END IF;

                        BEGIN
                            INSERT INTO procurement_staff (
                                user_id, asset_category, tenant_id, created_at, updated_at
                            ) VALUES (
                                p_user_id, cat_id, p_tenant_id, p_current_time, p_current_time
                            ) RETURNING to_jsonb(procurement_staff) INTO inserted_row;

                            result_after := result_after || jsonb_build_array(inserted_row);

                            BEGIN
                                PERFORM log_activity(
                                    'insert_procurement_staff',
                                    format('User %s added new assignment for user_id=%s to asset_category=%s', p_causer_name, p_user_id, cat_id),
                                    'procurement_staff',
                                    (inserted_row->>'id')::BIGINT,
                                    'user',
                                    p_causer_id,
                                    inserted_row,
                                    p_tenant_id
                                );
                            EXCEPTION WHEN OTHERS THEN NULL; END;
                        EXCEPTION WHEN OTHERS THEN NULL; END;
                    END LOOP;

                    FOREACH cat_id IN ARRAY to_delete LOOP
                        UPDATE procurement_staff
                        SET deleted_at = p_current_time
                        WHERE user_id = p_user_id AND asset_category = cat_id AND tenant_id = p_tenant_id AND deleted_at IS NULL
                        RETURNING to_jsonb(procurement_staff) INTO deleted_row;

                        result_before := result_before || jsonb_build_array(deleted_row);

                        BEGIN
                            PERFORM log_activity(
                                'delete_procurement_staff',
                                format('User %s removed assignment of user_id=%s from asset_category=%s', p_causer_name, p_user_id, cat_id),
                                'procurement_staff',
                                (deleted_row->>'id')::BIGINT,
                                'user',
                                p_causer_id,
                                deleted_row,
                                p_tenant_id
                            );
                        EXCEPTION WHEN OTHERS THEN NULL; END;
                    END LOOP;
                ELSE
                    RETURN QUERY SELECT 'FAILURE', 'Invalid action. Use INSERT or UPDATE.', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                IF jsonb_array_length(result_after) > 0 OR jsonb_array_length(result_before) > 0 THEN
                    RETURN QUERY SELECT 'SUCCESS', 'Operation completed', result_before, result_after;
                ELSE
                    RETURN QUERY SELECT 'FAILURE', 'No changes made', NULL::JSONB, NULL::JSONB;
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
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_procurement_staff(TEXT, BIGINT, JSONB, BIGINT, TIMESTAMPTZ, BIGINT, BIGINT, TEXT);");
    }
};