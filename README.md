# winalco/laravel-sms

Send SMS from your Laravel application through the Winalco relay: transactional
sending, database tracking, signed status webhook.

## 1. Create your account and API key

The code in this package is free (MIT), but sending goes through the Winalco
platform: you need an account and an API key.

> ### 👉 [Create an account / open the console](https://sms-relay.winalco.dz/app/)
> Free plan, an email address is all it takes, no credit card.
> Service overview and pricing: <https://sms-relay.winalco.dz>

Once logged in, go to **console → API Keys** and create a key. You get a
`wak_...` token that is **shown only once** — copy it right away.

The **status webhook** field on that same page is used in step 4 — leave it empty
for now, until your application is deployed.

## 2. Install the package

```bash
composer require winalco/laravel-sms
php artisan migrate   # creates the sms_messages table (no-op if it already exists)
```

In your `.env`:

```
WINALCO_SMS_KEY=wak_...              # the key from step 1
WINALCO_SMS_URL=https://sms-relay.winalco.dz
WINALCO_SMS_WEBHOOK_SECRET=          # filled in at step 4
```

To tweak the configuration: `php artisan vendor:publish --tag=winalco-sms-config`.

## 3. Send an SMS

```php
use Winalco\Sms\Models\SmsMessage;

// Single entry point: validates the Algerian number (05/06/07, +213, 00213),
// records the row, sends through the queue. Returns null (logged) if the number
// is invalid or on internal failure — never breaks the caller.
SmsMessage::queue(
    to: $user->phone,
    message: __('Winalco: payment confirmed. Ref :ref.', ['ref' => $ref]),
    notable: $order,                          // optional morph
    context: 'payment_confirmation',          // free-form, shown in your UIs
    idempotencyKey: 'order-paid-'.$order->id, // prevents double sends on the relay
);
```

Sends go through the `Winalco\Sms\Jobs\SendSms` job, so a queue worker must be
running. On shared hosting, a one-minute cron is enough:

```
* * * * * cd /path/to/app && php artisan queue:work --stop-when-empty --max-time=50
```

Need something lower level? `Winalco\Sms\WinalcoSms` exposes `send()`,
`sendBulk()` (max 500 numbers), `usage()` (quotas) and `status($id)`.

**Message length:** stay ≤ 160 characters and avoid `ê â î ô û ç` (silently
switches to UCS-2, i.e. 70 characters per segment).

## 4. Receive delivery statuses (webhook)

The package already exposes the `POST /api/webhooks/winalco-sms` route:
HMAC-SHA256 signature verified, duplicates ignored, final statuses (`sent`,
`failed`, `canceled`) written to the `sms_messages` row. Nothing to code.

Do it in this order, otherwise the relay will push statuses into the void:

1. Deploy the code and run `php artisan migrate` in production.
2. In [console → API Keys](https://sms-relay.winalco.dz/app/), set the **status
   webhook** to `https://your-domain.tld/api/webhooks/winalco-sms` (HTTPS and
   publicly reachable — no `localhost`).
3. Copy the `whs_...` secret, **shown only once**, into
   `WINALCO_SMS_WEBHOOK_SECRET`.
4. Clear the cache: `php artisan config:clear`.

As long as `WINALCO_SMS_WEBHOOK_SECRET` is empty, every incoming request is
rejected with a 403 — that is intentional. If you disable then re-enable the
webhook in the console, a **new** secret is issued: update your `.env`.

Note: the `sent` status means "handed to the mobile network", not "received by
the recipient".

## 5. Data retention

Every `sms_messages` row keeps the recipient's number and the message body —
often an OTP or a payment reference. Nothing is deleted by default. To purge,
set a duration and schedule `model:prune`:

```
WINALCO_SMS_PRUNE_AFTER_DAYS=90
```

```php
// routes/console.php (or app/Console/Kernel.php)
Schedule::command('model:prune', ['--model' => [\Winalco\Sms\Models\SmsMessage::class]])->daily();
```

Phone numbers never appear in clear text in the package logs (only the last
3 digits are kept).

## Compatibility

Laravel 10, 11, 12 and 13 · PHP 8.1+.

## Help

- HTTP API details: [`docs/winalco-sms-api-reference.md`](docs/winalco-sms-api-reference.md)
  or <https://sms-relay.winalco.dz/api.html>
- Account, quotas, billing: <https://sms-relay.winalco.dz/app/>
- Support: contact@winalco.dz

Code under the [MIT license](LICENSE). The sending service itself (relay, SIM
cards, quotas) is provided by the Winalco platform and requires an account.
