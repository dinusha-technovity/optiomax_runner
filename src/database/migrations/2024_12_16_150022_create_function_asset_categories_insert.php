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
            CREATE OR REPLACE FUNCTION insert_asset_categories(
                IN _categories_name VARCHAR(255),
                IN _categories_description TEXT,
                IN _readingsParameters JSON,
                IN _tenant_id BIGINT,
                IN _current_time TIMESTAMP WITH TIME ZONE
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
                        created_at,
                        updated_at
                    )
                    VALUES (
                        _categories_name, 
                        _categories_description, 
                        _readingsParameters,
                        _tenant_id,
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
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_asset_categories');
    }
};