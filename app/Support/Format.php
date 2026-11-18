<?php

declare(strict_types=1);

namespace App\Support;

final class Format
{
    /** Peso amount, e.g. ₱1,234.50. (The JavaScript side uses Intl.NumberFormat.) */
    public static function money($amount): string
    {
        return '₱' . number_format((float) $amount, 2);
    }
}
