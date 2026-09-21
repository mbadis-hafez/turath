<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>You've been invited to {{ config('app.name') }}</title>
    <!--[if mso]>
    <style>
        .button-td { background: #1f4e79 !important; }
        .button-a { color: #ffffff !important; text-decoration: none !important; }
    </style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #fbfaf6; -webkit-text-size-adjust: 100%;">
    <!-- Preheader (hidden preview text in inboxes) -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        Your {{ config('app.name') }} account is ready — sign in with the credentials inside.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #fbfaf6;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width: 560px; max-width: 100%; background-color: #ffffff; border: 1px solid #ddd9cd; border-radius: 8px; overflow: hidden;">

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
                        <td style="padding: 36px 32px 24px 32px;">
                            <h1 style="margin: 0 0 16px 0; color: #1a1a18; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; line-height: 1.3; font-weight: 600;">
                                Welcome to {{ config('app.name') }}
                            </h1>
                            <p style="margin: 0 0 24px 0; color: #54524b; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.6;">
                                Hi {{ $user->name }}, an account has been created for you on the
                                {{ config('app.name') }} archive. Sign in with the credentials below:
                            </p>

                            <!-- Credentials -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #fbfaf6; border: 1px solid #ddd9cd; border-radius: 6px; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 6px 0; color: #7d7a70; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 12px; text-transform: uppercase; letter-spacing: 0.8px; width: 180px; vertical-align: top;">
                                                    Email
                                                </td>
                                                <td style="padding: 6px 0; color: #1a1a18; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 600;">
                                                    {{ $user->email }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; color: #7d7a70; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 12px; text-transform: uppercase; letter-spacing: 0.8px; width: 180px; vertical-align: top;">
                                                    Temporary password
                                                </td>
                                                <td style="padding: 6px 0; color: #1a1a18; font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 15px; font-weight: 600; letter-spacing: 0.5px;">
                                                    {{ $password }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA button -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 28px;">
                                <tr>
                                    <td align="center">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td class="button-td" style="background-color: #1f4e79; border-radius: 6px;">
                                                    <a class="button-a" href="{{ rtrim((string) config('app.frontend_url'), '/') . '/login' }}" style="display: inline-block; padding: 14px 32px; color: #ffffff; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 600; text-decoration: none;">
                                                        Sign in to {{ config('app.name') }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Security note -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background-color: #dbe4ee; border-radius: 6px; padding: 14px 18px; color: #163a5c; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.6;">
                                        For your security, you will be asked to choose a new password
                                        the first time you sign in. This temporary password only works until then.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 32px 28px 32px; border-top: 1px solid #ddd9cd;">
                            <p style="margin: 0; color: #7d7a70; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.6;">
                                If you were not expecting this invitation, you can ignore this email.
                            </p>
                            <p style="margin: 8px 0 0 0; color: #7d7a70; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 12px;">
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
