<?php

namespace App\Services;

use App\Models\Company;

class ReportingPeriodGuard
{
    public function assertEditable(Company $company, string|\DateTimeInterface $date): void
    {
        $locked = $company->reports()
            ->whereIn('status', ['approved', 'closed'])
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();

        abort_if($locked, 403, 'Transactions in an approved or closed reporting period cannot be changed.');
    }
}
