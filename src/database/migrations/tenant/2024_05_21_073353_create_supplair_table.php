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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); 
            $table->json('contact_no')->nullable();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->string('supplier_type')->nullable();
            $table->string('supplier_reg_no')->nullable();
            $table->string('supplier_reg_status')->nullable();
            $table->json('supplier_asset_classes')->nullable();
            $table->bigInteger('supplier_rating')->nullable(); 
            $table->string('supplier_business_name')->nullable();
            $table->string('supplier_business_register_no')->nullable();
            $table->string('supplier_primary_email')->nullable();
            $table->string('supplier_secondary_email')->nullable();
            $table->string('supplier_br_attachment')->nullable();
            $table->string('supplier_website')->nullable();
            $table->string('supplier_tel_no')->nullable();
            $table->string('supplier_mobile')->nullable();
            $table->string('supplier_fax')->nullable();
            $table->string('supplier_city')->nullable();
            $table->string('supplier_location_latitude')->nullable();
            $table->string('supplier_location_longitude')->nullable();

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
        Schema::dropIfExists('suppliers');
    }
};