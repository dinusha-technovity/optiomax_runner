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

            DROP FUNCTION IF EXISTS insert_or_update_supplier(
                    VARCHAR, VARCHAR, TEXT, VARCHAR, JSON, JSON, BIGINT,
                    VARCHAR, VARCHAR, VARCHAR, VARCHAR, VARCHAR, VARCHAR, VARCHAR,
                    VARCHAR, VARCHAR, VARCHAR, VARCHAR, VARCHAR, JSON, BIGINT, TIMESTAMPTZ,
                    BIGINT, TEXT, BIGINT, BIGINT, BIGINT, VARCHAR, VARCHAR
            );

            DROP FUNCTION IF EXISTS insert_or_update_supplier(
                    VARCHAR, VARCHAR, TEXT, VARCHAR, JSON, JSON, BIGINT,
                    VARCHAR, VARCHAR, VARCHAR, VARCHAR, JSON, VARCHAR, VARCHAR,
                    VARCHAR, VARCHAR, VARCHAR, VARCHAR, VARCHAR, JSON, BIGINT, TIMESTAMPTZ,
                    BIGINT, TEXT, BIGINT, BIGINT, BIGINT, VARCHAR, VARCHAR, BIGINT, VARCHAR
            );

        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback actions needed for this migration
    }
};
