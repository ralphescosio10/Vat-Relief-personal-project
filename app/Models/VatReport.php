<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'period_start', 'period_end', 'due_date', 'sales_received_at',
    'purchases_received_at', 'supporting_docs_received_at', 'status', 'snapshot', 'prepared_by',
])]
class VatReport extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'sales_received_at' => 'datetime',
            'purchases_received_at' => 'datetime',
            'supporting_docs_received_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ReportApproval::class);
    }
}
