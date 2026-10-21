<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ClientDocument;
use App\Models\VatReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientDocumentController extends Controller
{
    public function store(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('uploadTransactions', $company);
        $data = $request->validate([
            'report_id' => ['nullable', 'integer', 'exists:vat_reports,id'],
            'category' => ['required', 'in:sales,purchases,supporting'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,png', 'max:10240'],
        ]);
        $report = null;
        if (! empty($data['report_id'])) {
            $report = $company->reports()->whereKey($data['report_id'])->firstOrFail();
            abort_unless($report->status !== 'closed', 409);
        }
        $upload = $request->file('document');
        $path = $upload->store('client-documents/'.$company->id, 'local');
        if ($path === false) {
            throw new \RuntimeException('The private document could not be stored.');
        }
        $persisted = false;
        try {
            DB::transaction(function () use ($company, $report, $request, $upload, $data, $path): void {
                $document = ClientDocument::create([
                    'company_id' => $company->id,
                    'vat_report_id' => $report?->id,
                    'uploaded_by' => $request->user()->id,
                    'category' => $data['category'],
                    'original_name' => $upload->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $upload->getSize(),
                ]);
                if ($report) {
                    $receivedField = match ($data['category']) {
                        'sales' => 'sales_received_at',
                        'purchases' => 'purchases_received_at',
                        'supporting' => 'supporting_docs_received_at',
                    };
                    $report->update([$receivedField => $report->{$receivedField} ?? now()]);
                }
                AuditLog::create([
                    'company_id' => $company->id,
                    'user_id' => $request->user()->id,
                    'action' => 'document.uploaded',
                    'subject_type' => ClientDocument::class,
                    'subject_id' => $document->id,
                    'details' => ['category' => $document->category, 'name' => $document->original_name, 'size' => $document->size_bytes],
                ]);
            });
            $persisted = true;
        } finally {
            if (! $persisted) {
                Storage::disk('local')->delete($path);
            }
        }

        return back()->with('success', 'Document uploaded securely.');
    }

    public function download(Company $company, ClientDocument $document): StreamedResponse
    {
        Gate::authorize('view', $company);
        abort_unless($document->company_id === $company->id, 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Request $request, Company $company, ClientDocument $document): RedirectResponse
    {
        Gate::authorize('manageRecords', $company);
        abort_unless($document->company_id === $company->id, 404);
        DB::transaction(function () use ($request, $company, $document): void {
            $path = $document->path;
            $document->delete();
            AuditLog::create([
                'company_id' => $company->id,
                'user_id' => $request->user()->id,
                'action' => 'document.deleted',
                'subject_type' => ClientDocument::class,
                'subject_id' => $document->id,
            ]);
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        });

        return back()->with('success', 'Document deleted.');
    }
}
