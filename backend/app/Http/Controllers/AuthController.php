<?php

namespace App\Http\Controllers;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Support\LoginTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /** Minutos de vida del código que se envía por correo. */
    private const CODE_TTL_MINUTES = 10;

    #[OA\Post(
        path: '/api/register',
        summary: 'Registrar un nuevo usuario',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Andy Test'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'andy@test.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuario creado y token generado'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // OJO: este token entra directo, sin pasar por el código del correo.
        // Si el registro también debe exigir verificación, quita el token de aquí
        // y haz que el usuario inicie sesión después de registrarse.
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'Iniciar sesión',
        description: 'El superadmin recibe el token directo. Un usuario normal recibe requires_2fa y debe confirmar el código enviado a su correo en /api/verify-code.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'andy@test.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Superadmin: user, token e is_superadmin. Usuario normal: requires_2fa, is_superadmin=false y email (sin token).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', type: 'object'),
                        new OA\Property(property: 'token', type: 'string', example: '1|laravel_token_xyz...'),
                        new OA\Property(property: 'is_superadmin', type: 'boolean', example: true),
                        new OA\Property(property: 'requires_2fa', type: 'boolean', example: false),
                        new OA\Property(property: 'email', type: 'string', example: 'ana@correo.com'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Credenciales incorrectas'),
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        // Superadmin: entra directo. Solo se decide por el rol guardado en la base de datos.
        // (Antes también se aceptaba un correo fijo, lo que permitía saltarse el código.)
        if ($user->role === 'superadmin') {
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
                'is_superadmin' => true,
                'message' => 'Bienvenido Superadmin, acceso directo concedido.',
            ]);
        }

        // Usuario normal: todavía no entra. Recibe un boleto temporal (10 min) y elige cómo
        // verificar su identidad: código por correo o QR con Google Authenticator.
        // El boleto NO es un token: no sirve para llamar a ninguna otra ruta.
        return response()->json([
            'requires_2fa' => true,
            'is_superadmin' => false,
            'email' => $user->email,
            'ticket' => LoginTicket::issue($user),
            'message' => 'Elige cómo quieres verificar tu identidad.',
        ]);
    }

    #[OA\Post(
        path: '/api/2fa/email/send',
        summary: 'Enviar el código de 6 dígitos al correo del usuario',
        description: 'Requiere el header X-Login-Ticket que entrega /api/login.',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Código enviado'),
            new OA\Response(response: 401, description: 'El ticket no existe o ya venció'),
        ]
    )]
    public function sendCode(Request $request)
    {
        $user = LoginTicket::user($request);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ])->save();

        try {
            Mail::to($user->email)->send(new VerificationCodeMail($code));
        } catch (\Throwable $e) {
            // No tumbamos la petición, pero dejamos el error en storage/logs/laravel.log
            report($e);
        }

        $response = [
            'email' => $user->email,
            'message' => 'Se ha enviado un código de verificación a tu correo electrónico.',
        ];

        // Solo para desarrollo local. En producción nunca se devuelve el código.
        if (app()->environment('local')) {
            $response['debug_code'] = $code;
        }

        return response()->json($response);
    }

    #[OA\Post(
        path: '/api/verify-code',
        summary: 'Verificar el código enviado por correo',
        description: 'Requiere el header X-Login-Ticket que entrega /api/login.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: '048213'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Código correcto: devuelve user y token'),
            new OA\Response(response: 401, description: 'El ticket no existe o ya venció'),
            new OA\Response(response: 422, description: 'Código incorrecto, vencido o con formato inválido'),
            new OA\Response(response: 429, description: 'Demasiados intentos'),
        ]
    )]
    public function verifyCode(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ]);

        $user = LoginTicket::user($request);

        $esValido = $user->verification_code
            && $user->verification_code_expires_at
            && $user->verification_code_expires_at->isFuture()
            && hash_equals($user->verification_code, (string) $request->input('code'));

        if (! $esValido) {
            return response()->json([
                'message' => 'El código ingresado es incorrecto o ya expiró.',
            ], 422);
        }

        // El código y el ticket solo se pueden usar una vez
        $user->forceFill([
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ])->save();
        LoginTicket::forget($request);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Verificación exitosa.',
        ]);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Cerrar sesión',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sesión cerrada correctamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}