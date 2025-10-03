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
        // Create the sequence and function to generate formatted asset item tag IDs
        DB::unprepared(<<<SQL
        -- 1. Create the sequence
        CREATE SEQUENCE asset_item_tag_seq START WITH 1 INCREMENT BY 1;

        -- 2. Create the function to get the formatted ID
        CREATE OR REPLACE FUNCTION generate_asset_item_tag_id()
        RETURNS TEXT AS $$
        DECLARE
            seq_value BIGINT;
        BEGIN
            seq_value := nextval('asset_item_tag_seq');
            RETURN 'AST-' || LPAD(seq_value::TEXT, 5, '0');
        END;
        $$ LANGUAGE plpgsql;

        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS generate_asset_item_tag_id();
            DROP SEQUENCE IF EXISTS asset_item_tag_seq;
        SQL);
    }
};
