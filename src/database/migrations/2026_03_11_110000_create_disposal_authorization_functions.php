<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Functions for Disposal Authorization Users Management
     */
    public function up(): void
    {
        // ================================================================
        // Function 1: Create or Update Disposal Authorization User
        // ================================================================
        DB::unprepared('DROP FUNCTION IF EXISTS create_or_update_disposal_auth_user CASCADE');
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION create_or_update_disposal_auth_user(
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_current_user_name TEXT,
                p_disposal_auth_id BIGINT DEFAULT NULL,
                p_target_user_id BIGINT DEFAULT NULL,
                p_authorization_level VARCHAR DEFAULT 'level-1',
                p_authorized_from DATE DEFAULT NULL,
                p_authorized_until DATE DEFAULT NULL,
                p_max_single_disposal_value NUMERIC DEFAULT NULL,
                p_max_monthly_disposal_value NUMERIC DEFAULT NULL,
                p_authorized_asset_categories JSONB DEFAULT NULL,
                p_authorized_disposal_methods JSONB DEFAULT NULL,
                p_location_restrictions JSONB DEFAULT NULL,
                p_certification_number VARCHAR DEFAULT NULL,
                p_certification_date DATE DEFAULT NULL,
                p_certification_expiry DATE DEFAULT NULL,
                p_training_records TEXT DEFAULT NULL,
                p_status VARCHAR DEFAULT 'pending',
                p_approved_by BIGINT DEFAULT NULL,
                p_approval_notes TEXT DEFAULT NULL,
                p_authorization_scope_description TEXT DEFAULT NULL,
                p_special_conditions TEXT DEFAULT NULL,
                p_compliance_requirements JSONB DEFAULT NULL,
                p_emergency_contact_name VARCHAR DEFAULT NULL,
                p_emergency_contact_phone VARCHAR DEFAULT NULL,
                p_backup_authorizer_id VARCHAR DEFAULT NULL,
                p_current_time TIMESTAMPTZ DEFAULT NOW()
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_disposal_auth_id BIGINT;
                v_registration_number VARCHAR(50);
                v_is_update BOOLEAN := FALSE;
                v_old_data JSONB;
                v_new_data JSONB;
                v_action_type TEXT;
                v_log_id BIGINT;
                v_existing_count INTEGER;
                v_next_reg_num INTEGER;
                v_target_user_name TEXT;
                v_target_user_email TEXT;
            BEGIN
                -- Validation: Required fields for create
                IF p_disposal_auth_id IS NULL THEN
                    IF p_target_user_id IS NULL THEN
                        RETURN jsonb_build_object(
                            'status', 'ERROR',
                            'message', 'Target user ID is required for creating a new disposal authorization'
                        );
                    END IF;
                    
                    IF p_authorized_from IS NULL THEN
                        RETURN jsonb_build_object(
                            'status', 'ERROR',
                            'message', 'Authorization start date (authorized_from) is required'
                        );
                    END IF;
                    
                    -- Check if user already has active authorization
                    SELECT COUNT(*) INTO v_existing_count
                    FROM disposal_authorization_users
                    WHERE user_id = p_target_user_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL;
                    
                    IF v_existing_count > 0 THEN
                        RETURN jsonb_build_object(
                            'status', 'ERROR',
                            'message', 'User already has a disposal authorization staff'
                        );
                    END IF;
                    
                    -- Validate date range if both provided
                    IF p_authorized_until IS NOT NULL THEN
                        IF p_authorized_until < p_authorized_from THEN
                            RETURN jsonb_build_object(
                                'status', 'ERROR',
                                'message', 'Authorization end date cannot be before start date'
                            );
                        END IF;
                    END IF;
                    
                    -- Validate certification dates
                    IF p_certification_date IS NOT NULL AND p_certification_expiry IS NOT NULL THEN
                        IF p_certification_expiry < p_certification_date THEN
                            RETURN jsonb_build_object(
                                'status', 'ERROR',
                                'message', 'Certification expiry cannot be before certification date'
                            );
                        END IF;
                    END IF;
                END IF;
                
                -- Get target user details for email notification
                BEGIN
                    SELECT name, email INTO v_target_user_name, v_target_user_email
                    FROM users
                    WHERE id = COALESCE(p_target_user_id, (SELECT user_id FROM disposal_authorization_users WHERE id = p_disposal_auth_id))
                        AND tenant_id = p_tenant_id;
                EXCEPTION WHEN OTHERS THEN
                    v_target_user_name := 'Unknown User';
                    v_target_user_email := NULL;
                END;
                
                -- Check if updating existing authorization
                IF p_disposal_auth_id IS NOT NULL THEN
                    SELECT COUNT(*) INTO v_existing_count
                    FROM disposal_authorization_users
                    WHERE id = p_disposal_auth_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL;

                    IF v_existing_count = 0 THEN
                        RETURN jsonb_build_object(
                            'status', 'ERROR',
                            'message', 'Disposal authorization record not found'
                        );
                    END IF;
                    
                    v_is_update := TRUE;
                    
                    -- Capture old data for logging
                    SELECT to_jsonb(a.*) INTO v_old_data
                    FROM disposal_authorization_users a
                    WHERE id = p_disposal_auth_id;
                END IF;

                IF v_is_update THEN
                    -- UPDATE existing authorization
                    UPDATE disposal_authorization_users
                    SET
                        authorization_level = COALESCE(p_authorization_level, authorization_level),
                        authorized_from = COALESCE(p_authorized_from, authorized_from),
                        authorized_until = COALESCE(p_authorized_until, authorized_until),
                        max_single_disposal_value = COALESCE(p_max_single_disposal_value, max_single_disposal_value),
                        max_monthly_disposal_value = COALESCE(p_max_monthly_disposal_value, max_monthly_disposal_value),
                        authorized_asset_categories = COALESCE(p_authorized_asset_categories, authorized_asset_categories),
                        authorized_disposal_methods = COALESCE(p_authorized_disposal_methods, authorized_disposal_methods),
                        location_restrictions = COALESCE(p_location_restrictions, location_restrictions),
                        certification_number = COALESCE(p_certification_number, certification_number),
                        certification_date = COALESCE(p_certification_date, certification_date),
                        certification_expiry = COALESCE(p_certification_expiry, certification_expiry),
                        training_records = COALESCE(p_training_records, training_records),
                        status = COALESCE(p_status, status),
                        approved_by = CASE WHEN p_status = 'active' THEN COALESCE(p_approved_by, p_user_id) ELSE approved_by END,
                        approved_at = CASE WHEN p_status = 'active' AND approved_at IS NULL THEN p_current_time ELSE approved_at END,
                        approval_notes = COALESCE(p_approval_notes, approval_notes),
                        authorization_scope_description = COALESCE(p_authorization_scope_description, authorization_scope_description),
                        special_conditions = COALESCE(p_special_conditions, special_conditions),
                        compliance_requirements = COALESCE(p_compliance_requirements, compliance_requirements),
                        emergency_contact_name = COALESCE(p_emergency_contact_name, emergency_contact_name),
                        emergency_contact_phone = COALESCE(p_emergency_contact_phone, emergency_contact_phone),
                        backup_authorizer_id = COALESCE(p_backup_authorizer_id, backup_authorizer_id),
                        updated_by = p_user_id,
                        updated_at = p_current_time
                    WHERE id = p_disposal_auth_id
                        AND tenant_id = p_tenant_id
                    RETURNING id, registration_number INTO v_disposal_auth_id, v_registration_number;
                    
                    v_action_type := 'updated';
                    
                    -- Capture new data for logging
                    SELECT to_jsonb(a.*) INTO v_new_data
                    FROM disposal_authorization_users a
                    WHERE id = v_disposal_auth_id;
                    
                    -- Log activity for update
                    BEGIN
                        v_log_id := log_activity(
                            'disposal_auth.updated',
                            'Disposal authorization updated: ' || v_registration_number || ' for user ' || v_target_user_name || ' by ' || p_current_user_name,
                            'disposal_authorization_users',
                            v_disposal_auth_id,
                            'user',
                            p_user_id,
                            jsonb_build_object(
                                'registration_number', v_registration_number,
                                'target_user_id', p_target_user_id,
                                'target_user_name', v_target_user_name,
                                'action', 'update',
                                'old_data', v_old_data,
                                'new_data', v_new_data,
                                'updated_by', p_current_user_name,
                                'updated_at', p_current_time
                            ),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        RAISE NOTICE 'Log activity failed: %', SQLERRM;
                    END;
                    
                ELSE
                    -- Generate registration number: DAU-000001, DAU-000002, etc.
                    SELECT COALESCE(MAX(CAST(SUBSTRING(registration_number FROM 5) AS INTEGER)), 0) + 1 
                    INTO v_next_reg_num
                    FROM disposal_authorization_users
                    WHERE tenant_id = p_tenant_id;
                    
                    v_registration_number := 'DAU-' || LPAD(v_next_reg_num::TEXT, 6, '0');
                    
                    -- INSERT new authorization
                    INSERT INTO disposal_authorization_users (
                        tenant_id,
                        registration_number,
                        user_id,
                        authorization_level,
                        authorized_from,
                        authorized_until,
                        max_single_disposal_value,
                        max_monthly_disposal_value,
                        authorized_asset_categories,
                        authorized_disposal_methods,
                        location_restrictions,
                        certification_number,
                        certification_date,
                        certification_expiry,
                        training_records,
                        status,
                        approved_by,
                        approved_at,
                        approval_notes,
                        authorization_scope_description,
                        special_conditions,
                        compliance_requirements,
                        emergency_contact_name,
                        emergency_contact_phone,
                        backup_authorizer_id,
                        created_by,
                        updated_by,
                        isactive,
                        created_at,
                        updated_at
                    ) VALUES (
                        p_tenant_id,
                        v_registration_number,
                        p_target_user_id,
                        p_authorization_level,
                        p_authorized_from,
                        p_authorized_until,
                        p_max_single_disposal_value,
                        p_max_monthly_disposal_value,
                        p_authorized_asset_categories,
                        p_authorized_disposal_methods,
                        p_location_restrictions,
                        p_certification_number,
                        p_certification_date,
                        p_certification_expiry,
                        p_training_records,
                        p_status,
                        CASE WHEN p_status = 'active' THEN COALESCE(p_approved_by, p_user_id) ELSE NULL END,
                        CASE WHEN p_status = 'active' THEN p_current_time ELSE NULL END,
                        p_approval_notes,
                        p_authorization_scope_description,
                        p_special_conditions,
                        p_compliance_requirements,
                        p_emergency_contact_name,
                        p_emergency_contact_phone,
                        p_backup_authorizer_id,
                        p_user_id,
                        p_user_id,
                        TRUE,
                        p_current_time,
                        p_current_time
                    ) RETURNING id, registration_number INTO v_disposal_auth_id, v_registration_number;
                    
                    v_action_type := 'created';
                    
                    -- Capture new data for logging
                    SELECT to_jsonb(a.*) INTO v_new_data
                    FROM disposal_authorization_users a
                    WHERE id = v_disposal_auth_id;
                    
                    -- Log activity for create
                    BEGIN
                        v_log_id := log_activity(
                            'disposal_auth.assigned',
                            'Disposal authorization assigned: ' || v_registration_number || ' to user ' || v_target_user_name || ' by ' || p_current_user_name,
                            'disposal_authorization_users',
                            v_disposal_auth_id,
                            'user',
                            p_user_id,
                            jsonb_build_object(
                                'registration_number', v_registration_number,
                                'target_user_id', p_target_user_id,
                                'target_user_name', v_target_user_name,
                                'target_user_email', v_target_user_email,
                                'authorization_level', p_authorization_level,
                                'action', 'assign',
                                'new_data', v_new_data,
                                'assigned_by', p_current_user_name,
                                'created_at', p_current_time
                            ),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        RAISE NOTICE 'Log activity failed: %', SQLERRM;
                    END;
                END IF;

                RETURN jsonb_build_object(
                    'status', 'SUCCESS',
                    'message', CASE 
                        WHEN v_is_update THEN 'Disposal authorization updated successfully'
                        ELSE 'Disposal authorization assigned successfully'
                    END,
                    'disposal_auth_id', v_disposal_auth_id,
                    'registration_number', v_registration_number,
                    'is_update', v_is_update,
                    'activity_log_id', v_log_id,
                    'target_user_email', v_target_user_email,
                    'target_user_name', v_target_user_name,
                    'send_email', NOT v_is_update
                );

            EXCEPTION
                WHEN foreign_key_violation THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Invalid reference: check user_id or approved_by'
                    );
                WHEN unique_violation THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'User already has a disposal authorization staff'
                    );
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Database error: ' || SQLERRM
                    );
            END;
            $$;
        SQL);

        // ================================================================
        // Function 2: Revoke Disposal Authorization
        // ================================================================
        DB::unprepared('DROP FUNCTION IF EXISTS revoke_disposal_auth_user CASCADE');
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION revoke_disposal_auth_user(
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_current_user_name TEXT,
                p_disposal_auth_id BIGINT,
                p_revocation_reason TEXT DEFAULT NULL,
                p_current_time TIMESTAMPTZ DEFAULT NOW()
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_registration_number VARCHAR(50);
                v_old_status VARCHAR;
                v_target_user_name TEXT;
                v_target_user_email TEXT;
                v_log_id BIGINT;
                v_existing_count INTEGER;
            BEGIN
                -- Check if authorization exists
                SELECT COUNT(*) INTO v_existing_count
                FROM disposal_authorization_users
                WHERE id = p_disposal_auth_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                IF v_existing_count = 0 THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Disposal authorization record not found'
                    );
                END IF;
                
                -- Get current status and user details
                SELECT 
                    da.registration_number,
                    da.status,
                    u.name,
                    u.email
                INTO 
                    v_registration_number,
                    v_old_status,
                    v_target_user_name,
                    v_target_user_email
                FROM disposal_authorization_users da
                JOIN users u ON u.id = da.user_id
                WHERE da.id = p_disposal_auth_id;
                
                -- Check if already revoked
                IF v_old_status = 'revoked' THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Authorization is already revoked'
                    );
                END IF;
                
                -- Update to revoked status
                UPDATE disposal_authorization_users
                SET
                    status = 'revoked',
                    revoked_by = p_user_id,
                    revoked_at = p_current_time,
                    revocation_reason = p_revocation_reason,
                    isactive = FALSE,
                    updated_by = p_user_id,
                    updated_at = p_current_time
                WHERE id = p_disposal_auth_id
                    AND tenant_id = p_tenant_id;
                
                -- Log activity
                BEGIN
                    v_log_id := log_activity(
                        'disposal_auth.revoked',
                        'Disposal authorization revoked: ' || v_registration_number || ' for user ' || v_target_user_name || ' by ' || p_current_user_name,
                        'disposal_authorization_users',
                        p_disposal_auth_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'registration_number', v_registration_number,
                            'target_user_name', v_target_user_name,
                            'target_user_email', v_target_user_email,
                            'old_status', v_old_status,
                            'new_status', 'revoked',
                            'revocation_reason', p_revocation_reason,
                            'revoked_by', p_current_user_name,
                            'revoked_at', p_current_time
                        ),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    RAISE NOTICE 'Log activity failed: %', SQLERRM;
                END;
                
                RETURN jsonb_build_object(
                    'status', 'SUCCESS',
                    'message', 'Disposal authorization revoked successfully',
                    'disposal_auth_id', p_disposal_auth_id,
                    'registration_number', v_registration_number,
                    'activity_log_id', v_log_id,
                    'target_user_email', v_target_user_email,
                    'target_user_name', v_target_user_name,
                    'send_email', TRUE
                );

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Database error: ' || SQLERRM
                    );
            END;
            $$;
        SQL);

        // ================================================================
        // Function 3: Delete (Soft Delete) Disposal Authorization
        // ================================================================
        DB::unprepared('DROP FUNCTION IF EXISTS delete_disposal_auth_user CASCADE');
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION delete_disposal_auth_user(
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_current_user_name TEXT,
                p_disposal_auth_id BIGINT,
                p_current_time TIMESTAMPTZ DEFAULT NOW()
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_registration_number VARCHAR(50);
                v_target_user_name TEXT;
                v_log_id BIGINT;
                v_existing_count INTEGER;
            BEGIN
                -- Check if authorization exists and not already deleted
                SELECT COUNT(*) INTO v_existing_count
                FROM disposal_authorization_users
                WHERE id = p_disposal_auth_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                IF v_existing_count = 0 THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Disposal authorization record not found or already deleted'
                    );
                END IF;
                
                -- Get details before deletion
                SELECT 
                    da.registration_number,
                    u.name
                INTO 
                    v_registration_number,
                    v_target_user_name
                FROM disposal_authorization_users da
                JOIN users u ON u.id = da.user_id
                WHERE da.id = p_disposal_auth_id;
                
                -- Soft delete
                UPDATE disposal_authorization_users
                SET
                    deleted_at = p_current_time,
                    isactive = FALSE,
                    updated_by = p_user_id,
                    updated_at = p_current_time
                WHERE id = p_disposal_auth_id
                    AND tenant_id = p_tenant_id;
                
                -- Log activity
                BEGIN
                    v_log_id := log_activity(
                        'disposal_auth.deleted',
                        'Disposal authorization deleted: ' || v_registration_number || ' for user ' || v_target_user_name || ' by ' || p_current_user_name,
                        'disposal_authorization_users',
                        p_disposal_auth_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'registration_number', v_registration_number,
                            'target_user_name', v_target_user_name,
                            'deleted_by', p_current_user_name,
                            'deleted_at', p_current_time
                        ),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    RAISE NOTICE 'Log activity failed: %', SQLERRM;
                END;
                
                RETURN jsonb_build_object(
                    'status', 'SUCCESS',
                    'message', 'Disposal authorization deleted successfully',
                    'disposal_auth_id', p_disposal_auth_id,
                    'registration_number', v_registration_number,
                    'activity_log_id', v_log_id
                );

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Database error: ' || SQLERRM
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
        DB::unprepared('DROP FUNCTION IF EXISTS create_or_update_disposal_auth_user CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS revoke_disposal_auth_user CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS delete_disposal_auth_user CASCADE');
    }
};
