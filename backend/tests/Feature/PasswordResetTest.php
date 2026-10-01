<?php

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    config(['app.frontend_url' => 'https://bidayaat.example.test/']);
    // The controller sends the mail after the response; run it inline instead.
    $this->withoutDefer();
});

function resetUser(array $attributes = []): User
{
    return User::factory()->create([
        'email' => 'reader@example.test',
        'password' => Hash::make('old-password'),
        ...$attributes,
    ]);
}

function resetTokenCount(): int
{
    return DB::table('password_reset_tokens')->count();
}

it('mails an active account a link to the SPA reset page in the language it was requested in', function () {
    Mail::fake();
    $user = resetUser();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reader@example.test'], ['Accept-Language' => 'en'])
        ->assertNoContent();

    expect(resetTokenCount())->toBe(1);

    Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use ($user) {
        // Rendering fixes the subject in the mail's own locale before hasSubject reads it.
        $html = $mail->render();
        parse_str((string) parse_url($mail->url, PHP_URL_QUERY), $query);

        return $mail->hasTo($user->email)
            && $mail->locale === 'en'
            && str_starts_with($mail->url, 'https://bidayaat.example.test/en/reset-password?')
            && $query['email'] === $user->email
            && Password::broker()->tokenExists($user, $query['token'])
            && $mail->hasSubject('Reset your '.config('app.name').' password')
            && str_contains($html, e($mail->url));
    });
});

it('writes the mail in Arabic, right to left, when the request is in Arabic', function () {
    Mail::fake();
    resetUser();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reader@example.test'])->assertNoContent();

    Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) {
        $html = $mail->render();

        return $mail->locale === 'ar'
            && str_contains($mail->url, '/ar/reset-password?')
            && $mail->hasSubject('إعادة تعيين كلمة المرور في '.config('app.name'))
            && str_contains($html, 'dir="rtl"')
            && str_contains($html, 'اختر كلمة مرور جديدة');
    });
});

it('answers an unknown, deactivated or deleted address exactly like a real one, and sends nothing', function () {
    Mail::fake();
    resetUser(['email' => 'inactive@example.test', 'is_active' => false]);
    resetUser(['email' => 'deleted@example.test'])->delete();

    foreach (['nobody@example.test', 'inactive@example.test', 'deleted@example.test'] as $email) {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])->assertNoContent();
    }

    Mail::assertNothingSent();
    expect(resetTokenCount())->toBe(0);
});

it('does not send a second link straight away, but answers the same', function () {
    Mail::fake();
    resetUser();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reader@example.test'])->assertNoContent();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reader@example.test'])->assertNoContent();

    Mail::assertSent(PasswordResetMail::class, 1);
});

it('still answers the same when the mail cannot be sent, and reports the failure', function () {
    Exceptions::fake();
    Mail::shouldReceive('to')->andThrow(new TransportException('Mail server unreachable'));
    resetUser();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reader@example.test'])->assertNoContent();

    Exceptions::assertReported(TransportException::class);
});

it('requires a valid email address to request a link', function () {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('resets the password with a valid token, satisfies a forced change, and uses the token up', function () {
    $user = resetUser(['must_change_password' => true]);
    $rememberToken = $user->remember_token;
    $token = Password::broker()->createToken($user);

    $payload = [
        'token' => $token,
        'email' => 'reader@example.test',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ];

    $this->postJson('/api/v1/auth/reset-password', $payload)->assertNoContent();

    $user->refresh();
    expect(Hash::check('a-brand-new-password', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->remember_token)->not->toBe($rememberToken)
        ->and(resetTokenCount())->toBe(0);

    $this->postJson('/api/v1/auth/login', ['email' => 'reader@example.test', 'password' => 'a-brand-new-password'], ['Origin' => 'http://localhost'])
        ->assertOk();

    $this->postJson('/api/v1/auth/reset-password', [...$payload, 'password' => 'yet-another-one', 'password_confirmation' => 'yet-another-one'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});

it('refuses a wrong token, an expired one, and a deactivated account with the same error', function () {
    $user = resetUser();
    $attempt = fn (string $token) => $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'reader@example.test',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ], ['Accept-Language' => 'en']);

    $token = Password::broker()->createToken($user);
    $attempt('not-the-token')
        ->assertUnprocessable()
        ->assertJsonPath('errors.token.0', 'This password reset link is invalid or has expired.');

    $this->travel(61)->minutes();
    $attempt($token)->assertUnprocessable()->assertJsonValidationErrors('token');
    $this->travelBack();

    $token = Password::broker()->createToken($user);
    $user->update(['is_active' => false]);
    $attempt($token)->assertUnprocessable()->assertJsonValidationErrors('token');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('validates the new password', function () {
    $user = resetUser();
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'reader@example.test',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('rate limits reset requests after 5 per minute per email and ip', function () {
    Mail::fake();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])->assertNoContent();
    }

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])->assertTooManyRequests();
});
