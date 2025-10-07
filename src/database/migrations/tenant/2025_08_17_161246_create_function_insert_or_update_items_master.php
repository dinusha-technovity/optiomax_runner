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
        // DB::unprepared(<<<SQL
        //     -- Drop the old version if it exists
        //     DROP FUNCTION IF EXISTS upsert_item_with_suppliers_func(
        //         BIGINT, TEXT, TEXT, TEXT, BIGINT, BIGINT, BIGINT, DECIMAL, BIGINT, DECIMAL, BIGINT,
        //         INTEGER, INTEGER, INTEGER, BOOLEAN, BOOLEAN, BIGINT, JSONB, JSONB, BIGINT, TEXT
        //     );

        //     -- Create the new version
        //     CREATE OR REPLACE FUNCTION insert_or_update_items_master(
        //         p_id BIGINT,
        //         p_item_id TEXT,  
        //         p_item_name TEXT,
        //         p_item_description TEXT,
        //         p_category_id BIGINT,
        //         p_type_id BIGINT,
        //         p_unit_of_measure_id BIGINT,
        //         p_purchase_price DECIMAL(15,2),
        //         p_purchase_price_currency_id BIGINT,
        //         p_selling_price DECIMAL(15,2),
        //         p_selling_price_currency_id BIGINT,
        //         p_min_inventory_level INTEGER,
        //         p_max_inventory_level INTEGER,
        //         p_reorder_level INTEGER,
        //         p_low_stock_alert BOOLEAN,
        //         p_over_stock_alert BOOLEAN,
        //         p_tenant_id BIGINT,
        //         p_image_links JSONB,
        //         p_suppliers JSONB,
        //         p_item_category_type_id BIGINT, -- ✅ new param
        //         p_user_id BIGINT,
        //         p_user_name TEXT
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         result_data JSONB
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         v_item_id BIGINT;
        //         v_supplier_record JSONB;
        //         v_supplier_id BIGINT;
        //         v_lead_time INTEGER;
        //         v_existing_supplier_ids BIGINT[];
        //         v_validation_errors TEXT[];
        //         v_item_data JSONB;
        //         v_old_data JSONB;
        //         v_new_data JSONB;
        //         v_action_type TEXT;
        //         v_log_success BOOLEAN;
        //         v_error_message TEXT;
        //     BEGIN
        //         v_validation_errors := ARRAY[]::TEXT[];

        //         -- Required fields
        //         IF p_item_name IS NULL OR p_item_name = '' THEN
        //             v_validation_errors := array_append(v_validation_errors, 'Item name is required');
        //         END IF;

        //         IF p_category_id IS NULL THEN
        //             v_validation_errors := array_append(v_validation_errors, 'Category ID is required');
        //         END IF;

        //         IF p_type_id IS NULL THEN
        //             v_validation_errors := array_append(v_validation_errors, 'Type ID is required');
        //         END IF;

        //         IF p_unit_of_measure_id IS NULL THEN
        //             v_validation_errors := array_append(v_validation_errors, 'Unit of measure ID is required');
        //         END IF;

        //         -- ✅ Validate new item_category_type_id
        //         IF p_item_category_type_id IS NULL THEN
        //             v_validation_errors := array_append(v_validation_errors, 'Item Category Type ID is required');
        //         ELSIF NOT EXISTS (
        //             SELECT 1 FROM item_category_type
        //             WHERE id = p_item_category_type_id
        //             AND deleted_at IS NULL
        //             AND is_active = TRUE
        //         ) THEN
        //             v_validation_errors := array_append(v_validation_errors, 'Invalid Item Category Type ID');
        //         END IF;

        //         -- Duplicate checks (item_id, item_name), suppliers validation...
        //         -- (same logic from your current function, unchanged)

        //         -- If validation errors
        //         IF array_length(v_validation_errors, 1) > 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT AS status,
        //                 'Validation failed'::TEXT AS message,
        //                 jsonb_build_object('errors', v_validation_errors) AS result_data;
        //             RETURN;
        //         END IF;

        //         BEGIN
        //             -- Determine create/update
        //             IF p_id = 0 THEN
        //                 v_action_type := 'created';
        //             ELSE
        //                 v_action_type := 'updated';

        //                 SELECT jsonb_build_object(
        //                     'item_id', item_id,
        //                     'item_name', item_name,
        //                     'item_description', item_description,
        //                     'category_id', category_id,
        //                     'type_id', type_id,
        //                     'unit_of_measure_id', unit_of_measure_id,
        //                     'purchase_price', purchase_price,
        //                     'purchase_price_currency_id', purchase_price_currency_id,
        //                     'selling_price', selling_price,
        //                     'selling_price_currency_id', selling_price_currency_id,
        //                     'max_inventory_level', max_inventory_level,
        //                     'min_inventory_level', min_inventory_level,
        //                     're_order_level', re_order_level,
        //                     'low_stock_alert', low_stock_alert,
        //                     'over_stock_alert', over_stock_alert,
        //                     'isactive', isactive,
        //                     'image_links', image_links,
        //                     'item_category_type_id', item_category_type_id -- ✅ log old
        //                 ) INTO v_old_data
        //                 FROM items
        //                 WHERE id = p_id;
        //             END IF;

        //             -- Insert / Update
        //             IF p_id = 0 THEN
        //                 INSERT INTO items (
        //                     item_id, item_name, item_description, category_id, type_id, unit_of_measure_id,
        //                     purchase_price, purchase_price_currency_id, selling_price, selling_price_currency_id,
        //                     min_inventory_level, max_inventory_level, re_order_level,
        //                     low_stock_alert, over_stock_alert, tenant_id, image_links, item_category_type_id
        //                 ) VALUES (
        //                     p_item_id, p_item_name, p_item_description, p_category_id, p_type_id, p_unit_of_measure_id,
        //                     p_purchase_price, p_purchase_price_currency_id, p_selling_price, p_selling_price_currency_id,
        //                     p_min_inventory_level, p_max_inventory_level, p_reorder_level,
        //                     p_low_stock_alert, p_over_stock_alert, p_tenant_id, p_image_links, p_item_category_type_id
        //                 ) RETURNING id INTO v_item_id;
        //             ELSE
        //                 UPDATE items SET
        //                     item_id = p_item_id,
        //                     item_name = p_item_name,
        //                     item_description = p_item_description,
        //                     category_id = p_category_id,
        //                     type_id = p_type_id,
        //                     unit_of_measure_id = p_unit_of_measure_id,
        //                     purchase_price = p_purchase_price,
        //                     purchase_price_currency_id = p_purchase_price_currency_id,
        //                     selling_price = p_selling_price,
        //                     selling_price_currency_id = p_selling_price_currency_id,
        //                     min_inventory_level = p_min_inventory_level,
        //                     max_inventory_level = p_max_inventory_level,
        //                     re_order_level = p_reorder_level,
        //                     low_stock_alert = p_low_stock_alert,
        //                     over_stock_alert = p_over_stock_alert,
        //                     image_links = p_image_links,
        //                     item_category_type_id = p_item_category_type_id -- ✅ update
        //                 WHERE id = p_id
        //                 RETURNING id INTO v_item_id;
        //             END IF;

        //             -- Log new data
        //             v_new_data := jsonb_build_object(
        //                 'id', v_item_id,
        //                 'item_id', p_item_id,
        //                 'item_name', p_item_name,
        //                 'item_description', p_item_description,
        //                 'category_id', p_category_id,
        //                 'type_id', p_type_id,
        //                 'unit_of_measure_id', p_unit_of_measure_id,
        //                 'purchase_price', p_purchase_price,
        //                 'purchase_currency_id', p_purchase_price_currency_id,
        //                 'selling_price', p_selling_price,
        //                 'selling_currency_id', p_selling_price_currency_id,
        //                 'min_inventory', p_min_inventory_level,
        //                 'max_inventory', p_max_inventory_level,
        //                 'reorder_level', p_reorder_level,
        //                 'low_stock_alert', p_low_stock_alert,
        //                 'over_stock_alert', p_over_stock_alert,
        //                 'image_links', p_image_links,
        //                 'suppliers', p_suppliers,
        //                 'item_category_type_id', p_item_category_type_id, -- ✅ log new
        //                 'action', v_action_type,
        //                 'tenant_id', p_tenant_id,
        //                 'user_id', p_user_id,
        //                 'user_name', p_user_name
        //             );
 
        //             -- (rest of suppliers + logging unchanged...)

        //             RETURN QUERY SELECT 
        //                 'SUCCESS'::TEXT AS status,
        //                 CASE WHEN p_id = 0 THEN 'Item created successfully' ELSE 'Item updated successfully' END AS message,
        //                 jsonb_build_object(
        //                     'item_id', v_item_id,
        //                     'log_data', v_item_data,
        //                     'log_success', COALESCE(v_log_success, FALSE)
        //                 ) AS result_data;
        //         END;
        //     END;
        //     $$;
        // SQL);

        DB::unprepared(<<<SQL
            -- Drop the old version if it exists
            DROP FUNCTION IF EXISTS upsert_item_with_suppliers_func(
                BIGINT, TEXT, TEXT, TEXT, BIGINT, BIGINT, BIGINT, DECIMAL, BIGINT, DECIMAL, BIGINT,
                INTEGER, INTEGER, INTEGER, BOOLEAN, BOOLEAN, BIGINT, JSONB, JSONB, BIGINT, TEXT
            );

            -- Create the new version
            CREATE OR REPLACE FUNCTION insert_or_update_items_master(
                p_id BIGINT,
                p_item_id TEXT,  
                p_item_name TEXT,
                p_item_description TEXT,
                p_category_id BIGINT,
                p_type_id BIGINT,
                p_unit_of_measure_id BIGINT,
                p_purchase_price DECIMAL(15,2),
                p_purchase_price_currency_id BIGINT,
                p_selling_price DECIMAL(15,2),
                p_selling_price_currency_id BIGINT,
                p_min_inventory_level INTEGER,
                p_max_inventory_level INTEGER,
                p_reorder_level INTEGER,
                p_low_stock_alert BOOLEAN,
                p_over_stock_alert BOOLEAN,
                p_tenant_id BIGINT,
                p_image_links JSONB,
                p_suppliers JSONB,
                p_item_category_type_id BIGINT,
                p_user_id BIGINT,
                p_user_name TEXT
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                result_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_item_id BIGINT;
                v_supplier_record JSONB;
                v_supplier_id BIGINT;
                v_lead_time INTEGER;
                v_existing_supplier_ids BIGINT[];
                v_validation_errors TEXT[];
                v_item_data JSONB;
                v_old_data JSONB;
                v_new_data JSONB;
                v_action_type TEXT;
                v_log_success BOOLEAN;
                v_error_message TEXT;
            BEGIN
                v_validation_errors := ARRAY[]::TEXT[];

                -- Required fields
                IF p_item_name IS NULL OR p_item_name = '' THEN
                    v_validation_errors := array_append(v_validation_errors, 'Item name is required');
                END IF;

                IF p_category_id IS NULL THEN
                    v_validation_errors := array_append(v_validation_errors, 'Category ID is required');
                END IF;

                IF p_type_id IS NULL THEN
                    v_validation_errors := array_append(v_validation_errors, 'Type ID is required');
                END IF;

                IF p_unit_of_measure_id IS NULL THEN
                    v_validation_errors := array_append(v_validation_errors, 'Unit of measure ID is required');
                END IF;

                -- Validate new item_category_type_id
                IF p_item_category_type_id IS NULL THEN
                    v_validation_errors := array_append(v_validation_errors, 'Item Category Type ID is required');
                ELSIF NOT EXISTS (
                    SELECT 1 FROM item_category_type
                    WHERE id = p_item_category_type_id
                    AND deleted_at IS NULL
                    AND is_active = TRUE
                ) THEN
                    v_validation_errors := array_append(v_validation_errors, 'Invalid Item Category Type ID');
                END IF;

                -- Duplicate checks
                IF p_item_id IS NOT NULL AND p_item_id <> '' THEN
                    IF EXISTS (
                        SELECT 1 FROM items 
                        WHERE item_id = p_item_id 
                        AND id <> COALESCE(NULLIF(p_id, 0), 0)
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                    ) THEN
                        v_validation_errors := array_append(v_validation_errors, 'Item ID already exists');
                    END IF;
                END IF;

                IF EXISTS (
                    SELECT 1 FROM items 
                    WHERE item_name = p_item_name 
                    AND id <> COALESCE(NULLIF(p_id, 0), 0)
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL
                ) THEN
                    v_validation_errors := array_append(v_validation_errors, 'Item Name already exists');
                END IF;

                -- Supplier validation
                IF p_suppliers IS NOT NULL THEN
                    FOR v_supplier_record IN SELECT * FROM jsonb_array_elements(p_suppliers)
                    LOOP
                        v_supplier_id := (v_supplier_record->>'id')::BIGINT;
                        IF NOT EXISTS (
                            SELECT 1 FROM suppliers 
                            WHERE id = v_supplier_id 
                            AND deleted_at IS NULL 
                            AND isactive = TRUE
                        ) THEN
                            v_validation_errors := array_append(v_validation_errors, 'Invalid or inactive supplier ID: ' || v_supplier_id::TEXT);
                        END IF;
                    END LOOP;
                END IF;

                -- Return validation errors
                IF array_length(v_validation_errors, 1) > 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Validation failed'::TEXT AS message,
                        jsonb_build_object('errors', v_validation_errors) AS result_data;
                    RETURN;
                END IF;

                BEGIN
                    -- Action type
                    IF p_id = 0 THEN
                        v_action_type := 'created';
                    ELSE
                        v_action_type := 'updated';
                        SELECT jsonb_build_object(
                            'item_id', item_id,
                            'item_name', item_name,
                            'item_description', item_description,
                            'category_id', category_id,
                            'type_id', type_id,
                            'unit_of_measure_id', unit_of_measure_id,
                            'purchase_price', purchase_price,
                            'purchase_price_currency_id', purchase_price_currency_id,
                            'selling_price', selling_price,
                            'selling_price_currency_id', selling_price_currency_id,
                            'max_inventory_level', max_inventory_level,
                            'min_inventory_level', min_inventory_level,
                            're_order_level', re_order_level,
                            'low_stock_alert', low_stock_alert,
                            'over_stock_alert', over_stock_alert,
                            'isactive', isactive,
                            'image_links', image_links,
                            'item_category_type_id', item_category_type_id
                        ) INTO v_old_data
                        FROM items
                        WHERE id = p_id;
                    END IF;

                    -- Insert / Update
                    IF p_id = 0 THEN
                        INSERT INTO items (
                            item_id, item_name, item_description, category_id, type_id, unit_of_measure_id,
                            purchase_price, purchase_price_currency_id, selling_price, selling_price_currency_id,
                            min_inventory_level, max_inventory_level, re_order_level,
                            low_stock_alert, over_stock_alert, tenant_id, image_links, item_category_type_id
                        ) VALUES (
                            p_item_id, p_item_name, p_item_description, p_category_id, p_type_id, p_unit_of_measure_id,
                            p_purchase_price, p_purchase_price_currency_id, p_selling_price, p_selling_price_currency_id,
                            p_min_inventory_level, p_max_inventory_level, p_reorder_level,
                            p_low_stock_alert, p_over_stock_alert, p_tenant_id, p_image_links, p_item_category_type_id
                        ) RETURNING id INTO v_item_id;
                    ELSE
                        UPDATE items SET
                            item_id = p_item_id,
                            item_name = p_item_name,
                            item_description = p_item_description,
                            category_id = p_category_id,
                            type_id = p_type_id,
                            unit_of_measure_id = p_unit_of_measure_id,
                            purchase_price = p_purchase_price,
                            purchase_price_currency_id = p_purchase_price_currency_id,
                            selling_price = p_selling_price,
                            selling_price_currency_id = p_selling_price_currency_id,
                            min_inventory_level = p_min_inventory_level,
                            max_inventory_level = p_max_inventory_level,
                            re_order_level = p_reorder_level,
                            low_stock_alert = p_low_stock_alert,
                            over_stock_alert = p_over_stock_alert,
                            image_links = p_image_links,
                            item_category_type_id = p_item_category_type_id
                        WHERE id = p_id
                        RETURNING id INTO v_item_id;

                        IF NOT FOUND THEN
                            RETURN QUERY SELECT 
                                'FAILURE'::TEXT AS status,
                                'Item not found'::TEXT AS message,
                                NULL::JSONB AS result_data;
                            RETURN;
                        END IF;
                    END IF;

                    -- New data log
                    v_new_data := jsonb_build_object(
                        'id', v_item_id,
                        'item_id', p_item_id,
                        'item_name', p_item_name,
                        'item_description', p_item_description,
                        'category_id', p_category_id,
                        'type_id', p_type_id,
                        'unit_of_measure_id', p_unit_of_measure_id,
                        'purchase_price', p_purchase_price,
                        'purchase_currency_id', p_purchase_price_currency_id,
                        'selling_price', p_selling_price,
                        'selling_currency_id', p_selling_price_currency_id,
                        'min_inventory', p_min_inventory_level,
                        'max_inventory', p_max_inventory_level,
                        'reorder_level', p_reorder_level,
                        'low_stock_alert', p_low_stock_alert,
                        'over_stock_alert', p_over_stock_alert,
                        'image_links', p_image_links,
                        'suppliers', p_suppliers,
                        'item_category_type_id', p_item_category_type_id,
                        'action', v_action_type,
                        'tenant_id', p_tenant_id,
                        'user_id', p_user_id,
                        'user_name', p_user_name
                    );

                    v_item_data := jsonb_build_object('old_data', v_old_data, 'new_data', v_new_data);

                    -- Log activity
                    BEGIN
                        PERFORM log_activity(
                            'item.' || v_action_type,
                            'Item ' || v_action_type || ' by ' || p_user_name || ': ' || p_item_name,
                            'item',
                            v_item_id,
                            'user',
                            p_user_id,
                            v_item_data,
                            p_tenant_id
                        );
                        v_log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                        v_log_success := FALSE;
                        v_error_message := 'Logging failed: ' || SQLERRM;
                    END;

                    -- Supplier sync
                    SELECT array_agg(supplier_id) INTO v_existing_supplier_ids
                    FROM suppliers_for_item 
                    WHERE master_item_id = v_item_id 
                    AND deleted_at IS NULL;

                    IF p_suppliers IS NOT NULL THEN
                        FOR v_supplier_record IN SELECT * FROM jsonb_array_elements(p_suppliers)
                        LOOP
                            v_supplier_id := (v_supplier_record->>'id')::BIGINT;
                            v_lead_time := (v_supplier_record->>'lead_time')::INTEGER;

                            v_existing_supplier_ids := array_remove(v_existing_supplier_ids, v_supplier_id);

                            IF EXISTS (
                                SELECT 1 FROM suppliers_for_item 
                                WHERE master_item_id = v_item_id 
                                AND supplier_id = v_supplier_id
                                AND deleted_at IS NULL
                            ) THEN
                                UPDATE suppliers_for_item SET
                                    lead_time = v_lead_time,
                                    isactive = TRUE,
                                    deleted_at = NULL
                                WHERE master_item_id = v_item_id 
                                AND supplier_id = v_supplier_id;
                            ELSE
                                INSERT INTO suppliers_for_item (
                                    master_item_id, supplier_id, lead_time, tenant_id, isactive
                                ) VALUES (
                                    v_item_id, v_supplier_id, v_lead_time, p_tenant_id, TRUE
                                );
                            END IF;
                        END LOOP;
                    END IF;

                    IF v_existing_supplier_ids IS NOT NULL AND array_length(v_existing_supplier_ids, 1) > 0 THEN
                        UPDATE suppliers_for_item SET
                            deleted_at = NOW(),
                            isactive = FALSE
                        WHERE master_item_id = v_item_id
                        AND supplier_id = ANY(v_existing_supplier_ids)
                        AND deleted_at IS NULL;
                    END IF;

                    -- Success
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        CASE WHEN p_id = 0 THEN 'Item created successfully' ELSE 'Item updated successfully' END ||
                        CASE WHEN NOT v_log_success THEN ' (but logging failed: ' || v_error_message || ')' ELSE '' END::TEXT AS message,
                        jsonb_build_object(
                            'item_id', v_item_id,
                            'log_data', v_item_data,
                            'log_success', COALESCE(v_log_success, FALSE)
                        ) AS result_data;

                EXCEPTION WHEN OTHERS THEN
                    BEGIN
                        PERFORM log_activity(
                            'item.error',
                            'Failed to ' || COALESCE(v_action_type, 'process') || ' item: ' || SQLERRM,
                            'item',
                            COALESCE(v_item_id, p_id),
                            'user',
                            p_user_id,
                            jsonb_build_object(
                                'error', SQLERRM,
                                'input', jsonb_build_object(
                                    'item_name', p_item_name,
                                    'item_id', p_item_id,
                                    'user_id', p_user_id,
                                    'tenant_id', p_tenant_id
                                )
                            ),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN END;

                    RETURN QUERY SELECT 
                        'ERROR'::TEXT AS status,
                        'Database error: ' || SQLERRM::TEXT AS message,
                        NULL::JSONB AS result_data;
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
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS upsert_item_with_suppliers_func(
                BIGINT, TEXT, TEXT, TEXT, BIGINT, BIGINT, BIGINT, DECIMAL, BIGINT, DECIMAL, BIGINT,
                INTEGER, INTEGER, INTEGER, BOOLEAN, BOOLEAN, BIGINT, JSONB, JSONB, BIGINT, TEXT
            );
        SQL);
    }
};