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
    @if (session('error'))
        <div class="se-soft-card flex items-center gap-3 border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <svg class="h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Seguimiento</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Disciplinario</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Año lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="se-disc-curso" class="form-label">Curso</label>
                <select id="se-disc-curso" wire:model.live="idCurso" class="form-select mt-1.5">
                    <option value="">— Seleccione —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="se-disc-alumno" class="form-label">Alumno</label>
                <select id="se-disc-alumno" wire:model.live="idMatricula" class="form-select mt-1.5" @disabled(! $idCurso)>
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
                            destino="seguimiento.disciplinario.antecedentes"
                            :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO_ANTECEDENTES"
                            :matricula="$matricula->id"
                            class="inline">
                            <span class="btn-secondary btn-sm">Antecedentes</span>
                        </x-nav-contexto-estudiante>
                        <x-nav-contexto-estudiante
                            destino="seguimiento.disciplinario.create"
                            :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO"
                            :matricula="$matricula->id"
                            class="inline">
                            <span class="btn-primary btn-sm">+ Nueva sanción</span>
                        </x-nav-contexto-estudiante>
                    </div>
                </div>
            </div>

            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <table class="min-w-[980px] border-collapse sm:min-w-full">
                        <thead class="bg-accent-50">
                            <tr>
                                <th class="table-header w-28">Fecha</th>
                                <th class="table-header w-56">Tipo</th>
                                <th class="table-header w-28">Cantidad</th>
                                <th class="table-header">Motivo</th>
                                <th class="table-header w-48">Solicitada por</th>
                                <th class="table-header whitespace-nowrap text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-200 bg-white">
                            @forelse ($sanciones as $s)
                                <tr class="transition-colors hover:bg-accent-50/60">
                                    <td class="table-cell font-mono">{{ $s->fecha?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="table-cell">{{ $s->tipo?->tipo ?? ('#'.$s->idTipoSancion) }}</td>
                                    <td class="table-cell font-mono">{{ $s->cantidad ?? '—' }}</td>
                                    <td class="table-cell">
                                        <div class="line-clamp-2">{{ $s->motivo ?? '—' }}</div>
                                    </td>
                                    <td class="table-cell">{{ $s->solipor ?: ($s->profesor?->nombre_completo ?? '—') }}</td>
                                    <td class="table-cell whitespace-nowrap">
                                        <div class="flex flex-nowrap items-center justify-end gap-1">
                                            <a class="btn-secondary btn-sm shrink-0"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               title="Imprimir comunicado"
                                               href="{{ route('seguimiento.disciplinario.print', ['id' => $s->id]) }}">
                                                Imprimir
                                            </a>
                                            @php
                                                $tieneActa = ! \App\Support\Seguimiento\SancionActaHtmlSanitizer::estaVacio($s->acta ?? null);
                                            @endphp
                                            @if ($tieneActa)
                                                <a class="btn-sm inline-flex shrink-0 items-center gap-1 rounded-lg border border-primary-300 bg-primary-50 px-2 py-1 text-xs font-semibold text-primary-700 hover:bg-primary-100"
                                                   title="Acta cargada — clic para editar"
                                                   href="{{ route('seguimiento.disciplinario.acta', ['id' => $s->id]) }}">
                                                    <svg class="h-3.5 w-3.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    Acta
                                                </a>
                                            @else
                                                <a class="btn-secondary btn-sm shrink-0"
                                                   title="Cargar acta (opcional)"
                                                   href="{{ route('seguimiento.disciplinario.acta', ['id' => $s->id]) }}">
                                                    Acta
                                                </a>
                                            @endif
                                            {{-- Notif. Padres: solo si el tipo lo permite --}}
                                            @if ($s->tipo?->permiteNotifPadres ?? true)
                                            @if ($s->comunicadaPadres)
                                                <button type="button"
                                                        title="Ya notificada — clic para reenviar"
                                                        x-on:click="seSwalConfirmar('Esta sanción ya fue notificada a la familia. ¿Deseás reenviar la notificación?', 'Reenviar notificación').then(ok => ok && $wire.notificarPadres({{ $s->id }}))"
                                                        class="btn-sm inline-flex shrink-0 items-center gap-1 rounded-lg border border-primary-300 bg-primary-50 px-2 py-1 text-xs font-semibold text-primary-700 hover:bg-primary-100">
                                                    <svg class="h-3.5 w-3.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    Notif. Padres
                                                </button>
                                            @else
                                                <button type="button"
                                                        title="Notificar a los padres"
                                                        x-on:click="seSwalConfirmar('¿Enviar notificación a la familia sobre esta sanción?', 'Notificar padres').then(ok => ok && $wire.notificarPadres({{ $s->id }}))"
                                                        class="btn-secondary btn-sm shrink-0">
                                                    Notif. Padres
                                                </button>
                                            @endif
                                            @endif {{-- permiteNotifPadres --}}
                                            <a class="btn-secondary btn-sm shrink-0" href="{{ route('seguimiento.disciplinario.edit', ['id' => $s->id]) }}">
                                                Editar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="table-cell py-12 text-center text-sm text-neutral-500">
                                        Sin sanciones registradas para esta matrícula en el año actual.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="se-card px-5 py-10">
            <p class="text-center text-sm text-neutral-600">
                Seleccioná un curso y un alumno para ver las sanciones del año actual.
            </p>
        </div>
    @endif

</div>

@script
<script>
    $wire.on('se-swal-exito', (e) => { window.seSwalExito?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
    $wire.on('se-swal-error', (e) => { window.seSwalError?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
    $wire.on('se-swal-aviso', (e) => { window.seSwalAviso?.(e.mensaje ?? e[0]?.mensaje ?? '', e.titulo ?? e[0]?.titulo ?? 'Atención'); });
</script>
@endscript
