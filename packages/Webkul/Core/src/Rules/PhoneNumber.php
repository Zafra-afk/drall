<?php

namespace Webkul\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumber implements ValidationRule
{
    /**
     * Validate a phone number containing at least seven digits.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^\+?[\s(]*\d[\d\s.\-()]*$/', $value) || preg_match_all('/\d/', $value) < 7) {
            $fail('core::validation.phone-number')->translate();
        }
    }
}
