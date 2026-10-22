<?php

use Illuminate\Support\Facades\Artisan;

// ============================================================================
// Configured Locale Scope
// ============================================================================

it('should not require root translation folders for locales excluded from the storefront', function () {
    config(['app.allowed_locales' => ['fr']]);

    expect(Artisan::call('bagisto:translations:check', ['--locale' => 'ar']))->toBe(0)
        ->and(Artisan::output())->not->toContain('📦 Root');
});
