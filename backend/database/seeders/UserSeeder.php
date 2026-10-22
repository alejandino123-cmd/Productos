<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Usuarios de prueba. Se puede correr varias veces: si el correo ya existe,
     * se actualiza en vez de duplicarse.
     * La contraseña se guarda cifrada sola (User.php tiene el cast 'hashed').
     */
    public function run(): void
    {
        // Superadmin: entra directo, sin código por correo ni QR
        User::updateOrCreate(
            ['email' => 'admin@uls.com'],
            [
                'name' => 'Administrador',
                'password' => 'Admin123*',
                'role' => 'superadmin',
            ]
        );

        // Usuario normal: contraseña y luego código por correo o QR
        User::updateOrCreate(
            ['email' => 'alejandino123@gmail.com'],
            [
                'name' => 'Alejandino',
                'password' => 'Usuario123*',
            ]
        );
    }
}