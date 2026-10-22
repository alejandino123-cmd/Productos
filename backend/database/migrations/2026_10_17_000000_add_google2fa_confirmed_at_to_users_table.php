<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si la columna ya existe (por ejemplo, se creó a mano), no se vuelve a crear
        if (Schema::hasColumn('users', 'google2fa_confirmed_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('google2fa_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'google2fa_confirmed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('google2fa_confirmed_at');
            });
        }
    }
};