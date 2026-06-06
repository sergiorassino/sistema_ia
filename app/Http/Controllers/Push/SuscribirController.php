<?php

namespace App\Http\Controllers\Push;

use App\Http\Controllers\Controller;
use App\Push\PushSubscriptionRepository;
use App\Push\PushUserKey;

class SuscribirController extends Controller
{
    public function __invoke()
    {
        $userKey = PushUserKey::forAuthenticatedUser();

        return view('push.suscribir', [
            'hasSubscription' => PushSubscriptionRepository::hasAnyForUserKey($userKey),
            'layout'          => request()->routeIs('portalDocente.push.suscribir') ? 'layouts.docente' : \App\Support\ProfesorMenuPortal::layoutStaff(),
        ]);
    }
}
