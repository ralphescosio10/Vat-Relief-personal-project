<?php

use App\Enums\CompanyRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_user')->update([
            'role' => CompanyRole::Accountant->value,
            'updated_at' => now(),
        ]);

        $companyIds = DB::table('companies')->pluck('id');
        foreach ($companyIds as $companyId) {
            DB::table('users')->orderBy('id')->chunkById(500, function ($users) use ($companyId): void {
                $now = now();
                $memberships = $users->map(fn ($user) => [
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'role' => CompanyRole::Accountant->value,
                    'assigned_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                DB::table('company_user')->insertOrIgnore($memberships);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_firm_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_firm_admin')->default(false);
        });
    }
};
