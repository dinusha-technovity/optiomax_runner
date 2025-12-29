<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Change qr_code column from string to jsonb type
     * Format: [{"id": 5, "name": "qr_1_45053_1766406572.png"}]
     * Where id is the document_media id and name is the stored filename
     */
    public function up(): void
    {
        // Step 1: Add a temporary column to store the old data
        Schema::table('asset_items', function (Blueprint $table) {
            $table->string('qr_code_temp')->nullable()->after('qr_code');
        });

        // Step 2: Copy existing data to temp column
        DB::statement('UPDATE asset_items SET qr_code_temp = qr_code WHERE qr_code IS NOT NULL');

        // Step 3: Drop the old qr_code column
        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropColumn('qr_code');
        });

        // Step 4: Add new qr_code column as jsonb
        Schema::table('asset_items', function (Blueprint $table) {
            $table->jsonb('qr_code')->nullable()->after('thumbnail_image');
        });

        // Step 5: Migrate old data to new jsonb format
        // Convert string paths to jsonb array format: [{"id": null, "name": "filename.png"}]
        DB::statement("
            UPDATE asset_items 
            SET qr_code = jsonb_build_array(
                jsonb_build_object(
                    'id', NULL,
                    'name', SUBSTRING(qr_code_temp FROM '[^/]+$')
                )
            )
            WHERE qr_code_temp IS NOT NULL
        ");

        // Step 6: Drop the temporary column
        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropColumn('qr_code_temp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add a temporary column to store the jsonb data
        Schema::table('asset_items', function (Blueprint $table) {
            $table->jsonb('qr_code_temp')->nullable()->after('qr_code');
        });

        // Step 2: Copy existing jsonb data to temp column
        DB::statement('UPDATE asset_items SET qr_code_temp = qr_code WHERE qr_code IS NOT NULL');

        // Step 3: Drop the jsonb qr_code column
        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropColumn('qr_code');
        });

        // Step 4: Add back the string qr_code column
        Schema::table('asset_items', function (Blueprint $table) {
            $table->string('qr_code')->nullable()->after('thumbnail_image');
        });

        // Step 5: Convert jsonb back to string (extract first element's name)
        DB::statement("
            UPDATE asset_items 
            SET qr_code = qr_code_temp->0->>'name'
            WHERE qr_code_temp IS NOT NULL
        ");

        // Step 6: Drop the temporary column
        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropColumn('qr_code_temp');
        });
    }
};
