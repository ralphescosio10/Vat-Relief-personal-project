<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Counterparty;
use App\Models\Transaction;
use App\Models\AuditLog;
use App\Services\ReportingPeriodGuard;
use App\Services\VatCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request, Company $company): View
    {
        Gate::authorize('view', $company);
        $query = $company->transactions()->with('counterparty')->latest('invoice_date');
        if ($request->filled('type') && in_array($request->type, ['sale', 'purchase'], true)) {
            $query->where('type', $request->type);
        }

        return view('transactions.index', [
            'company' => $company,
            'transactions' => $query->paginate(20)->withQueryString(),
            'counterparties' => $company->counterparties()->orderBy('name')->get(),
            'canManageRecords' => Gate::allows('manageRecords', $company),
        ]);
    }

    public function edit(Company $company, Transaction $transaction): View
    {
        Gate::authorize('manageRecords', $company);
        abort_unless($transaction->company_id === $company->id, 404);

        return view('transactions.edit', [
            'company' => $company,
            'transaction' => $transaction,
            'counterparties' => $company->counterparties()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Company $company, VatCalculator $calculator, ReportingPeriodGuard $periodGuard): RedirectResponse
    {
        Gate::authorize('manageRecords', $company);
        $data = $this->validatedData($request, $company);
        $periodGuard->assertEditable($company, $data['invoice_date']);
        $computed = $this->amounts($data, $calculator);
        $transaction = $company->transactions()->create($data + $computed + ['created_by' => $request->user()->id]);
        $this->audit($request, $company, 'transaction.created', $transaction);

        return back()->with('success', 'Transaction recorded.')->with('created_transaction', $transaction->id);
    }

    public function update(Request $request, Company $company, Transaction $transaction, VatCalculator $calculator, ReportingPeriodGuard $periodGuard): RedirectResponse
    {
        Gate::authorize('manageRecords', $company);
        abort_unless($transaction->company_id === $company->id, 404);
        $periodGuard->assertEditable($company, $transaction->invoice_date);
        $data = $this->validatedData($request, $company, $transaction);
        $periodGuard->assertEditable($company, $data['invoice_date']);
        $before = $transaction->getAttributes();
        $transaction->update($data + $this->amounts($data, $calculator));
        $this->audit($request, $company, 'transaction.updated', $transaction, ['before' => $before, 'after' => $transaction->getAttributes()]);

        return back()->with('success', 'Transaction updated.');
    }

    public function destroy(Request $request, Company $company, Transaction $transaction, ReportingPeriodGuard $periodGuard): RedirectResponse
    {
        Gate::authorize('manageRecords', $company);
        abort_unless($transaction->company_id === $company->id, 404);
        $periodGuard->assertEditable($company, $transaction->invoice_date);
        $before = $transaction->getAttributes();
        $transaction->delete();
        $this->audit($request, $company, 'transaction.deleted', $transaction, ['before' => $before]);

        return back()->with('success', 'Transaction deleted.');
    }

    private function validatedData(Request $request, Company $company, ?Transaction $ignore = null): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:sale,purchase'],
            'counterparty_id' => ['nullable', 'integer', 'exists:counterparties,id'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'classification' => ['required', 'in:vatable,exempt,zero_rated'],
            'vat_inclusive' => ['sometimes', 'boolean'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (isset($data['counterparty_id'])) {
            $party = Counterparty::where('company_id', $company->id)->whereKey($data['counterparty_id'])->first();
            if (! $party || $party->type !== ($data['type'] === 'sale' ? 'customer' : 'supplier')) {
                throw ValidationException::withMessages([
                    'counterparty_id' => 'Choose a customer for sales or a supplier for purchases from this company.',
                ]);
            }
        }
        $duplicateQuery = $company->transactions()
            ->where('type', $data['type'])
            ->where('invoice_number', $data['invoice_number'])
            ->whereDate('invoice_date', $data['invoice_date']);
        $party = isset($data['counterparty_id']) ? Counterparty::find($data['counterparty_id']) : null;
        if ($party?->tin) {
            $duplicateQuery->whereHas('counterparty', fn ($query) => $query->where('tin', $party->tin));
        } elseif ($party) {
            $duplicateQuery->where('counterparty_id', $party->id);
        } else {
            $duplicateQuery->whereNull('counterparty_id');
        }
        if ($ignore) {
            $duplicateQuery->where('id', '!=', $ignore->id);
        }
        $duplicate = $duplicateQuery->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['invoice_number' => 'A transaction with this type, invoice number and date already exists.']);
        }
        $data['vat_inclusive'] = $request->boolean('vat_inclusive');

        return $data;
    }

    private function amounts(array $data, VatCalculator $calculator): array
    {
        $amount = (float) $data['amount'];
        $classification = $data['classification'];
        if ($classification !== 'vatable') {
            return [
                'taxable_amount' => 0,
                'vat_amount' => 0,
                'exempt_amount' => $classification === 'exempt' ? $amount : 0,
                'zero_rated_amount' => $classification === 'zero_rated' ? $amount : 0,
                'total_amount' => $amount,
            ];
        }
        $result = $calculator->calculate($amount, (float) $data['vat_rate'], $data['vat_inclusive']);

        return $result + ['exempt_amount' => 0, 'zero_rated_amount' => 0];
    }

    private function audit(Request $request, Company $company, string $action, Transaction $transaction, array $details = []): void
    {
        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => Transaction::class,
            'subject_id' => $transaction->id,
            'details' => $details,
        ]);
    }
}
