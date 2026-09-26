<?php

namespace App\Support\ProyectosExtracurriculares;

use DateTimeInterface;

/**
 * Redacción del formulario de autorización de padres o tutores.
 * Toma el modelo en papel (viaje educativo) y lo arma con los datos del alumno y de la actividad.
 */
final class AutorizacionActividadRedaccion
{
    /** @var array<int, string> */
    private const MESES = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    /** @var array<string, string> */
    private const TITULOS_CRONOGRAMA = [
        'actividades previas' => 'Actividades Previas',
        'actividades durante' => 'Actividades Durante',
        'actividades posteriores' => 'Actividades Posteriores',
    ];

    public static function fechaLarga(DateTimeInterface $fecha): string
    {
        $mes = self::MESES[(int) $fecha->format('n')] ?? $fecha->format('m');

        return $fecha->format('j').' de '.$mes.' de '.$fecha->format('Y');
    }

    public static function nombreProsa(string $nombre, string $apellido): string
    {
        return trim(trim($nombre).' '.trim($apellido));
    }

    /**
     * @param  list<string>  $nombres
     */
    public static function enumerar(array $nombres): string
    {
        $nombres = array_values(array_filter(array_map(
            static fn ($nombre) => trim((string) $nombre),
            $nombres
        ), static fn (string $nombre) => $nombre !== ''));

        $cantidad = count($nombres);
        if ($cantidad === 0) {
            return '';
        }
        if ($cantidad === 1) {
            return $nombres[0];
        }
        if ($cantidad === 2) {
            return $nombres[0].' y '.$nombres[1];
        }

        $ultimo = array_pop($nombres);

        return implode(', ', $nombres).' y '.$ultimo;
    }

    /**
     * @return array{calle: string, numero: string}
     */
    public static function calleYNumero(string $domicilio): array
    {
        $domicilio = trim($domicilio);
        if ($domicilio !== '' && preg_match('/^(.*?)[\s,]+(\d+\s*[A-Za-z°º]*)$/u', $domicilio, $m)) {
            $calle = trim($m[1], " \t,");
            $numero = trim($m[2]);
            if ($calle !== '' && $numero !== '') {
                return ['calle' => $calle, 'numero' => $numero];
            }
        }

        return ['calle' => $domicilio, 'numero' => ''];
    }

