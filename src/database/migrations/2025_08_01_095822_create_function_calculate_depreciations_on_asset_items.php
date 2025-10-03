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

        -- CREATE OR REPLACE FUNCTION generate_daily_depreciation(
        --     p_tenant_id BIGINT,
        --     p_record_date DATE
        -- )
        -- RETURNS BIGINT AS $$
        -- DECLARE
        --     asset_rec RECORD;
        --     dep_method_rec RECORD;
        --     time_unit_rec RECORD;
        --     days_in_life INTEGER;
        --     days_elapsed INTEGER;
        --     book_value_start NUMERIC;
        --     depreciation_amount NUMERIC;
        --     book_value_end NUMERIC;
        --     salvage_value NUMERIC;
        --     sum_of_years INTEGER;
        --     remaining_years INTEGER;
        --     multiplier NUMERIC;
        --     already_processed BOOLEAN;
        --     missing_fields TEXT;
        --     completed_years INTEGER;
        --     yearly_depreciation NUMERIC;
            
        --     -- Batch logging variables
        --     v_start_time TIMESTAMP;
        --     v_end_time TIMESTAMP;
        --     v_total_assets INTEGER := 0;
        --     v_success_count INTEGER := 0;
        --     v_failed_count INTEGER := 0;
        --     v_failed_assets JSONB := '[]'::jsonb;
        --     v_system_errors JSONB := '[]'::jsonb;
        --     v_batch_id BIGINT;
        --     v_error_message TEXT;
        -- BEGIN
        --     -- Initialize batch logging
        --     v_start_time := NOW();
            
        --     -- Get total count of assets to process
        --     SELECT COUNT(*) INTO v_total_assets
        --     FROM asset_items ai
        --     JOIN depreciation_method_table dm ON ai.depreciation_method = dm.id
        --     WHERE ai.isactive = TRUE
        --         AND ai.tenant_id = p_tenant_id
        --         AND ai.depreciation_start_date IS NOT NULL
        --         AND ai.depreciation_start_date <= p_record_date;
            
        --     -- Loop through all active assets for the tenant that should be depreciated on this date
        --     FOR asset_rec IN 
        --         SELECT 
        --             ai.id, ai.purchase_cost, ai.salvage_value, ai.expected_life_time, 
        --             ai.depreciation_method, ai.expected_life_time_unit, ai.depreciation_start_date,
        --             dm.slug as depreciation_method_slug, dm.multiplier
        --         FROM 
        --             asset_items ai
        --         JOIN 
        --             depreciation_method_table dm ON ai.depreciation_method = dm.id
        --         WHERE 
        --             ai.isactive = TRUE
        --             AND ai.tenant_id = p_tenant_id
        --             AND ai.depreciation_start_date IS NOT NULL
        --             AND ai.depreciation_start_date <= p_record_date
        --     LOOP
        --         -- Reset variables for each asset
        --         missing_fields := '';
                
        --         -- Check if required fields are present
        --         IF asset_rec.purchase_cost IS NULL THEN
        --             missing_fields := missing_fields || 'purchase_cost, ';
        --         END IF;
                
        --         IF asset_rec.expected_life_time IS NULL THEN
        --             missing_fields := missing_fields || 'expected_life_time, ';
        --         END IF;
                
        --         IF asset_rec.depreciation_method IS NULL THEN
        --             missing_fields := missing_fields || 'depreciation_method, ';
        --         END IF;
                
        --         IF asset_rec.expected_life_time_unit IS NULL THEN
        --             missing_fields := missing_fields || 'expected_life_time_unit, ';
        --         END IF;
                
        --         -- If any required fields are missing, log and skip this asset
        --         IF missing_fields <> '' THEN
        --             v_failed_count := v_failed_count + 1;
        --             v_error_message := 'Missing required fields: ' || substring(missing_fields from 1 for length(missing_fields)-2);
        --             v_failed_assets := v_failed_assets || jsonb_build_object(
        --                 'asset_id', asset_rec.id,
        --                 'error', v_error_message
        --             );
                    
        --             -- PERFORM log_activity(
        --             --     'asset_depreciation.skipped',
        --             --     'Skipped depreciation for asset ID ' || COALESCE(asset_rec.id::text, 'unknown') || 
        --             --     ' due to missing required fields: ' || COALESCE(substring(missing_fields from 1 for length(missing_fields)-2), 'unknown'),
        --             --     'asset_items',
        --             --     asset_rec.id,
        --             --     'system',
        --             --     NULL,
        --             --     NULL,
        --             --     p_tenant_id
        --             -- );
        --             CONTINUE;
        --         END IF;
                
        --         -- Check if depreciation already exists for this asset and date
        --         SELECT EXISTS (
        --             SELECT 1 FROM asset_depreciation_schedules 
        --             WHERE asset_item_id = asset_rec.id 
        --             AND record_date = p_record_date
        --             AND tenant_id = p_tenant_id
        --         ) INTO already_processed;
                
        --         IF already_processed THEN
        --             CONTINUE;
        --         END IF;
                
        --         -- Get the time period unit details
        --         SELECT * INTO time_unit_rec 
        --         FROM time_period_entries 
        --         WHERE id = asset_rec.expected_life_time_unit;
                
        --         IF NOT FOUND THEN
        --             v_failed_count := v_failed_count + 1;
        --             v_error_message := 'Invalid expected_life_time_unit: ' || COALESCE(asset_rec.expected_life_time_unit::text, 'null');
        --             v_failed_assets := v_failed_assets || jsonb_build_object(
        --                 'asset_id', asset_rec.id,
        --                 'error', v_error_message
        --             );
                    
        --             -- PERFORM log_activity(
        --             --     'asset_depreciation.skipped',
        --             --     'Skipped depreciation for asset ID ' || asset_rec.id || 
        --             --     ' due to invalid expected_life_time_unit: ' || COALESCE(asset_rec.expected_life_time_unit::text, 'null'),
        --             --     'asset_items',
        --             --     asset_rec.id,
        --             --     'system',
        --             --     NULL,
        --             --     NULL,
        --             --     p_tenant_id
        --             -- );
        --             CONTINUE;
        --         END IF;
                
        --         -- Convert expected lifetime to days
        --         CASE time_unit_rec.slug
        --             WHEN 'year' THEN
        --                 days_in_life := asset_rec.expected_life_time::numeric * 365;
        --             WHEN 'month' THEN
        --                 days_in_life := asset_rec.expected_life_time::numeric * 30;
        --             WHEN 'day' THEN
        --                 days_in_life := asset_rec.expected_life_time::numeric;
        --             ELSE
        --                 v_failed_count := v_failed_count + 1;
        --                 v_error_message := 'Unsupported time unit: ' || COALESCE(time_unit_rec.slug, 'unknown');
        --                 v_failed_assets := v_failed_assets || jsonb_build_object(
        --                     'asset_id', asset_rec.id,
        --                     'error', v_error_message
        --                 );
                        
        --                 -- PERFORM log_activity(
        --                 --     'asset_depreciation.skipped',
        --                 --     'Skipped depreciation for asset ID ' || asset_rec.id || 
        --                 --     ' due to unsupported time unit: ' || COALESCE(time_unit_rec.slug, 'unknown'),
        --                 --     'asset_items',
        --                 --     asset_rec.id,
        --                 --     'system',
        --                 --     NULL,
        --                 --     NULL,
        --                 --     p_tenant_id
        --                 -- );
        --                 CONTINUE;
        --         END CASE;
                
        --         -- Calculate days elapsed since depreciation start
        --         days_elapsed := p_record_date - asset_rec.depreciation_start_date;
                
        --         -- Get the latest book value or fall back to purchase cost
        --         SELECT ads.book_value_end INTO book_value_start
        --         FROM asset_depreciation_schedules ads
        --         WHERE ads.asset_item_id = asset_rec.id
        --         AND ads.record_date < p_record_date
        --         AND ads.tenant_id = p_tenant_id
        --         ORDER BY ads.record_date DESC
        --         LIMIT 1;
                
        --         IF NOT FOUND THEN
        --             book_value_start := asset_rec.purchase_cost;
        --         END IF;
                
        --         -- Set salvage value (default to 0 if NULL)
        --         salvage_value := COALESCE(asset_rec.salvage_value, 0);
                
        --         -- Skip if book value is already at or below salvage value
        --         IF book_value_start <= salvage_value THEN
        --             CONTINUE;
        --         END IF;
                
        --         -- Calculate depreciation based on method
        --         CASE asset_rec.depreciation_method_slug
        --             WHEN 'straightline' THEN
        --                 -- Straightline depreciation
        --                 depreciation_amount := (asset_rec.purchase_cost - salvage_value) / days_in_life;
                        
        --             WHEN 'declining-balance' THEN
        --                 -- Double Declining Balance depreciation
        --                 multiplier := COALESCE(asset_rec.multiplier, 2.0);
        --                 depreciation_amount := book_value_start * multiplier / 365;
                        
        --                 -- Ensure we don't depreciate below salvage value
        --                 IF (book_value_start - depreciation_amount) < salvage_value THEN
        --                     depreciation_amount := book_value_start - salvage_value;
        --                 END IF;
                        
        --             WHEN 'sum-of-the-years-digits' THEN
        --                 -- Sum-of-the-Years' Digits depreciation
        --                 -- sum_of_years := (asset_rec.expected_life_time::numeric * (asset_rec.expected_life_time::numeric + 1)) / 2;
        --                 -- remaining_years := asset_rec.expected_life_time::numeric - floor(days_elapsed / 365);
                        
        --                 -- IF remaining_years < 0 THEN
        --                 --     remaining_years := 0;
        --                 -- END IF;
                        
        --                 -- depreciation_amount := ((asset_rec.purchase_cost - salvage_value) * remaining_years / sum_of_years) / 365;

        --                 sum_of_years := (asset_rec.expected_life_time::numeric * (asset_rec.expected_life_time::numeric + 1)) / 2;
        --                 completed_years := floor(days_elapsed / 365);
        --                 remaining_years := asset_rec.expected_life_time::numeric - completed_years;

        --                 IF remaining_years <= 0 THEN
        --                     depreciation_amount := 0;
        --                 ELSE
        --                     yearly_depreciation := (asset_rec.purchase_cost - salvage_value) * remaining_years / sum_of_years;
        --                     depreciation_amount := yearly_depreciation / 365;
        --                 END IF;
                        
        --             ELSE
        --                 -- Skip unsupported methods (like Units of Production)
        --                 v_failed_count := v_failed_count + 1;
        --                 v_error_message := 'Unsupported depreciation method: ' || COALESCE(asset_rec.depreciation_method_slug, 'unknown');
        --                 v_failed_assets := v_failed_assets || jsonb_build_object(
        --                     'asset_id', asset_rec.id,
        --                     'error', v_error_message
        --                 );
                        
        --                 -- PERFORM log_activity(
        --                 --     'asset_depreciation.skipped',
        --                 --     'Skipped depreciation for asset ID ' || asset_rec.id || 
        --                 --     ' due to unsupported depreciation method: ' || COALESCE(asset_rec.depreciation_method_slug, 'unknown'),
        --                 --     'asset_items',
        --                 --     asset_rec.id,
        --                 --     'system',
        --                 --     NULL,
        --                 --     NULL,
        --                 --     p_tenant_id
        --                 -- );
        --                 CONTINUE;
        --         END CASE;
                
        --         -- Skip if depreciation amount is zero or negative
        --         IF depreciation_amount <= 0 THEN
        --             CONTINUE;
        --         END IF;
                
        --         -- Calculate ending book value
        --         book_value_end := book_value_start - depreciation_amount;
                
        --         -- Ensure book value doesn't go below salvage value
        --         IF book_value_end < salvage_value THEN
        --             book_value_end := salvage_value;
        --             depreciation_amount := book_value_start - salvage_value;
        --         END IF;
                
        --         -- Insert the depreciation record
        --         INSERT INTO asset_depreciation_schedules (
        --             asset_item_id,
        --             tenant_id,
        --             fiscal_year,
        --             fiscal_month,
        --             record_date,
        --             book_value_start,
        --             depreciation_amount,
        --             book_value_end,
        --             calculated_at,
        --             depreciation_method_id,
        --             created_at,
        --             updated_at
        --         ) VALUES (
        --             asset_rec.id,
        --             p_tenant_id,
        --             EXTRACT(YEAR FROM p_record_date),
        --             EXTRACT(MONTH FROM p_record_date),
        --             p_record_date,
        --             book_value_start,
        --             depreciation_amount,
        --             book_value_end,
        --             NOW(),
        --             asset_rec.depreciation_method,
        --             NOW(),
        --             NOW()
        --         );
                
        --         -- Increment success counter
        --         v_success_count := v_success_count + 1;
                
        --     END LOOP;
            
        --     -- Log the batch execution
        --     v_end_time := NOW();
            
        --     SELECT log_depreciation_batch(
        --         v_start_time,
        --         v_end_time,
        --         v_total_assets,
        --         v_success_count,
        --         v_failed_count,
        --         p_tenant_id,
        --         CASE WHEN v_failed_count > 0 THEN v_failed_assets ELSE NULL END,
        --         'Daily depreciation calculation for date: ' || p_record_date::text,
        --         CASE WHEN jsonb_array_length(v_system_errors) > 0 THEN v_system_errors ELSE NULL END,
        --         'system'
        --     ) INTO v_batch_id;
            
        --     RETURN v_batch_id;
        -- END;
        -- $$ LANGUAGE plpgsql;


        CREATE OR REPLACE PROCEDURE generate_daily_depreciation(
            IN p_tenant_id BIGINT,
            IN p_record_date DATE
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            asset_rec RECORD;
            dep_method_rec RECORD;
            time_unit_rec RECORD;
            days_in_life INTEGER;
            days_elapsed INTEGER;
            book_value_start NUMERIC;
            depreciation_amount NUMERIC;
            book_value_end NUMERIC;
            salvage_value NUMERIC;
            sum_of_years INTEGER;
            remaining_years INTEGER;
            multiplier NUMERIC;
            already_processed BOOLEAN;
            missing_fields TEXT;
            completed_years INTEGER;
            yearly_depreciation NUMERIC;

            -- Batch logging variables
            v_start_time TIMESTAMP;
            v_end_time TIMESTAMP;
            v_total_assets INTEGER := 0;
            v_success_count INTEGER := 0;
            v_failed_count INTEGER := 0;
            v_failed_assets JSONB := '[]'::jsonb;
            v_system_errors JSONB := '[]'::jsonb;
            v_batch_id BIGINT;
            v_error_message TEXT;
        BEGIN
            -- Initialize batch logging
            v_start_time := NOW();

            -- Get total count of assets to process
            SELECT COUNT(*) INTO v_total_assets
            FROM asset_items ai
            JOIN depreciation_method_table dm ON ai.depreciation_method = dm.id
            WHERE ai.isactive = TRUE
            AND ai.tenant_id = p_tenant_id
            AND ai.depreciation_start_date IS NOT NULL
            AND ai.depreciation_start_date <= p_record_date;

            FOR asset_rec IN 
                SELECT 
                    ai.id, ai.purchase_cost, ai.salvage_value, ai.expected_life_time, 
                    ai.depreciation_method, ai.expected_life_time_unit, ai.depreciation_start_date,
                    dm.slug as depreciation_method_slug, dm.multiplier
                FROM 
                    asset_items ai
                JOIN 
                    depreciation_method_table dm ON ai.depreciation_method = dm.id
                WHERE 
                    ai.isactive = TRUE
                    AND ai.tenant_id = p_tenant_id
                    AND ai.depreciation_start_date IS NOT NULL
                    AND ai.depreciation_start_date <= p_record_date
            LOOP
                missing_fields := '';

                IF asset_rec.purchase_cost IS NULL THEN
                    missing_fields := missing_fields || 'purchase_cost, ';
                END IF;

                IF asset_rec.expected_life_time IS NULL THEN
                    missing_fields := missing_fields || 'expected_life_time, ';
                END IF;

                IF asset_rec.depreciation_method IS NULL THEN
                    missing_fields := missing_fields || 'depreciation_method, ';
                END IF;

                IF asset_rec.expected_life_time_unit IS NULL THEN
                    missing_fields := missing_fields || 'expected_life_time_unit, ';
                END IF;

                IF missing_fields <> '' THEN
                    v_failed_count := v_failed_count + 1;
                    v_error_message := 'Missing required fields: ' || substring(missing_fields from 1 for length(missing_fields)-2);
                    v_failed_assets := v_failed_assets || jsonb_build_object(
                        'asset_id', asset_rec.id,
                        'error', v_error_message
                    );
                    CONTINUE;
                END IF;

                SELECT EXISTS (
                    SELECT 1 FROM asset_depreciation_schedules 
                    WHERE asset_item_id = asset_rec.id 
                    AND record_date = p_record_date
                    AND tenant_id = p_tenant_id
                ) INTO already_processed;

                IF already_processed THEN
                    CONTINUE;
                END IF;

                SELECT * INTO time_unit_rec 
                FROM time_period_entries 
                WHERE id = asset_rec.expected_life_time_unit;

                IF NOT FOUND THEN
                    v_failed_count := v_failed_count + 1;
                    v_error_message := 'Invalid expected_life_time_unit: ' || COALESCE(asset_rec.expected_life_time_unit::text, 'null');
                    v_failed_assets := v_failed_assets || jsonb_build_object(
                        'asset_id', asset_rec.id,
                        'error', v_error_message
                    );
                    CONTINUE;
                END IF;

                CASE time_unit_rec.slug
                    WHEN 'year' THEN
                        days_in_life := asset_rec.expected_life_time::numeric * 365;
                    WHEN 'month' THEN
                        days_in_life := asset_rec.expected_life_time::numeric * 30;
                    WHEN 'day' THEN
                        days_in_life := asset_rec.expected_life_time::numeric;
                    ELSE
                        v_failed_count := v_failed_count + 1;
                        v_error_message := 'Unsupported time unit: ' || COALESCE(time_unit_rec.slug, 'unknown');
                        v_failed_assets := v_failed_assets || jsonb_build_object(
                            'asset_id', asset_rec.id,
                            'error', v_error_message
                        );
                        CONTINUE;
                END CASE;

                days_elapsed := p_record_date - asset_rec.depreciation_start_date;

                SELECT ads.book_value_end INTO book_value_start
                FROM asset_depreciation_schedules ads
                WHERE ads.asset_item_id = asset_rec.id
                AND ads.record_date < p_record_date
                AND ads.tenant_id = p_tenant_id
                ORDER BY ads.record_date DESC
                LIMIT 1;

                IF NOT FOUND THEN
                    book_value_start := asset_rec.purchase_cost;
                END IF;

                salvage_value := COALESCE(asset_rec.salvage_value, 0);

                IF book_value_start <= salvage_value THEN
                    CONTINUE;
                END IF;

                CASE asset_rec.depreciation_method_slug
                    WHEN 'straightline' THEN
                        depreciation_amount := (asset_rec.purchase_cost - salvage_value) / days_in_life;

                    WHEN 'declining-balance' THEN
                        multiplier := COALESCE(asset_rec.multiplier, 2.0);
                        depreciation_amount := book_value_start * multiplier / 365;

                        IF (book_value_start - depreciation_amount) < salvage_value THEN
                            depreciation_amount := book_value_start - salvage_value;
                        END IF;

                    WHEN 'sum-of-the-years-digits' THEN
                        sum_of_years := (asset_rec.expected_life_time::numeric * (asset_rec.expected_life_time::numeric + 1)) / 2;
                        completed_years := floor(days_elapsed / 365);
                        remaining_years := asset_rec.expected_life_time::numeric - completed_years;

                        IF remaining_years <= 0 THEN
                            depreciation_amount := 0;
                        ELSE
                            yearly_depreciation := (asset_rec.purchase_cost - salvage_value) * remaining_years / sum_of_years;
                            depreciation_amount := yearly_depreciation / 365;
                        END IF;

                    ELSE
                        v_failed_count := v_failed_count + 1;
                        v_error_message := 'Unsupported depreciation method: ' || COALESCE(asset_rec.depreciation_method_slug, 'unknown');
                        v_failed_assets := v_failed_assets || jsonb_build_object(
                            'asset_id', asset_rec.id,
                            'error', v_error_message
                        );
                        CONTINUE;
                END CASE;

                IF depreciation_amount <= 0 THEN
                    CONTINUE;
                END IF;

                book_value_end := book_value_start - depreciation_amount;

                IF book_value_end < salvage_value THEN
                    book_value_end := salvage_value;
                    depreciation_amount := book_value_start - salvage_value;
                END IF;

                INSERT INTO asset_depreciation_schedules (
                    asset_item_id,
                    tenant_id,
                    fiscal_year,
                    fiscal_month,
                    record_date,
                    book_value_start,
                    depreciation_amount,
                    book_value_end,
                    calculated_at,
                    depreciation_method_id,
                    created_at,
                    updated_at
                ) VALUES (
                    asset_rec.id,
                    p_tenant_id,
                    EXTRACT(YEAR FROM p_record_date),
                    EXTRACT(MONTH FROM p_record_date),
                    p_record_date,
                    book_value_start,
                    depreciation_amount,
                    book_value_end,
                    NOW(),
                    asset_rec.depreciation_method,
                    NOW(),
                    NOW()
                );

                v_success_count := v_success_count + 1;
            END LOOP;

            v_end_time := NOW();

            -- Final batch log
            PERFORM log_depreciation_batch(
                v_start_time,
                v_end_time,
                v_total_assets,
                v_success_count,
                v_failed_count,
                p_tenant_id,
                CASE WHEN v_failed_count > 0 THEN v_failed_assets ELSE NULL END,
                'Daily depreciation calculation for date: ' || p_record_date::text,
                CASE WHEN jsonb_array_length(v_system_errors) > 0 THEN v_system_errors ELSE NULL END,
                'system'
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
        // DB::unprepared('DROP FUNCTION IF EXISTS generate_daily_depreciation( BIGINT, DATE);');

        DB::unprepared('DROP PROCEDURE IF EXISTS generate_daily_depreciation( BIGINT, DATE);');
    }
};
