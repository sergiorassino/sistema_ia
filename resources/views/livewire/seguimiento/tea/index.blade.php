<div class="se-page max-w-6xl">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Asistencia estudiantes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Gestión de TEA</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Año lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
        </div>
    </section>

    @if (! $tablasDisponibles)
        <div class="se-card px-5 py-8">
            <p class="text-center text-sm text-amber-800">
                No hay tablas de registros TEA (<code class="text-xs">reinco2025</code> / <code class="text-xs">reinco2025_tipo</code>)
                en esta base de datos. Contacte al administrador del sistema.
            </p>
        </div>
    @else
        <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
            <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="se-tea-curso" class="form-label">Curso</label>
                    <select id="se-tea-curso" wire:model.live="idCurso" class="form-select mt-1.5">
                        <option value="">— Seleccione —</option>
                        @foreach ($cursos as $c)
                            <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="se-tea-alumno" class="form-label">Alumno</label>
                    <select id="se-tea-alumno" wire:model.live="idMatricula" class="form-select mt-1.5" @disabled(! $idCurso)>
                        <option value="">— Seleccione —</option>
                        @foreach ($alumnos as $a)
                            <option value="{{ $a->id }}">{{ trim(($a->apellido ?? '').', '.($a->nombre ?? '')) }}{{ $a->dni ? ' · DNI '.$a->dni : '' }}</option>
                        @endforeach
                    </select>
                    @if ($idCurso && $alumnos->isEmpty())
                        <p class="mt-1.5 text-xs text-amber-800">No hay matrículas para ese curso en el año actual.</p>
                    @endif
                </div>
            </div>
        </div>

        @if ($matricula)
            <div class="se-card overflow-hidden">
                <div class="border-b border-accent-200 bg-white px-5 py-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-neutral-900">
                                {{ $matricula->legajo?->apellido }}, {{ $matricula->legajo?->nombre }}
                            </p>
                            <p class="mt-0.5 text-xs text-neutral-500">
                                {{ $matricula->curso?->nombreParaListado() ?? '—' }} · Matrícula #{{ $matricula->id }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-nav-contexto-estudiante
                                destino="seguimiento.tea.create"
                                :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::SEGUIMIENTO_TEA"
                                :matricula="$matricula->id"
                                :curso="$matricula->idCursos"
                                class="inline">
                                <span class="btn-primary btn-sm">+ Nuevo registro de TEA</span>
                            </x-nav-contexto-estudiante>
                        </div>
                    </div>
                </div>

                <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                    <table class="se-matriz-list-tabla se-grid-pocos-campos min-w-[42rem]">
                        <thead class="bg-accent-50">
                            <tr>
                                <th class="table-header w-28">Fecha</th>
                                <th class="table-header">Situación TEA</th>
                                <th class="table-header">Observaciones</th>
                                <th class="table-header whitespace-nowrap text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-200 bg-white">
                            @forelse ($registros as $r)
                                <tr class="transition-colors hover:bg-accent-50/60">
                                    <td class="table-cell font-mono">{{ $r->fecha?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="table-cell">{{ $r->etiquetaTipo() }}</td>
                                    <td class="table-cell">
                                        <div class="line-clamp-2 max-w-md">{{ $r->obs ?? '—' }}</div>
                                    </td>
                                    <td class="table-cell whitespace-nowrap">
                                        <div class="flex flex-nowrap items-center justify-end gap-1">
                                            <a class="btn-secondary btn-sm" href="{{ route('seguimiento.tea.edit', ['id' => $r->id]) }}">
                                                Editar
                                            </a>
                                            <button type="button"
                                                    class="btn-danger btn-sm"
                                                    x-on:click="window.seSwalConfirmar('¿Confirma borrar este registro TEA?', 'Confirmar borrado', { confirmButtonText: 'Sí, borrar' }).then((ok) => { if (ok) $wire.borrar({{ (int) $r->id }}); })">
                                                Borrar
                                            </button>
                                            @if (tenantTeaRegistroPdfDisponible((int) $r->idReinco_tipo))
                                                <a class="btn-secondary btn-sm"
                                                   href="{{ route('seguimiento.tea.registro.pdf', ['id' => $r->id]) }}"
                                                   target="_blank"
                                                   rel="noopener">
                                                    PDF
                                                </a>
                                            @else
                                                <span class="btn-secondary btn-sm cursor-not-allowed opacity-50"
                                                      title="Plantilla PDF no configurada para esta situación">
                                                    PDF
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="table-cell py-12 text-center text-sm text-neutral-500">
                                        Sin registros TEA para esta matrícula en el año actual.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="se-card px-5 py-10">
                <p class="text-center text-sm text-neutral-600">
                    Seleccioná un curso y un alumno para ver y gestionar sus registros TEA del año actual.
                </p>
            </div>
        @endif
    @endif

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => {
            window.seSwalExito(mensaje ?? 'Operación realizada.');
        });
        $wire.on('se-swal-error', ({ mensaje }) => {
            window.seSwalError(mensaje ?? 'No se pudo completar la operación.');
        });
    </script>
    @endscript
</div>
