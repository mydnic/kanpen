<?php

namespace Mydnic\Kanpen\Test;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Mydnic\Kanpen\Models\Subscriber;
use Mydnic\Kanpen\Notifications\SubscriberVerifyEmail;
use PHPUnit\Framework\Attributes\Test;

/**
 * On a double opt-in the verification email is the whole funnel, so an app has
 * to be able to replace its copy without reaching past the model.
 */
class VerificationNotificationTest extends TestCase
{
    #[Test]
    public function it_sends_the_package_notification_by_default(): void
    {
        Notification::fake();

        $subscriber = Subscriber::create(['email' => 'some@email.com']);
        $subscriber->sendEmailVerificationNotification();

        Notification::assertSentTo($subscriber, SubscriberVerifyEmail::class);
    }

    /**
     * A config file published before the key existed, cached, keeps working.
     */
    #[Test]
    public function it_falls_back_to_the_package_notification_when_the_key_is_missing(): void
    {
        Notification::fake();
        config()->set('kanpen.notification', null);

        $subscriber = Subscriber::create(['email' => 'some@email.com']);
        $subscriber->sendEmailVerificationNotification();

        Notification::assertSentTo($subscriber, SubscriberVerifyEmail::class);
    }

    #[Test]
    public function it_sends_the_configured_notification_instead(): void
    {
        Notification::fake();
        config()->set('kanpen.notification', BrandedVerifyEmail::class);

        $subscriber = Subscriber::create(['email' => 'some@email.com']);
        $subscriber->sendEmailVerificationNotification();

        Notification::assertSentTo($subscriber, BrandedVerifyEmail::class);
        Notification::assertNotSentTo($subscriber, SubscriberVerifyEmail::class);
    }

    #[Test]
    public function it_sends_the_configured_notification_on_subscription(): void
    {
        Notification::fake();
        config()->set('kanpen.verify', true);
        config()->set('kanpen.notification', BrandedVerifyEmail::class);

        $this->postJson('/kanpen-api/subscriber', ['email' => 'some@email.com'])
            ->assertStatus(201);

        Notification::assertSentTo(Subscriber::first(), BrandedVerifyEmail::class);
    }

    #[Test]
    public function it_resolves_the_configured_notification_from_the_container(): void
    {
        Notification::fake();
        config()->set('kanpen.notification', BrandedVerifyEmail::class);
        $this->app->bind(BrandedVerifyEmail::class, fn () => new BrandedVerifyEmail('Injected'));

        $subscriber = Subscriber::create(['email' => 'some@email.com']);
        $subscriber->sendEmailVerificationNotification();

        Notification::assertSentTo(
            $subscriber,
            BrandedVerifyEmail::class,
            fn (BrandedVerifyEmail $notification) => $notification->subject === 'Injected'
        );
    }
}

class BrandedVerifyEmail extends SubscriberVerifyEmail
{
    public function __construct(public string $subject = 'Confirm your subscription') {}

    public function toMail($notifiable): MailMessage
    {
        return parent::toMail($notifiable)->subject($this->subject);
    }
}
