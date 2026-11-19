<?php

namespace Webkul\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PostCode implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (is_string($value) && trim($value) === '') {
            return;
        }

        if (
            (! is_string($value) && ! is_int($value))
            || ! preg_match('/^[\pL\pN](?:[\pL\pN\s-]*[\pL\pN])?$/u', (string) $value)
        ) {
            $fail('core::validation.postcode')->translate();
        }
    }
}
