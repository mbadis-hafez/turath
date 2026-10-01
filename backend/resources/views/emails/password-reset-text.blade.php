{!! __('passwords.mail.heading') !!}

{!! __('passwords.mail.greeting', ['name' => $user->name]) !!}

{!! __('passwords.mail.intro', ['app' => config('app.name')]) !!}

{!! $url !!}

{!! __('passwords.mail.expiry', ['minutes' => $minutes]) !!}

{!! __('passwords.mail.ignore') !!}

© {{ date('Y') }} {{ config('app.name') }}
