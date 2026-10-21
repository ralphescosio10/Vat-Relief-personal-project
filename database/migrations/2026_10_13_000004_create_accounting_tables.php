<?php

use App\Enums\CompanyRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['customer', 'supplier']);
            $table->string('name');
            $table->string('tin', 20)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'type', 'name']);
            $table->unique(['company_id', 'type', 'tin']);
            $table->unique(['company_id', 'id']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->enum('type', ['sale', 'purchase']);
            $table->string('invoice_number', 100);
            $table->date('invoice_date');
            $table->string('classification', 32);
            $table->boolean('vat_inclusive')->default(false);
            $table->decimal('taxable_amount', 15, 2);
            $table->decimal('vat_rate', 5, 2)->default(12);
            $table->decimal('vat_amount', 15, 2);
            $table->decimal('exempt_amount', 15, 2)->default(0);
            $table->decimal('zero_rated_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreign(['company_id', 'counterparty_id'])
                ->references(['company_id', 'id'])
                ->on('counterparties')
                ->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'type', 'invoice_date']);
            $table->index(['company_id', 'invoice_number', 'invoice_date']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->string('file_hash', 64);
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->enum('status', ['validated', 'imported', 'rejected']);
            $table->json('results')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'file_hash']);
        });

        Schema::create('vat_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'for_review', 'returned', 'approved', 'closed'])->default('draft');
            $table->json('snapshot')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'period_start', 'period_end']);
            $table->index(['company_id', 'status', 'period_end']);
        });

        Schema::create('report_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vat_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('report_approvals');
        Schema::dropIfExists('vat_reports');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('counterparties');
    }
};
