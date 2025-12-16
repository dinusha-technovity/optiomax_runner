<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number')->unique();
            $table->string('employee_name');
            $table->string('email')->unique();
            $table->unsignedBigInteger('department')->nullable(); // Foreign key to organization
            $table->string('phone_number')->nullable();
            $table->unsignedBigInteger('contact_no_code')->nullable(); // Foreign key to countries
            $table->text('address')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable(); // Foreign key to designations
            $table->unsignedBigInteger('user_id')->nullable(); // Foreign key to users
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('contact_no_code')->references('id')->on('countries')->onDelete('restrict');
            $table->foreign('department')->references('id')->on('organization')->onDelete('restrict');
            $table->foreign('designation_id')->references('id')->on('designations')->onDelete('restrict');

            // Indexes for performance
            $table->index(['user_id', 'department', 'designation_id']);
            $table->index('employee_number');
            $table->index('email');
            $table->index('isactive');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};