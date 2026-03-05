<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add tenant_id and deleted_at to the already-deployed
     * zombie_asset_reporter_types table (100001, batch 172).
     *
     * isactive already exists on this table — only tenant_id
     * and deleted_at are added here.
     */
    public function up(): void
    {
        Schema::table('zombie_asset_reporter_types', function (Blueprint $table) {
            // tenant_id: NULL = global/platform-wide record
            $table->unsignedBigInteger('tenant_id')->nullable()
                  ->after('description')
                  ->comment('NULL = global record; non-null = tenant-specific override');
            $table->timestamp('deleted_at')->nullable()->after('isactive');

            $table->index(
                ['tenant_id', 'isactive', 'deleted_at'],
                'idx_zombie_reporter_type_tenant'
            );
        });
    }

    public function down(): void
    {
        Schema::table('zombie_asset_reporter_types', function (Blueprint $table) {
            $table->dropIndex('idx_zombie_reporter_type_tenant');
            $table->dropColumn(['tenant_id', 'deleted_at']);
        });
    }
};
