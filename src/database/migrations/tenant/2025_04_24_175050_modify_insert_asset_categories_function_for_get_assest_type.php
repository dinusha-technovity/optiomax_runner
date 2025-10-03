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

        DROP FUNCTION IF EXISTS insert_asset_categories(
                IN _categories_name VARCHAR(255),
                IN _categories_description TEXT,
                IN _readingsParameters JSON,
                IN _tenant_id BIGINT,
                IN _current_time TIMESTAMP WITH TIME ZONE
            );
            CREATE OR REPLACE FUNCTION insert_asset_categories(
                IN _categories_name VARCHAR(255),
                IN _categories_description TEXT,
                IN _readingsParameters JSON,
                IN _tenant_id BIGINT,
                IN _current_time TIMESTAMP WITH TIME ZONE,
                IN _assest_type BIGINT
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                category_id BIGINT
            ) 
            LANGUAGE plpgsql
            AS $$
            DECLARE
                category_id BIGINT; -- Captures the ID of the inserted item
                existing_count INT; -- Check if the category already exists
            BEGIN
                -- Validate critical inputs
                IF _categories_name IS NULL OR LENGTH(TRIM(_categories_name)) = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Category name cannot be empty'::TEXT AS message, 
                        NULL::BIGINT AS category_id;
                    RETURN;
                END IF;
            
                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Invalid tenant ID provided'::TEXT AS message, 
                        NULL::BIGINT AS category_id;
                    RETURN;
                END IF;
            
                -- Check if the category name already exists
                SELECT COUNT(*) INTO existing_count
                FROM asset_categories
                WHERE name = _categories_name
                AND tenant_id = _tenant_id
                AND deleted_at IS NULL;
            
                IF existing_count > 0 THEN
                    -- Return failure message if category already exists
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Asset Category name already exists'::TEXT AS message, 
                        NULL::BIGINT AS category_id;
                ELSE
                    -- Insert into asset_categories and get the generated ID
                    INSERT INTO asset_categories (
                        name, 
                        description, 
                        reading_parameters,
                        tenant_id,
                        assets_type,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        _categories_name, 
                        _categories_description, 
                        _readingsParameters,
                        _tenant_id,
                        _assest_type,
                        _current_time,
                        _current_time
                    )
                    RETURNING id INTO category_id;
            
                    -- Return success message and generated category ID
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Asset Category Registerd successfully'::TEXT AS message, 
                        category_id;
                END IF;
            END;
            $$;
        SQL);

        // update function

        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS update_asset_category_details(
                p_asset_categories_id BIGINT,
                p_categories_name VARCHAR(255),
                p_categories_description TEXT,
                p_tenant_id BIGINT,
                p_current_time TIMESTAMP WITH TIME ZONE
                
            );
            CREATE OR REPLACE FUNCTION update_asset_category_details(
                p_asset_categories_id BIGINT,
                p_categories_name VARCHAR(255),
                p_categories_description TEXT,
                p_tenant_id BIGINT,
                p_current_time TIMESTAMP WITH TIME ZONE,
                p_assest_type BIGINT
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT
            )
            LANGUAGE plpgsql 
            AS $$
            DECLARE
                rows_updated INT;          -- Variable to capture affected rows
                existing_count INT;        -- Variable to count existing category names
            BEGIN
                -- Check if the category name already exists in another row
                SELECT COUNT(*) INTO existing_count
                FROM asset_categories
                WHERE name = p_categories_name
                AND tenant_id = p_tenant_id
                AND id != p_asset_categories_id -- Ensure it's not the same row
                AND deleted_at IS NULL;

                IF existing_count > 0 THEN
                    -- Return failure message if category name already exists
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Category name already exists'::TEXT AS message;
                    RETURN;
                END IF;

                -- Update the asset_categories table
                UPDATE asset_categories
                SET 
                    name = p_categories_name,
                    description = p_categories_description,
                    assets_type = p_assest_type,
                    updated_at = p_current_time
                WHERE id = p_asset_categories_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                -- Check if the update was successful
                IF rows_updated > 0 THEN
                    -- Return success message
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Update Asset Categories Details Successfully'::TEXT AS message;
                ELSE
                    -- Return failure message if no rows were updated
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Asset Category not found.'::TEXT AS message;
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
        DB::unprepared('DROP FUNCTION IF EXISTS insert_asset_categories');
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_category_details');
    }
};
