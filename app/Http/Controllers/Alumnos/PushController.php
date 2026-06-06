<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Push\PushSubscriptionRepository;
use App\Push\PushUserKey;

class PushController extends Controller
{
    public function index()
    {
        $userKey = PushUserKey::forAuthenticatedUser();
        $hasSubscription = PushSubscriptionRepository::hasAnyForUserKey($userKey);

        return view('alumnos.push.index', [
            'hasSubscription' => $hasSubscription,
        ]);
    }
}
