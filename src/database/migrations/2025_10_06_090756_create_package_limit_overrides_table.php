<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_limit_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('package_id');
            $table->string('limit_type'); // credits, workflows, users, storage
            $table->integer('original_value');
            $table->integer('override_value');
            $table->text('reason')->nullable();
            $table->timestamp('effective_from')->default(now());
            $table->timestamp('effective_until')->nullable();
            $table->boolean('is_permanent')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('package_id')->references('id')->on('tenant_packages')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['tenant_id', 'package_id']);
            $table->index(['effective_from', 'effective_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_limit_overrides');
    }
};