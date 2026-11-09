<?php

namespace Mydnic\Kanpen\Test;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Mydnic\Kanpen\Events\SubscriberCreated;
use Mydnic\Kanpen\Events\SubscriberDeleted;
use Mydnic\Kanpen\Events\SubscriberVerified;
use Mydnic\Kanpen\Models\Subscriber;
use PHPUnit\Framework\Attributes\Test;

class SubscriberTest extends TestCase
{
    #[Test]
    public function it_saves_the_subscriber_via_api(): void
    {
        Event::fake([SubscriberCreated::class]);

        $response = $this->post('/kanpen-api/subscriber', [
            'email' => 'some@email.com',
        ]);

        $response->assertStatus(201);

        $subscriber = Subscriber::first();
        $this->assertEquals('some@email.com', $subscriber->email);

        Event::assertDispatched(SubscriberCreated::class, fn ($e) => $e->subscriber->id === $subscriber->id);
    }

    #[Test]
    public function it_saves_the_subscriber_via_web(): void
    {
        Event::fake([SubscriberCreated::class]);

        $response = $this->post('/kanpen/subscriber', [
            'email' => 'someweb@email.com',
        ]);

        $response->assertStatus(302);

        $subscriber = Subscriber::first();
        $this->assertEquals('someweb@email.com', $subscriber->email);

        Event::assertDispatched(SubscriberCreated::class, fn ($e) => $e->subscriber->id === $subscriber->id);
    }

    #[Test]
    public function it_generates_an_unsubscribe_token_on_creation(): void
    {
        $subscriber = Subscriber::create(['email' => 'some@email.com']);

        $this->assertNotEmpty($subscriber->unsubscribe_token);
        $this->assertEquals(64, strlen($subscriber->unsubscribe_token));
    }

    #[Test]
    public function it_refuses_existing_subscribers(): void
    {
        Subscriber::create(['email' => 'some@email.com']);

        $this->post('/kanpen-api/subscriber', ['email' => 'some@email.com']);

        $this->assertEquals(1, Subscriber::count());
    }

