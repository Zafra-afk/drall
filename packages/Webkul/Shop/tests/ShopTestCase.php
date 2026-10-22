<?php

namespace Webkul\Shop\Tests;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Webkul\Core\Tests\Concerns\CoreAssertions;
use Webkul\Shop\Tests\Concerns\ShopTestBench;

class ShopTestCase extends TestCase
{
    use CoreAssertions, ShopTestBench;

    /**
     * Restore the canonical internal locale expected by package test factories.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::table('locales')->where('id', 1)->exists()) {
            DB::table('locales')->insert([
                'id' => 1,
                'code' => 'en',
                'name' => 'English',
                'direction' => 'ltr',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
