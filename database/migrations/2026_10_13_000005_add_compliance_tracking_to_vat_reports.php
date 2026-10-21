<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vat_reports', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('period_end');
            $table->timestamp('sales_received_at')->nullable();
            $table->timestamp('purchases_received_at')->nullable();
            $table->timestamp('supporting_docs_received_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vat_reports', function (Blueprint $table) {
            $table->dropColumn([
                'due_date',
                'sales_received_at',
                'purchases_received_at',
                'supporting_docs_received_at',
            ]);
        });
    }
};
