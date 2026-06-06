<?php

namespace App\Models;

use App\Support\Aspirantes\AspirantesColumnaTipo;
use App\Support\Aspirantes\CampoAspiranteOpciones;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parametrización de los campos visibles del formulario público de aspirantes.
 *
 * Para cada columna real de la tabla `aspirantes`, define el orden en el form.
 * Visible, obligatorio, etiqueta, ayuda y opciones del select viven en `campos_aspirantes_nivel`.
 */
class CampoAspirante extends Model
{
    protected $table = 'campos_aspirantes';

    public $timestamps = false;

    protected $fillable = [
        'columna',
        'orden',
    ];

    protected $casts = [
        'orden'       => 'integer',
    ];

    /**
     * Devuelve las columnas activas (visibles), ordenadas, listas para construir el form público.
     *
     * @return list<array{columna: string, etiqueta: string, ayuda: ?string, obligatorio: bool, opciones: list<string>, es_fecha: bool}>
     */
    public static function camposParaFormularioPublico(?int $idNivel = null): array
    {
        if (! Schema::hasTable('campos_aspirantes')) {
            return [];
        }

        $idNivel = $idNivel !== null ? (int) $idNivel : null;

        if ($idNivel === null || $idNivel <= 0) {
            return [];
        }

        if (! Schema::hasTable('campos_aspirantes_nivel')) {
            return [];
        }

        $select = [
            'campos_aspirantes.columna as columna',
            'cn.obligatorio as obligatorio',
        ];
        if (Schema::hasColumn('campos_aspirantes_nivel', 'etiqueta')) {
            $select[] = 'cn.etiqueta as etiqueta';
        }
        if (Schema::hasColumn('campos_aspirantes_nivel', 'opciones')) {
            $select[] = 'cn.opciones as opciones';
        }
        if (Schema::hasColumn('campos_aspirantes_nivel', 'ayuda')) {
            $select[] = 'cn.ayuda as ayuda';
        }

        $rows = DB::table('campos_aspirantes')
            ->join('campos_aspirantes_nivel as cn', 'cn.campo_aspirante_id', '=', 'campos_aspirantes.id')
            ->where('cn.idNivel', $idNivel)
            ->where('cn.visible', 1)
            ->orderBy('campos_aspirantes.orden')
            ->orderBy('campos_aspirantes.columna')
            ->get($select);

        return $rows
            ->map(static function ($r) {
                $opcionesRaw = property_exists($r, 'opciones') ? $r->opciones : null;

                $etiqueta = property_exists($r, 'etiqueta') ? $r->etiqueta : null;
                $ayudaRaw = property_exists($r, 'ayuda') ? $r->ayuda : null;
                $ayuda = is_string($ayudaRaw) ? trim($ayudaRaw) : '';
                $ayuda = $ayuda !== '' ? mb_substr($ayuda, 0, 500) : null;

                $columna = (string) $r->columna;

                return [
                    'columna'     => $columna,
                    'etiqueta'    => $etiqueta !== null && $etiqueta !== '' ? (string) $etiqueta : $columna,
                    'ayuda'       => $ayuda,
                    'obligatorio' => (bool) $r->obligatorio,
                    'opciones'    => CampoAspiranteOpciones::parse(
                        $opcionesRaw !== null ? (string) $opcionesRaw : null
                    ),
                    'es_fecha'    => AspirantesColumnaTipo::esFecha($columna),
                ];
            })
            ->all();
    }
}
