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
        // DB::unprepared(
        //     'CREATE OR REPLACE PROCEDURE store_procedure_delete_organization(
        //         IN p_organization_id bigint,
        //         IN p_deleted_at TIMESTAMP
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         UPDATE organization
        //         SET 
        //             deleted_at = p_deleted_at
        //         WHERE id = p_organization_id;
        //     END; 
        //     $$; 
        // ');
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_organization(
                p_organization_id BIGINT,
                p_tenant_id BIGINT,
                p_deleted_at TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT,
                deleted_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT;        -- Variable to capture affected rows
                deleted_data JSONB;      -- Variable to store data before the update
                existing_count INT;      -- Tracks number of existing references
            BEGIN
                -- Check for references in `asset_requisitions_items`
                SELECT COUNT(*) INTO existing_count
                FROM asset_requisitions_items
                WHERE organization = p_organization_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;
        
                IF existing_count > 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'You cannot delete this Organization Node, as it is associated with asset requisitions'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN; -- Exit early
                END IF;
        
                -- Check for references in `asset_items`
                SELECT COUNT(*) INTO existing_count
                FROM asset_items
                WHERE department = p_organization_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;
        
                IF existing_count > 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'You cannot delete this Organization Node, as it is associated with asset items'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN; -- Exit early
                END IF;
        
                -- Fetch data before the update
                SELECT jsonb_build_object(
                    'id', id,
                    'parent_node_id', parent_node_id,
                    'level', level,
                    'relationship', relationship,
                    'data', data,
                    'updated_at', updated_at
                ) INTO deleted_data
                FROM organization
                WHERE id = p_organization_id;
        
                -- Check if the organization exists
                IF deleted_data IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows deleted. Organization not found.'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN; -- Exit early
                END IF;
        
                -- Update the organization table
                UPDATE organization
                SET 
                    deleted_at = p_deleted_at
                WHERE id = p_organization_id;
        
                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;
        
                -- Check if the update was successful
                IF rows_updated > 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Organization deleted successfully'::TEXT AS message,
                        deleted_data;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows deleted. Organization not found.'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
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
        DB::unprepared('DROP FUNCTION IF EXISTS delete_organization');
    }
};
