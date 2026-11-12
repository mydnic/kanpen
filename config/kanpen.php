<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Table Names
     |--------------------------------------------------------------------------
     | Customize these if the default names conflict with your existing tables.
     */
    'tables' => [
        'subscribers' => 'kanpen_subscribers',
        'campaigns' => 'kanpen_campaigns',
        'campaign_deliveries' => 'kanpen_campaign_deliveries',
        'campaign_clicks' => 'kanpen_campaign_clicks',
    ],

    /*
     |--------------------------------------------------------------------------
     | Email Verification
     |--------------------------------------------------------------------------
     | When enabled, subscribers must verify their email before being considered active.
     */
    'verify' => env('KANPEN_VERIFY', false),

    /*
     |--------------------------------------------------------------------------
     | Verification Email Expiration
     |--------------------------------------------------------------------------
     | How long (in minutes) the email verification link remains valid.
     | To customize the email content, publish and override the notification:
     | php artisan vendor:publish --tag=kanpen-notifications
     */
    'verification_expiration' => 60,

    /*
     |--------------------------------------------------------------------------
     | Redirect URL
     |--------------------------------------------------------------------------
     | Named route to redirect to after subscription (web form only).
     */
    'redirect_url' => 'home',

    /*
     |--------------------------------------------------------------------------
     | Subscription Throttling
     |--------------------------------------------------------------------------
     | The subscribe endpoints are public and send mail, so they are rate
     | limited by default. The IP cap stops a single client hammering the
     | endpoint; the email cap stops a pool of IPs burying one mailbox.
     | Set 'enabled' to false only if you apply your own throttle middleware.
     */
    'throttle' => [
        'enabled' => true,

        'per_ip' => ['attempts' => 10, 'minutes' => 1],
        'per_email' => ['attempts' => 3, 'minutes' => 60],
    ],

    /*
     |--------------------------------------------------------------------------
     | Routes
     |--------------------------------------------------------------------------
     | Turn 'web' or 'api' off to use the models, campaigns and commands
     | without the package HTTP layer, and register your own routes instead.
     | The names below are the routes the package resolves when it builds the
     | links that go out in emails. They all live in the 'web' group, so when
     | you turn it off you either point them at your own routes or turn the
     | matching feature off ('verify' above, 'tracking' below). The confirm
     | form on the unsubscribe page posts to '<unsubscribe_name>.destroy'.
     */
    'routes' => [
        'web' => true,
        'api' => true,

        'unsubscribe_name' => 'kanpen.unsubscribe',
        'verify_name' => 'kanpen.verify',
        'tracking_open_name' => 'kanpen.tracking.open',
        'tracking_click_name' => 'kanpen.tracking.click',
    ],

    /*
     |--------------------------------------------------------------------------
     | Campaigns
     |--------------------------------------------------------------------------
     | Configuration for the newsletter campaign feature.
     */
    'campaigns' => [
        'enabled' => true,

        // Middleware applied to campaign management API routes
        'middleware' => ['api'],

        // Default sender for campaigns (falls back to config('mail.from.*'))
        'from' => [
            'name' => env('MAIL_FROM_NAME', 'Newsletter'),
            'email' => env('MAIL_FROM_ADDRESS', 'newsletter@example.com'),
        ],

        // Queue name for campaign send jobs
        'queue' => env('KANPEN_QUEUE', 'default'),

        // Automatically register the `kanpen:dispatch-scheduled` command on the scheduler
        // running every minute. Set to false if you prefer to schedule it yourself.
        'schedule' => true,
    ],

    /*
     |--------------------------------------------------------------------------
     | Tracking
     |--------------------------------------------------------------------------
     | Open tracking (pixel) and click tracking (link proxy).
     */
    'tracking' => [
        'enabled' => true,
        'open' => true,
        'click' => true,

        // Allowlist of domains for click tracking. Empty = allow all.
        // Example: ['mysite.com', 'blog.mysite.com']
        'allowed_domains' => [],
    ],
];
