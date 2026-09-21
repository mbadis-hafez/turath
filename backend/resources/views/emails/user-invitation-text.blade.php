Welcome to {{ config('app.name') }}

Hi {{ $user->name }},

An account has been created for you on the {{ config('app.name') }} archive.
Sign in with these credentials:

  Email:                 {{ $user->email }}
  Temporary password:    {!! $password !!}

Sign in here:
{{ rtrim((string) config('app.frontend_url'), '/') . '/login' }}

For your security, you will be asked to choose a new password the first time
you sign in. This temporary password only works until then.

If you were not expecting this invitation, you can ignore this email.

© {{ date('Y') }} {{ config('app.name') }}
