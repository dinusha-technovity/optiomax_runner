<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{ 
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION insert_organization(
                IN p_parent_node_id BIGINT,
                IN p_level BIGINT,
                IN p_relationship VARCHAR(255),
                IN p_data JSONB,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE,
                IN p_contact_no_code BIGINT, -- New column
                IN p_country BIGINT, -- New column
                IN p_city VARCHAR(255)
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                organization_id BIGINT
            ) 
            LANGUAGE plpgsql
            AS $$
            DECLARE
                organization_id BIGINT;  -- Captures the ID of the inserted organization
                error_message TEXT;  -- Stores any error messages
            BEGIN
                -- Validate the level input
                IF p_level IS NULL OR p_level <= 0 THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT AS status, 
                        'Level cannot be NULL or less than or equal to 0'::TEXT AS message, 
                        NULL::BIGINT AS organization_id;
                    RETURN;
                END IF;

                -- Validate the tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT AS status, 
                        'Tenant ID cannot be NULL or less than or equal to 0'::TEXT AS message, 
                        NULL::BIGINT AS organization_id;
                    RETURN;
                END IF;

                -- Attempt to insert into the organization table
                BEGIN
                    INSERT INTO organization (
                        parent_node_id,
                        level,
                        relationship,
                        data,
                        tenant_id,
                        created_at,
                        updated_at,
                        contact_no_code,
                        country,
                        city 
                    )
                    VALUES (
                        p_parent_node_id,
                        p_level,
                        p_relationship,
                        p_data,
                        p_tenant_id,
                        p_current_time,
                        p_current_time,
                        p_contact_no_code,
                        p_country,  
                        p_city 
                    )
                    RETURNING id INTO organization_id;

                    -- Return success message with the generated organization ID
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Organization inserted successfully'::TEXT AS message, 
                        organization_id;
                EXCEPTION
                    WHEN OTHERS THEN
                        error_message := SQLERRM;
                        -- Return error message in case of an exception
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT AS status, 
                            ('Error during insert: ' || error_message)::TEXT AS message, 
                            NULL::BIGINT AS organization_id;
                END;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_organization');
    }
};