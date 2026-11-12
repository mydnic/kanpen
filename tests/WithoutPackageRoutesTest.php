<?php

namespace Mydnic\Kanpen\Test;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Mydnic\Kanpen\Mail\CampaignMail;
use Mydnic\Kanpen\Models\Campaign;
use Mydnic\Kanpen\Models\CampaignDelivery;
use Mydnic\Kanpen\Models\Subscriber;
use Mydnic\Kanpen\Notifications\SubscriberVerifyEmail;
use Mydnic\Kanpen\Services\TrackingUrlService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Sending must keep working when the package HTTP layer is turned off, which
 * means every link the package puts in an email resolves a configurable route
 * name instead of a hardcoded one.
 */
class WithoutPackageRoutesTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $config = $app->get('config');
        $config->set('kanpen.routes.web', false);
        $config->set('kanpen.routes.unsubscribe_name', 'app.unsubscribe');
        $config->set('kanpen.routes.verify_name', 'app.verify');
        $config->set('kanpen.routes.tracking_open_name', 'app.tracking.open');
        $config->set('kanpen.routes.tracking_click_name', 'app.tracking.click');
    }

    protected function setUpRoutes(): void
    {
        parent::setUpRoutes();

        Route::get('news/leave/{token}', fn () => 'bye')->name('app.unsubscribe');
        Route::get('news/verify/{id}/{hash}', fn () => 'verified')->name('app.verify');
        Route::get('news/open/{token}', fn () => 'open')->name('app.tracking.open');
        Route::get('news/click/{token}', fn () => 'click')->name('app.tracking.click');
    }

    private function makeSend(): CampaignDelivery
    {
        $subscriber = Subscriber::create(['email' => 'track@example.com']);

        $campaign = Campaign::create([
            'name' => 'Track Test',
            'subject' => 'Track',
            'content_html' => '<p>Hello <a href="https://example.com/post">read this</a></p>',
            'status' => 'sending',
        ]);

        return CampaignDelivery::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subscriber->id,
            'token' => Str::random(64),
        ]);
    }

    #[Test]
    public function it_does_not_register_the_package_web_routes(): void
    {
        $this->assertFalse(Route::has('kanpen.unsubscribe'));
        $this->assertFalse(Route::has('kanpen.verify'));
        $this->assertFalse(Route::has('kanpen.tracking.open'));
    }

    #[Test]
    public function it_builds_tracking_urls_from_the_configured_route_names(): void
    {
        $send = $this->makeSend();

        $html = app(TrackingUrlService::class)->processHtml(
            '<html><body><a href="https://example.com/post">read this</a></body></html>',
            $send
        );

        $this->assertStringContainsString("/news/open/{$send->token}", $html);
        $this->assertStringContainsString("/news/click/{$send->token}", $html);
    }

    #[Test]
    public function it_builds_the_verification_url_from_the_configured_route_name(): void
    {
        $subscriber = Subscriber::create(['email' => 'verify@example.com']);

        $url = (new SubscriberVerifyEmail)->toMail($subscriber)->actionUrl;

        $this->assertStringContainsString("/news/verify/{$subscriber->id}/", $url);
    }

    #[Test]
    public function it_renders_a_campaign_email(): void
    {
        $send = $this->makeSend();

        $html = (new CampaignMail($send->campaign, $send))->content()->htmlString;

        $this->assertStringContainsString("/news/leave/{$send->subscriber->unsubscribe_token}", $html);
        $this->assertStringContainsString("/news/open/{$send->token}", $html);
        $this->assertStringContainsString("/news/click/{$send->token}", $html);
    }
}
