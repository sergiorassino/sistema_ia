<?php

namespace App\Push;

use Illuminate\Support\Facades\DB;

class PushSubscriptionRepository
{
    public static function hasAnyForUserKey(string $userKey): bool
    {
        $uk = trim($userKey);
        if ($uk === '') {
            return false;
        }

        return DB::table('push_subscriptions')
            ->where('user_key', $uk)
            ->limit(1)
            ->exists();
    }

    public static function deviceTypeFromUserAgent(?string $userAgent): string
    {
        if ($userAgent === null || $userAgent === '') {
            return 'desktop';
        }

        $ua = $userAgent;
        if (stripos($ua, 'iPad') !== false || (stripos($ua, 'Tablet') !== false && stripos($ua, 'Mobile') === false)) {
            return 'tablet';
        }

        if (
            stripos($ua, 'iPhone') !== false || stripos($ua, 'iPod') !== false
            || stripos($ua, 'Android') !== false || stripos($ua, 'Mobile') !== false
            || stripos($ua, 'Windows Phone') !== false || stripos($ua, 'webOS') !== false
        ) {
            return 'mobile';
        }

        return 'desktop';
    }

    public static function deviceLabelFromUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        $ua = $userAgent;
        $maxLen = 100;

        if (stripos($ua, 'iPad') !== false) {
            return 'iPad';
        }
        if (stripos($ua, 'iPhone') !== false) {
            return 'iPhone';
        }
        if (stripos($ua, 'iPod') !== false) {
            return 'iPod';
        }
        if (stripos($ua, 'Windows Phone') !== false) {
            return 'Windows Phone';
        }

        if (preg_match('/Android\s+[\d.]+;\s*([^)\)]+?)(?:\s+Build)?\s*\)/i', $ua, $m)) {
            $model = trim($m[1]);
            if (preg_match('/^(.+?)\s+Build\//', $model, $m2)) {
                $model = trim($m2[1]);
            }
            if ($model !== '' && strlen($model) <= $maxLen) {
                if (preg_match('/^SM-[A-Z0-9]+$/i', $model)) {
                    return 'Samsung ' . $model;
                }
                if (stripos($model, 'Pixel') === 0) {
                    return 'Google ' . $model;
                }
                if (stripos($model, 'Moto') === 0 || stripos($model, 'XT') === 0) {
                    return 'Motorola ' . $model;
                }
                if (stripos($model, 'Redmi') !== false || stripos($model, 'Mi ') === 0 || stripos($model, 'POCO') === 0) {
                    return $model;
                }
                if (strlen($model) <= 2) {
                    return 'Android (modelo ' . $model . ')';
                }
                return $model;
            }
        }

        if (stripos($ua, 'Android') !== false) {
            return 'Android';
        }
        if (stripos($ua, 'Mobile') !== false) {
            return 'mobile';
        }

        if (stripos($ua, 'Windows') !== false && stripos($ua, 'Chrome') !== false) {
            return 'Chrome Windows';
        }
        if (stripos($ua, 'Mac OS') !== false && stripos($ua, 'Chrome') !== false) {
            return 'Chrome Mac';
        }
        if (stripos($ua, 'Firefox') !== false) {
            return 'Firefox';
        }
        if (stripos($ua, 'Edg') !== false) {
            return 'Edge';
        }
        if (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) {
            return 'Safari';
        }

        return 'desktop';
    }

    public static function clientHintsFromUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        $ua = $userAgent;
        $platform = 'Unknown';
        if (stripos($ua, 'Windows') !== false) {
            $platform = 'Windows';
        } elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) {
            $platform = 'macOS';
        } elseif (stripos($ua, 'Android') !== false) {
            $platform = 'Android';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'iPod') !== false) {
            $platform = 'iOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $platform = 'Linux';
        } elseif (stripos($ua, 'CrOS') !== false) {
            $platform = 'Chrome OS';
        }

        $mobile = self::deviceTypeFromUserAgent($userAgent) !== 'desktop';
        $brands = [];
        if (stripos($ua, 'Edg/') !== false) {
            $brands[] = ['brand' => 'Microsoft Edge', 'version' => ''];
        } elseif (stripos($ua, 'Chrome') !== false && stripos($ua, 'Chromium') !== false) {
            $brands[] = ['brand' => 'Chromium', 'version' => ''];
        } elseif (stripos($ua, 'Chrome') !== false) {
            $brands[] = ['brand' => 'Google Chrome', 'version' => ''];
        } elseif (stripos($ua, 'Firefox') !== false) {
            $brands[] = ['brand' => 'Firefox', 'version' => ''];
        } elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) {
            $brands[] = ['brand' => 'Safari', 'version' => ''];
        }

        $hints = ['platform' => $platform, 'mobile' => $mobile];
        if (! empty($brands)) {
            $hints['brands'] = $brands;
        }

        return json_encode($hints);
    }

    public static function save(
        string $endpoint,
        string $auth,
        string $p256dh,
        ?string $userKey,
        ?string $deviceType = null,
        ?string $userAgent = null,
        ?string $clientHints = null
    ): void {
        $hash = hash('sha256', $endpoint);

        $uk = ($userKey !== null && $userKey !== '') ? $userKey : '';
        $device = ($deviceType !== null && $deviceType !== '') ? $deviceType : null;
        $uaStored = ($userAgent !== null && $userAgent !== '') ? substr($userAgent, 0, 512) : null;
        $label = self::deviceLabelFromUserAgent($userAgent);
        $hints = ($clientHints !== null && $clientHints !== '') ? substr($clientHints, 0, 512) : null;

        DB::table('push_subscriptions')->updateOrInsert(
            ['endpoint_hash' => $hash, 'user_key' => $uk],
            [
                'endpoint' => $endpoint,
                'auth_key' => $auth,
                'p256dh_key' => $p256dh,
                'device_type' => $device,
                'user_agent' => $uaStored,
                'device_label' => $label,
                'client_hints' => $hints,
                'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
            ]
        );
    }

    public static function deleteByEndpoint(string $endpoint): void
    {
        $hash = hash('sha256', $endpoint);
        DB::table('push_subscriptions')->where('endpoint_hash', $hash)->delete();
    }

    /**
     * @param list<string> $userKeys
     * @return array<int, array{endpoint:string, auth_key:string, p256dh_key:string, user_key:string}>
     */
    public static function getByUserKeys(array $userKeys): array
    {
        if (empty($userKeys)) {
            return [];
        }

        return DB::table('push_subscriptions')
            ->select(['endpoint', 'auth_key', 'p256dh_key', 'user_key'])
            ->whereIn('user_key', $userKeys)
            ->orderBy('user_key')
            ->orderBy('endpoint_hash')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * @param list<string> $userKeys
     * @return list<string>
     */
    public static function getSubscribedUserKeys(array $userKeys): array
    {
        if (empty($userKeys)) {
            return [];
        }

        return DB::table('push_subscriptions')
            ->whereIn('user_key', $userKeys)
            ->distinct()
            ->pluck('user_key')
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values()
            ->all();
    }
}

