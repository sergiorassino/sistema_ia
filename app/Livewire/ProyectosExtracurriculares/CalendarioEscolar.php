<?php

namespace App\Livewire\ProyectosExtracurriculares;

use App\Models\ExtActividad;
use App\Support\PermisosIaCatalog;
use App\Support\ProfesorMenuPortal;
use App\Support\ProyectosExtracurriculares\ExtActividadesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CalendarioEscolar extends Component
{
    /** mes | semana | dia */
    public string $vista = 'mes';

    public string $ancla = '';

    public ?int $detalleId = null;

    public function mount(): void
    {
        if ($this->ancla === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->ancla)) {
            $this->ancla = Carbon::today()->format('Y-m-d');
        }
        if (! in_array($this->vista, ['mes', 'semana', 'dia'], true)) {
            $this->vista = 'mes';
        }
    }

    public function cambiarVista(string $vista): void
    {
        if (! in_array($vista, ['mes', 'semana', 'dia'], true)) {
            return;
        }
        $this->vista = $vista;
        $this->detalleId = null;
    }

    public function updatedVista(): void
    {
        if (! in_array($this->vista, ['mes', 'semana', 'dia'], true)) {
            $this->vista = 'mes';
        }
    }

    public function irHoy(): void
    {
        $this->ancla = Carbon::today()->format('Y-m-d');
        $this->detalleId = null;
    }

    public function anterior(): void
    {
        $d = Carbon::parse($this->ancla);
        $this->ancla = match ($this->vista) {
            'semana' => $d->subWeek()->format('Y-m-d'),
            'dia' => $d->subDay()->format('Y-m-d'),
            default => $d->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
        };
        $this->detalleId = null;
    }

    public function siguiente(): void
    {
        $d = Carbon::parse($this->ancla);
        $this->ancla = match ($this->vista) {
            'semana' => $d->addWeek()->format('Y-m-d'),
            'dia' => $d->addDay()->format('Y-m-d'),
            default => $d->addMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
        };
        $this->detalleId = null;
    }

    public function irADia(string $ymd): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return;
        }
        $this->ancla = $ymd;
        $this->vista = 'dia';
        $this->detalleId = null;
    }

    public function verDetalle(int $id): void
    {
        if ($id < 1 || ! ExtActividadesService::tablasDisponibles()) {
            return;
        }

        $act = ExtActividadesService::scopedQuery()->whereKey($id)->first();
        if (! $act instanceof ExtActividad || ! $act->estaAprobada()) {
            $this->detalleId = null;

            return;
        }

        $this->detalleId = $id;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    public function render()
    {
        $tablasOk = ExtActividadesService::tablasDisponibles();
        $ancla = Carbon::parse($this->ancla)->startOfDay();
        $hoy = Carbon::today();

        $celdasMes = [];
        $diasSemana = [];
        $eventosPorDia = [];
        $eventosDia = [];
        $tituloPeriodo = '';

        if ($tablasOk) {
            if ($this->vista === 'mes') {
                $inicioMes = $ancla->copy()->startOfMonth();
                $finMes = $ancla->copy()->endOfMonth();
                $tituloPeriodo = self::nombreMes($inicioMes->month).' '.$inicioMes->year;
                $inicioGrilla = $inicioMes->copy()->startOfWeek(Carbon::MONDAY);
                $finGrilla = $finMes->copy()->endOfWeek(Carbon::SUNDAY);
                $eventos = ExtActividadesService::eventosEnRango($inicioGrilla, $finGrilla);
                $eventosPorDia = self::agruparPorDia($eventos);
                $cursor = $inicioGrilla->copy();
                while ($cursor->lte($finGrilla)) {
                    $key = $cursor->format('Y-m-d');
                    $celdasMes[] = [
                        'ymd' => $key,
                        'dia' => (int) $cursor->day,
                        'fuera' => $cursor->month !== $inicioMes->month,
                        'hoy' => $cursor->isSameDay($hoy),
                        'eventos' => $eventosPorDia[$key] ?? [],
                    ];
                    $cursor->addDay();
                }
            } elseif ($this->vista === 'semana') {
                $inicio = $ancla->copy()->startOfWeek(Carbon::MONDAY);
                $fin = $ancla->copy()->endOfWeek(Carbon::SUNDAY);
                $tituloPeriodo = $inicio->format('d/m/Y').' — '.$fin->format('d/m/Y');
                $eventos = ExtActividadesService::eventosEnRango($inicio, $fin);
                $eventosPorDia = self::agruparPorDia($eventos);
                $cursor = $inicio->copy();
                while ($cursor->lte($fin)) {
                    $key = $cursor->format('Y-m-d');
                    $diasSemana[] = [
                        'ymd' => $key,
                        'label' => self::nombreDia($cursor->dayOfWeekIso).' '.$cursor->format('d/m'),
                        'hoy' => $cursor->isSameDay($hoy),
                        'eventos' => $eventosPorDia[$key] ?? [],
                    ];
                    $cursor->addDay();
                }
            } else {
                $tituloPeriodo = self::nombreDia($ancla->dayOfWeekIso).' '.$ancla->format('d/m/Y');
                $eventos = ExtActividadesService::eventosEnRango($ancla->copy(), $ancla->copy());
                $eventosDia = self::mapearEventos($eventos);
            }
        }

        $detalle = null;
        if ($tablasOk && $this->detalleId !== null) {
            try {
                $detalle = ExtActividadesService::cargarCompleta($this->detalleId);
                if (! $detalle->estaAprobada()) {
                    $detalle = null;
                    $this->detalleId = null;
                }
            } catch (\Throwable $e) {
                $detalle = null;
                $this->detalleId = null;
            }
        }

        $modoPortalDocente = ProfesorMenuPortal::usaMenuDocentes(Auth::user());
        $layout = $modoPortalDocente ? 'layouts.docente' : layoutMenuStaff();

        return view('livewire.proyectos-extracurriculares.calendario', [
            'tablasOk' => $tablasOk,
            'mensajeTabla' => $tablasOk ? '' : ExtActividadesService::mensajeTablasFaltantes(),
            'celdasMes' => $celdasMes,
            'diasSemana' => $diasSemana,
            'eventosDia' => $eventosDia,
            'tituloPeriodo' => $tituloPeriodo,
            'detalle' => $detalle,
            'nombresDias' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            'rutaIndexProyectos' => $modoPortalDocente
                ? 'portalDocente.proyectosExtracurriculares.index'
                : (tienePermiso(PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_APROBAR) ? 'proyectosExtracurriculares.gestion' : null),
        ])->layout($layout, ['pageTitle' => 'Calendario escolar']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\ExtFecha>  $eventos
     * @return array<string, list<array{id: int, nombre: string, hora: string}>>
     */
    private static function agruparPorDia($eventos): array
    {
        $out = [];
        foreach (self::mapearEventos($eventos) as $ev) {
            $out[$ev['ymd']][] = $ev;
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\ExtFecha>  $eventos
     * @return list<array{id: int, nombre: string, hora: string, ymd: string, lugar: string}>
     */
    private static function mapearEventos($eventos): array
    {
        $out = [];
        foreach ($eventos as $f) {
            $act = $f->actividad;
            if (! $act instanceof ExtActividad) {
                continue;
            }
            $ini = ExtActividadesService::formatearHora((string) ($f->hora_inicio ?? ''));
            $fin = ExtActividadesService::formatearHora((string) ($f->hora_fin ?? ''));
            $hora = trim($ini.($ini !== '' && $fin !== '' ? '–' : '').$fin);
            $out[] = [
                'id' => (int) $act->id,
                'nombre' => (string) $act->nombre,
                'hora' => $hora,
                'ymd' => $f->fecha instanceof Carbon ? $f->fecha->format('Y-m-d') : (string) $f->fecha,
                'lugar' => (string) ($act->lugar ?? ''),
            ];
        }

        return $out;
    }

    private static function nombreMes(int $mes): string
    {
        return [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ][$mes] ?? '';
    }

    private static function nombreDia(int $iso): string
    {
        return [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
            5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
        ][$iso] ?? '';
    }
}
