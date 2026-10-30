# Administrator password reset setup

The login modal's **Forgot password?** link opens `views/forgot_password.php`.
Requests always show the same response whether or not an administrator email exists.
A valid link is single-use and expires in 30 minutes. Resetting a password revokes
existing administrator sessions on their next authenticated request.

## 1. Database

Back up the database, then run `database/password_reset_migration.sql` once in the
SBCIS database. Fresh installations using `database/sbcdb.sql` already have the
tables. The migration creates only `password_reset_tokens` and
`password_reset_requests`; it does not change any administrator passwords.

## 2. EmailJS (recommended if you use EmailJS)

Create an EmailJS email service and a password-reset template. Set the template's
**To Email** field to `{{to_email}}`. Suggested subject:
`Reset your SBCIS password`. Suggested HTML template:

```html
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:28px;color:#17352a">
  <p style="font-size:12px;letter-spacing:.08em;color:#146b4d">SOUTHERN LEYTE SOIL INFORMATION SYSTEM</p>
  <h1 style="font-size:25px">Reset your password</h1>
  <p>A password reset was requested for your SBCIS administrator account.</p>
  <p><a href="{{reset_url}}" style="display:inline-block;background:#146b4d;color:#fff;padding:12px 18px;text-decoration:none">Set a new password</a></p>
  <p>This link expires in {{expires_minutes}} minutes and can be used once. If you did not request it, ignore this email.</p>
</div>
```

Configure the **PHP web server's environment** (never commit actual keys):

```text
SBCIS_PUBLIC_BASE_URL=https://your-domain.example
SBCIS_EMAILJS_SERVICE_ID=your_email_service_id
SBCIS_EMAILJS_TEMPLATE_ID=your_password_reset_template_id
SBCIS_EMAILJS_PUBLIC_KEY=your_emailjs_public_key
SBCIS_EMAILJS_PRIVATE_KEY=your_emailjs_private_key
```

Find Service ID under EmailJS **Email Services**, Template ID under **Email Templates**,
and Public/Private Keys under **Account**. The private key is optional in the EmailJS
REST API unless account security requires it; if enabled, set it **only on the server**.
The PHP backend calls `https://api.emailjs.com/api/v1.0/email/send`; it sends
`to_email`, `reset_url`, `expires_minutes`, and `app_name` as template parameters.
Keep the template recipient dynamic so the link goes to the account that requested it.
Do not add EmailJS to `src/js/` or put a reset token in browser JavaScript.

Because PHP sends from the server, turn on **Allow EmailJS API for non-browser
applications** in EmailJS **Account → Security**. EmailJS disables non-browser API
requests by default; an HTTP 403 from its send endpoint commonly means this setting
is off. Confirm the account's service, template, and public/private keys belong
to that same account. If you enabled a domain allowlist, review it in the EmailJS
dashboard as well.

The public base URL must point to the actual SBCIS root, including any subdirectory,
without a trailing slash. Production requires HTTPS. For local development only,
`http://127.0.0.1:8000` or `http://localhost:8000` is accepted as the base URL;
the mailbox recipient must open that local URL on the same computer. Restart PHP
or the web server after setting process environment variables.

### InfinityFree free hosting

InfinityFree free hosting does not pass user-configured environment variables to PHP.
Copy `config/email.local.example.php` to `config/email.local.php` and put your
EmailJS IDs, your **rotated** private key, and the real HTTPS SBCIS URL in the
returned PHP array. Upload `email.local.php` to `htdocs/config/email.local.php`
using FTP or the file manager; do not commit it to Git. SBCIS reads this file only
when the corresponding environment variable is unset. Upload the project's root
`.htaccess` too; it blocks browser access to `/config/`. Check that
`https://your-domain/config/email.local.php` returns HTTP 403 or 404. If it does
not, stop and fix access control before accepting any password-reset requests.

This config file fixes the **EmailJS keys only**. The current SBCIS production
profile also requires environment-based database/cache configuration and an
installed CLI regeneration worker. InfinityFree free hosting does not provide
those facilities as currently required; do not claim the full site is production
ready merely because password-reset delivery works.

### Alternative: generic email API

Set these **server process** environment variables; never put the key in JavaScript
or a tracked file:

```text
SBCIS_PUBLIC_BASE_URL=https://your-domain.example
SBCIS_EMAIL_API_URL=https://your-email-provider.example/send
SBCIS_EMAIL_API_KEY=your-secret-api-key
SBCIS_EMAIL_FROM=verified-sender@your-domain.example
```

The PHP backend makes a POST to the configured HTTPS URL with
`Authorization: Bearer <SBCIS_EMAIL_API_KEY>` and `Content-Type: application/json`.
Its JSON body contains `from`, `to`, `subject`, `text`, and `html` string fields.
If your provider expects a different JSON shape or authentication header, adapt
`PasswordResetService::send()`. Ensure the sender address is verified at the provider.
When all three EmailJS IDs are configured, EmailJS takes precedence over this generic API.

The public base URL must be the actual SBCIS root, including any subdirectory,
without a trailing slash. Production requires HTTPS. For local development only,
`http://127.0.0.1:8000` or `http://localhost:8000` is accepted as the base URL;
the email API itself still requires HTTPS. The mailbox recipient must be able to
open that local URL on the same computer.

## 3. Verify

Run `php tools/test-password-reset.php` (isolated temporary tables). Then request
a link for an administrator email, check delivery and the 30-minute expiry,
set a new 12–128 character password, sign in, and confirm the link cannot be
reused. Test with an unknown email; the page must show the same acknowledgement.

If mail does not arrive, inspect the server error log for a delivery/configuration
error and the provider delivery log. The application never prints a reset token in
logs or on the request page. Do not turn on a mail-to-screen fallback in production.

### Windows PHP certificate error

If the PHP terminal says `SSL certificate OpenSSL verify result: unable to get local
issuer certificate (20)`, PHP's cURL installation has no usable CA bundle. On this
development machine, Git for Windows provides a certificate bundle at
`C:\Program Files\Git\mingw64\etc\ssl\cert.pem`. Stop the development server,
then start it in the same PowerShell window where the EmailJS variables are set:

```powershell
php -d 'curl.cainfo=C:\Program Files\Git\mingw64\etc\ssl\cert.pem' -S localhost:8000 -t .
```

For persistent configuration, set `curl.cainfo="C:\Program Files\Git\mingw64\etc\ssl\cert.pem"`
in the loaded `php.ini` reported by `php --ini`, then restart PHP. Install and keep
the CA bundle updated on the production host as appropriate for that host. Do not
disable certificate verification (`CURLOPT_SSL_VERIFYPEER`/`CURLOPT_SSL_VERIFYHOST`).
Check the EmailJS template's To Email field uses `{{to_email}}` and review its
delivery history if the TLS error is resolved but the message still does not arrive.
