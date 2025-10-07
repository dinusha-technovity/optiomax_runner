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
        Schema::create('asset_maintenance_incident_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset');
            $table->dateTime('incident_date_time')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->jsonb('reporter_details')->nullable();
            $table->text('incident_description')->nullable();
            $table->jsonb('immediate_actions')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->boolean('production_affected')->default(false);
            $table->boolean('safety_risk')->default(false);
            $table->text('impact_description')->nullable();
            $table->jsonb('requested_actions')->nullable();
            $table->unsignedBigInteger('priority_level');
            $table->jsonb('attachments')->nullable();
            $table->text('root_cause_analysis')->nullable();
            $table->text('follow_up_actions')->nullable();
            $table->string('incident_reports_status')->default('saved');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->date('verification_date')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('report_created_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('asset')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('priority_level')->references('id')->on('asset_maintenance_incident_report_priority_levels')->onDelete('restrict');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('report_created_by')->references('id')->on('users')->onDelete('restrict');
        });

        // Add interval column via raw SQL
        DB::statement("ALTER TABLE asset_maintenance_incident_reports ADD COLUMN downtime_duration INTERVAL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_incident_report');
    }
};