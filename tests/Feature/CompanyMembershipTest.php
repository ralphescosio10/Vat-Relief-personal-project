<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CompanyMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_belong_to_multiple_companies_with_different_roles(): void
    {
        $user = User::factory()->create();
        $accountant = User::factory()->create();
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();

        $firstCompany->users()->attach($user, [
            'role' => CompanyRole::Accountant->value,
            'assigned_by' => $accountant->id,
        ]);
        $secondCompany->users()->attach($user, [
            'role' => CompanyRole::Reviewer->value,
            'assigned_by' => $accountant->id,
        ]);

        $this->assertCount(2, $user->companies);
        $this->assertSame(CompanyRole::Accountant, $firstCompany->userRole($user));
        $this->assertSame(CompanyRole::Reviewer, $secondCompany->userRole($user));
        $this->assertTrue($user->hasCompanyAccess($firstCompany));
    }

    public function test_only_company_members_can_view_a_company_and_members_can_create_companies(): void
    {
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $company = Company::factory()->create();

        $company->users()->attach($member, [
            'role' => CompanyRole::Accountant->value,
        ]);

        $this->assertTrue(Gate::forUser($member)->allows('view', $company));
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $company));
        $this->assertTrue(Gate::forUser($member)->allows('create', Company::class));
    }

    public function test_database_prevents_duplicate_company_assignments(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $company->users()->attach($user, ['role' => CompanyRole::Accountant->value]);

        $this->expectException(QueryException::class);

        $company->users()->attach($user, ['role' => CompanyRole::Reviewer->value]);
    }
}
