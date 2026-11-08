<?php

namespace Mydnic\Kanpen\Http\Controllers;

use Illuminate\Routing\Controller;
use Mydnic\Kanpen\Http\Requests\StoreSubscriberRequest;
use Mydnic\Kanpen\Models\Subscriber;

class SubscriberController extends Controller
{
    public function store(StoreSubscriberRequest $request)
    {
        $subscriber = Subscriber::create($request->validated());

        if (config('kanpen.verify')) {
            $subscriber->sendEmailVerificationNotification();

            return redirect()->route(config('kanpen.redirect_url'))
                ->with('subscribed', __('Please verify your email address!'));
        }

        return redirect()->route(config('kanpen.redirect_url'))
            ->with('subscribed', __('You are successfully subscribed to our list!'));
    }
}
