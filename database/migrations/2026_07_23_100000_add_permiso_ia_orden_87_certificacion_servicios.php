<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permisos_ia')->updateOrInsert(
            ['orden' => 87],
            [
                'id' => 87,
                'tema' => 'LEGAJOS DOCENTES',
                'descripcion' => 'Certificación de servicios: carga de períodos de servicio y licencias, e impresión del certificado PDF.',
            ],
        );
    }

    public function down(): void
    {
        DB::table('permisos_ia')->where('orden', 87)->delete();
    }
};
