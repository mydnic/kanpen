<?php

namespace Mydnic\Kanpen\Http\Controllers;

use Illuminate\Routing\Controller;
use Mydnic\Kanpen\Models\Subscriber;

class UnsubscribeController extends Controller
{
    public function show(string $token)
    {
        // Rendered for any token, valid or not, so the page cannot be used
        // to tell whether an address is on the list.
        return view('kanpen::subscriber.unsubscribe', ['token' => $token]);
    }

    public function destroy(string $token)
    {
        Subscriber::where('unsubscribe_token', $token)->first()?->delete();

        return view('kanpen::subscriber.deleted');
    }
}
