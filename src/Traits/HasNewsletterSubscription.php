<?php

namespace Mydnic\Kanpen\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Mydnic\Kanpen\Events\SubscriberDeleted;
use Mydnic\Kanpen\Models\Subscriber;

/**
 * Add this trait to your User model (or any Eloquent model with an email column)
 * to automatically sync it with the subscribers table.
 *
 * You must implement two methods:
 *
 *   shouldBeSubscribed(): defines when the model should be synced as a subscriber.
 *   onUnsubscribed(): called when the subscriber is removed from outside your app
 *                     (e.g. the user clicks the unsubscribe link in an email).
 *                     Use this to keep your own model in sync.
 */
trait HasNewsletterSubscription
{
    abstract public function shouldBeSubscribed(): bool;

    abstract public function onUnsubscribed(): void;

    public static function bootHasNewsletterSubscription(): void
    {
        static::saved(function ($model) {
            $model->syncSubscriberRecord();
        });

        static::deleted(function ($model) {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                Subscriber::where('email', $model->getSubscriberEmail())->forceDelete();
            } else {
                Subscriber::where('email', $model->getSubscriberEmail())->delete();
            }
        });

        Event::listen(SubscriberDeleted::class, function (SubscriberDeleted $event) {
            static::where('email', $event->subscriber->email)
                ->each(fn ($model) => $model->onUnsubscribed());
        });
    }

    public function syncSubscriberRecord(): void
    {
        $email = $this->getSubscriberEmail();
        $emailVerifiedAt = $this->email_verified_at ?? null;

        if (empty($email)) {
            return;
        }

        if ($this->shouldBeSubscribed()) {
            $existing = Subscriber::withTrashed()->where('email', $email)->first();

            if ($existing && $existing->trashed()) {
                $existing->restore();
            } elseif (! $existing) {
                Subscriber::create([
                    'email' => $email,
                    'email_verified_at' => $emailVerifiedAt,
                ]);

                return;
            }

            if (! is_null($emailVerifiedAt) && is_null($existing->email_verified_at)) {
                $existing->forceFill(['email_verified_at' => $emailVerifiedAt])->saveQuietly();
            }
        } else {
            Subscriber::where('email', $email)->delete();
        }
    }

    public function getSubscriberEmail(): string
    {
        return (string) ($this->email ?? '');
    }

    public function subscriber(): ?Builder
    {
        return Subscriber::where('email', $this->getSubscriberEmail());
    }

    public function subscribe(): void
    {
        $email = $this->getSubscriberEmail();
        $emailVerifiedAt = $this->email_verified_at ?? null;
        $subscriber = Subscriber::withTrashed()->where('email', $email)->first();

        if ($subscriber && $subscriber->trashed()) {
            $subscriber->restore();
        } elseif (! $subscriber) {
            $subscriber = Subscriber::create([
                'email' => $email,
                'email_verified_at' => $emailVerifiedAt,
            ]);
        }

        if (! is_null($emailVerifiedAt) && is_null($subscriber->email_verified_at)) {
            $subscriber->forceFill(['email_verified_at' => $emailVerifiedAt])->saveQuietly();
        }

        if (config('kanpen.verify') && ! $subscriber->hasVerifiedEmail()) {
            $subscriber->sendEmailVerificationNotification();
        }
    }

    public function unsubscribe(): void
    {
        Subscriber::where('email', $this->getSubscriberEmail())->delete();
    }

    public function isSubscribed(): bool
    {
        return Subscriber::where('email', $this->getSubscriberEmail())->exists();
    }
}
