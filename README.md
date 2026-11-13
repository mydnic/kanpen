# Kanpen

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mydnic/kanpen.svg)](https://packagist.org/packages/mydnic/kanpen)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12%20%7C%2013-red)](https://laravel.com)
[![Filament](https://img.shields.io/badge/Filament-3%20%7C%204%20%7C%205-orange)](https://filamentphp.com)

A lightweight newsletter subscriber management package for Laravel. Handle subscriptions, send campaigns, track opens and clicks — all without a third-party service.

> **Heads-up:** This package is designed for small to medium audiences (think side-projects, indie apps, internal tools). It sends mail through whatever driver is configured in your `config/mail.php` and has **no bounce handling, no complaint webhooks, and no deliverability tooling**. If you're sending to tens of thousands of subscribers or need professional deliverability guarantees, use a dedicated email service provider instead. [Sendboo](https://sendboo.com) is a great option with full campaign management, AI features, and solid deliverability.

---

## Features

- **Subscriber management** — subscribe, unsubscribe, soft-delete, restore
- **Email verification** — optional double opt-in with signed URLs
- **Campaigns** — create and send HTML newsletters to all subscribers via Laravel queues
- **Open & click tracking** — pixel-based open tracking and link proxy click tracking
- **User sync** — automatically sync your `User` model with the subscribers table via a trait or Artisan command
- **Publishable views** — override the email layout and unsubscribe page in your own app
- **Nova integration** — Laravel Nova resource and metrics card included
- **Filament integration** — full Filament plugin with resources, infolists, and widgets
- **Events** — every action fires an event you can listen to

---

## Requirements

- PHP 8.1+
- Laravel >= 10

---

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Subscriber Management](#subscriber-management)
  - [Web Form](#web-form)
  - [API Endpoint](#api-endpoint)
  - [Programmatic Subscription](#programmatic-subscription)
  - [Email Verification](#email-verification)
  - [Unsubscribing](#unsubscribing)
- [Campaigns](#campaigns)
  - [Creating a Campaign](#creating-a-campaign)
  - [Sending a Campaign](#sending-a-campaign)
  - [Scheduling a Campaign](#scheduling-a-campaign)
  - [Custom Blade Views](#custom-blade-views)
  - [Campaign API](#campaign-api)
- [Tracking](#tracking)
  - [How It Works](#how-it-works)
  - [Tracking Events](#tracking-events)
- [User Sync](#user-sync)
  - [HasNewsletterSubscription Trait](#hasnewslettersubscription-trait)
  - [Artisan Sync Command](#artisan-sync-command)
- [Events Reference](#events-reference)
- [Publishing Assets](#publishing-assets)
- [Nova Integration](#nova-integration)
- [Filament Integration](#filament-integration)
- [Upgrading](#upgrading)

---

## Installation

Install via Composer:

```bash
composer require mydnic/kanpen
```

The service provider is auto-discovered. Next, publish and run the migrations:

```bash
php artisan vendor:publish --tag="kanpen-migrations"
php artisan migrate
```

This creates three tables: `subscribers`, `campaigns`, and `campaign_deliveries`.

---

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag="kanpen-config"
```

This creates `config/kanpen.php`:

```php
return [
    // Enable email verification (double opt-in)
    'verify' => env('KANPEN_VERIFY', false),

    // Named route to redirect to after web form submission
    'redirect_url' => 'home',

    // Rate limiting on the two public subscribe endpoints
    'throttle' => [
        'enabled'   => true,
        'per_ip'    => ['attempts' => 10, 'minutes' => 1],
        'per_email' => ['attempts' => 3, 'minutes' => 60],
    ],

    // Route registration
    'routes' => [
        'web'                 => true,  // POST subscribe, unsubscribe, verify, tracking
        'api'                 => true,  // POST /kanpen-api/subscriber
        // Routes resolved when building the links that go out in emails
        'unsubscribe_name'    => 'kanpen.unsubscribe',
        'verify_name'         => 'kanpen.verify',
        'tracking_open_name'  => 'kanpen.tracking.open',
        'tracking_click_name' => 'kanpen.tracking.click',
    ],

    // Verification email content
    'mail' => [
        'verify' => [
            'expiration' => 60, // minutes
            'subject'    => 'Verify Email Address',
            'greeting'   => 'Hello!',
            'content'    => ['Please click the button below to verify your email address.'],
            'action'     => 'Verify Email Address',
            'footer'     => ['If you did not sign up for our newsletter, no further action is required.'],
        ],
    ],

    // Campaign sending
    'campaigns' => [
        'enabled'    => true,
        'middleware' => ['api'],           // middleware for campaign management routes
        'from' => [
            'name'  => env('MAIL_FROM_NAME', 'Newsletter'),
            'email' => env('MAIL_FROM_ADDRESS', 'newsletter@example.com'),
        ],
        'queue'    => env('KANPEN_QUEUE', 'default'),
        'schedule' => true, // auto-register the dispatch command on the scheduler
    ],

    // Open and click tracking
    'tracking' => [
        'enabled'         => true,
        'open'            => true,
        'click'           => true,
        'allowed_domains' => [], // empty = allow all; ['example.com'] = allowlist
    ],
];
```

---

## Subscriber Management

### Web Form

Add a form anywhere in your Blade views:

```blade
<form action="{{ route('kanpen.store') }}" method="POST">
    @csrf
    <input type="email" name="email" placeholder="Your email address" required>
    <button type="submit">Subscribe</button>
</form>

@if (session('subscribed'))
    <div class="alert alert-success">
        {{ session('subscribed') }}
    </div>
@endif
```

On success the user is redirected to the route defined in `redirect_url` with a `subscribed` session flash message.

### API Endpoint

A JSON endpoint is also available:

```
POST /kanpen-api/subscriber
Content-Type: application/json

{ "email": "someone@example.com" }
```

Response `201 Created`:
```json
{ "created": true }
```

Duplicate emails return a `422 Unprocessable Entity` with a validation error.

### Throttling

Both subscribe endpoints are public and send mail, so they are rate limited out of the box by a named limiter, `kanpen-subscribe`. Two caps apply at once:

| Cap | Default | What it stops |
|-----|---------|---------------|
| per IP | 10 per minute | one client hammering the endpoint |
| per email address | 3 per hour | a pool of IPs burying a single mailbox |

Over the limit, the endpoints return `429 Too Many Requests`. Adjust the numbers in the `throttle` config key, or set `throttle.enabled` to `false` if you prefer to apply your own middleware.

Email addresses are lowercased and trimmed before they are counted, and only their hash is kept in the limiter cache. A request that carries no address, or something other than a string, is capped per IP only: it never reaches a mailbox, so it stops at validation with a `422` instead of being counted against a shared bucket.

### Programmatic Subscription

Add the `HasNewsletterSubscription` trait to any Eloquent model that has an `email` attribute:

```php
use Mydnic\Kanpen\Traits\HasNewsletterSubscription;

class User extends Authenticatable
{
    use HasNewsletterSubscription;
}
```

Then call the trait methods:

```php
$user->subscribe();       // adds the user's email to subscribers
$user->unsubscribe();     // soft-deletes the subscriber record
$user->isSubscribed();    // returns bool
```

If `verify` is enabled in config, `subscribe()` automatically sends the verification email.

### Email Verification

Set `KANPEN_VERIFY=true` in your `.env` (or set `'verify' => true` in config) to enable double opt-in. When enabled:

- Subscribers are saved immediately but are **not considered active** until they click the verification link.
- A verification email is sent automatically on `subscribe()` or web form submission.
- Only verified subscribers (`email_verified_at` is not null) receive campaigns.

You can customise every line of the verification email in the `mail.verify` config key.

The verification route is `GET /kanpen/verify/{id}/{hash}` — this is handled automatically.

The link is a temporary signed URL and the route carries Laravel's `signed` middleware, so the signature is checked on arrival. A link that was not generated by the verification email is rejected with a `403`, and so is one past `verification_expiration`. The `{id}` and `{hash}` segments are not a credential on their own: anyone who knows an address can compute them, which is why the signature is what actually proves the click came from the subscriber's mailbox.

### Unsubscribing

Every subscriber gets a unique random `unsubscribe_token` generated automatically on creation. Use it to build a safe unsubscribe link — the subscriber's email address is never exposed in the URL:

```blade
<a href="{{ $subscriber->getUnsubscribeUrl() }}">Unsubscribe</a>
```

This generates a URL like `/kanpen/unsubscribe/Xk9mP...` (64-char opaque token).

Unsubscribing takes two steps:

| Method | Route | What it does |
|--------|-------|--------------|
| `GET` | `/kanpen/unsubscribe/{token}` | shows a confirmation page with a single button |
| `DELETE` | `/kanpen/unsubscribe/{token}` | soft-deletes the subscriber and shows the "unsubscribed" page |

The `GET` never deletes anything. Mail clients and corporate link scanners (Outlook Safe Links, antivirus proxies) fetch every link in a message before the recipient sees it, so a one-click `GET` unsubscribe silently removes people who never clicked. Both pages are publishable and customisable — see [Publishing Assets](#publishing-assets).

Neither route reveals whether the token exists, so the URL cannot be used to probe for addresses.

The token is also injected automatically into all campaign emails via the default `base.blade.php` layout, so you don't need to add it manually to campaigns.

> **Note:** For subscribers created before this version (without a token), `getUnsubscribeUrl()` generates and persists a token on the fly. The backfill migration handles bulk assignment for existing rows.

### Using the data layer without the package routes

If you'd rather register your own routes, turn the package ones off and point each link at yours:

```php
// config/kanpen.php
'routes' => [
    'web'                 => false,
    'api'                 => false,
    'unsubscribe_name'    => 'app.unsubscribe',
    'verify_name'         => 'app.verify',
    'tracking_open_name'  => 'app.tracking.open',
    'tracking_click_name' => 'app.tracking.click',
],
```

The models, campaigns, jobs, commands and events keep working. The HTTP layer is skipped, but the links the package puts in an email are not: every one of them resolves a named route, so turning `web` off means you have to say which route takes its place.

| Config key | Used by | Expected parameters |
| --- | --- | --- |
| `unsubscribe_name` | `getUnsubscribeUrl()` and the campaign footer | `{token}` |
| `<unsubscribe_name>.destroy` | the confirm form on the unsubscribe page | `{token}`, `DELETE` |
| `verify_name` | the verification email, as a signed URL | `{id}`, `{hash}` |
| `tracking_open_name` | the open pixel | `{token}` |
| `tracking_click_name` | the click proxy | `{token}`, `?url=` |

You only need the routes for the features you run: `verify_name` is read when `verify` is on, and the two tracking names when `tracking.enabled` is on. Leave a name pointing at a route that does not exist with its feature enabled and the send throws `RouteNotFoundException`.

Pointing a name at your own route is enough, and the package controllers are not tied to the package routes:

```php
// routes/web.php
use Mydnic\Kanpen\Http\Controllers\TrackingController;
use Mydnic\Kanpen\Http\Controllers\UnsubscribeController;

Route::get('newsletter/leave/{token}', [UnsubscribeController::class, 'show'])->name('app.unsubscribe');
Route::delete('newsletter/leave/{token}', [UnsubscribeController::class, 'destroy'])->name('app.unsubscribe.destroy');
Route::get('newsletter/open/{token}', [TrackingController::class, 'open'])->name('app.tracking.open');
```

---

## Campaigns

### Creating a Campaign

Use the `Campaign` model directly:

```php
use Mydnic\Kanpen\Models\Campaign;

$campaign = Campaign::create([
    'name'         => 'March Newsletter',
    'subject'      => 'What\'s new this month',
    'from_name'    => 'Acme Newsletter',     // optional, falls back to config
    'from_email'   => 'news@acme.com',       // optional, falls back to config
    'reply_to'     => 'support@acme.com',    // optional
    'content_html' => '<h1>Hello!</h1><p>Here is what\'s new...</p>',
]);
```

Campaigns are created in `draft` status and are not sent until you explicitly trigger a send.

### Sending a Campaign

Inject or resolve the `SendCampaignAction` and call `execute()`:

```php
use Mydnic\Kanpen\Actions\SendCampaignAction;
use Mydnic\Kanpen\Models\Campaign;

$campaign = Campaign::find(1);

app(SendCampaignAction::class)->execute($campaign);
```

This dispatches a queued job that:

1. Sets the campaign status to `sending`
2. Chunks through all (verified) subscribers
3. Creates a `CampaignDelivery` record with a unique tracking token per subscriber
4. Dispatches an individual queued job per subscriber that sends the email
5. Sets the status to `sent` once all jobs are dispatched

Make sure you have a queue worker running:

```bash
php artisan queue:work
```

### Scheduling a Campaign

Set `scheduled_at` on a campaign to send it at a specific time in the future:

```php
use Mydnic\Kanpen\Models\Campaign;

$campaign = Campaign::create([
    'name'         => 'March Newsletter',
    'subject'      => 'What\'s new this month',
    'content_html' => '<p>...</p>',
    'scheduled_at' => now()->addDays(3), // send in 3 days
]);
```

The campaign stays in `draft` status and is sent automatically when its `scheduled_at` time passes.

#### How It Works

The package registers the `kanpen:dispatch-scheduled` Artisan command on your application scheduler and runs it every minute:

```
kanpen:dispatch-scheduled
```

This command queries for all `draft` campaigns whose `scheduled_at` is in the past and calls `SendCampaignAction::execute()` on each of them. Internally this dispatches `SendCampaignJob` to your configured queue — the same path as an immediate send.

> **Important:** Your scheduler must be running. Add this to your server's cron if you haven't already:
> ```cron
> * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
> ```

#### Opting Out of Auto-Registration

If you prefer to schedule the command yourself (e.g. less frequently, or with a specific environment condition), set `schedule` to `false` in config and add the command manually in your `Console/Kernel.php` (Laravel 10) or `bootstrap/app.php` (Laravel 11+):

```php
// config/kanpen.php
'campaigns' => [
    'schedule' => false,
],
```

```php
// bootstrap/app.php (Laravel 11+)
->withSchedule(function (Schedule $schedule) {
    $schedule->command('kanpen:dispatch-scheduled')->everyFiveMinutes();
})
```

---

### Custom Blade Views

By default campaigns are rendered using the package's built-in email layout. You can point a campaign to any Blade view in your application:

```php
$campaign = Campaign::create([
    'name'    => 'Special Announcement',
    'subject' => 'Big news!',
    'view'    => 'emails.special-announcement', // your own Blade view
]);
```

Your view receives these variables:

| Variable      | Type           | Description                          |
|---------------|----------------|--------------------------------------|
| `$campaign`   | `Campaign`     | The campaign model                   |
| `$send`       | `CampaignDelivery` | The per-subscriber send record       |
| `$subscriber` | `Subscriber`   | The subscriber receiving this email  |

Example view:

```blade
{{-- resources/views/emails/special-announcement.blade.php --}}
<!DOCTYPE html>
<html>
<body>
    <h1>{{ $campaign->subject }}</h1>
    <p>Hi {{ $subscriber->email }},</p>
    <p>We have big news for you!</p>
    <a href="{{ $subscriber->getUnsubscribeUrl() }}">Unsubscribe</a>
</body>
</html>
```

Tracking (open pixel and link rewriting) is applied automatically to the rendered HTML regardless of which view is used.

### Campaign API

A full REST API for managing campaigns is available under `/kanpen-api/campaigns`. The middleware protecting these routes defaults to `['api']` and is configurable via `campaigns.middleware` in config.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/kanpen-api/campaigns` | List all campaigns (paginated) |
| `POST` | `/kanpen-api/campaigns` | Create a new campaign |
| `GET` | `/kanpen-api/campaigns/{id}` | Get campaign details + stats |
| `PUT` | `/kanpen-api/campaigns/{id}` | Update a draft campaign |
| `DELETE` | `/kanpen-api/campaigns/{id}` | Soft-delete a campaign |
| `POST` | `/kanpen-api/campaigns/{id}/send` | Dispatch the send job |
| `POST` | `/kanpen-api/campaigns/{id}/test` | Send a test copy to one address |

**Create a campaign:**

```bash
curl -X POST /kanpen-api/campaigns \
  -H "Content-Type: application/json" \
  -d '{
    "name": "April Newsletter",
    "subject": "Hello from April",
    "content_html": "<p>This month...</p>"
  }'
```

**Get campaign stats:**

```json
{
  "campaign": { "id": 1, "name": "April Newsletter", "status": "sent" },
  "stats": {
    "sent": 1200,
    "opened": 340,
    "clicked": 85,
    "open_rate": 28.33,
    "click_rate": 7.08
  }
}
```

**Send a test email:**

```bash
curl -X POST /kanpen-api/campaigns/1/test \
  -H "Content-Type: application/json" \
  -d '{ "email": "you@example.com" }'
```

The email is sent immediately (not queued) as an exact copy of what subscribers would receive — same subject, same content, same tracking links (though the token won't exist in the database so opens/clicks won't be recorded). Does not create any `CampaignDelivery` records or alter the campaign's status or counts. Works for campaigns in any status — useful for previewing already-sent campaigns too.

To add authentication to campaign routes, update the middleware in config:

```php
'campaigns' => [
    'middleware' => ['api', 'auth:sanctum'],
],
```

---

## Tracking

### How It Works

When a campaign is sent, the package automatically processes every email's HTML before delivery:

1. **Open tracking** — a 1×1 transparent GIF pixel is injected just before `</body>`:
   ```html
   <img src="https://yourapp.com/kanpen/tracking/open/{token}" width="1" height="1" style="display:none;" />
   ```
   When a mail client loads the pixel, `opened_at` is set and `open_count` is incremented on the `CampaignDelivery` record.

2. **Click tracking** — every `<a href="...">` in the email is rewritten to go through a redirect proxy:
   ```
   /kanpen/tracking/click/{token}?url=<base64-encoded-original-url>
   ```
   When a subscriber clicks, the original URL is decoded, `clicked_at` is set, and the click is appended to `click_log` before the redirect.

Both tracking routes are public and do not require authentication. `mailto:` and `#anchor` links are left untouched.

### Disabling Tracking

You can disable tracking globally or individually:

```php
// config/kanpen.php
'tracking' => [
    'enabled' => false, // disables all tracking
    'open'    => false, // disables open pixel only
    'click'   => false, // disables click rewriting only
],
```

### Restricting Click Tracking to Specific Domains

To prevent the click proxy from redirecting to arbitrary domains, set an allowlist:

```php
'tracking' => [
    'allowed_domains' => ['mysite.com', 'blog.mysite.com'],
],
```

Clicks to domains not on the list return a `403` response.

### Tracking Events

Listen to tracking events in your `EventServiceProvider`:

```php
use Mydnic\Kanpen\Events\EmailOpened;
use Mydnic\Kanpen\Events\EmailLinkClicked;

protected $listen = [
    EmailOpened::class => [
        UpdateAnalyticsDashboardListener::class,
    ],
    EmailLinkClicked::class => [
        LogClickListener::class,
    ],
];
```

Both events carry the `CampaignDelivery` model (which has the `campaign`, `subscriber`, and all tracking timestamps).

---

## User Sync

You can automatically keep your application's users in sync with the subscribers table.

### HasNewsletterSubscription Trait

Add the `HasNewsletterSubscription` trait to your `User` model and implement the two required methods.

```php
use Mydnic\Kanpen\Traits\HasNewsletterSubscription;

class User extends Authenticatable
{
    use HasNewsletterSubscription;

    /**
     * Define when this user should be a subscriber.
     * Any logic works — check a column, a role, a plan, a combination.
     */
    public function shouldBeSubscribed(): bool
    {
        return $this->subscribed_to_newsletter;
    }

    /**
     * Called automatically when the subscriber is removed from outside your app
     * (e.g. the user clicks the unsubscribe link in an email).
     * Use this to keep your own model in sync so the user isn't re-subscribed next time they save.
     */
    public function onUnsubscribed(): void
    {
        $this->updateQuietly(['subscribed_to_newsletter' => false]);
    }
}
```

**How it works:**

- When the model is saved and `shouldBeSubscribed()` returns `true` → the email is added to the subscribers table.
- If the model has `email_verified_at` set → it is copied to the subscriber record.
- When `shouldBeSubscribed()` returns `false` → the subscriber record is soft-deleted.
- When the user is hard-deleted → the subscriber record is force-deleted.
- If a previously unsubscribed user re-subscribes → the soft-deleted record is restored (no duplicate).
- When a subscriber is removed externally (unsubscribe link in email) → `onUnsubscribed()` is called on your model so your own data stays in sync.

> **Important:** Use `updateQuietly()` inside `onUnsubscribed()` to avoid triggering the `saved` event and causing a sync loop.

**Manual sync trigger:**

You can also call `syncSubscriberRecord()` directly:

```php
$user->syncSubscriberRecord();
```

### Artisan Sync Command

To sync your existing users in bulk (e.g. after adding the trait to an app that already has users), use the `kanpen:sync` command:

```bash
php artisan kanpen:sync "App\Models\User"
```

This calls `syncSubscriberRecord()` on every record, which in turn calls your `shouldBeSubscribed()` implementation. Records that return `true` are subscribed (or restored if previously unsubscribed), records that return `false` are soft-deleted from the subscribers table.

---

## Events Reference

All events live in the `Mydnic\Kanpen\Events` namespace.

| Event | Fired When | Properties |
|-------|-----------|------------|
| `SubscriberCreated` | A new subscriber is saved | `$subscriber` |
| `SubscriberDeleted` | A subscriber is deleted | `$subscriber` |
| `SubscriberVerified` | A subscriber verifies their email | `$subscriber` |
| `CampaignDeliverying` | A campaign's send job starts | `$campaign` |
| `CampaignSent` | All subscriber jobs are dispatched | `$campaign` |
| `EmailOpened` | A tracking pixel is loaded | `$send` |
| `EmailLinkClicked` | A tracked link is clicked | `$send`, `$url` |

---

## Publishing Assets

### Views

Publish and customise the email layout and unsubscribe page:

```bash
php artisan vendor:publish --tag="kanpen-views"
```

Files are copied to `resources/views/vendor/kanpen/`:

```
resources/views/vendor/kanpen/
├── mail/
│   └── campaign.blade.php     ← email template (full-doc passthrough or default HTML wrapper)
└── subscriber/
    ├── unsubscribe.blade.php  ← unsubscribe confirmation page (the button that sends the DELETE)
    └── deleted.blade.php      ← "you have been unsubscribed" page
```

If you override `unsubscribe.blade.php`, keep the form posting to `route('kanpen.unsubscribe.destroy', ['token' => $token])` with `@csrf` and `@method('DELETE')` — that post is what actually unsubscribes.

Laravel's view resolution picks up your published files automatically — no config change needed.

## Filament Integration

A first-party Filament plugin is available at **[mydnic/kanpen-filament-plugin](https://github.com/mydnic/kanpen-filament-plugin)**.

It adds a full admin UI on top of Kanpen:

- **Subscribers** — list, search, soft delete, restore, and view a subscriber's full campaign history with open and click stats per campaign
- **Campaigns** — create, edit, send, schedule, and preview campaigns; per-campaign stats (sent, opens, clicks); send-test action
- **Email Templates** — reusable templates with a drag-and-drop builder ([Unlayer](https://unlayer.com)) or a plain Markdown editor; load a template into any campaign with one click
- **Email builder** — Unlayer drag-and-drop editor embedded in the Filament form; import any design JSON from [unlayer.com/templates](https://unlayer.com/templates)
- **Markdown editor** — write emails in Markdown and have them automatically wrapped in your app's default Laravel mail theme

```bash
composer require mydnic/kanpen-filament-plugin
```

See the [plugin README](https://github.com/mydnic/kanpen-filament-plugin) for full installation and usage instructions.

---

## Upgrading

### From v1.x to v2.x

v2 is a breaking release. The following changes require attention:

**PHP and Laravel requirements**

- PHP 7.x is no longer supported. Requires **PHP 8.1+**.
- Laravel 8 and 9 are no longer supported. Requires **Laravel 10+**.

**Model namespace**

The `Subscriber` model has moved from `Mydnic\Subscribers\Subscriber` to `Mydnic\Kanpen\Models\Subscriber`.

The old class still exists as a deprecated alias, so existing code continues to work, but you should update your imports:

```php
// Before
use Mydnic\Subscribers\Subscriber;

// After
use Mydnic\Kanpen\Models\Subscriber;
```

**Migrations**

Publish and run the new migrations to create the `campaigns` and `campaign_deliveries` tables:

```bash
php artisan vendor:publish --tag="kanpen-migrations"
php artisan migrate
```

**Events**

The three subscriber events (`SubscriberCreated`, `SubscriberDeleted`, `SubscriberVerified`) no longer implement `ShouldBroadcast`. If you were broadcasting these events, re-implement broadcasting in your own listeners.

---

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
