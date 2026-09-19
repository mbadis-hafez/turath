<?php

it('returns validation messages in english when requested', function () {
    $response = $this->postJson('/api/v1/auth/login', [], ['Accept-Language' => 'en']);

    $response->assertUnprocessable()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('errors.email.0', 'The email field is required.');
});

it('defaults to arabic and falls back to arabic for unsupported locales', function (string $header, string $expected) {
    $headers = $header === '' ? [] : ['Accept-Language' => $header];

    $response = $this->postJson('/api/v1/auth/login', [], $headers);

    $response->assertUnprocessable()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('errors.email.0', $expected);
})->with([
    'default arabic' => ['', 'حقل البريد الإلكتروني مطلوب.'],
    'unsupported locale falls back to arabic' => ['fr', 'حقل البريد الإلكتروني مطلوب.'],
]);
