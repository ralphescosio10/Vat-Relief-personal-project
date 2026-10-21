<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Counterparty;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CounterpartyController extends Controller
{
    public function index(Company $company): View
    {
        Gate::authorize('view', $company);

        return view('counterparties.index', [
            'company' => $company,
            'counterparties' => $company->counterparties()->orderBy('type')->orderBy('name')->paginate(20),
            'canManageRecords' => Gate::allows('manageRecords', $company),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('manageRecords', $company);
        $data = $request->validate([
            'type' => ['required', 'in:customer,supplier'],
            'name' => ['required', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:20', \Illuminate\Validation\Rule::unique('counterparties', 'tin')->where(fn ($query) => $query->where('company_id', $company->id)->where('type', $request->input('type')))],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);
        $counterparty = $company->counterparties()->create($data);
        $this->audit($request, $company, 'counterparty.created', $counterparty, ['after' => $counterparty->getAttributes()]);

        return back()->with('success', 'Customer or supplier added.');
    }

    public function destroy(Request $request, Company $company, Counterparty $counterparty): RedirectResponse
    {
        Gate::authorize('manageRecords', $company);
        abort_unless($counterparty->company_id === $company->id, 404);
        $before = $counterparty->getAttributes();
        $counterparty->delete();
        $this->audit($request, $company, 'counterparty.deleted', $counterparty, ['before' => $before]);

        return back()->with('success', 'Customer or supplier deleted.');
    }

    private function audit(Request $request, Company $company, string $action, Counterparty $counterparty, array $details): void
    {
        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => Counterparty::class,
            'subject_id' => $counterparty->id,
            'details' => $details,
        ]);
    }
}
