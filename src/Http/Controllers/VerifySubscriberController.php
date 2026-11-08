<?php

namespace Mydnic\Kanpen\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Mydnic\Kanpen\Events\SubscriberVerified;
use Mydnic\Kanpen\Exceptions\SubscriberVerificationException;
use Mydnic\Kanpen\Models\Subscriber;

class VerifySubscriberController extends Controller
{
    public function __invoke(Request $request)
    {
        $subscriber = Subscriber::find($request->route('id'));

        if (! $subscriber) {
            throw new SubscriberVerificationException;
        }

        if (! hash_equals((string) $request->route('hash'), sha1($subscriber->getEmailForVerification()))) {
            throw new SubscriberVerificationException;
        }

        if ($subscriber->hasVerifiedEmail()) {
            return $request->wantsJson()
                ? new Response('', 204)
                : redirect()->route(config('kanpen.redirect_url'));
        }

        if ($subscriber->markEmailAsVerified()) {
            event(new SubscriberVerified($subscriber));
        }

        return $request->wantsJson()
            ? new Response('', 204)
            : redirect()->route(config('kanpen.redirect_url'))
                ->with('verified', __('You are successfully subscribed to our list!'));
    }
}
