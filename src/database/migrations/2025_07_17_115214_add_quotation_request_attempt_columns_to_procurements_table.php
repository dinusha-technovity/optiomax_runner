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
        Schema::table('procurements', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->after('id');
            $table->unsignedInteger('quotation_request_attempt_count')
                ->default(0)
                ->after('created_by');

            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->index('created_by');

            $table->dropColumn([
                'procurement_by',
                'date',
                'selected_suppliers',
                'rpf_document',
                'attachment',
                'required_date',
                'comment',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'quotation_request_attempt_count']);

            $table->unsignedBigInteger('procurement_by')->nullable()->after('id');
            $table->date('date')->nullable()->after('procurement_by');
            $table->jsonb('selected_suppliers')->nullable()->after('date');
            $table->jsonb('rpf_document')->nullable()->after('selected_suppliers');
            $table->jsonb('attachment')->nullable()->after('rpf_document');
            $table->date('required_date')->nullable()->after('attachment');
            $table->text('comment')->nullable()->after('required_date');
        });
    }
};