    #[Test]
    public function it_rejects_an_email_with_a_non_existent_domain(): void
    {
        // .invalid is reserved by RFC 2606 and is guaranteed to never resolve
        $response = $this->postJson('/kanpen-api/subscriber', [
            'email' => 'someone@this-domain-does-not-exist.invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $this->assertEquals(0, Subscriber::count());
    }

    #[Test]
    public function it_swallows_notification_failures_when_sending_verification_email(): void
    {
        $subscriber = new class extends Subscriber
        {
            public function notify($instance): void
            {
                throw new \RuntimeException('Cloudflare rejected the address');
            }
        };
        $subscriber->forceFill(['email' => 'some@email.com']);

        $subscriber->sendEmailVerificationNotification();

        $this->assertTrue(true);
    }

    #[Test]
    public function it_only_shows_a_confirmation_page_on_a_get_unsubscribe(): void
    {
        // Link scanners (Outlook Safe Links, antivirus proxies) follow every link
        // in a message, so a GET must never delete anything.
        Event::fake([SubscriberDeleted::class]);

        $subscriber = Subscriber::create(['email' => 'some@email.com']);

        $response = $this->get("/kanpen/unsubscribe/{$subscriber->unsubscribe_token}");

        $response->assertStatus(200);
        $this->assertEquals(1, Subscriber::count());

        Event::assertNotDispatched(SubscriberDeleted::class);
    }

    #[Test]
    public function it_unsubscribes_via_token_on_a_confirmed_delete(): void
    {
        Event::fake([SubscriberDeleted::class]);

        $subscriber = Subscriber::create(['email' => 'some@email.com']);

        $response = $this->delete("/kanpen/unsubscribe/{$subscriber->unsubscribe_token}");

        $response->assertStatus(200);
        $this->assertEquals(0, Subscriber::count());

        Event::assertDispatched(SubscriberDeleted::class);
    }

    #[Test]
    public function it_shows_the_confirmation_page_for_an_unknown_token(): void
    {
        // Unknown token silently returns 200 — no email enumeration possible
        $response = $this->get('/kanpen/unsubscribe/totally-fake-token');

        $response->assertStatus(200);
    }

    #[Test]
    public function it_shows_unsubscribed_page_for_unknown_token_on_delete(): void
    {
        $response = $this->delete('/kanpen/unsubscribe/totally-fake-token');

        $response->assertStatus(200);
    }

    #[Test]
    public function it_verifies_a_subscriber_through_a_signed_url(): void
    {
        Event::fake([SubscriberVerified::class]);

        $subscriber = Subscriber::create(['email' => 'some@email.com']);

        $response = $this->get($this->verificationUrl($subscriber));

        $response->assertRedirect();
        $this->assertTrue($subscriber->fresh()->hasVerifiedEmail());

        Event::assertDispatched(SubscriberVerified::class);
    }

    #[Test]
    public function it_refuses_a_verification_url_that_is_not_signed(): void
    {
        $subscriber = Subscriber::create(['email' => 'some@email.com']);
        $hash = sha1($subscriber->getEmailForVerification());

        // Anyone knowing the address can compute id and sha1(email); only the
        // signature proves the link came out of the verification email.
        $response = $this->get("/kanpen/verify/{$subscriber->id}/{$hash}");

        $response->assertStatus(403);
        $this->assertFalse($subscriber->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function it_refuses_a_verification_url_whose_signature_was_tampered_with(): void
    {
        $subscriber = Subscriber::create(['email' => 'some@email.com']);
        $other = Subscriber::create(['email' => 'other@email.com']);

        $url = $this->verificationUrl($subscriber);
        $tampered = str_replace("/verify/{$subscriber->id}/", "/verify/{$other->id}/", $url);

        $response = $this->get($tampered);

        $response->assertStatus(403);
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function it_refuses_an_expired_verification_url(): void
    {
        $subscriber = Subscriber::create(['email' => 'some@email.com']);

        $url = $this->verificationUrl($subscriber);

        $this->travel(config('kanpen.verification_expiration') + 1)->minutes();

        $response = $this->get($url);

        $response->assertStatus(403);
        $this->assertFalse($subscriber->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function it_returns_404_for_an_invalid_verification_hash(): void
    {
        $subscriber = Subscriber::create(['email' => 'some@email.com']);

        $response = $this->get(URL::temporarySignedRoute('kanpen.verify', now()->addHour(), [
            'id' => $subscriber->id,
            'hash' => 'not-the-real-hash',
        ]));

        $response->assertStatus(404);
        $this->assertFalse($subscriber->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function it_returns_404_for_an_unknown_subscriber_id(): void
    {
        $response = $this->get(URL::temporarySignedRoute('kanpen.verify', now()->addHour(), [
            'id' => 99999,
            'hash' => sha1('ghost@email.com'),
        ]));

        $response->assertStatus(404);
    }

    #[Test]
    public function it_builds_the_unsubscribe_url_from_the_configured_route_name(): void
    {
        Route::get('newsletter/leave/{token}', fn () => 'bye')->name('app.unsubscribe');
        config()->set('kanpen.routes.unsubscribe_name', 'app.unsubscribe');

        $subscriber = Subscriber::create(['email' => 'some@email.com']);

        $this->assertStringContainsString(
            "/newsletter/leave/{$subscriber->unsubscribe_token}",
            $subscriber->getUnsubscribeUrl()
        );
    }

    protected function verificationUrl(Subscriber $subscriber): string
    {
        return URL::temporarySignedRoute(
            'kanpen.verify',
            now()->addMinutes(config('kanpen.verification_expiration')),
            [
                'id' => $subscriber->getKey(),
                'hash' => sha1($subscriber->getEmailForVerification()),
            ]
        );
    }
}
