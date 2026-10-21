<?php

namespace App\Services;

use InvalidArgumentException;

class VatCalculator
{
    /**
     * Compute VAT from the transaction's taxable base (or gross amount if inclusive).
     *
     * @return array{taxable_amount: float, vat_amount: float, total_amount: float}
     */
    public function calculate(float $amount, float $rate, bool $inclusive): array
    {
        if ($amount < 0 || $rate < 0 || $rate > 100) {
            throw new InvalidArgumentException('Amount must be non-negative and VAT rate must be between 0 and 100.');
        }

        $taxable = $inclusive ? round($amount / (1 + $rate / 100), 2) : round($amount, 2);
        $vat = round($taxable * $rate / 100, 2);

        return [
            'taxable_amount' => $taxable,
            'vat_amount' => $vat,
            'total_amount' => $inclusive ? round($amount, 2) : round($taxable + $vat, 2),
        ];
    }
}
