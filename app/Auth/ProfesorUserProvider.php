<?php

namespace App\Auth;

use App\Models\Profesor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class ProfesorUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return Profesor::find($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // No remember token support on legacy table
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials['dni'])) {
            return null;
        }

        $query = Profesor::where('dni', $credentials['dni']);

        if (array_key_exists('nivel', $credentials) && $credentials['nivel'] !== '' && $credentials['nivel'] !== null) {
            $query->where('nivel', (int) $credentials['nivel']);
        }

        return $query->first();
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain  = $credentials['pwrd'] ?? '';
        $stored = $user->getAuthPassword();

        // Support future bcrypt hashes as well as plain-text legacy passwords
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return password_verify($plain, $stored);
        }

        return hash_equals((string) $stored, (string) $plain);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Plain-text passwords — rehash on demand when bcrypt upgrade is triggered
    }
}
