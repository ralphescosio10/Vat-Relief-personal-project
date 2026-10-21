<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Counterparty;
use App\Services\VatCalculator;
use App\Services\ReportingPeriodGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController extends Controller
{
    public function index(Company $company): View
    {
        Gate::authorize('view', $company);
        $preview = null;
        $batchId = request()->query('batch', request()->session()->pull('preview_batch'));
        if ($batchId) {
            $preview = DB::table('import_batches')
                ->where('company_id', $company->id)
                ->where('id', $batchId)
                ->first();
            abort_unless($preview, 404);
            if ($company->userRole(request()->user())?->value === 'client_user') {
                abort_unless($preview->created_by === request()->user()->id, 403);
            }
        }
        $batchQuery = $company->getConnection()->table('import_batches')->where('company_id', $company->id);
        if ($company->userRole(request()->user())?->value === 'client_user') {
            $batchQuery->where('created_by', request()->user()->id);
        }

        return view('imports.index', [
            'company' => $company,
            'batches' => $batchQuery->latest()->paginate(15),
            'preview' => $preview,
            'canCommit' => Gate::allows('manageRecords', $company),
        ]);
    }

    public function store(Request $request, Company $company, ReportingPeriodGuard $periodGuard): RedirectResponse
    {
        Gate::authorize('uploadTransactions', $company);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240']]);
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        $existing = DB::table('import_batches')->where('company_id', $company->id)->where('file_hash', $hash)->first();
        if ($existing) {
            return redirect()->route('companies.imports', $company)->withErrors(['file' => 'This exact file has already been uploaded for this company.']);
        }

        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            try {
                $reader = IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(true);
                $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
                {
                    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                    {
                        return $row <= 5002 && \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($columnAddress) <= 32;
                    }
                });
                $sheetRows = $reader->load($file->getRealPath())->getActiveSheet()->toArray(null, false, true, false);
                if (count($sheetRows) > 5002) {
                    return back()->withErrors(['file' => 'A single import can contain no more than 5,000 non-empty rows.']);
                }
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $exception) {
                return back()->withErrors(['file' => 'The uploaded workbook could not be opened. Check that it is a valid, unencrypted .xlsx file.']);
            }
            $headers = array_shift($sheetRows);
            $sourceRows = $sheetRows;
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            if ($handle === false) {
                throw new \RuntimeException('The uploaded CSV could not be opened.');
            }
            $headers = fgetcsv($handle);
            $sourceRows = [];
            while (($sourceRow = fgetcsv($handle)) !== false) {
                if (count($sourceRows) >= 5001) {
                    fclose($handle);

                    return back()->withErrors(['file' => 'A single import can contain no more than 5,000 non-empty rows.']);
                }
                $sourceRows[] = $sourceRow;
            }
            fclose($handle);
        }
        if (! is_array($headers)) {
            return back()->withErrors(['file' => 'The CSV file is empty.']);
        }
        $headers = array_map(fn ($header) => strtolower(trim(ltrim((string) $header, "\xEF\xBB\xBF"))), $headers);
        $required = ['type', 'invoice_number', 'invoice_date', 'classification', 'amount', 'vat_rate'];
        $missing = array_diff($required, $headers);
        if ($missing !== []) {
            return back()->withErrors(['file' => 'Missing required columns: '.implode(', ', $missing)]);
        }

        $results = [];
        $seen = [];
        $line = 1;
        foreach ($sourceRows as $values) {
            $line++;
            if ($values === [null] || $values === [] || count(array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')) === 0) {
                continue;
            }
            if (count($results) >= 5000) {
                return back()->withErrors(['file' => 'A single import can contain no more than 5,000 non-empty rows.']);
            }
            $row = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), null));
            $row = $this->normalizeRow($row);
            $errors = $this->rowErrors($row, $company, $periodGuard, $seen);
            $results[] = ['line' => $line, 'data' => $row, 'errors' => $errors, 'valid' => $errors === [], 'imported' => false];
        }
        $batchId = DB::table('import_batches')->insertGetId([
            'company_id' => $company->id,
            'created_by' => $request->user()->id,
            'filename' => $file->getClientOriginalName(),
            'file_hash' => $hash,
            'row_count' => count($results),
            'invalid_count' => count(array_filter($results, fn ($row) => ! $row['valid'])),
            'status' => 'validated',
            'results' => json_encode($results, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'action' => 'import.validated',
            'subject_type' => 'import_batch',
            'subject_id' => $batchId,
            'details' => [
                'filename' => $file->getClientOriginalName(),
                'rows' => count($results),
                'invalid_count' => count(array_filter($results, fn ($row) => ! $row['valid'])),
            ],
        ]);

        return redirect()->route('companies.imports', $company)->with('preview_batch', $batchId)->with('success', 'CSV validated. Review row errors before importing valid rows.');
    }

    public function commit(Request $request, Company $company, int $batch, VatCalculator $calculator, ReportingPeriodGuard $periodGuard): RedirectResponse
    {
        Gate::authorize('manageRecords', $company);
        $batchRow = DB::table('import_batches')->where('company_id', $company->id)->where('id', $batch)->first();
        abort_unless($batchRow, 404);
        abort_unless($batchRow->status === 'validated', 409, 'This import was already processed.');
        $results = json_decode($batchRow->results, true, flags: JSON_THROW_ON_ERROR);
        $corrections = $request->input('corrections', []);
        if (! is_array($corrections)) {
            throw ValidationException::withMessages(['corrections' => 'Import corrections must be submitted as row data.']);
        }
        $allowedFields = ['type', 'invoice_number', 'invoice_date', 'classification', 'counterparty_name', 'counterparty_tin', 'amount', 'vat_rate', 'vat_inclusive'];
        foreach ($corrections as $index => $correction) {
            if (! is_array($correction) || ! ctype_digit((string) $index) || ! isset($results[$index]) || $results[$index]['valid'] || ($results[$index]['imported'] ?? false)) {
                throw ValidationException::withMessages(['corrections' => 'Only unresolved invalid rows can be corrected.']);
            }
            foreach ($correction as $field => $value) {
                if (! in_array($field, $allowedFields, true) || ! is_scalar($value)) {
                    throw ValidationException::withMessages(['corrections' => 'Each correction must contain only scalar import fields.']);
                }
            }
            $results[$index]['data'] = $this->normalizeRow(array_replace(
                $results[$index]['data'],
                array_intersect_key($correction, array_flip($allowedFields)),
            ));
            $results[$index]['valid'] = false;
            $results[$index]['errors'] = [];
        }
        if ($corrections !== []) {
            AuditLog::create([
                'company_id' => $company->id,
                'user_id' => $request->user()->id,
                'action' => 'import.rows_corrected',
                'subject_type' => 'import_batch',
                'subject_id' => $batchRow->id,
                'details' => ['corrected_rows' => array_map(fn ($index) => $results[$index]['line'], array_keys($corrections))],
            ]);
        }
        $seen = [];
        foreach ($results as $index => &$result) {
            if (($result['imported'] ?? false) === true) {
                continue;
            }
            $result['errors'] = $this->rowErrors($result['data'], $company, $periodGuard, $seen);
            $result['valid'] = $result['errors'] === [];
        }
        unset($result);

        $imported = 0;
        DB::transaction(function () use (&$results, $batchRow, $company, $request, $calculator, $periodGuard, &$imported): void {
            foreach ($results as $index => &$result) {
                if (! $result['valid'] || ($result['imported'] ?? false)) {
                    continue;
                }
                $row = $result['data'];
                $periodGuard->assertEditable($company, $row['invoice_date']);
                $duplicateQuery = $company->transactions()
                    ->where('type', $row['type'])
                    ->where('invoice_number', $row['invoice_number'])
                    ->whereDate('invoice_date', $row['invoice_date']);
                if ($row['counterparty_tin'] !== '') {
                    $duplicateQuery->whereHas('counterparty', fn ($query) => $query->where('tin', $row['counterparty_tin']));
                } elseif ($row['counterparty_name'] !== '') {
                    $duplicateQuery->whereHas('counterparty', fn ($query) => $query->where('name', $row['counterparty_name']));
                } else {
                    $duplicateQuery->whereNull('counterparty_id');
                }
                if ($duplicateQuery->exists()) {
                    $result['valid'] = false;
                    $result['errors'][] = 'Possible duplicate invoice appeared after preview. Review the row again.';
                    continue;
                }
                $counterparty = null;
                $counterpartyType = $row['type'] === 'sale' ? 'customer' : 'supplier';
                if ($row['counterparty_name'] !== '') {
                    $counterpartyQuery = $company->counterparties()->where('type', $counterpartyType);
                    if ($row['counterparty_tin'] !== '') {
                        $counterparty = $counterpartyQuery->where('tin', $row['counterparty_tin'])->first();
                    }
                    $counterparty ??= $counterpartyQuery->where('name', $row['counterparty_name'])->first();
                    if (! $counterparty) {
                        $counterparty = $company->counterparties()->create([
                            'type' => $counterpartyType,
                            'name' => $row['counterparty_name'],
                            'tin' => $row['counterparty_tin'] ?: null,
                        ]);
                    }
                }
                $amount = (float) $row['amount'];
                $classification = $row['classification'];
                if ($classification === 'vatable') {
                    $totals = $calculator->calculate($amount, (float) $row['vat_rate'], (bool) $row['vat_inclusive']);
                    $exempt = $zeroRated = 0;
                } else {
                    $totals = ['taxable_amount' => 0, 'vat_amount' => 0, 'total_amount' => $amount];
                    $exempt = $classification === 'exempt' ? $amount : 0;
                    $zeroRated = $classification === 'zero_rated' ? $amount : 0;
                }
                $company->transactions()->create([
                    'counterparty_id' => $counterparty?->id,
                    'created_by' => $request->user()->id,
                    'type' => $row['type'],
                    'invoice_number' => $row['invoice_number'],
                    'invoice_date' => $row['invoice_date'],
                    'classification' => $classification,
                    'vat_inclusive' => $row['vat_inclusive'],
                    'vat_rate' => $row['vat_rate'],
                    'exempt_amount' => $exempt,
                    'zero_rated_amount' => $zeroRated,
                ] + $totals);
                $result['imported'] = true;
                $imported++;
            }
            unset($result);
            $remaining = count(array_filter($results, fn ($row) => ! ($row['imported'] ?? false) && ! $row['valid']));
            DB::table('import_batches')->where('id', $batchRow->id)->update([
                'status' => $remaining === 0 ? 'imported' : 'validated',
                'imported_count' => (int) $batchRow->imported_count + $imported,
                'invalid_count' => $remaining,
                'results' => json_encode($results, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });

        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'action' => 'import.completed',
            'subject_type' => 'import_batch',
            'subject_id' => $batchRow->id,
            'details' => ['imported_count' => $imported, 'remaining_invalid_count' => count(array_filter($results, fn ($row) => ! ($row['imported'] ?? false) && ! $row['valid']))],
        ]);

        $remainingInvalid = count(array_filter($results, fn ($row) => ! ($row['imported'] ?? false) && ! $row['valid']));

        return redirect()->route('companies.imports', [$company, 'batch' => $batchRow->id])
            ->with('success', "{$imported} valid rows imported. {$remainingInvalid} unresolved row(s) remain available for correction.");
    }

    private function normalizeRow(array $row): array
    {
        return [
            'type' => strtolower(trim((string) ($row['type'] ?? ''))),
            'invoice_number' => trim((string) ($row['invoice_number'] ?? '')),
            'invoice_date' => trim((string) ($row['invoice_date'] ?? '')),
            'classification' => strtolower(trim((string) ($row['classification'] ?? ''))),
            'amount' => trim((string) ($row['amount'] ?? '')),
            'vat_rate' => trim((string) ($row['vat_rate'] ?? '12')),
            'vat_inclusive' => filter_var($row['vat_inclusive'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'counterparty_name' => trim((string) ($row['counterparty_name'] ?? '')),
            'counterparty_tin' => trim((string) ($row['counterparty_tin'] ?? '')),
        ];
    }

    private function rowErrors(array $row, Company $company, ReportingPeriodGuard $periodGuard, array &$seen): array
    {
        $validation = Validator::make($row, [
            'type' => ['required', 'in:sale,purchase'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'classification' => ['required', 'in:vatable,exempt,zero_rated'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'counterparty_name' => ['nullable', 'string', 'max:255'],
            'counterparty_tin' => ['nullable', 'string', 'max:20'],
        ]);
        $errors = array_merge(...array_values($validation->errors()->toArray()));
        if ($errors !== []) {
            return $errors;
        }
        try {
            $periodGuard->assertEditable($company, $row['invoice_date']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            if ($exception->getStatusCode() !== 403) {
                throw $exception;
            }
            $errors[] = 'This invoice date belongs to an approved or closed period.';
        }
        $partyKey = $row['counterparty_tin'] !== '' ? $row['counterparty_tin'] : strtolower($row['counterparty_name']);
        $key = $row['type'].'|'.$row['invoice_number'].'|'.$row['invoice_date'].'|'.$partyKey;
        $duplicateQuery = $company->transactions()
            ->where('type', $row['type'])
            ->where('invoice_number', $row['invoice_number'])
            ->whereDate('invoice_date', $row['invoice_date']);
        if ($row['counterparty_tin'] !== '') {
            $duplicateQuery->whereHas('counterparty', fn ($query) => $query->where('tin', $row['counterparty_tin']));
        } elseif ($row['counterparty_name'] !== '') {
            $duplicateQuery->whereHas('counterparty', fn ($query) => $query->where('name', $row['counterparty_name']));
        } else {
            $duplicateQuery->whereNull('counterparty_id');
        }
        if (isset($seen[$key]) || $duplicateQuery->exists()) {
            $errors[] = 'Possible duplicate invoice.';
        }
        $seen[$key] = true;

        return $errors;
    }
}
