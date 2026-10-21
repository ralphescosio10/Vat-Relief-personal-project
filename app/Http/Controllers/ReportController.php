<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClientDocument;
use App\Models\Company;
use App\Models\ReportApproval;
use App\Models\Transaction;
use App\Models\VatReport;
use App\Services\VatCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function index(Company $company): View
    {
        Gate::authorize('view', $company);

        return view('reports.index', [
            'company' => $company,
            'reports' => $company->reports()->latest('period_start')->paginate(20),
            'canPrepareReports' => Gate::allows('prepareReports', $company),
        ]);
    }

    public function generate(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('prepareReports', $company);
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);
        $overlap = $company->reports()
            ->whereDate('period_start', '<=', $data['period_end'])
            ->whereDate('period_end', '>=', $data['period_start'])
            ->where(function ($query) use ($data) {
                $query->whereDate('period_start', '!=', $data['period_start'])
                    ->orWhereDate('period_end', '!=', $data['period_end']);
            })
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['period_start' => 'Reporting periods for the same company cannot overlap.']);
        }
        $report = $company->reports()
            ->whereDate('period_start', $data['period_start'])
            ->whereDate('period_end', $data['period_end'])
            ->first();
        $report ??= $company->reports()->make([
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
        ]);
        if (in_array($report->status, ['approved', 'closed'], true)) {
            throw ValidationException::withMessages(['period_start' => 'This period is approved or closed and cannot be regenerated.']);
        }
        if ($report->status === 'for_review') {
            throw ValidationException::withMessages(['period_start' => 'A report under review must first be returned before it can be regenerated.']);
        }
        $transactions = $company->transactions()
            ->with('counterparty')
            ->whereBetween('invoice_date', [$data['period_start'], $data['period_end']])
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get();
        $report->fill([
            'status' => $report->status === 'returned' ? 'returned' : 'draft',
            'prepared_by' => $request->user()->id,
            'snapshot' => $this->makeSnapshot($transactions),
        ])->save();
        $this->audit($request, $company, 'report.generated', $report);

        return redirect()->route('companies.reports.show', [$company, $report])->with('success', 'VAT schedule generated.');
    }

    public function show(Company $company, VatReport $report): View
    {
        Gate::authorize('view', $company);
        abort_unless($report->company_id === $company->id, 404);
        $report->load(['approvals' => fn ($query) => $query->with('user')->latest()]);
        $liveTransactions = $company->transactions()
            ->with('counterparty')
            ->whereBetween('invoice_date', [$report->period_start, $report->period_end])
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get();
        $liveSnapshot = $this->makeSnapshot($liveTransactions);
        $isReconciled = $liveSnapshot == ($report->snapshot ?? []);

        return view('reports.show', [
            'company' => $company,
            'report' => $report,
            'isReconciled' => $isReconciled,
            'liveSnapshot' => $liveSnapshot,
            'canPrepareReports' => Gate::allows('prepareReports', $company),
            'canReviewReports' => in_array($company->userRole(auth()->user())?->value, ['accountant', 'reviewer'], true),
            'canTrackSubmissions' => Gate::allows('trackSubmissions', $company),
            'canUploadDocuments' => Gate::allows('uploadTransactions', $company),
            'documents' => ClientDocument::with('uploader')
                ->where('company_id', $company->id)
                ->where('vat_report_id', $report->id)
                ->latest()
                ->get(),
        ]);
    }

    public function submit(Request $request, Company $company, VatReport $report): RedirectResponse
    {
        Gate::authorize('prepareReports', $company);
        abort_unless($report->company_id === $company->id, 404);
        abort_unless(in_array($report->status, ['draft', 'returned'], true), 409);
        $report->update(['status' => 'for_review']);
        $report->approvals()->create(['user_id' => $request->user()->id, 'action' => 'submitted']);
        $this->audit($request, $company, 'report.submitted', $report);

        return back()->with('success', 'Report submitted for review.');
    }

    public function decision(Request $request, Company $company, VatReport $report): RedirectResponse
    {
        Gate::authorize('view', $company);
        abort_unless($report->company_id === $company->id, 404);
        $this->assertReviewer($request, $company);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,return'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless($report->status === 'for_review', 409);
        if ($data['decision'] === 'approve' && ! $this->reportMatchesLiveData($company, $report)) {
            throw ValidationException::withMessages([
                'decision' => 'The transactions changed after this report was prepared. Return it for correction and regenerate the schedule before approval.',
            ]);
        }
        if ($data['decision'] === 'approve' && ! empty($report->snapshot['validation_issues']) && trim($data['comment'] ?? '') === '') {
            throw ValidationException::withMessages([
                'comment' => 'Add a review note acknowledging the validation warnings before approving this schedule.',
            ]);
        }
        $status = $data['decision'] === 'approve' ? 'approved' : 'returned';
        $report->update(['status' => $status]);
        $report->approvals()->create([
            'user_id' => $request->user()->id,
            'action' => $data['decision'],
            'comment' => $data['comment'] ?? null,
        ]);
        $this->audit($request, $company, 'report.'.$status, $report, ['comment' => $data['comment'] ?? null]);

        return back()->with('success', 'Report '.$status.'.');
    }

    public function close(Request $request, Company $company, VatReport $report): RedirectResponse
    {
        Gate::authorize('view', $company);
        abort_unless($report->company_id === $company->id, 404);
        $this->assertReviewer($request, $company);
        abort_unless($report->status === 'approved', 409);
        $report->update(['status' => 'closed']);
        $report->approvals()->create(['user_id' => $request->user()->id, 'action' => 'closed']);
        $this->audit($request, $company, 'report.closed', $report);

        return back()->with('success', 'Reporting period closed and locked.');
    }

    public function compliance(Request $request, Company $company, VatReport $report): RedirectResponse
    {
        Gate::authorize('trackSubmissions', $company);
        abort_unless($report->company_id === $company->id, 404);
        abort_unless($report->status !== 'closed', 409);
        $isClientUser = $company->userRole($request->user())?->value === 'client_user';
        $data = $request->validate([
            'due_date' => $isClientUser ? ['prohibited'] : ['nullable', 'date'],
            'sales_received' => ['sometimes', 'boolean'],
            'purchases_received' => ['sometimes', 'boolean'],
            'supporting_docs_received' => ['sometimes', 'boolean'],
        ]);
        $fields = [
            'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $report->due_date,
            'sales_received_at' => $isClientUser
                ? $report->sales_received_at
                : ($request->boolean('sales_received') ? ($report->sales_received_at ?? now()) : null),
            'purchases_received_at' => $isClientUser
                ? $report->purchases_received_at
                : ($request->boolean('purchases_received') ? ($report->purchases_received_at ?? now()) : null),
            'supporting_docs_received_at' => $isClientUser
                ? $report->supporting_docs_received_at
                : ($request->boolean('supporting_docs_received') ? ($report->supporting_docs_received_at ?? now()) : null),
        ];
        $report->update($fields);
        $this->audit($request, $company, 'report.submissions_updated', $report, [
            'due_date' => $fields['due_date'],
            'sales_received' => $fields['sales_received_at'] !== null,
            'purchases_received' => $fields['purchases_received_at'] !== null,
            'supporting_docs_received' => $fields['supporting_docs_received_at'] !== null,
        ]);

        return back()->with('success', 'Submission checklist updated.');
    }

    public function reopen(Request $request, Company $company, VatReport $report): RedirectResponse
    {
        Gate::authorize('manageRecords', $company);
        abort_unless($report->company_id === $company->id, 404);
        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']]);
        abort_unless($report->status === 'closed', 409);
        $report->update(['status' => 'draft', 'snapshot' => null]);
        $report->approvals()->create([
            'user_id' => $request->user()->id,
            'action' => 'reopened',
            'comment' => $data['comment'],
        ]);
        $this->audit($request, $company, 'report.reopened', $report, ['comment' => $data['comment']]);

        return back()->with('success', 'Period reopened. Generate a fresh schedule before submitting it for review.');
    }

    public function export(Request $request, Company $company, VatReport $report): StreamedResponse
    {
        Gate::authorize('view', $company);
        abort_unless($report->company_id === $company->id, 404);
        abort_unless(in_array($report->status, ['approved', 'closed'], true), 403, 'Only approved reports can be exported.');
        $rows = $report->snapshot['transactions'] ?? [];
        $format = $request->query('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 400, 'Export format must be csv or xlsx.');
        $this->audit($request, $company, 'report.exported', $report, ['format' => $format]);
        $baseFilename = 'vat-schedule-'.$company->id.'-'.$report->period_start->format('Ymd');

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($rows): void {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->fromArray([
                    ['Type', 'Invoice Number', 'Invoice Date', 'Customer/Supplier', 'Classification', 'Taxable Amount', 'VAT Rate', 'VAT Amount', 'Exempt Amount', 'Zero-rated Amount', 'Total Amount'],
                    ...array_map(fn ($row) => [
                        $this->safeExportCell($row['type']), $this->safeExportCell($row['invoice_number']), $this->safeExportCell($row['invoice_date']), $this->safeExportCell($row['counterparty']),
                        $this->safeExportCell($row['classification']), $row['taxable_amount'], $row['vat_rate'], $row['vat_amount'],
                        $row['exempt_amount'], $row['zero_rated_amount'], $row['total_amount'],
                    ], $rows),
                ]);
                (new Xlsx($spreadsheet))->save('php://output');
                $spreadsheet->disconnectWorksheets();
            }, $baseFilename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Type', 'Invoice Number', 'Invoice Date', 'Customer/Supplier', 'Classification', 'Taxable Amount', 'VAT Rate', 'VAT Amount', 'Exempt Amount', 'Zero-rated Amount', 'Total Amount']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $this->safeExportCell($row['type']), $this->safeExportCell($row['invoice_number']), $this->safeExportCell($row['invoice_date']), $this->safeExportCell($row['counterparty']),
                    $this->safeExportCell($row['classification']), $row['taxable_amount'], $row['vat_rate'], $row['vat_amount'],
                    $row['exempt_amount'], $row['zero_rated_amount'], $row['total_amount'],
                ]);
            }
            fclose($output);
        }, $baseFilename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeExportCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    private function makeSnapshot(iterable $transactions): array
    {
        $items = [];
        $issues = [];
        $seen = [];
        $calculator = app(VatCalculator::class);
        foreach ($transactions as $transaction) {
            $partyIdentity = $transaction->counterparty?->tin ?: strtolower($transaction->counterparty?->name ?? '');
            if (! $transaction->counterparty) {
                $issues[] = ['invoice_number' => $transaction->invoice_number, 'message' => 'Customer or supplier is not specified.'];
            } elseif (! $transaction->counterparty->tin) {
                $issues[] = ['invoice_number' => $transaction->invoice_number, 'message' => 'Customer or supplier TIN is missing.'];
            }
            $duplicateKey = implode('|', [
                $transaction->type,
                $transaction->invoice_number,
                $transaction->invoice_date->toDateString(),
                $partyIdentity,
            ]);
            if (isset($seen[$duplicateKey])) {
                $issues[] = ['invoice_number' => $transaction->invoice_number, 'message' => 'Possible duplicate invoice for this customer or supplier.'];
            }
            $seen[$duplicateKey] = true;
            if ($transaction->classification === 'vatable') {
                $basis = (float) ($transaction->vat_inclusive ? $transaction->total_amount : $transaction->taxable_amount);
                $computed = $calculator->calculate($basis, (float) $transaction->vat_rate, $transaction->vat_inclusive);
                if (abs($computed['vat_amount'] - (float) $transaction->vat_amount) >= 0.01
                    || abs($computed['total_amount'] - (float) $transaction->total_amount) >= 0.01) {
                    $issues[] = ['invoice_number' => $transaction->invoice_number, 'message' => 'Stored VAT amount does not reconcile with the configured rate.'];
                }
            } elseif ((float) $transaction->vat_amount !== 0.0) {
                $issues[] = ['invoice_number' => $transaction->invoice_number, 'message' => 'Non-vatable classification has a non-zero VAT amount.'];
            }
            $items[] = [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'invoice_number' => $transaction->invoice_number,
                'invoice_date' => $transaction->invoice_date->toDateString(),
                'counterparty' => $transaction->counterparty?->name ?? '',
                'classification' => $transaction->classification,
                'taxable_amount' => (float) $transaction->taxable_amount,
                'vat_rate' => (float) $transaction->vat_rate,
                'vat_amount' => (float) $transaction->vat_amount,
                'exempt_amount' => (float) $transaction->exempt_amount,
                'zero_rated_amount' => (float) $transaction->zero_rated_amount,
                'total_amount' => (float) $transaction->total_amount,
            ];
        }

        $totals = ['sales_vat' => 0.0, 'purchases_vat' => 0.0, 'sales_total' => 0.0, 'purchases_total' => 0.0];
        foreach ($items as $item) {
            $prefix = $item['type'] === 'sale' ? 'sales' : 'purchases';
            $totals[$prefix.'_vat'] += $item['vat_amount'];
            $totals[$prefix.'_total'] += $item['total_amount'];
        }
        foreach ($totals as &$total) {
            $total = round($total, 2);
        }
        unset($total);

        return [
            'totals' => $totals,
            'transaction_count' => count($items),
            'validation_issues' => $issues,
            'transactions' => $items,
        ];
    }

    private function assertReviewer(Request $request, Company $company): void
    {
        $user = $request->user();
        $role = $company->userRole($user);
        abort_unless(in_array($role?->value, ['accountant', 'reviewer'], true), 403, 'An accountant or reviewer must perform this action.');
    }

    private function reportMatchesLiveData(Company $company, VatReport $report): bool
    {
        $transactions = $company->transactions()
            ->with('counterparty')
            ->whereBetween('invoice_date', [$report->period_start, $report->period_end])
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get();

        return $this->makeSnapshot($transactions) == ($report->snapshot ?? []);
    }

    private function audit(Request $request, Company $company, string $action, VatReport $report, array $details = []): void
    {
        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => VatReport::class,
            'subject_id' => $report->id,
            'details' => $details,
        ]);
    }
}
