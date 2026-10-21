<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'counterparty_id', 'type', 'invoice_number', 'invoice_date',
    'classification', 'vat_inclusive', 'taxable_amount', 'vat_rate',
    'vat_amount', 'exempt_amount', 'zero_rated_amount', 'total_amount',
    'notes', 'created_by',
])]
class Transaction extends Model
{
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'vat_inclusive' => 'boolean',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'exempt_amount' => 'decimal:2',
            'zero_rated_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class)->withTrashed();
    }
}
