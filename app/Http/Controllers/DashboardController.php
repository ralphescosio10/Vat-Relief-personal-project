<?php

namespace App\Http\Controllers;

use App\Models\VatReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = Auth::user();
        $companies = $user->companies()->withCount('transactions')->orderBy('legal_name')->get();
        $companyIds = $companies->modelKeys();
        $pendingReviewReport = VatReport::with('company')
            ->whereIn('company_id', $companyIds)
            ->where('status', 'for_review')
            ->latest()
            ->first();
        $submissionWatch = VatReport::with('company')
            ->whereIn('company_id', $companyIds)
            ->where(function ($query) {
                $query->where(function ($due) {
                    $due->whereNotNull('due_date')
                        ->whereBetween('due_date', [today(), today()->addDays(30)]);
                })->orWhereNull('sales_received_at')
                    ->orWhereNull('purchases_received_at')
                    ->orWhereNull('supporting_docs_received_at');
            })
            ->whereNotIn('status', ['closed'])
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        return view('dashboard', [
            'companies' => $companies,
            'transactionCount' => $companyIds === [] ? 0 : \App\Models\Transaction::whereIn('company_id', $companyIds)->count(),
            'pendingReports' => VatReport::whereIn('company_id', $companyIds)->where('status', 'for_review')->count(),
            'pendingReviewReport' => $pendingReviewReport,
            'recentReports' => VatReport::with('company')->whereIn('company_id', $companyIds)->latest()->limit(6)->get(),
            'submissionWatch' => $submissionWatch,
        ]);
    }
}
