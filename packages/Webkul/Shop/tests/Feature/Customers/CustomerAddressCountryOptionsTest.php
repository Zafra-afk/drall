<?php

use Webkul\Customer\Models\Customer;
use Webkul\Customer\Models\CustomerAddress;

// ============================================================================
// Customer Address Country Options
// ============================================================================

it('shows database countries in customer address forms when no storefront filter is configured', function () {
    config()->set('app.storefront_countries', []);

    $customer = Customer::factory()->create();
    $country = core()->countries()->first();

    expect($country)->not->toBeNull();

    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'country' => $country->code,
    ]);

    $this->loginAsCustomer($customer);

    $this->get(route('shop.customers.account.addresses.create'))
        ->assertOk()
        ->assertSee($country->code)
        ->assertSeeText($country->name);

    $this->get(route('shop.customers.account.addresses.edit', $address->id))
        ->assertOk()
        ->assertSee($country->code)
        ->assertSeeText($country->name);

    $this->getJson(route('shop.api.core.countries'))
        ->assertOk()
        ->assertJsonFragment([
            'code' => $country->code,
            'name' => $country->name,
        ]);
});

it('rejects a country code that does not exist when the storefront filter is empty', function () {
    config()->set('app.storefront_countries', []);

    $this->loginAsCustomer();

    postJson(route('shop.customers.account.addresses.store'), $this->storefrontAddress([
        'country' => 'NOT-A-COUNTRY',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('country');
});
