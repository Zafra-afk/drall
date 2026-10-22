<?php

use Illuminate\Support\Facades\DB;
use Webkul\Customer\Models\CustomerNote;

// ============================================================================
// Customer Notifications
// ============================================================================

it('shows only notifications sent to the signed-in customer and marks them read', function () {
    $customer = $this->loginAsCustomer();
    $otherCustomer = $this->loginAsCustomer();

    $notification = CustomerNote::create([
        'customer_id' => $customer->id,
        'note' => 'Your order is ready for pickup.',
        'customer_notified' => true,
    ]);

    CustomerNote::create([
        'customer_id' => $otherCustomer->id,
        'note' => 'Private notification for another customer.',
        'customer_notified' => true,
    ]);

    CustomerNote::create([
        'customer_id' => $customer->id,
        'note' => 'Internal note that was not sent.',
        'customer_notified' => false,
    ]);

    $this->actingAs($customer, 'customer')
        ->get(route('shop.customers.account.notifications.index'))
        ->assertOk()
        ->assertSee('Your order is ready for pickup.')
        ->assertDontSee('Private notification for another customer.')
        ->assertDontSee('Internal note that was not sent.');

    expect(DB::table('customer_notes')->where('id', $notification->id)->value('customer_read_at'))
        ->not->toBeNull();
});

it('requires customer authentication to view notifications', function () {
    auth()->guard('customer')->logout();

    $this->get(route('shop.customers.account.notifications.index'))
        ->assertRedirect();
});
