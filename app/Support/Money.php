<?php

namespace App\Support;

class Money
{
    public static function format(int $cents, string $currency = 'USD', string $locale = 'en_US'): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($cents / 100, $currency);
    }
}
