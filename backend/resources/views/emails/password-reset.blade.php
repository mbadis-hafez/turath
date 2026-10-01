@php
    $sans = "-apple-system, 'Segoe UI', Helvetica, Arial, sans-serif";
    $display = $dir === 'rtl' ? $sans : "Georgia, 'Times New Roman', serif";
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ __('passwords.mail.subject', ['app' => config('app.name')]) }}</title>
    <!--[if mso]>
    <style>
        .button-td { background: #1f4e79 !important; }
        .button-a { color: #ffffff !important; text-decoration: none !important; }
    </style>
    <![endif]-->
</head>
<body dir="{{ $dir }}" style="margin: 0; padding: 0; background-color: #fbfaf6; -webkit-text-size-adjust: 100%;">
    <!-- Preheader (hidden preview text in inboxes) -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        {{ __('passwords.mail.preheader') }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #fbfaf6;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" dir="{{ $dir }}" width="560" cellpadding="0" cellspacing="0" style="width: 560px; max-width: 100%; background-color: #ffffff; border: 1px solid #ddd9cd; border-radius: 8px; overflow: hidden;">

                    <!-- Header -->
                    <tr>
                        <td align="center" style="background-color: #1a1a18; padding: 28px 32px;">
                            <img
                                src="{{ $message->embed(base_path('../frontend/public/logo-light.png')) }}"
                                alt="{{ config('app.name') }}"
                                width="113"
                                height="48"
                                style="display: block; width: 113px; height: 48px; margin: 0 auto; border: 0;"
                            >
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td align="{{ $align }}" style="padding: 36px 32px 24px 32px; text-align: {{ $align }};">
                            <h1 style="margin: 0 0 16px 0; color: #1a1a18; font-family: {!! $display !!}; font-size: 22px; line-height: 1.3; font-weight: 600;">
                                {{ __('passwords.mail.heading') }}
                            </h1>
                            <p style="margin: 0 0 8px 0; color: #54524b; font-family: {!! $sans !!}; font-size: 15px; line-height: 1.6;">
                                {{ __('passwords.mail.greeting', ['name' => $user->name]) }}
                            </p>
                            <p style="margin: 0 0 28px 0; color: #54524b; font-family: {!! $sans !!}; font-size: 15px; line-height: 1.6;">
                                {{ __('passwords.mail.intro', ['app' => config('app.name')]) }}
                            </p>

                            <!-- CTA button -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 28px;">
                                <tr>
                                    <td align="center">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td class="button-td" style="background-color: #1f4e79; border-radius: 6px;">
                                                    <a class="button-a" href="{{ $url }}" style="display: inline-block; padding: 14px 32px; color: #ffffff; font-family: {!! $sans !!}; font-size: 15px; font-weight: 600; text-decoration: none;">
                                                        {{ __('passwords.mail.action') }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Expiry note -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td align="{{ $align }}" style="background-color: #dbe4ee; border-radius: 6px; padding: 14px 18px; color: #163a5c; font-family: {!! $sans !!}; font-size: 13px; line-height: 1.6; text-align: {{ $align }};">
                                        {{ __('passwords.mail.expiry', ['minutes' => $minutes]) }}
                                    </td>
                                </tr>
                            </table>

                            <!-- Fallback link -->
                            <p style="margin: 0 0 6px 0; color: #7d7a70; font-family: {!! $sans !!}; font-size: 12px; line-height: 1.6;">
                                {{ __('passwords.mail.fallback') }}
                            </p>
                            <p dir="ltr" style="margin: 0; font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; line-height: 1.6; word-break: break-all; text-align: left;">
                                <a href="{{ $url }}" style="color: #1f4e79;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="{{ $align }}" style="padding: 20px 32px 28px 32px; border-top: 1px solid #ddd9cd; text-align: {{ $align }};">
                            <p style="margin: 0; color: #7d7a70; font-family: {!! $sans !!}; font-size: 12px; line-height: 1.6;">
                                {{ __('passwords.mail.ignore') }}
                            </p>
                            <p style="margin: 8px 0 0 0; color: #7d7a70; font-family: {!! $sans !!}; font-size: 12px;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
