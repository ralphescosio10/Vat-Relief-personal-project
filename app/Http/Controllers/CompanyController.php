<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\AuditLog;
use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $companies = $user->companies()->withCount('transactions')->orderBy('legal_name')->paginate(15);

        return view('companies.index', compact('companies'));
    }

    public function create(): View
    {
        Gate::authorize('create', Company::class);

        return view('companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Company::class);
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:20'],
            'branch_code' => ['nullable', 'string', 'max:10'],
            'registered_address' => ['nullable', 'string', 'max:2000'],
            'default_vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $company = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request): Company {
            $company = Company::create($data + ['status' => 'active']);
            $userIds = User::query()->pluck('id');
            $members = $userIds->mapWithKeys(fn (int $userId) => [
                $userId => [
                    'role' => CompanyRole::Accountant->value,
                    'assigned_by' => $request->user()->id,
                ],
            ])->all();
            $company->users()->attach($members);
            $this->audit($request, $company, 'company.created', Company::class, $company->id);

            return $company;
        });

        return redirect()->route('companies.show', $company)->with('success', 'Client company created.');
    }

    public function edit(Company $company): View
    {
        Gate::authorize('update', $company);

        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('update', $company);
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:20'],
            'branch_code' => ['nullable', 'string', 'max:10'],
            'registered_address' => ['nullable', 'string', 'max:2000'],
            'default_vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
        ]);
        $before = $company->getAttributes();
        $company->update($data);
        $this->audit($request, $company, 'company.updated', Company::class, $company->id, [
            'before' => $before,
            'after' => $company->getAttributes(),
        ]);

        return redirect()->route('companies.show', $company)->with('success', 'Client company updated.');
    }

    public function show(Company $company): View
    {
        Gate::authorize('view', $company);
        $company->loadCount('transactions');

        return view('companies.show', compact('company'));
    }

    private function audit(Request $request, Company $company, string $action, string $subjectType, int $subjectId, array $details = []): void
    {
        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => $details,
        ]);
    }
}
