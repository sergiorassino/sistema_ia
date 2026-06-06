<?php

namespace App\Livewire\Alumnos\Comunicaciones;

use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComunicacionesRepository;
use App\Models\Legajo;
use App\Models\ProfesorTipo;
use App\Support\Comunicaciones\ComCanalRolCatalog;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class NuevoComunicadoFamilia extends Component
{
    public string $vinculo   = '';  // madre|padre|tutor|resp_admin|otro
    public string $asunto    = '';
    public string $contenido = '';
    /** Clave de canal receptor: `tipo:{id}` (profesortipo) */
    public string $rolReceptor = '';

    public array $destinatariosDisponibles = [];
    public ?int $idDestinatario = null;

    /** @var list<array{value:string,label:string}> */
    public array $opcionesRolReceptor = [];

    public array $vinculos = [
        'madre'      => 'Madre',
        'padre'      => 'Padre',
        'tutor'      => 'Tutor/a',
        'resp_admin' => 'Responsable Administrativo/a',
        'otro'       => 'Otro responsable',
    ];

    public function mount(): void
    {
        $idNivel = (int) (studentCtx()->idNivel ?? 0);
        $claves  = CanalesPolicy::receptoresPermitidosParaIniciar(ComCanalRolCatalog::CLAVE_FAMILIA, $idNivel);

        $catalogo = ComCanalRolCatalog::catalogo();
        $opciones = [];
        foreach ($claves as $clave) {
            if ($clave === ComCanalRolCatalog::CLAVE_FAMILIA || ! isset($catalogo[$clave])) {
                continue;
            }
            $opciones[] = [
                'value' => $clave,
                'label' => $catalogo[$clave],
            ];
        }
        usort($opciones, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));
        $this->opcionesRolReceptor = $opciones;
    }

    public function updatedRolReceptor(): void
    {
        $this->idDestinatario = null;
        $this->destinatariosDisponibles = [];

        if ($this->rolReceptor === '') {
            return;
        }

        $parsed = ComCanalRolCatalog::parseClave($this->rolReceptor);
        $idTipo = $parsed['id_tipo_prof'];
        if ($idTipo === null) {
            return;
        }

        $ctx = studentCtx();
        $idNivel  = (int) $ctx->idNivel;
        $idLegajo = (int) $ctx->idLegajo;
        $idTerlec = (int) $ctx->idTerlec;

        $tipoStr = trim((string) (ProfesorTipo::query()->whereKey($idTipo)->value('tipo') ?? ''));
        $t       = mb_strtolower($tipoStr);

        if (str_contains($t, 'preceptor')) {
            $this->destinatariosDisponibles = ComunicacionesRepository::preceptoresDeCurso(
                $idLegajo, $idNivel, $idTerlec
            );
        } else {
            $this->destinatariosDisponibles = ComunicacionesRepository::profesoresDelNivelParaSelectorPorIdTipoProf(
                $idNivel,
                $idTipo,
                '',
                200
            );
        }
    }

    public function enviar(): void
    {
        $key = 'com:nuevo:fam:' . (auth('alumno')->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, config('comunicaciones.rate_limit_max', 20))) {
            $this->addError('contenido', 'Demasiados envíos. Espere un momento.');
            return;
        }
        RateLimiter::hit($key, config('comunicaciones.rate_limit_decay', 60));

        $valoresRol = array_column($this->opcionesRolReceptor, 'value');

        $this->validate([
            'vinculo'        => 'required|in:madre,padre,tutor,resp_admin,otro',
            'rolReceptor'    => ['required', 'string', Rule::in($valoresRol)],
            'idDestinatario' => 'required|integer',
            'asunto'         => 'required|string|max:' . config('comunicaciones.max_asunto', 200),
            'contenido'      => 'required|string|max:' . config('comunicaciones.max_contenido', 2000),
        ]);

        $ctx      = studentCtx();
        $idLegajo = (int) $ctx->idLegajo;
        $idNivel  = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        if (! CanalesPolicy::puedeIniciar(ComCanalRolCatalog::CLAVE_FAMILIA, $this->rolReceptor, $idNivel)) {
            $this->addError('rolReceptor', 'La familia no puede iniciar conversaciones con ese destinatario en este momento.');
            return;
        }

        $legajo = Legajo::find($idLegajo);
        [$nombreSnap, $dniSnap] = $this->snapshotDatosFamiliares($legajo, $this->vinculo);

        $mediosCanal = CanalesPolicy::mediosPermitidos(
            ComCanalRolCatalog::CLAVE_FAMILIA,
            $this->rolReceptor,
            $idNivel
        );

        ComunicacionesRepository::crearHiloConMensaje([
            'asunto'                   => $this->asunto,
            'contenido'                => $this->contenido,
            'scope'                    => 'alumno',
            'id_legajos'               => [],
            'id_curso'                 => null,
            'id_nivel'                 => $idNivel,
            'id_terlec'                => $idTerlec,
            'creado_por_tipo'          => 'familia',
            'creado_por_id'            => $idLegajo,
            'creado_por_rol'           => ComCanalRolCatalog::CLAVE_FAMILIA,
            'rol_receptor'             => $this->rolReceptor,
            'vinculo_familiar'         => $this->vinculo,
            'nombre_remitente'         => $nombreSnap,
            'dni_remitente'            => $dniSnap,
            'destinatarios_profesores' => [$this->idDestinatario],
            'familia_puede_responder'  => true,
        ], $mediosCanal);

        session()->flash('success', 'Comunicado enviado.');
        $this->redirectRoute('alumnos.comunicaciones.index');
    }

    private function snapshotDatosFamiliares(?Legajo $legajo, string $vinculo): array
    {
        if ($legajo === null) {
            return ['Familiar', ''];
        }
        return match ($vinculo) {
            'madre'      => [trim((string) $legajo->nombremad), (string) ($legajo->dnimad ?? '')],
            'padre'      => [trim((string) $legajo->nombrepad), (string) ($legajo->dnipad ?? '')],
            'tutor'      => [trim((string) $legajo->nombretut), (string) ($legajo->dnitut ?? '')],
            'resp_admin' => [trim((string) $legajo->respAdmiNom), (string) ($legajo->respAdmiDni ?? '')],
            default      => ['Familiar', ''],
        };
    }

    public function render()
    {
        return view('comunicaciones::livewire.alumnos.comunicaciones.nuevo-comunicado-familia', [
            'maxContenido' => config('comunicaciones.max_contenido', 2000),
            'maxAsunto'    => config('comunicaciones.max_asunto', 200),
        ])->layout('layouts.alumno', ['pageTitle' => 'Nuevo comunicado']);
    }
}
