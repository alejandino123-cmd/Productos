<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Boleto" que recibe un usuario normal al poner bien su contraseña.
 * Dura 10 minutos y solo sirve para el segundo paso (correo o QR).
 * No es un token de Sanctum, así que no abre productos ni ninguna otra ruta.
 */
class LoginTicket
{
    private const TTL_MINUTES = 10;

    public static function issue(User $user): string
    {
        $ticket = Str::random(60);
        Cache::put(self::key($ticket), $user->id, now()->addMinutes(self::TTL_MINUTES));

        return $ticket;
    }

    public static function user(Request $request): User
    {
        $ticket = (string) $request->header('X-Login-Ticket');
        $id = $ticket !== '' ? Cache::get(self::key($ticket)) : null;
        $user = $id ? User::find($id) : null;

        abort_unless($user, 401, 'La verificación expiró. Inicia sesión de nuevo.');

        return $user;
    }

    public static function forget(Request $request): void
    {
        Cache::forget(self::key((string) $request->header('X-Login-Ticket')));
    }

    private static function key(string $ticket): string
    {
        return 'login_ticket:' . hash('sha256', $ticket);
    }
}