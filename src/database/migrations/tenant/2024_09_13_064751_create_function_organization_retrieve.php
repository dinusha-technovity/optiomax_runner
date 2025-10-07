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
            CREATE OR REPLACE FUNCTION retrieve_organization(
                    p_tenant_id BIGINT,
                    p_organization_id INT DEFAULT NULL
                )
                RETURNS TABLE (
                    status TEXT,
                    message TEXT,
                    id BIGINT, 
                    parent_node_id INT,
                    level INT,
                    relationship TEXT,
                    data JSON,
                    contact_no_code TEXT,  -- Added field
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    isactive BOOLEAN
                )
                LANGUAGE plpgsql
                AS $$
                DECLARE
                    organization_count INT;
                BEGIN
                    -- Validate tenant ID
                    IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Invalid tenant ID provided'::TEXT AS message,
                            NULL::BIGINT AS id,
                            NULL::INT AS parent_node_id,
                            NULL::INT AS level,
                            NULL::TEXT AS relationship,
                            NULL::JSON AS data,
                            NULL::TEXT AS contact_no_code,
                            NULL::TIMESTAMP AS created_at,
                            NULL::TIMESTAMP AS updated_at,
                            NULL::BOOLEAN AS isactive;
                        RETURN;
                    END IF;

                    -- Validate organization ID (optional)
                    IF p_organization_id IS NOT NULL AND p_organization_id < 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Invalid organization ID provided'::TEXT AS message,
                            NULL::BIGINT AS id,
                            NULL::INT AS parent_node_id,
                            NULL::INT AS level,
                            NULL::TEXT AS relationship,
                            NULL::JSON AS data,
                            NULL::TEXT AS contact_no_code,
                            NULL::TIMESTAMP AS created_at,
                            NULL::TIMESTAMP AS updated_at,
                            NULL::BOOLEAN AS isactive;
                        RETURN;
                    END IF;

                    -- Check if any matching records exist
                    SELECT COUNT(*) INTO organization_count
                    FROM organization org
                    WHERE (p_organization_id IS NULL OR org.id = p_organization_id)
                    AND org.tenant_id = p_tenant_id
                    AND org.deleted_at IS NULL
                    AND org.isactive = TRUE;

                    IF organization_count = 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'No matching organizations found'::TEXT AS message,
                            NULL::BIGINT AS id,
                            NULL::INT AS parent_node_id,
                            NULL::INT AS level,
                            NULL::TEXT AS relationship,
                            NULL::JSON AS data,
                            NULL::TEXT AS contact_no_code,
                            NULL::TIMESTAMP AS created_at,
                            NULL::TIMESTAMP AS updated_at,
                            NULL::BOOLEAN AS isactive;
                        RETURN;
                    END IF;

                    -- Return the matching records with contact_no_code
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'Organizations fetched successfully'::TEXT AS message,
                        org.id,
                        org.parent_node_id,
                        org.level,
                        org.relationship::TEXT,
                        org.data::JSON AS data,
                        org.contact_no_code::TEXT,  -- Added field
                        org.created_at,
                        org.updated_at,
                        org.isactive
                    FROM
                        organization org
                    WHERE
                        (p_organization_id IS NULL OR org.id = p_organization_id)
                        AND org.tenant_id = p_tenant_id
                        AND org.deleted_at IS NULL
                        AND org.isactive = TRUE;
                END;
                $$;

        SQL);
        
        

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS retrieve_organization');
    }
};