    public static function horaModelo(string $hora): string
    {
        $hora = trim($hora);
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $hora, $m)) {
            return $hora;
        }

        return ((int) $m[1]).':'.$m[2];
    }

    /**
     * Texto del itinerario, en el orden del formulario (previas, durante, posteriores).
     *
     * @param  list<array{titulo: string, texto: string}>  $secciones
     */
    public static function textoParticipacion(array $secciones): string
    {
        $partes = [];
        foreach ($secciones as $seccion) {
            $texto = trim((string) ($seccion['texto'] ?? ''));
            if ($texto !== '') {
                $partes[] = $texto;
            }
        }

        return implode(' ', $partes);
    }

    /**
     * @return list<array{titulo: string, texto: string}>
     */
    public static function seccionesCronograma(string $texto): array
    {
        $texto = trim(str_replace(["\r\n", "\r"], "\n", $texto));
        if ($texto === '') {
            return [];
        }

        $pattern = '/^[ \t]*(Actividades Previas|Actividades Durante|Actividades Posteriores)[ \t]*:[ \t]*/imu';
        if (! preg_match($pattern, $texto)) {
            return [['titulo' => 'Desarrollo de la actividad', 'texto' => $texto]];
        }

        $partes = preg_split($pattern, $texto, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (! is_array($partes)) {
            return [['titulo' => 'Desarrollo de la actividad', 'texto' => $texto]];
        }

        $out = [];
        $preambulo = trim((string) ($partes[0] ?? ''));
        if ($preambulo !== '') {
            $out[] = ['titulo' => 'Desarrollo de la actividad', 'texto' => $preambulo];
        }

        $total = count($partes);
        for ($i = 1; $i < $total; $i += 2) {
            $clave = mb_strtolower(trim((string) $partes[$i]));
            $cuerpo = trim((string) ($partes[$i + 1] ?? ''));
            if ($cuerpo === '') {
                continue;
            }
            $out[] = [
                'titulo' => self::TITULOS_CRONOGRAMA[$clave] ?? trim((string) $partes[$i]),
                'texto' => $cuerpo,
            ];
        }

        return $out;
    }

    /**
     * @param  array{
     *   nombre: string,
     *   lugar: string,
     *   localidad_salida: string,
     *   acompanantes: string,
     *   horario: string,
     *   jornadas: list<array{fecha: string, fecha_larga: string, inicio: string, fin: string}>
     * }  $viaje
     */
    public static function fraseCuando(array $viaje): string
    {
        $jornadas = $viaje['jornadas'];
        $cantidad = count($jornadas);
        if ($cantidad === 0) {
            return '';
        }

        if ($cantidad === 1) {
            return ', a realizarse el día '.$jornadas[0]['fecha_larga'];
        }

        $primera = $jornadas[0]['fecha_larga'];
        $ultima = $jornadas[$cantidad - 1]['fecha_larga'];

        return ', a realizarse entre el '.$primera.' y el '.$ultima;
    }

    /**
     * @param  array{apellido: string, nombre: string, dni: string, grupo_sanguineo: string, curso: string, division: string, calle: string, numero: string, localidad: string}  $alumno
     * @param  array{
     *   nombre: string,
     *   lugar: string,
     *   localidad_salida: string,
     *   acompanantes: string,
     *   horario: string,
     *   participacion: string,
     *   jornadas: list<array{fecha: string, fecha_larga: string, inicio: string, fin: string}>
     * }  $viaje
     */
    public static function parrafoAutorizacion(array $alumno, array $viaje): string
    {
        $estudiante = trim($alumno['apellido'].', '.$alumno['nombre']);
        if ($estudiante === ',' || $estudiante === '') {
            $estudiante = '_______________________________';
        }

        $curso = trim($alumno['curso']);
        $division = trim($alumno['division']);
        if ($curso === '' && $division === '') {
            $cursoTxt = 'Curso ______________ división ______________';
        } elseif ($division !== '') {
            $cursoTxt = 'Curso '.$curso.' división '.$division;
        } else {
            $cursoTxt = 'Curso '.$curso;
        }

        $calle = trim($alumno['calle']);
        $numero = trim($alumno['numero']);
        if ($calle === '' && $numero === '') {
            $domicilioTxt = 'Calle ______________________ Número ________';
        } elseif ($numero !== '') {
            $domicilioTxt = 'Calle '.$calle.' Número '.$numero;
        } else {
            $domicilioTxt = 'Calle '.$calle;
        }

        $desde = trim($viaje['localidad_salida']);
        $hasta = trim($viaje['lugar']);
        if ($desde !== '' && $hasta !== '') {
            $salida = ', con salida desde '.$desde.' hasta '.$hasta;
        } elseif ($hasta !== '') {
            $salida = ', hasta '.$hasta;
        } elseif ($desde !== '') {
            $salida = ', con salida desde '.$desde;
        } else {
            $salida = '';
        }

        $participacion = trim($viaje['participacion']);
        $participar = $participacion !== '' ? ' para participar de '.$participacion : '';

        $acompanantes = trim($viaje['acompanantes']);
        $acomp = $acompanantes !== ''
            ? ', acompañado/a de '.$acompanantes
            : ', acompañado/a del personal docente designado';

        return 'Por la presente AUTORIZO a mi hijo/a '.$estudiante
            .', D.N.I Nº '.self::oLinea($alumno['dni'])
            .', Grupo y Factor sanguíneo '.self::oLinea($alumno['grupo_sanguineo'])
            .' Alumno/a del '.$cursoTxt
            .', con domicilio en '.$domicilioTxt
            .', de la Localidad de '.self::oLinea($alumno['localidad'], '_________________________________')
            .' a realizar un Viaje Educativo'
            .$salida
            .$participar
            .self::fraseCuando($viaje)
            .$acomp
            .', según Agenda, desde el inicio al final del viaje:';
    }

    /**
     * @param  array{
     *   jornadas: list<array{fecha: string, fecha_larga: string, inicio: string, fin: string}>
     * }  $viaje
     */
    public static function fraseTraslado(array $viaje): string
    {
        $jornadas = $viaje['jornadas'];
        if ($jornadas === []) {
            return 'El traslado de los estudiantes hasta el punto de salida, al igual que el regreso, estará a cargo de ______________________';
        }

        $salida = $jornadas[0]['fecha'];
        $regreso = $jornadas[count($jornadas) - 1]['fecha'];

        return 'El traslado de los estudiantes hasta el punto de salida el día '.$salida
            .', al igual que el regreso el día '.$regreso
            .' estará a cargo de ______________________';
    }

    /**
     * @param  array{
     *   horario: string,
     *   jornadas: list<array{fecha: string, fecha_larga: string, inicio: string, fin: string}>
     * }  $viaje
     * @return list<string>
     */
    public static function lineasDetalle(array $viaje): array
    {
        $jornadas = $viaje['jornadas'];
        $lineaTransporte = 'TRANSPORTE: ______________________________';

        if ($jornadas === []) {
            return [
                'SALIDA: Fecha y hora. ______________________________',
                'REGRESO: Fecha y hora. ______________________________',
                $lineaTransporte,
            ];
        }

        $primera = $jornadas[0];
        $ultima = $jornadas[count($jornadas) - 1];

        return [
            'SALIDA: Fecha y hora. '.self::fechaYHora($primera, 'inicio'),
            'REGRESO: Fecha y hora. '.self::fechaYHora($ultima, 'fin').'.',
            $lineaTransporte,
        ];
    }

    /**
     * @param  array{fecha: string, fecha_larga: string, inicio: string, fin: string}  $jornada
     */
    private static function fechaYHora(array $jornada, string $claveHora): string
    {
        $hora = self::horaModelo($jornada[$claveHora]);
        if ($hora === '') {
            return $jornada['fecha_larga'];
        }

        return $jornada['fecha_larga'].' a las '.$hora.' hs';
    }

    private static function oLinea(string $valor, string $blanco = '_______________'): string
    {
        $valor = trim($valor);

        return $valor !== '' ? $valor : $blanco;
    }

    /**
     * Horario libre de la actividad, si no repite el único tramo ya dicho en la jornada.
     *
     * @param  list<array{fecha: string, fecha_larga: string, inicio: string, fin: string}>  $jornadas
     */
    public static function horarioComplementario(string $horario, array $jornadas): string
    {
        $horario = trim($horario);
        if ($horario === '' || count($jornadas) !== 1) {
            return $horario;
        }

        $inicio = $jornadas[0]['inicio'];
        $fin = $jornadas[0]['fin'];
        $compacto = trim($inicio.($inicio !== '' && $fin !== '' ? ' a ' : '').$fin);

        return $compacto !== '' && $horario === $compacto ? '' : $horario;
    }
}
