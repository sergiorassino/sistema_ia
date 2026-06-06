<?php

namespace App\Livewire\Aspirantes;

use App\Models\AspiCursoModelo;
use App\Models\Aspicurso;
use App\Models\Aspirante;
use App\Models\Aspiento;
use App\Models\CampoAspirante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.aspirantes-publico')]
class RegistroAspiranteForm extends Component
{
    public string $token = '';

    /** @var array<string, mixed> */
    public array $datos = [];

    public ?int $idCursoModelo = null;

    /** Honeypot: si llega con valor, descartamos. */
    public string $sitio_web = '';

    public bool $enviado = false;

    public function mount(string $token): void
    {
        $this->token = $token;

        $instancia = $this->instancia();
        if (! $instancia) {
            abort(404);
        }
        if (! $instancia->aceptaRegistros()) {
            abort(410, 'La inscripción no está disponible.');
        }

        foreach ($this->campos() as $c) {
            $this->datos[$c['columna']] = '';
        }
    }

    public function instancia(): ?Aspiento
    {
        return Aspiento::query()->with('terlec')->where('token', $this->token)->first();
    }

    /**
     * @return list<array{columna: string, etiqueta: string, ayuda: ?string, obligatorio: bool, opciones: list<string>, es_fecha: bool}>
     */
    public function campos(): array
    {
        $instancia = $this->instancia();
        $idNivel = $instancia ? (int) $instancia->idNivel : null;

        return CampoAspirante::camposParaFormularioPublico($idNivel);
    }

    /**
     * @return \Illuminate\Support\Collection<int, AspiCursoModelo>
     */
    public function cursosHabilitados()
    {
        $instancia = $this->instancia();
        if (! $instancia) {
            return collect();
        }

        $idAspiento = (int) $instancia->getKey();
        $q = Aspicurso::query()
            ->where('idAspiento', $idAspiento)
            ->whereNotNull('idCursoModelo');

        // Marcado como habilitado en alguno de los dos flags (nuevo `activo` o legacy `habilitado`).
        $columnasFlag = array_values(array_filter([
            Schema::hasColumn('aspicursos', 'activo')     ? 'activo'     : null,
            Schema::hasColumn('aspicursos', 'habilitado') ? 'habilitado' : null,
        ]));
        if ($columnasFlag !== []) {
            $q->where(function ($q) use ($columnasFlag) {
                foreach ($columnasFlag as $col) {
                    $q->orWhere($col, 1);
                }
            });
        }

        $ids = $q->pluck('idCursoModelo')->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();

        if ($ids === []) {
            Log::warning('[aspirantes] cursosHabilitados sin aspicursos habilitados', [
                'idAspiento' => $idAspiento,
                'token'      => $this->token,
                'flags'      => $columnasFlag,
            ]);

            return collect();
        }

        // No filtramos por aspicursosmodelo.activo: si el operador habilitó el modelo en la instancia,
        // confiamos en eso (la baja real se hace desmarcando en InstanciaForm).
        $modelos = AspiCursoModelo::query()
            ->whereIn('id', $ids)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        if ($modelos->isEmpty()) {
            Log::warning('[aspirantes] cursosHabilitados sin coincidencias en aspicursosmodelo', [
                'idAspiento' => $idAspiento,
                'idsBuscados' => $ids,
            ]);
        }

        return $modelos;
    }

    public function registrar(): void
    {
        if ($this->sitio_web !== '') {
            $this->enviado = true;

            return;
        }

        $rateKey = 'aspirantes-registro:'.(request()->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $this->addError('_registro', 'Demasiados intentos de envío. Esperá un minuto e intentá de nuevo.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $instancia = $this->instancia();
        if (! $instancia || ! $instancia->aceptaRegistros()) {
            abort(410, 'La inscripción no está disponible.');
        }

        $cursosOk = $this->cursosHabilitados()->pluck('id')->map(fn ($v) => (int) $v)->all();
        if ($cursosOk === []) {
            $this->addError('idCursoModelo', 'No hay cursos disponibles para inscripción en este momento.');

            return;
        }

        $rules = [
            'idCursoModelo' => ['required', 'integer', 'in:'.implode(',', $cursosOk)],
        ];
        foreach ($this->campos() as $c) {
            $key = 'datos.'.$c['columna'];
            $base = $c['obligatorio'] ? ['required'] : ['nullable'];
            $opciones = $c['opciones'] ?? [];
            if ($opciones !== []) {
                $rules[$key] = array_merge($base, ['string', Rule::in($opciones)]);
            } elseif ($c['es_fecha'] ?? false) {
                $rules[$key] = array_merge($base, ['date']);
            } else {
                $rules[$key] = array_merge($base, ['string', 'max:255']);
            }
        }

        $this->validate($rules, [
            'idCursoModelo.required' => 'Elegí la sala, grado o curso de destino.',
            'idCursoModelo.in'       => 'El curso elegido no está disponible.',
            'datos.*.date'           => 'La fecha no es válida.',
        ]);

        DB::transaction(function () use ($instancia) {
            $valores = [];
            foreach ($this->campos() as $c) {
                $col = $c['columna'];
                $val = $this->datos[$col] ?? null;
                $valores[$col] = is_string($val) ? trim($val) : $val;
            }

            $valores['idAspiento']    = (int) $instancia->getKey();
            $valores['idCursoModelo'] = (int) $this->idCursoModelo;
            $valores['idNivel']       = (int) $instancia->idNivel;

            // Compatibilidad legacy: el "curso de destino" se guarda en aspirantes.destino si existe.
            if (Schema::hasColumn('aspirantes', 'destino')) {
                $modelo = AspiCursoModelo::query()->whereKey((int) $this->idCursoModelo)->first();
                $valores['destino'] = $modelo ? mb_substr((string) $modelo->nombre, 0, 255) : (string) $this->idCursoModelo;
            }

            if (Schema::hasColumn('aspirantes', 'created_at')) {
                $valores['created_at'] = now();
            }
            if (Schema::hasColumn('aspirantes', 'ip_origen')) {
                $valores['ip_origen'] = request()->ip();
            }
            if (Schema::hasColumn('aspirantes', 'user_agent')) {
                $valores['user_agent'] = mb_substr((string) request()->userAgent(), 0, 255);
            }

            Aspirante::query()->create($valores);
        });

        $this->enviado = true;
    }

    public function render()
    {
        return view('livewire.aspirantes.registro-form', [
            'instancia' => $this->instancia(),
            'campos'    => $this->campos(),
            'cursos'    => $this->cursosHabilitados(),
        ]);
    }
}
