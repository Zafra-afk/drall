<?php

use Illuminate\Support\Facades\Hash;
use Webkul\User\Models\Admin;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

// ============================================================================
// Login Page
// ============================================================================

it('should return the admin login page', function () {
    get(route('admin.session.create'))
        ->assertOk()
        ->assertSeeText(trans('admin::app.users.sessions.title'));
});

it('should switch the anonymous admin interface to a permitted locale and persist the choice', function () {
    $response = get(route('admin.session.create', ['admin_locale' => 'fr']));

    $response->assertOk()
        ->assertSee('admin-anonymous-locale-switcher');

    expect(session('admin.locale'))->toBe('fr')
        ->and(app()->getLocale())->toBe('fr');

    expect(preg_match('/<html\s+lang="fr"/', $response->getContent()))->toBe(1);
});

it('should reject Malagasy in the French-only admin interface', function () {
    $response = get(route('admin.session.create', ['admin_locale' => 'mg']));

    $response->assertOk()
        ->assertSee('admin-anonymous-locale-switcher');

    expect(session('admin.locale'))->toBeNull()
        ->and(app()->getLocale())->toBe('fr');

    expect(preg_match('/<html\s+lang="fr"/', $response->getContent()))->toBe(1);
});

it('should show the language switcher in the authenticated admin interface', function () {
    $this->loginAsAdmin();

    get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertSee('admin-locale-switcher')
        ->assertSee('Français')
        ->assertDontSee('option value="mg"', false)
        ->assertDontSee('option value="en"', false);
});

it('should redirect to dashboard if already authenticated', function () {
    $this->loginAsAdmin();

    get(route('admin.session.create'))
        ->assertRedirect(route('admin.dashboard.index'));
});

// ============================================================================
// Login
// ============================================================================

it('should login with valid credentials', function () {
    $admin = Admin::factory()->create([
        'password' => Hash::make('admin123'),
        'status' => true,
    ]);
    $sessionId = session()->getId();

    postJson(route('admin.session.store'), [
        'email' => $admin->email,
        'password' => 'admin123',
    ])
        ->assertRedirect(route('admin.dashboard.index'));

    $this->assertAuthenticatedAs($admin, 'admin');

    expect(session()->getId())->not->toBe($sessionId);
});

it('should throttle repeated admin login failures', function () {
    $admin = Admin::factory()->create([
        'password' => Hash::make('admin123'),
        'status' => true,
    ]);

    foreach (range(1, 6) as $attempt) {
        $response = postJson(route('admin.session.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);
    }

    $response->assertTooManyRequests()
        ->assertJsonValidationErrorFor('email');

    expect($response->json('errors.email.0'))->toContain('Trop de tentatives')
        ->and(auth()->guard('admin')->check())->toBeFalse();
});

it('should not login with a wrong password', function () {
    $admin = Admin::factory()->create([
        'password' => Hash::make('admin123'),
    ]);

    postJson(route('admin.session.store'), [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ])
        ->assertRedirect()
        ->assertSessionHas('error', trans('admin::app.settings.users.login-error'));

    $this->assertGuest('admin');
});

it('should not login with an email no admin has', function () {
    postJson(route('admin.session.store'), [
        'email' => fake()->unique()->safeEmail(),
        'password' => 'admin123',
    ])
        ->assertRedirect()
        ->assertSessionHas('error', trans('admin::app.settings.users.login-error'));

    $this->assertGuest('admin');
});

it('should fail validation when email or password is missing', function () {
    postJson(route('admin.session.store'))
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('email')
        ->assertJsonValidationErrorFor('password');

    $this->assertGuest('admin');
});

it('should not login when admin account is inactive', function () {
    $admin = Admin::factory()->create([
        'password' => Hash::make('admin123'),
        'status' => false,
    ]);

    postJson(route('admin.session.store'), [
        'email' => $admin->email,
        'password' => 'admin123',
    ])
        ->assertRedirect(route('admin.session.create'))
        ->assertSessionHas('warning', trans('admin::app.settings.users.activate-warning'));

    $this->assertGuest('admin');
});

// ============================================================================
// Logout
// ============================================================================

it('should logout the admin', function () {
    $this->loginAsAdmin();

    deleteJson(route('admin.session.destroy'))
        ->assertRedirect(route('admin.session.create'));

    $this->assertGuest('admin');
});
