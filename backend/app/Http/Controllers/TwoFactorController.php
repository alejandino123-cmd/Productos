<?php

namespace App\Http\Controllers;

use App\Support\LoginTicket;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    // Entrega el QR SOLO la primera vez. Cuando ya fue confirmado no se vuelve a mostrar:
    // si no, quien tenga la contraseña podría sacar la clave secreta y saltarse el 2FA.
    public function generateQr(Request $request)
    {
        $user = LoginTicket::user($request);

        if ($user->google2fa_confirmed_at) {
            return response()->json(['already_configured' => true]);
        }

        $google2fa = new Google2FA();

        if (! $user->google2fa_secret) {
            $user->google2fa_secret = $google2fa->generateSecretKey();
            $user->save();
        }

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'ULS App',
            $user->email,
            $user->google2fa_secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeImage = base64_encode($writer->writeString($qrCodeUrl));

        return response()->json([
            'already_configured' => false,
            'qr_code' => 'data:image/svg+xml;base64,' . $qrCodeImage,
            'secret' => $user->google2fa_secret,
        ]);
    }

    // Valida el código de 6 dígitos. Si es correcto, entrega el token definitivo.
    public function verifyQr(Request $request)
    {
        $request->validate([
            'one_time_password' => 'required|digits:6',
        ]);

        $user = LoginTicket::user($request);

        if (! $user->google2fa_secret) {
            return response()->json([
                'message' => 'Primero escanea el código QR con tu aplicación.',
            ], 422);
        }

        $valid = (new Google2FA())->verifyKey(
            $user->google2fa_secret,
            (string) $request->input('one_time_password')
        );

        if (! $valid) {
            return response()->json(['message' => 'Código inválido o expirado.'], 422);
        }

        if (! $user->google2fa_confirmed_at) {
            $user->google2fa_confirmed_at = now();
            $user->save();
        }

        LoginTicket::forget($request);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('api-token')->plainTextToken,
            'message' => 'Verificación exitosa.',
        ]);
    }
}