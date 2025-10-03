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
        // Create sequence for customer codes
        DB::statement('CREATE SEQUENCE customer_code_seq START 1');
        
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('national_id')->nullable();
            $table->string('primary_contact_person')->nullable();
            $table->string('designation')->nullable();
            $table->string('phone_mobile')->nullable();
            $table->unsignedBigInteger('phone_mobile_code_id')->nullable();
            $table->string('phone_landline')->nullable();
            $table->unsignedBigInteger('phone_landline_code_id')->nullable();
            $table->string('phone_office')->nullable();
            $table->unsignedBigInteger('phone_office_code_id')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('customer_type_id')->nullable();
            $table->string('customer_code')->nullable()->unique()->default(DB::raw("'CUS-' || LPAD(nextval('customer_code_seq')::text, 4, '0')"));
            $table->text('billing_address')->nullable();
            $table->string('payment_terms')->nullable(); // e.g., Net 30, Prepaid
            $table->unsignedTinyInteger('customer_rating')->default(0); // 0–5 stars
            $table->text('notes')->nullable();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('status')->default('pending'); // e.g., active, inactive, pending
            $table->boolean('is_updated')->default(false);
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->boolean('is_active')->default(true);
            $table->jsonb('customer_attachments')->nullable();
            $table->unsignedBigInteger('workflow_queue_id')->nullable();
            $table->jsonb('thumbnail_image')->nullable();
            $table->string('location_latitude')->nullable();
            $table->string('location_longitude')->nullable();

            $table->timestamps();

            $table->foreign('customer_type_id')->references('id')->on('customer_types')->onDelete('set null');
            $table->foreign('phone_mobile_code_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('phone_landline_code_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('phone_office_code_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('workflow_queue_id')->references('id')->on('workflow_request_queues')->onDelete('set null');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');


            $table->index(['tenant_id', 'name', 'customer_code', 'status', 'is_active']);
            $table->index('created_by');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
        DB::statement('DROP SEQUENCE IF EXISTS customer_code_seq');
    }
};
