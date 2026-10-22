<?php

// ============================================================================
// Oversized Requests
// ============================================================================

it('should return a localized html response for a post exceeding the configured limit', function () {
    $response = $this->call('POST', '/up', [], [], [], [
        'CONTENT_LENGTH' => (string) (ini_parse_quantity(ini_get('post_max_size')) + 1),
        'HTTP_ACCEPT' => 'text/html',
    ]);

    $response
        ->assertStatus(413)
        ->assertSee('413')
        ->assertSee(trans('validation.post_too_large', ['limit' => ini_get('post_max_size')]))
        ->assertDontSee('PostTooLargeException');
});

it('should return a json response for a json post exceeding the configured limit', function () {
    $response = $this->call('POST', '/up', [], [], [], [
        'CONTENT_LENGTH' => (string) (ini_parse_quantity(ini_get('post_max_size')) + 1),
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $response
        ->assertStatus(413)
        ->assertJsonPath('message', trans('validation.post_too_large', ['limit' => ini_get('post_max_size')]))
        ->assertDontSee('PostTooLargeException');
});
