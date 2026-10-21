<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return $company->hasUser($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Company $company): bool
    {
        return $company->hasUser($user);
    }

    public function delete(User $user, Company $company): bool
    {
        return $company->hasUser($user);
    }

    public function manageRecords(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $company->userRole($user) === CompanyRole::Accountant;
    }

    public function uploadTransactions(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && in_array(
                $company->userRole($user),
                [CompanyRole::Accountant, CompanyRole::ClientUser],
                true,
            );
    }

    public function prepareReports(User $user, Company $company): bool
    {
        return $this->manageRecords($user, $company);
    }

    public function trackSubmissions(User $user, Company $company): bool
    {
        return $company->userRole($user) === CompanyRole::Accountant;
    }

    public function viewAudit(User $user, Company $company): bool
    {
        return in_array(
            $company->userRole($user),
            [CompanyRole::Accountant, CompanyRole::Reviewer],
            true,
        );
    }
}
