<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ComCanalesSeeder extends Seeder
{
    /**
     * Configuración por defecto de canales de comunicación (por nivel).
     *
     * medios_permitidos es la intersección de lo que el canal permite con
     * lo que la familia/profesor haya elegido en sus preferencias.
     */
    public function run(): void
    {
        if (! Schema::hasTable('com_canales')) {
            return;
        }

        $tieneNivel = Schema::hasColumn('com_canales', 'id_nivel');
        $niveles = DB::table('niveles')->orderBy('id')->pluck('id');

        if ($niveles->isEmpty()) {
            $niveles = collect([1]);
        }

        $canales = [
            [
                'rol_emisor'      => 'familia',
                'rol_receptor'    => 'preceptor',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email', 'whatsapp']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'familia',
                'rol_receptor'    => 'profesor',
                'puede_iniciar'   => false,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'familia',
                'rol_receptor'    => 'directivo',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'profesor',
                'rol_receptor'    => 'familia',
                'puede_iniciar'   => true,
                'puede_responder' => false,
                'medios_permitidos' => json_encode(['push', 'email']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'preceptor',
                'rol_receptor'    => 'familia',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email', 'whatsapp']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'directivo',
                'rol_receptor'    => 'familia',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email', 'whatsapp']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'preceptor',
                'rol_receptor'    => 'profesor',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'profesor',
                'rol_receptor'    => 'profesor',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'profesor',
                'rol_receptor'    => 'preceptor',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'profesor',
                'rol_receptor'    => 'directivo',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'preceptor',
                'rol_receptor'    => 'preceptor',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email', 'whatsapp']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'preceptor',
                'rol_receptor'    => 'directivo',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email', 'whatsapp']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'directivo',
                'rol_receptor'    => 'profesor',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'directivo',
                'rol_receptor'    => 'preceptor',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email', 'whatsapp']),
                'activo'          => true,
            ],
            [
                'rol_emisor'      => 'directivo',
                'rol_receptor'    => 'directivo',
                'puede_iniciar'   => true,
                'puede_responder' => true,
                'medios_permitidos' => json_encode(['push', 'email', 'whatsapp']),
                'activo'          => true,
            ],
        ];

        foreach ($niveles as $idNivel) {
            foreach ($canales as $canal) {
                $clave = $tieneNivel
                    ? [
                        'id_nivel'     => $idNivel,
                        'rol_emisor'   => $canal['rol_emisor'],
                        'rol_receptor' => $canal['rol_receptor'],
                    ]
                    : [
                        'rol_emisor'   => $canal['rol_emisor'],
                        'rol_receptor' => $canal['rol_receptor'],
                    ];

                $datos = array_merge($canal, ['created_at' => now(), 'updated_at' => now()]);
                if ($tieneNivel) {
                    $datos['id_nivel'] = $idNivel;
                }

                DB::table('com_canales')->updateOrInsert($clave, $datos);
            }
        }
    }
}
