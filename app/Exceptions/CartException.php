<?php

namespace App\Exceptions;

use RuntimeException;

class CartException extends RuntimeException
{
    public static function productInactive(): self
    {
        return new self('This product is no longer available.');
    }

    public static function subscriptionQuantityExceeded(): self
    {
        return new self('You can only add one subscription product at a time.');
    }

    public static function insufficientStock(int $available): self
    {
        return new self("Only $available unit(s) available in stock.");
    }
}
