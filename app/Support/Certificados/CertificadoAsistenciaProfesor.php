<?php



namespace App\Support\Certificados;



use App\Models\CertAsistProf;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use Illuminate\Support\Facades\DB;



/**

 * Certificado de asistencia del profesor — listado, persistencia en certasistprof y URL del PDF.

 */

final class CertificadoAsistenciaProfesor

{

    /**

     * Personal del legajo con rol distinto de «Sin Rol» (IdTipoProf = 1).

     *

     * @return LengthAwarePaginator<int, array{

     *     idProfesores: int,

     *     apellido: string,

     *     nombre: string,

     *     dni: string,

     *     rol: string

     * }>

     */

    public static function paginarProfesores(?string $buscar, int $porPagina = 50): LengthAwarePaginator

    {

        $q = DB::table('profesores as p')

            ->leftJoin('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')

            ->where(function ($w) {

                $w->whereNull('p.IdTipoProf')->orWhere('p.IdTipoProf', '<>', 1);

            })

            ->select([

                'p.id as idProfesores',

                'p.apellido',

                'p.nombre',

                'p.dni',

                'pt.tipo as rolTipo',

            ])

            ->orderBy('p.apellido')

            ->orderBy('p.nombre')

            ->orderBy('p.id');



        $termino = self::normalizarBusqueda($buscar);

        if ($termino !== '') {

            $like = '%'.$termino.'%';

            $q->where(function ($w) use ($like, $termino) {

                $w->where('p.apellido', 'like', $like)

                    ->orWhere('p.nombre', 'like', $like);

                if (ctype_digit($termino)) {

                    $w->orWhere('p.dni', (int) $termino);

                }

            });

        }



        return $q->paginate(max(10, min(100, $porPagina)))

            ->through(static function (object $r): array {

                return [

                    'idProfesores' => (int) $r->idProfesores,

                    'apellido' => trim((string) ($r->apellido ?? '')),

                    'nombre' => trim((string) ($r->nombre ?? '')),

                    'dni' => trim((string) ($r->dni ?? '')),

                    'rol' => trim((string) ($r->rolTipo ?? '')),

                ];

            });

    }



    /**

     * @return array{

     *     idProfesores: int,

     *     apellido: string,

     *     nombre: string,

     *     dni: string,

     *     rol: string

     * }|null

     */

    public static function profesorElegible(int $idProfesores): ?array

    {

        if ($idProfesores < 1) {

            return null;

        }



        $row = DB::table('profesores as p')

            ->leftJoin('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')

            ->where('p.id', $idProfesores)

            ->where(function ($w) {

                $w->whereNull('p.IdTipoProf')->orWhere('p.IdTipoProf', '<>', 1);

            })

            ->select([

                'p.id as idProfesores',

                'p.apellido',

                'p.nombre',

                'p.dni',

                'pt.tipo as rolTipo',

            ])

            ->first();



        if ($row === null) {

            return null;

        }



        return [

            'idProfesores' => (int) $row->idProfesores,

            'apellido' => trim((string) ($row->apellido ?? '')),

            'nombre' => trim((string) ($row->nombre ?? '')),

            'dni' => trim((string) ($row->dni ?? '')),

            'rol' => trim((string) ($row->rolTipo ?? '')),

        ];

    }



    /**

     * @return array{

     *     fecha: string,

     *     texto: string,

     *     parapre: string

     * }

     */

    public static function valoresPorDefecto(): array

    {

        return [

            'fecha' => now()->format('Y-m-d'),

            'texto' => '',

            'parapre' => '',

        ];

    }



    /**

     * @return array{

     *     fecha: string,

     *     texto: string,

     *     parapre: string

     * }|null

     */

    public static function datosGuardados(int $idProfesores): ?array

    {

        if ($idProfesores < 1) {

            return null;

        }



        $row = CertAsistProf::query()

            ->where('idProfesores', $idProfesores)

            ->orderByDesc('id')

            ->first();



        if ($row === null) {

            return null;

        }



        return [

            'fecha' => $row->fecha?->format('Y-m-d') ?? now()->format('Y-m-d'),

            'texto' => trim((string) ($row->texto ?? '')),

            'parapre' => trim((string) ($row->parapre ?? '')),

        ];

    }



    /**

     * @param  array{

     *     fecha: string,

     *     texto: string,

     *     parapre: string

     * }  $datos

     */

    public static function guardar(int $idProfesores, array $datos): bool

    {

        if ($idProfesores < 1 || self::profesorElegible($idProfesores) === null) {

            return false;

        }



        $existente = CertAsistProf::query()

            ->where('idProfesores', $idProfesores)

            ->orderByDesc('id')

            ->first();



        $payload = [

            'fecha' => $datos['fecha'],

            'texto' => $datos['texto'] !== '' ? $datos['texto'] : null,

            'parapre' => $datos['parapre'] !== '' ? $datos['parapre'] : null,

        ];



        if ($existente !== null) {

            $existente->fill($payload);

            $existente->save();



            return true;

        }



        CertAsistProf::query()->create([

            'idProfesores' => $idProfesores,

            ...$payload,

        ]);



        return true;

    }



    /**

     * @param  array{

     *     fecha: string,

     *     texto: string,

     *     parapre: string

     * }  $datos

     */

    public static function pdfPost(int $idProfesores, array $datos): array
    {
        return \App\Support\Pdf\PdfPost::datos(route('certificados.asistenciaProfesor.pdf'), [
            'idProfesores' => $idProfesores,
            'fecha' => $datos['fecha'],
            'texto' => $datos['texto'],
            'parapre' => $datos['parapre'],
        ]);
    }



    /**

     * @return array<string, list<string>>

     */

    public static function reglasFormulario(): array

    {

        return [

            'fecha' => ['required', 'date'],

            'texto' => ['required', 'string', 'max:200'],

            'parapre' => ['required', 'string', 'max:300'],

        ];

    }



    /**

     * @return array<string, string>

     */

    public static function mensajesValidacion(): array

    {

        return [

            'fecha.required' => 'La fecha de emisión es obligatoria.',

            'fecha.date' => 'Fecha de emisión inválida.',

            'texto.required' => 'Indique el texto del certificado (cargo, materias, etc.).',

            'texto.max' => 'El texto no puede superar 200 caracteres.',

            'parapre.required' => 'Indique ante quién o para qué se presenta el certificado.',

            'parapre.max' => 'El destino no puede superar 300 caracteres.',

        ];

    }



    public static function normalizarBusqueda(?string $buscar): string

    {

        $t = trim((string) $buscar);

        if ($t === '') {

            return '';

        }



        return mb_strtolower($t, 'UTF-8');

    }

}

