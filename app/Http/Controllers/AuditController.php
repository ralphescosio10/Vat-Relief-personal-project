<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Company $company): View
    {
        Gate::authorize('viewAudit', $company);

        return view('audit.index', [
            'company' => $company,
            'logs' => AuditLog::with('user')
                ->where('company_id', $company->id)
                ->latest()
                ->paginate(30),
        ]);
    }
}
