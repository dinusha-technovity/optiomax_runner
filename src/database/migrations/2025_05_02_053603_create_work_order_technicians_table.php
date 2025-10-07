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
        Schema::create('work_order_technicians', function (Blueprint $table) {
            $table->id();
    
            // Name fields
            $table->string('name');
            
            
            // Contact information
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('address')->nullable();

            
            // Professional details
            $table->string('employee_id')->nullable()->unique();
            $table->string('job_title')->nullable();
            $table->string('specialization')->nullable();
            $table->json('certifications')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->boolean('is_contractor')->default(false);
            
            // Availability
            $table->boolean('is_available')->default(true);
            $table->text('unavailable_reason')->nullable();
            
            // Common columns
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_technicians');
    }
};
