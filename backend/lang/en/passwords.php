<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | outcome such as failure due to an invalid password / reset token.
    |
    */

    'reset' => 'Your password has been reset.',
    'sent' => 'We have emailed your password reset link.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset link is invalid or has expired.',
    'user' => "We can't find a user with that email address.",

    'mail' => [
        'subject' => 'Reset your :app password',
        'preheader' => 'Use the link inside to choose a new password.',
        'heading' => 'Reset your password',
        'greeting' => 'Hi :name,',
        'intro' => 'We received a request to reset the password for your :app account. Choose a new one using the link below.',
        'action' => 'Choose a new password',
        'expiry' => 'This link expires in :minutes minutes and works only once.',
        'fallback' => "If the button doesn't work, copy this address into your browser:",
        'ignore' => "If you didn't ask to reset your password, you can ignore this email. Your password stays the same.",
    ],

];
