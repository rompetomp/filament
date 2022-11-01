<?php

use Filament\Facades\Filament;
use Filament\Pages\Auth\PasswordReset\ResetPassword;
use Filament\Pages\Auth\Register;
use Filament\Tests\Models\User;
use Filament\Tests\TestCase;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use function Pest\Livewire\livewire;

uses(TestCase::class);

it('can render page', function () {
    $userToResetPassword = User::factory()->make();
    $token = Password::createToken($userToResetPassword);

    $this->get(Filament::getResetPasswordUrl(
        $token,
        $userToResetPassword,
    ))->assertSuccessful();
});

it('can reset password', function () {
    Event::fake();

    $this->assertGuest();

    $userToResetPassword = User::factory()->create();
    $token = Password::createToken($userToResetPassword);

    livewire(ResetPassword::class)
        ->set('email', $userToResetPassword->email)
        ->set('token', $token)
        ->set('password', 'new-password')
        ->set('passwordConfirmation', 'new-password')
        ->call('resetPassword')
        ->assertNotified()
        ->assertRedirect(Filament::getLoginUrl());

    Event::assertDispatched(PasswordReset::class);

    $this->assertCredentials([
        'email' => $userToResetPassword->email,
        'password' => 'new-password',
    ]);
});

it('requires request signature', function () {
    $userToResetPassword = User::factory()->make();
    $token = Password::createToken($userToResetPassword);

    $this->get(route("filament.admin.auth.password-reset.reset", [
        'email' => $userToResetPassword->getEmailForPasswordReset(),
        'token' => $token,
    ]))->assertForbidden();
});

it('requires valid email and token', function () {
    Event::fake();

    $this->assertGuest();

    $userToResetPassword = User::factory()->create();
    $token = Password::createToken($userToResetPassword);

    livewire(ResetPassword::class)
        ->set('email', $userToResetPassword->email)
        ->set('token', Str::random())
        ->set('password', 'new-password')
        ->set('passwordConfirmation', 'new-password')
        ->call('resetPassword')
        ->assertNotified()
        ->assertNoRedirect();

    Event::assertNotDispatched(PasswordReset::class);

    livewire(ResetPassword::class)
        ->set('email', fake()->email())
        ->set('token', $token)
        ->set('password', 'new-password')
        ->set('passwordConfirmation', 'new-password')
        ->call('resetPassword')
        ->assertNotified()
        ->assertNoRedirect();

    Event::assertNotDispatched(PasswordReset::class);
});

it('can throttle reset password attempts', function () {
    Event::fake();

    $this->assertGuest();

    $userToResetPassword = User::factory()->create();
    $token = Password::createToken($userToResetPassword);

    livewire(ResetPassword::class)
        ->set('email', $userToResetPassword->email)
        ->set('token', $token)
        ->set('password', 'new-password')
        ->set('passwordConfirmation', 'new-password')
        ->call('resetPassword')
        ->assertNotified()
        ->assertRedirect(Filament::getLoginUrl());

    Event::assertDispatchedTimes(PasswordReset::class, times: 1);

    $this->assertCredentials([
        'email' => $userToResetPassword->email,
        'password' => 'new-password',
    ]);

    livewire(ResetPassword::class)
        ->set('email', $userToResetPassword->email)
        ->set('token', $token)
        ->set('password', 'newer-password')
        ->set('passwordConfirmation', 'newer-password')
        ->call('resetPassword')
        ->assertNotified()
        ->assertNoRedirect();

    Event::assertDispatchedTimes(PasswordReset::class, times: 1);

    $this->assertCredentials([
        'email' => $userToResetPassword->email,
        'password' => 'new-password',
    ]);
});

it('can validate `password` is required', function () {
    livewire(ResetPassword::class)
        ->set('password', '')
        ->call('resetPassword')
        ->assertHasErrors(['password' => ['required']]);
});

it('can validate `password` is confirmed', function () {
    livewire(ResetPassword::class)
        ->set('password', Str::random())
        ->set('passwordConfirmation', Str::random())
        ->call('resetPassword')
        ->assertHasErrors(['password' => ['same']]);
});

it('can validate `passwordConfirmation` is required', function () {
    livewire(ResetPassword::class)
        ->set('passwordConfirmation', '')
        ->call('resetPassword')
        ->assertHasErrors(['passwordConfirmation' => ['required']]);
});