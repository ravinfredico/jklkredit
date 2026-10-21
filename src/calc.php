<?php
declare(strict_types=1);

/**
 * Flat-rate installment, the market convention for vehicle financing.
 * All amounts are whole rupiah (integers) so nothing is lost to floating point.
 *
 *   pokok     = OTR − DP
 *   bunga     = pokok × rate × tenor / 12
 *   angsuran  = (pokok + bunga) / tenor, rounded up to the next Rp100
 */
function calc_installment(int $otr, int $downPayment, int $tenorMonths, int $rateBp): array
{
    if ($otr <= 0 || $tenorMonths <= 0 || $downPayment < 0 || $downPayment >= $otr) {
        throw new InvalidArgumentException('OTR, DP dan tenor tidak valid untuk dihitung.');
    }
    $principal = $otr - $downPayment;
    $interest = intdiv($principal * $rateBp * $tenorMonths, 12 * 10000);
    $total = $principal + $interest;
    $perMonth = intdiv($total + $tenorMonths - 1, $tenorMonths);
    $perMonth = intdiv($perMonth + 99, 100) * 100;

    return [
        'principal' => $principal,
        'total_interest' => $interest,
        'total_payable' => $total,
        'installment' => $perMonth,
    ];
}
