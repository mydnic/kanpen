<?php

namespace Mydnic\Kanpen\Http\Controllers;

use Illuminate\Routing\Controller;
use Mydnic\Kanpen\Models\Subscriber;

class UnsubscribeController extends Controller
{
    public function __invoke(string $token)
    {
        Subscriber::where('unsubscribe_token', $token)->first()?->delete();

        return view('kanpen::subscriber.deleted');
    }
}
