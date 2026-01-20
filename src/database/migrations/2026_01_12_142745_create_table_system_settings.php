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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id')
                ->comment('ID of the tenant this setting belongs to');

            $table->string('key')->comment('Unique identifier for the setting');
            $table->text('value')->comment('Actual value of the setting');
            $table->string('type')->default('string');
            $table->string('category')->default('general');
            $table->string('label')->nullable();
            $table->text('description')->nullable();

            $table->boolean('is_editable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->jsonb('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'key'], 'system_settings_tenant_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
