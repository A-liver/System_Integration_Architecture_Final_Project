<?php

namespace App\Support;

class Money
{
    public static function format(int $cents, string $currency): string
    {
        return $currency.' '.number_format($cents / 100, 2);
    }
}
