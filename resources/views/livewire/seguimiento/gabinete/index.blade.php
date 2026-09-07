<div class="se-page max-w-7xl">
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
                <p class="se-eyebrow">Seguimiento</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Gabinete de orientación</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Año lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
        </div>
    </section>

    @if (! $tablasDisponibles)
        <div class="se-card px-5 py-8">
            <p class="text-center text-sm text-amber-800">
                No hay tablas de gabinete (<code class="text-xs">gabinete</code> / <code class="text-xs">gabinetetipo</code>)
                en esta base de datos. Contacte al administrador del sistema.
            </p>
        </div>
    @else
        <div class="se-toolbar mt-6 flex-col !items-stretch gap-4">
            <div class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="se-gab-curso" class="form-label">Curso</label>
                    <select id="se-gab-curso" wire:model.live="idCurso" class="form-select mt-1.5">
                        <option value="0">Todos los cursos</option>
                        @foreach ($cursos as $c)
                            <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="se-gab-vista" class="form-label">Mostrar</label>
                    <select id="se-gab-vista" wire:model.live="vista" class="form-select mt-1.5" @disabled((int) $idCurso > 0)>
                        <option value="curso">Agrupados por curso</option>
                        <option value="alfa">Orden alfabético</option>
                    </select>
                </div>
                <div>
                    <label for="se-gab-color" class="form-label">Color</label>
                    <select id="se-gab-color" wire:model.live="filtroColor" class="form-select mt-1.5" @disabled(! $marcaDisponible)>
                        <option value="">Todos</option>
                        <option value="verde">Solo verde</option>
                        <option value="amarillo">Solo amarillo</option>
                        <option value="rojo">Solo rojo</option>
                        <option value="sin">Sin marca</option>
                    </select>
                    @if (! $marcaDisponible)
                        <p class="mt-1.5 text-xs text-amber-800">Falta la tabla <code class="text-[10px]">gabinetemarca</code> para filtrar y marcar colores.</p>
                    @endif
                </div>
                <div>
                    <label for="se-gab-buscar" class="form-label">Buscar</label>
                    <input id="se-gab-buscar"
                           type="search"
                           wire:model.live.debounce.400ms="busqueda"
                           placeholder="Apellido, nombre o DNI…"
                           class="form-input mt-1.5"
                           autocomplete="off">
                </div>
            </div>
            <div class="flex justify-end">
                <span class="se-pill tabular-nums">{{ $alumnos->total() }} alumno{{ $alumnos->total() === 1 ? '' : 's' }}</span>
            </div>
        </div>

        <div class="se-card mt-6 overflow-hidden p-0">
            @if ($cursos->isEmpty())
                <div class="px-6 py-12 text-center text-sm text-neutral-600">
                    No hay cursos cargados para el ciclo lectivo activo en este nivel.
                </div>
            @elseif ($alumnos->isEmpty())
                <div class="px-6 py-12 text-center text-sm text-neutral-600">
                    No hay alumnos
                    @if (trim($busqueda) !== '' || $filtroColor !== '')
                        que coincidan con el filtro
                    @endif
                    @if ((int) $idCurso > 0)
                        en el curso seleccionado.
                    @else
                        en el nivel activo.
                    @endif
                </div>
            @else
                @php
                    $agrupar = $vista === 'curso' && (int) $idCurso === 0;
                    $mostrarCurso = $vista === 'alfa' && (int) $idCurso === 0;
                    $colspan = $mostrarCurso ? 5 : 4;
                    $cursoPrev = null;
                @endphp
                <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                    <table class="se-matriz-list-tabla se-grid-pocos-campos min-w-[40rem]">
                        <thead class="bg-accent-50">
                            <tr>
                                @if ($mostrarCurso)
                                    <th class="table-header">Curso</th>
                                @endif
                                <th class="table-header">Apellido y nombre</th>
                                <th class="table-header">DNI</th>
                                <th class="table-header whitespace-nowrap">Marca</th>
                                <th class="table-header whitespace-nowrap text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-200 bg-white">
                            @foreach ($alumnos as $fila)
                                @php
                                    $color = \App\Support\Seguimiento\GabineteSemaforo::normalizar($fila->colorGabinete ?? 0);
                                    $claseNombre = \App\Support\Seguimiento\GabineteSemaforo::claseFondoNombre($color);
                                    $cursoLabel = trim((string) ($fila->cursec ?? ''));
                                @endphp
                                @if ($agrupar && $cursoLabel !== $cursoPrev)
                                    <tr class="bg-accent-50">
                                        <td colspan="{{ $colspan }}" class="table-cell py-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-600">
                                            {{ $cursoLabel !== '' ? $cursoLabel : 'Sin curso' }}
                                        </td>
                                    </tr>
                                    @php $cursoPrev = $cursoLabel; @endphp
                                @endif
                                <tr class="transition-colors hover:bg-accent-50/60" wire:key="gab-alu-{{ (int) $fila->id }}">
                                    @if ($mostrarCurso)
                                        <td class="table-cell whitespace-nowrap">{{ $cursoLabel !== '' ? $cursoLabel : '—' }}</td>
                                    @endif
                                    <td class="table-cell">
                                        <span @class(['se-gabinete-nombre inline-block rounded-lg px-2 py-1 font-semibold', $claseNombre])>
                                            {{ trim(($fila->apellido ?? '').', '.($fila->nombre ?? '')) }}
                                        </span>
                                    </td>
                                    <td class="table-cell font-mono text-xs">{{ $fila->dni ?: '—' }}</td>
                                    <td class="table-cell">
                                        @if ($marcaDisponible)
                                            <div class="flex items-center gap-1.5" role="group" aria-label="Marca de color">
                                                <button type="button"
                                                        wire:click="marcarColor({{ (int) $fila->id }}, {{ \App\Support\Seguimiento\GabineteSemaforo::VERDE }})"
                                                        title="Verde"
                                                        class="se-gabinete-chip se-gabinete-chip--verde {{ $color === \App\Support\Seguimiento\GabineteSemaforo::VERDE ? 'is-active' : '' }}">
                                                    <span class="sr-only">Verde</span>
                                                </button>
                                                <button type="button"
                                                        wire:click="marcarColor({{ (int) $fila->id }}, {{ \App\Support\Seguimiento\GabineteSemaforo::AMARILLO }})"
                                                        title="Amarillo"
                                                        class="se-gabinete-chip se-gabinete-chip--amarillo {{ $color === \App\Support\Seguimiento\GabineteSemaforo::AMARILLO ? 'is-active' : '' }}">
                                                    <span class="sr-only">Amarillo</span>
                                                </button>
                                                <button type="button"
                                                        wire:click="marcarColor({{ (int) $fila->id }}, {{ \App\Support\Seguimiento\GabineteSemaforo::ROJO }})"
                                                        title="Rojo"
                                                        class="se-gabinete-chip se-gabinete-chip--rojo {{ $color === \App\Support\Seguimiento\GabineteSemaforo::ROJO ? 'is-active' : '' }}">
                                                    <span class="sr-only">Rojo</span>
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-xs text-neutral-400">—</span>
                                        @endif
                                    </td>
                                    <td class="table-cell whitespace-nowrap text-right">
                                        <x-nav-contexto-estudiante
                                            destino="seguimiento.gabinete.alumno"
                                            :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::SEGUIMIENTO_GABINETE"
                                            :matricula="$fila->id"
                                            :curso="$fila->idCursos"
                                            class="inline">
                                            <span class="btn-primary btn-sm">Seguimiento</span>
                                        </x-nav-contexto-estudiante>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($alumnos->hasPages())
                    <div class="se-matriz-list-footer">
                        {{ $alumnos->links('vendor.pagination.se-compact') }}
                    </div>
                @endif
            @endif
        </div>
    @endif

    @script
    <script>
        $wire.on('se-swal-error', ({ mensaje }) => {
            window.seSwalError(mensaje ?? 'No se pudo completar la operación.');
        });
    </script>
    @endscript
</div>
