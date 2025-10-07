<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // DB::unprepared('

        //     -- Drop old trigger first if exists
        //     DROP TRIGGER IF EXISTS trigger_process_asset_reading ON asset_items_readings;
        //     DROP FUNCTION IF EXISTS process_asset_reading();

        //     -- Create new function
        //     CREATE OR REPLACE FUNCTION process_asset_reading() RETURNS trigger AS $$
        //     DECLARE
        //         rec RECORD;
        //         matched BOOLEAN;
        //     BEGIN
        //         FOR rec IN
        //             SELECT *
        //             FROM asset_item_manufacturer_recommendation_maintain_schedules
        //             WHERE asset_item = NEW.asset_item
        //               AND reading_parameters = NEW.parameter
        //               AND isactive = true
        //         LOOP
        //             matched := false;

        //             IF rec.operator = \'>\' AND (NEW.value::numeric > rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'<\' AND (NEW.value::numeric < rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'=\' AND (NEW.value = rec.limit_or_value) THEN
        //                 matched := true;
        //             END IF;

        //             IF matched THEN
        //                 INSERT INTO asset_item_action_queries (asset_item, reading_id, recommendation_id, created_at, updated_at)
        //                 VALUES (NEW.asset_item, NEW.id, rec.id, NOW(), NOW());

        //                 -- Notify Laravel with reading id
        //                 PERFORM pg_notify(\'new_asset_action\', NEW.id::text);
        //             END IF;
        //         END LOOP;

        //         RETURN NEW;
        //     END;
        //     $$ LANGUAGE plpgsql;

        //     -- Create new trigger
        //     CREATE TRIGGER trigger_process_asset_reading
        //     AFTER INSERT ON asset_items_readings
        //     FOR EACH ROW EXECUTE PROCEDURE process_asset_reading();
        // ');

        // DB::unprepared('
        //     CREATE OR REPLACE FUNCTION process_asset_reading() RETURNS trigger AS $$
        //     DECLARE
        //         rec RECORD;
        //         matched BOOLEAN;
        //     BEGIN
        //         -- Loop through both tables using a UNION
        //         FOR rec IN
        //             SELECT id, asset_item, maintain_schedule_parameters, limit_or_value, operator, reading_parameters, \'manufacturer\' AS source
        //             FROM asset_item_manufacturer_recommendation_maintain_schedules
        //             WHERE asset_item = NEW.asset_item AND reading_parameters = NEW.parameter AND isactive = true
        //             UNION ALL
        //             SELECT id, asset_item, maintain_schedule_parameters, limit_or_value, operator, reading_parameters, \'usage\' AS source
        //             FROM asset_item_usage_based_maintain_schedules
        //             WHERE asset_item = NEW.asset_item AND reading_parameters = NEW.parameter AND isactive = true
        //         LOOP
        //             matched := false;

        //             IF rec.operator = \'>\' AND (NEW.value::numeric > rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'<\' AND (NEW.value::numeric < rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'=\' AND (NEW.value = rec.limit_or_value) THEN
        //                 matched := true;
        //             END IF;

        //             IF matched THEN
        //                 INSERT INTO asset_item_action_queries (asset_item, reading_id, recommendation_id, source, created_at, updated_at)
        //                 VALUES (NEW.asset_item, NEW.id, rec.id, rec.source, NOW(), NOW());

        //                 PERFORM pg_notify(\'new_asset_action\', NEW.id::text);
        //             END IF;
        //         END LOOP;

        //         RETURN NEW;
        //     END;
        //     $$ LANGUAGE plpgsql;

        //     CREATE TRIGGER trigger_process_asset_reading
        //     AFTER INSERT ON asset_items_readings
        //     FOR EACH ROW
        //     EXECUTE PROCEDURE process_asset_reading();
        // ');

        // DB::unprepared('
        //     CREATE OR REPLACE FUNCTION process_asset_reading() RETURNS trigger AS $$
        //     DECLARE
        //         rec RECORD;
        //         matched BOOLEAN;
        //     BEGIN
        //         -- Loop through both tables using a UNION
        //         FOR rec IN
        //             SELECT id, asset_item, maintain_schedule_parameters, limit_or_value, operator, reading_parameters, \'manufacturer\' AS source
        //             FROM asset_item_manufacturer_recommendation_maintain_schedules
        //             WHERE asset_item = NEW.asset_item AND reading_parameters = NEW.parameter AND isactive = true
        //             UNION ALL
        //             SELECT id, asset_item, maintain_schedule_parameters, limit_or_value, operator, reading_parameters, \'usage\' AS source
        //             FROM asset_item_usage_based_maintain_schedules
        //             WHERE asset_item = NEW.asset_item AND reading_parameters = NEW.parameter AND isactive = true
        //             UNION ALL
        //             SELECT 
        //                 amrms.id, 
        //                 amrms.asset AS asset_item, 
        //                 amrms.maintain_schedule_parameters, 
        //                 amrms.limit_or_value, operator, 
        //                 amrms.reading_parameters, 
        //                 \'asset_group_manufacturer\' AS source
        //             FROM asset_items ai
        //             INNER JOIN assets a ON ai.asset_id = a.id
        //             INNER JOIN asset_manufacturer_recommendation_maintain_schedules amrms ON a.id = amrms.asset
        //             WHERE ai.id = NEW.asset_item 
        //             AND amrms.reading_parameters = NEW.parameter 
        //             AND amrms.isactive = true
        //             UNION ALL
        //             SELECT 
        //                 aubms.id, 
        //                 aubms.asset AS asset_item, 
        //                 aubms.maintain_schedule_parameters, 
        //                 aubms.limit_or_value, operator, 
        //                 aubms.reading_parameters, 
        //                 \'asset_group_usage\' AS source
        //             FROM asset_items ai
        //             INNER JOIN assets a ON ai.asset_id = a.id
        //             INNER JOIN asset_usage_based_maintain_schedules aubms ON a.id = aubms.asset
        //             WHERE ai.id = NEW.asset_item 
        //             AND aubms.reading_parameters = NEW.parameter 
        //             AND aubms.isactive = true
        //         LOOP
        //             matched := false;

        //             IF rec.operator = \'>\' AND (NEW.value::numeric > rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'<\' AND (NEW.value::numeric < rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'=\' AND (NEW.value = rec.limit_or_value) THEN
        //                 matched := true;
        //             END IF;

        //             IF matched THEN
        //                 INSERT INTO asset_item_action_queries (asset_item, reading_id, recommendation_id, source, created_at, updated_at)
        //                 VALUES (NEW.asset_item, NEW.id, rec.id, rec.source, NOW(), NOW());

        //                 PERFORM pg_notify(\'new_asset_action\', NEW.id::text);
        //             END IF;
        //         END LOOP;

        //         RETURN NEW;
        //     END;
        //     $$ LANGUAGE plpgsql;

        //     CREATE TRIGGER trigger_process_asset_reading
        //     AFTER INSERT ON asset_items_readings
        //     FOR EACH ROW
        //     EXECUTE PROCEDURE process_asset_reading();
        // ');

        // DB::unprepared('
        //     CREATE OR REPLACE FUNCTION process_asset_reading() RETURNS trigger AS $$
        //     DECLARE
        //         rec RECORD;
        //         matched BOOLEAN;
        //     BEGIN
        //         -- Loop through both tables using a UNION
        //         FOR rec IN
        //             SELECT id, asset_item, maintain_schedule_parameters, limit_or_value, operator, reading_parameters, \'manufacturer\' AS source
        //             FROM asset_item_manufacturer_recommendation_maintain_schedules
        //             WHERE asset_item = NEW.asset_item AND reading_parameters = NEW.parameter AND isactive = true
        //             UNION ALL
        //             SELECT id, asset_item, maintain_schedule_parameters, limit_or_value, operator, reading_parameters, \'usage\' AS source
        //             FROM asset_item_usage_based_maintain_schedules
        //             WHERE asset_item = NEW.asset_item AND reading_parameters = NEW.parameter AND isactive = true
        //             UNION ALL
        //             SELECT 
        //                 amrms.id, 
        //                 amrms.asset AS asset_item, 
        //                 amrms.maintain_schedule_parameters, 
        //                 amrms.limit_or_value, operator, 
        //                 amrms.reading_parameters, 
        //                 \'asset_group_manufacturer\' AS source
        //             FROM asset_items ai
        //             INNER JOIN assets a ON ai.asset_id = a.id
        //             INNER JOIN asset_manufacturer_recommendation_maintain_schedules amrms ON a.id = amrms.asset
        //             WHERE ai.id = NEW.asset_item 
        //             AND amrms.reading_parameters = NEW.parameter 
        //             AND amrms.isactive = true
        //             UNION ALL
        //             SELECT 
        //                 aubms.id, 
        //                 aubms.asset AS asset_item, 
        //                 aubms.maintain_schedule_parameters, 
        //                 aubms.limit_or_value, operator, 
        //                 aubms.reading_parameters, 
        //                 \'asset_group_usage\' AS source
        //             FROM asset_items ai
        //             INNER JOIN assets a ON ai.asset_id = a.id
        //             INNER JOIN asset_usage_based_maintain_schedules aubms ON a.id = aubms.asset
        //             WHERE ai.id = NEW.asset_item 
        //             AND aubms.reading_parameters = NEW.parameter 
        //             AND aubms.isactive = true
        //         LOOP
        //             matched := false;

        //             -- Apply all operator logic
        //             IF rec.operator = \'>\' AND (NEW.value::numeric > rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'>=\' AND (NEW.value::numeric >= rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'<\' AND (NEW.value::numeric < rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'<=\' AND (NEW.value::numeric <= rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'=\' AND (NEW.value::numeric = rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'!=\' AND (NEW.value::numeric != rec.limit_or_value::numeric) THEN
        //                 matched := true;
        //             END IF;

        //             IF matched THEN
        //                 INSERT INTO asset_item_action_queries (asset_item, reading_id, recommendation_id, source, created_at, updated_at)
        //                 VALUES (NEW.asset_item, NEW.id, rec.id, rec.source, NOW(), NOW());

        //                 PERFORM pg_notify(\'new_asset_action\', NEW.id::text);
        //             END IF;
        //         END LOOP;

        //         RETURN NEW;
        //     END;
        //     $$ LANGUAGE plpgsql;

        //     CREATE TRIGGER trigger_process_asset_reading
        //     AFTER INSERT ON asset_items_readings
        //     FOR EACH ROW
        //     EXECUTE PROCEDURE process_asset_reading();
        // ');

        // DB::unprepared('
        //     CREATE OR REPLACE FUNCTION process_asset_reading() RETURNS trigger AS $$
        //     DECLARE
        //         rec RECORD;
        //         matched BOOLEAN;
        //     BEGIN
        //         -- Loop through both tables using a UNION
        //         FOR rec IN
        //             SELECT id, asset_item, maintain_schedule_parameters, limit_or_value, operator, reading_parameters, \'manufacturer\' AS source
        //             FROM asset_item_manufacturer_recommendation_maintain_schedules
        //             WHERE asset_item = NEW.asset_item AND reading_parameters = NEW.parameter AND isactive = true
        //             UNION ALL
        //             SELECT id, asset_item, maintain_schedule_parameters, limit_or_value, operator, reading_parameters, \'usage\' AS source
        //             FROM asset_item_usage_based_maintain_schedules
        //             WHERE asset_item = NEW.asset_item AND reading_parameters = NEW.parameter AND isactive = true
        //             UNION ALL
        //             SELECT 
        //                 amrms.id, 
        //                 amrms.asset AS asset_item, 
        //                 amrms.maintain_schedule_parameters, 
        //                 amrms.limit_or_value, operator, 
        //                 amrms.reading_parameters, 
        //                 \'asset_group_manufacturer\' AS source
        //             FROM asset_items ai
        //             INNER JOIN assets a ON ai.asset_id = a.id
        //             INNER JOIN asset_manufacturer_recommendation_maintain_schedules amrms ON a.id = amrms.asset
        //             WHERE ai.id = NEW.asset_item 
        //             AND amrms.reading_parameters = NEW.parameter 
        //             AND amrms.isactive = true
        //             UNION ALL
        //             SELECT 
        //                 aubms.id, 
        //                 aubms.asset AS asset_item, 
        //                 aubms.maintain_schedule_parameters, 
        //                 aubms.limit_or_value, operator, 
        //                 aubms.reading_parameters, 
        //                 \'asset_group_usage\' AS source
        //             FROM asset_items ai
        //             INNER JOIN assets a ON ai.asset_id = a.id
        //             INNER JOIN asset_usage_based_maintain_schedules aubms ON a.id = aubms.asset
        //             WHERE ai.id = NEW.asset_item 
        //             AND aubms.reading_parameters = NEW.parameter 
        //             AND aubms.isactive = true
        //         LOOP
        //             matched := false;

        //             -- Apply all operator logic
        //             IF rec.operator = \'>\' AND (rec.limit_or_value::numeric > NEW.value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'>=\' AND (rec.limit_or_value::numeric >= NEW.value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'<\' AND (rec.limit_or_value::numeric < NEW.value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'<=\' AND (rec.limit_or_value::numeric <= NEW.value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'=\' AND (rec.limit_or_value::numeric = NEW.value::numeric) THEN
        //                 matched := true;
        //             ELSIF rec.operator = \'!=\' AND (rec.limit_or_value::numeric != NEW.value::numeric) THEN
        //                 matched := true;
        //             END IF;

        //             IF matched THEN
        //                 INSERT INTO asset_item_action_queries (asset_item, reading_id, recommendation_id, source, tenant_id, created_at, updated_at)
        //                 VALUES (NEW.asset_item, NEW.id, rec.id, rec.source, NEW.tenant_id, NOW(), NOW());

        //                 PERFORM pg_notify(\'new_asset_action\', NEW.id::text);
        //             END IF;
        //         END LOOP;

        //         RETURN NEW;
        //     END;
        //     $$ LANGUAGE plpgsql;

        //     CREATE TRIGGER trigger_process_asset_reading
        //     AFTER INSERT ON asset_items_readings
        //     FOR EACH ROW
        //     EXECUTE PROCEDURE process_asset_reading();
        // ');

        DB::unprepared('
            CREATE OR REPLACE FUNCTION process_asset_reading() RETURNS trigger AS $$
            DECLARE
                rec RECORD;
                matched BOOLEAN;
                action_query_id BIGINT;
            BEGIN
                -- Loop through both tables using a UNION
                FOR rec IN
                    SELECT id, asset_item, maintain_schedule_parameters, limit_or_value, operator, reading_parameters, \'manufacturer\' AS source
                    FROM asset_item_manufacturer_recommendation_maintain_schedules
                    WHERE asset_item = NEW.asset_item AND reading_parameters = NEW.parameter AND isactive = true
                    UNION ALL
                    SELECT id, asset_item, maintain_schedule_parameters, limit_or_value, operator, reading_parameters, \'usage\' AS source
                    FROM asset_item_usage_based_maintain_schedules
                    WHERE asset_item = NEW.asset_item AND reading_parameters = NEW.parameter AND isactive = true
                    UNION ALL
                    SELECT 
                        amrms.id, 
                        amrms.asset AS asset_item, 
                        amrms.maintain_schedule_parameters, 
                        amrms.limit_or_value, operator, 
                        amrms.reading_parameters, 
                        \'asset_group_manufacturer\' AS source
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_manufacturer_recommendation_maintain_schedules amrms ON a.id = amrms.asset
                    WHERE ai.id = NEW.asset_item 
                    AND amrms.reading_parameters = NEW.parameter 
                    AND amrms.isactive = true
                    UNION ALL
                    SELECT 
                        aubms.id, 
                        aubms.asset AS asset_item, 
                        aubms.maintain_schedule_parameters, 
                        aubms.limit_or_value, operator, 
                        aubms.reading_parameters, 
                        \'asset_group_usage\' AS source
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_usage_based_maintain_schedules aubms ON a.id = aubms.asset
                    WHERE ai.id = NEW.asset_item 
                    AND aubms.reading_parameters = NEW.parameter 
                    AND aubms.isactive = true
                LOOP
                    matched := false;

                    -- Apply all operator logic
                    IF rec.operator = \'>\' AND (rec.limit_or_value::numeric > NEW.value::numeric) THEN
                        matched := true;
                    ELSIF rec.operator = \'>=\' AND (rec.limit_or_value::numeric >= NEW.value::numeric) THEN
                        matched := true;
                    ELSIF rec.operator = \'<\' AND (rec.limit_or_value::numeric < NEW.value::numeric) THEN
                        matched := true;
                    ELSIF rec.operator = \'<=\' AND (rec.limit_or_value::numeric <= NEW.value::numeric) THEN
                        matched := true;
                    ELSIF rec.operator = \'=\' AND (rec.limit_or_value::numeric = NEW.value::numeric) THEN
                        matched := true;
                    ELSIF rec.operator = \'!=\' AND (rec.limit_or_value::numeric != NEW.value::numeric) THEN
                        matched := true;
                    END IF;

                    IF matched THEN
                        INSERT INTO asset_item_action_queries (
                            asset_item, reading_id, recommendation_id, source, tenant_id, created_at, updated_at
                        ) VALUES (
                            NEW.asset_item, NEW.id, rec.id, rec.source, NEW.tenant_id, NOW(), NOW()
                        ) RETURNING id INTO action_query_id;

                        PERFORM pg_notify(\'new_asset_action\', NEW.id::text);

                        INSERT INTO work_order_tickets (
                            asset_id, reference_id, type, tenant_id, created_at, updated_at
                        ) VALUES (
                            NEW.asset_item, action_query_id, \'maintenance_alerts\', NEW.tenant_id, NOW(), NOW()
                        );

                    END IF;
                END LOOP;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trigger_process_asset_reading
            AFTER INSERT ON asset_items_readings
            FOR EACH ROW
            EXECUTE PROCEDURE process_asset_reading();
        ');

        }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::unprepared('
            DROP TRIGGER IF EXISTS trigger_process_asset_reading ON asset_items_readings;
            DROP FUNCTION IF EXISTS process_asset_reading();
        ');
    }
};