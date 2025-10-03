<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_feedbacks', function (Blueprint $table) {
            $table->unsignedBigInteger('procurements_quotation_request_attempts_id')->nullable();
            $table->foreign('procurements_quotation_request_attempts_id')
                  ->references('id')
                  ->on('procurements_quotation_request_attempts')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_feedbacks', function (Blueprint $table) {
            $table->dropForeign(['procurements_quotation_request_attempts_id']);
            $table->dropColumn('procurements_quotation_request_attempts_id');
        });
    }
};