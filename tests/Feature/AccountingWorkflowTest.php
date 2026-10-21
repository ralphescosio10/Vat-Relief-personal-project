<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Counterparty;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VatReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_record_vat_inclusive_sale_and_other_company_is_hidden(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $company->users()->attach($user, ['role' => CompanyRole::Accountant->value]);
        $customer = Counterparty::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'ABC Customer',
        ]);
        $payload = [
            'type' => 'sale',
            'counterparty_id' => $customer->id,
            'invoice_number' => 'SI-001',
            'invoice_date' => '2026-10-01',
            'classification' => 'vatable',
            'vat_inclusive' => '1',
            'amount' => '11200',
            'vat_rate' => '12',
        ];

        $this->actingAs($user)->post(route('companies.transactions.store', $company), $payload)
            ->assertRedirect();

        $transaction = Transaction::firstOrFail();
        $this->assertSame('10000.00', $transaction->taxable_amount);
        $this->assertSame('1200.00', $transaction->vat_amount);
        $this->assertSame('11200.00', $transaction->total_amount);
        $this->actingAs($user)->get(route('companies.transactions', $otherCompany))->assertForbidden();
    }

    public function test_transaction_cannot_reference_another_company_counterparty(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $company->users()->attach($user, ['role' => CompanyRole::Accountant->value]);
        $foreignParty = Counterparty::create([
            'company_id' => $otherCompany->id,
            'type' => 'customer',
            'name' => 'Foreign Customer',
        ]);

        $this->actingAs($user)->post(route('companies.transactions.store', $company), [
            'type' => 'sale',
            'counterparty_id' => $foreignParty->id,
            'invoice_number' => 'SI-002',
            'invoice_date' => '2026-10-01',
            'classification' => 'vatable',
            'amount' => '1000',
            'vat_rate' => '12',
        ])->assertSessionHasErrors('counterparty_id');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_report_review_approval_locks_transactions_in_the_period(): void
    {
        $accountant = User::factory()->create();
        $reviewer = User::factory()->create();
        $company = Company::factory()->create();
        $customer = Counterparty::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Known customer',
            'tin' => '111-222-333-000',
        ]);
        $company->users()->attach($accountant, ['role' => CompanyRole::Accountant->value]);
        $company->users()->attach($reviewer, ['role' => CompanyRole::Reviewer->value]);
        Transaction::create([
            'company_id' => $company->id,
            'counterparty_id' => $customer->id,
            'created_by' => $accountant->id,
            'type' => 'sale',
            'invoice_number' => 'SI-100',
            'invoice_date' => '2026-10-02',
            'classification' => 'vatable',
            'vat_inclusive' => false,
            'taxable_amount' => 1000,
            'vat_rate' => 12,
            'vat_amount' => 120,
            'exempt_amount' => 0,
            'zero_rated_amount' => 0,
            'total_amount' => 1120,
        ]);

        $this->actingAs($accountant)->post(route('companies.reports.generate', $company), [
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
        ])->assertRedirect();
        $report = VatReport::firstOrFail();
        $this->assertEquals(120, $report->snapshot['totals']['sales_vat']);
        $this->actingAs($accountant)->post(route('companies.reports.submit', [$company, $report]))->assertRedirect();
        $this->actingAs($reviewer)->post(route('companies.reports.decision', [$company, $report]), [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->assertDatabaseHas('vat_reports', ['id' => $report->id, 'status' => 'approved']);

        $transaction = Transaction::firstOrFail();
        $this->actingAs($accountant)->delete(route('companies.transactions.destroy', [$company, $transaction]))
            ->assertForbidden();
        $this->actingAs($accountant)->post(route('companies.transactions.store', $company), [
            'type' => 'sale',
            'invoice_number' => 'SI-LOCKED',
            'invoice_date' => '2026-10-03',
            'classification' => 'vatable',
            'amount' => '100',
            'vat_rate' => '12',
        ])->assertForbidden();
        $this->actingAs($reviewer)->get(route('companies.reports.export', [$company, $report]).'?format=xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($reviewer)->post(route('companies.reports.close', [$company, $report]))->assertRedirect();
        $this->actingAs($accountant)->post(route('companies.reports.reopen', [$company, $report]), [
            'comment' => 'Correct source file received after close.',
        ])->assertRedirect();
        $this->assertDatabaseHas('vat_reports', ['id' => $report->id, 'status' => 'draft', 'snapshot' => null]);
    }

    public function test_csv_import_requires_review_then_imports_valid_rows(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($user, ['role' => CompanyRole::Accountant->value]);
        $csv = "type,invoice_number,invoice_date,classification,amount,vat_rate,counterparty_name\n".
            "sale,SI-CSV-1,2026-10-03,vatable,1000,12,CSV Customer\n";

        $this->actingAs($user)->post(route('companies.imports.store', $company), [
            'file' => UploadedFile::fake()->createWithContent('transactions.csv', $csv),
        ])->assertRedirect();

        $batch = \Illuminate\Support\Facades\DB::table('import_batches')->first();
        $this->assertNotNull($batch);
        $this->assertSame('validated', $batch->status);
        $this->assertSame(0, $batch->invalid_count);

        $this->actingAs($user)->post(route('companies.imports.commit', [$company, $batch->id]))
            ->assertRedirect(route('companies.imports', [$company, 'batch' => $batch->id]));
        $transaction = Transaction::firstOrFail();
        $this->assertSame('SI-CSV-1', $transaction->invoice_number);
        $this->assertSame('120.00', $transaction->vat_amount);
        $this->assertDatabaseHas('import_batches', ['id' => $batch->id, 'status' => 'imported', 'imported_count' => 1]);
    }

    public function test_invalid_import_rows_can_be_corrected_before_they_are_imported(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($user, ['role' => CompanyRole::Accountant->value]);
        $csv = "type,invoice_number,invoice_date,classification,amount,vat_rate\n".
            "sale,SI-FIX-1,2026-10-03,vatable,wrong,12\n";

        $this->actingAs($user)->post(route('companies.imports.store', $company), [
            'file' => UploadedFile::fake()->createWithContent('needs-correction.csv', $csv),
        ])->assertRedirect();
        $batch = \Illuminate\Support\Facades\DB::table('import_batches')->first();
        $this->assertSame(1, $batch->invalid_count);

        $this->actingAs($user)->post(route('companies.imports.commit', [$company, $batch->id]), [
            'corrections' => [
                0 => [
                    'amount' => '1000.00',
                    'type' => 'sale',
                    'invoice_number' => 'SI-FIX-1',
                    'invoice_date' => '2026-10-03',
                    'classification' => 'vatable',
                    'vat_rate' => '12',
                    'vat_inclusive' => '0',
                ],
            ],
        ])->assertRedirect(route('companies.imports', [$company, 'batch' => $batch->id]));

        $this->assertDatabaseHas('transactions', [
            'company_id' => $company->id,
            'invoice_number' => 'SI-FIX-1',
            'taxable_amount' => 1000,
            'vat_amount' => 120,
        ]);
        $this->assertDatabaseHas('import_batches', [
            'id' => $batch->id,
            'status' => 'imported',
            'invalid_count' => 0,
            'imported_count' => 1,
        ]);
    }

    public function test_valid_import_rows_remain_imported_while_other_rows_are_corrected(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($user, ['role' => CompanyRole::Accountant->value]);
        $csv = "type,invoice_number,invoice_date,classification,amount,vat_rate\n".
            "sale,SI-READY,2026-10-03,vatable,1000,12\n".
            "sale,SI-NEEDS-FIX,2026-10-04,vatable,wrong,12\n";

        $this->actingAs($user)->post(route('companies.imports.store', $company), [
            'file' => UploadedFile::fake()->createWithContent('partial-import.csv', $csv),
        ])->assertRedirect();
        $batch = \Illuminate\Support\Facades\DB::table('import_batches')->firstOrFail();

        $this->actingAs($user)->post(route('companies.imports.commit', [$company, $batch->id]))
            ->assertRedirect(route('companies.imports', [$company, 'batch' => $batch->id]));
        $this->assertDatabaseHas('import_batches', [
            'id' => $batch->id,
            'status' => 'validated',
            'invalid_count' => 1,
            'imported_count' => 1,
        ]);
        $this->assertDatabaseHas('transactions', ['company_id' => $company->id, 'invoice_number' => 'SI-READY']);

        $this->actingAs($user)->post(route('companies.imports.commit', [$company, $batch->id]), [
            'corrections' => [
                1 => [
                    'amount' => '2000.00',
                    'type' => 'sale',
                    'invoice_number' => 'SI-NEEDS-FIX',
                    'invoice_date' => '2026-10-04',
                    'classification' => 'vatable',
                    'vat_rate' => '12',
                    'vat_inclusive' => '0',
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('import_batches', [
            'id' => $batch->id,
            'status' => 'imported',
            'invalid_count' => 0,
            'imported_count' => 2,
        ]);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_excel_workbook_can_be_validated_and_imported(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($user, ['role' => CompanyRole::Accountant->value]);
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['type', 'invoice_number', 'invoice_date', 'classification', 'amount', 'vat_rate'],
            ['purchase', 'PI-XLSX-1', '2026-10-05', 'vatable', '2000', '12'],
        ]);
        $temporary = tempnam(sys_get_temp_dir(), 'vat-xlsx-');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($temporary);
        $contents = file_get_contents($temporary);
        unlink($temporary);
        $spreadsheet->disconnectWorksheets();

        $this->actingAs($user)->post(route('companies.imports.store', $company), [
            'file' => UploadedFile::fake()->createWithContent('transactions.xlsx', $contents),
        ])->assertRedirect();

        $batch = \Illuminate\Support\Facades\DB::table('import_batches')->first();
        $this->assertNotNull($batch);
        $this->actingAs($user)->post(route('companies.imports.commit', [$company, $batch->id]))->assertRedirect();
        $this->assertDatabaseHas('transactions', ['company_id' => $company->id, 'invoice_number' => 'PI-XLSX-1', 'vat_amount' => 240]);
    }

    public function test_client_user_can_view_and_upload_but_cannot_change_financial_records(): void
    {
        $client = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($client, ['role' => CompanyRole::ClientUser->value]);

        $this->actingAs($client)->get(route('companies.transactions', $company))
            ->assertOk()
            ->assertDontSee('Record an invoice');
        $this->actingAs($client)->post(route('companies.transactions.store', $company), [
            'type' => 'sale',
            'invoice_number' => 'SI-FORBIDDEN',
            'invoice_date' => '2026-10-06',
            'classification' => 'vatable',
            'amount' => '100',
            'vat_rate' => '12',
        ])->assertForbidden();
    }

    public function test_approval_is_blocked_when_saved_report_no_longer_matches_transactions(): void
    {
        $accountant = User::factory()->create();
        $reviewer = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($accountant, ['role' => CompanyRole::Accountant->value]);
        $company->users()->attach($reviewer, ['role' => CompanyRole::Reviewer->value]);
        $customer = Counterparty::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Reconciled customer',
            'tin' => '222-333-444-000',
        ]);
        $transaction = Transaction::create([
            'company_id' => $company->id,
            'counterparty_id' => $customer->id,
            'created_by' => $accountant->id,
            'type' => 'sale',
            'invoice_number' => 'SI-RECON',
            'invoice_date' => '2026-10-08',
            'classification' => 'vatable',
            'vat_inclusive' => false,
            'taxable_amount' => 1000,
            'vat_rate' => 12,
            'vat_amount' => 120,
            'exempt_amount' => 0,
            'zero_rated_amount' => 0,
            'total_amount' => 1120,
        ]);
        $period = ['period_start' => '2026-10-01', 'period_end' => '2026-10-31'];
        $this->actingAs($accountant)->post(route('companies.reports.generate', $company), $period)->assertRedirect();
        $report = VatReport::firstOrFail();
        $this->actingAs($accountant)->post(route('companies.reports.submit', [$company, $report]))->assertRedirect();
        $transaction->update(['taxable_amount' => 2000, 'vat_amount' => 240, 'total_amount' => 2240]);

        $this->actingAs($reviewer)->post(route('companies.reports.decision', [$company, $report]), [
            'decision' => 'approve',
        ])->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('vat_reports', ['id' => $report->id, 'status' => 'for_review']);
        $this->actingAs($reviewer)->post(route('companies.reports.decision', [$company, $report]), [
            'decision' => 'return',
            'comment' => 'Reconcile the changed invoice.',
        ])->assertRedirect();
        $this->actingAs($accountant)->post(route('companies.reports.generate', $company), $period)->assertRedirect();
        $report->refresh();
        $this->actingAs($accountant)->post(route('companies.reports.submit', [$company, $report]))->assertRedirect();
        $this->actingAs($reviewer)->post(route('companies.reports.decision', [$company, $report]), [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->assertDatabaseHas('vat_reports', ['id' => $report->id, 'status' => 'approved']);
    }

    public function test_uploaded_documents_are_private_and_scoped_to_their_company(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        $company->users()->attach($client, ['role' => CompanyRole::ClientUser->value]);
        $otherCompany->users()->attach($otherUser, ['role' => CompanyRole::ClientUser->value]);
        $report = VatReport::create([
            'company_id' => $company->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'status' => 'draft',
            'snapshot' => [],
        ]);

        $this->actingAs($client)->post(route('companies.documents.store', $company), [
            'report_id' => $report->id,
            'category' => 'supporting',
            'document' => UploadedFile::fake()->create('support.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $document = \App\Models\ClientDocument::firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->assertNotNull($report->fresh()->supporting_docs_received_at);
        $this->actingAs($client)->get(route('companies.documents.download', [$company, $document]))->assertOk();
        $this->actingAs($otherUser)->get(route('companies.documents.download', [$otherCompany, $document]))->assertNotFound();
    }

    public function test_supporting_document_upload_rejects_excel_files(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($client, ['role' => CompanyRole::ClientUser->value]);

        $this->actingAs($client)->post(route('companies.documents.store', $company), [
            'category' => 'supporting',
            'document' => UploadedFile::fake()->create('support.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ])->assertSessionHasErrors('document');

        $this->assertDatabaseCount('client_documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('client-documents'));
    }
}
