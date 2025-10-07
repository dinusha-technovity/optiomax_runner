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
            CREATE OR REPLACE FUNCTION insert_supplier_for_item(
                IN _supplier_id BIGINT,
                IN _master_item_id BIGINT,
                IN _lead_time INT,
                IN _tenant_id BIGINT,
                IN _current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                supplier_for_item_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                supplier_for_item_id BIGINT; -- Captures the ID of the inserted row
                existing_count INT; -- Check if the combination of supplier and item already exists
            BEGIN
                -- Validate critical inputs
                IF _supplier_id IS NULL OR _supplier_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Invalid supplier ID provided'::TEXT AS message, 
                        NULL::BIGINT AS supplier_for_item_id;
                    RETURN;
                END IF;

                IF _master_item_id IS NULL OR _master_item_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Invalid item ID provided'::TEXT AS message, 
                        NULL::BIGINT AS supplier_for_item_id;
                    RETURN;
                END IF;

                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Invalid tenant ID provided'::TEXT AS message, 
                        NULL::BIGINT AS supplier_for_item_id;
                    RETURN;
                END IF;

                -- Check if the combination of supplier_id and master_item_id already exists
                SELECT COUNT(*) INTO existing_count
                FROM suppliers_for_item
                WHERE supplier_id = _supplier_id
                AND master_item_id = _master_item_id
                AND tenant_id = _tenant_id
                AND deleted_at IS NULL;
                
                IF existing_count > 0 THEN
                    -- Return failure message if the supplier-item combination already exists
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Supplier already linked to this item'::TEXT AS message, 
                        NULL::BIGINT AS supplier_for_item_id;
                ELSE
                    -- Insert into suppliers_for_item and get the generated ID
                    INSERT INTO suppliers_for_item (
                        supplier_id, 
                        master_item_id, 
                        lead_time, 
                        tenant_id, 
                        created_at, 
                        updated_at
                    )
                    VALUES (
                        _supplier_id, 
                        _master_item_id, 
                        _lead_time, 
                        _tenant_id, 
                        _current_time, 
                        _current_time
                    )
                    RETURNING id INTO supplier_for_item_id;

                    -- Return success message and generated supplier_for_item ID
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Supplier linked to item successfully'::TEXT AS message, 
                        supplier_for_item_id;
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
        DB::unprepared("DROP FUNCTION IF EXISTS insert_supplier_for_item");

    }
};
