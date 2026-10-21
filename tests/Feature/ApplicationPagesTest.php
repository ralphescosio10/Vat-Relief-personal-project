<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Models\VatReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ApplicationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_open_the_application_workspaces(): void
    {
        $accountant = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($accountant, ['role' => CompanyRole::Accountant->value]);
        $this->get(route('register'))->assertOk();
        $report = VatReport::create([
            'company_id' => $company->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'status' => 'draft',
            'snapshot' => [
                'totals' => ['sales_vat' => 0, 'purchases_vat' => 0, 'sales_total' => 0, 'purchases_total' => 0],
                'transaction_count' => 0,
                'validation_issues' => [],
                'transactions' => [],
            ],
        ]);
        $this->actingAs($accountant);

        foreach ([
            route('dashboard'),
            route('companies.index'),
            route('companies.create'),
            route('companies.show', $company),
            route('companies.edit', $company),
            route('companies.transactions', $company),
            route('companies.counterparties', $company),
            route('companies.imports', $company),
            route('companies.reports', $company),
            route('companies.reports.show', [$company, $report]),
            route('companies.audit', $company),
        ] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('companies.show', $company))
            ->assertOk()
            ->assertDontSee('Team access')
            ->assertDontSee('Create a new user account');
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_registration_connects_accountant_to_all_separate_company_workspaces(): void
    {
        $firstCompany = Company::factory()->create(['legal_name' => 'First Client Inc.']);
        $secondCompany = Company::factory()->create(['legal_name' => 'Second Client Inc.']);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('register'), false);
        $this->get(route('register'))->assertOk();
        $this->post(route('register'), [
            'name' => 'Shared Accountant',
            'email' => 'accountant@example.test',
            'password' => 'accountant-password-123',
            'password_confirmation' => 'accountant-password-123',
        ])->assertRedirect(route('dashboard'));
        $accountant = User::where('email', 'accountant@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($accountant);
        $this->assertSame(CompanyRole::Accountant, $firstCompany->fresh()->userRole($accountant));
        $this->assertSame(CompanyRole::Accountant, $secondCompany->fresh()->userRole($accountant));
        $this->get(route('companies.index'))
            ->assertOk()
            ->assertSee('First Client Inc.')
            ->assertSee('Second Client Inc.')
            ->assertSee(route('companies.show', $firstCompany), false)
            ->assertSee(route('companies.show', $secondCompany), false);
        $this->get(route('companies.show', $firstCompany))->assertOk();
        $this->get(route('companies.show', $secondCompany))->assertOk();
    }

    public function test_registration_rejects_an_email_that_already_exists_regardless_of_case(): void
    {
        User::factory()->create(['email' => 'accountant@example.test']);

        $this->from(route('register'))->post(route('register'), [
            'name' => 'Duplicate Account',
            'email' => 'ACCOUNTANT@EXAMPLE.TEST',
            'password' => 'accountant-password-123',
            'password_confirmation' => 'accountant-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_new_company_is_connected_to_every_existing_accountant(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->post(route('companies.store'), [
            'legal_name' => 'New Shared Client Inc.',
        ])->assertRedirect();
        $company = Company::where('legal_name', 'New Shared Client Inc.')->firstOrFail();

        $this->assertSame(CompanyRole::Accountant, $company->userRole($first));
        $this->assertSame(CompanyRole::Accountant, $company->userRole($second));
        $this->actingAs($second)->get(route('companies.show', $company))->assertOk();
    }

    public function test_password_reset_link_response_does_not_reveal_if_an_account_exists(): void
    {
        $this->get(route('password.request'))->assertOk();

        $this->post(route('password.email'), ['email' => 'missing@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', 'If an account exists for that address, a password reset link has been sent.');
        $this->assertDatabaseCount('password_reset_tokens', 0);

        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status', 'If an account exists for that address, a password reset link has been sent.');
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        $token = Password::createToken($user);
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'another-strong-password-456',
            'password_confirmation' => 'another-strong-password-456',
        ])->assertRedirect(route('login'));
        $this->assertTrue(password_verify('another-strong-password-456', $user->fresh()->password));
    }

    public function test_dashboard_summary_cards_link_to_their_workspaces(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($user, ['role' => CompanyRole::Accountant->value]);
        $report = VatReport::create([
            'company_id' => $company->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'status' => 'for_review',
            'snapshot' => [],
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('companies.index').'"', false)
            ->assertSee('href="'.route('companies.transactions', $company).'"', false)
            ->assertSee('href="'.route('companies.reports.show', [$company, $report]).'"', false);
    }
}
