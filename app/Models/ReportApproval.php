<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vat_report_id', 'user_id', 'action', 'comment'])]
class ReportApproval extends Model
{
    public function report(): BelongsTo
    {
        return $this->belongsTo(VatReport::class, 'vat_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
