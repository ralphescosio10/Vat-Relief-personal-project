<?php

namespace App\Models;

use App\Enums\CompanyRole;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'legal_name',
    'trade_name',
    'tin',
    'branch_code',
    'registered_address',
    'status',
    'default_vat_rate',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'default_vat_rate' => 'decimal:2',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'assigned_by'])
            ->withTimestamps();
    }

    public function counterparties(): HasMany
    {
        return $this->hasMany(Counterparty::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(VatReport::class);
    }

    public function hasUser(User $user): bool
    {
        return $this->users()->whereKey($user->getKey())->exists();
    }

    public function userRole(User $user): ?CompanyRole
    {
        $role = $this->users()
            ->whereKey($user->getKey())
            ->value('company_user.role');

        return $role === null ? null : CompanyRole::tryFrom($role);
    }
}
