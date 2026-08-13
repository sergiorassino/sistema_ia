<div class="se-page max-w-7xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Matrícula web</p>
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Bloqueos de matrícula</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() ?? '—' }}
                </p>
            </div>
        </div>
    </section>

    @php
        $cursoFiltro = $idCurso > 0 ? $opcionesCurso->firstWhere('id', $idCurso) : null;
        $etiquetaCursoFiltro = is_array($cursoFiltro)
            ? (string) ($cursoFiltro['etiqueta'] ?? 'el curso seleccionado')
            : ($idCurso > 0 ? 'el curso seleccionado' : 'el nivel activo');
        $sujetoMasivo = $totalAlumnos === 1
            ? '1 alumno regular'
            : $totalAlumnos.' alumnos regulares';
        $alcanceMasivo = $sujetoMasivo.' de '.$etiquetaCursoFiltro.' (listado actual, todas las páginas)';
        $msgBloquearPed = '¿Aplicar bloqueo pedagógico a '.$alcanceMasivo.'?';
        $msgDesbloquearPed = '¿Quitar el bloqueo pedagógico a '.$alcanceMasivo.'?';
        $msgBloquearAdm = '¿Aplicar bloqueo administrativo a '.$alcanceMasivo.'?';
        $msgDesbloquearAdm = '¿Quitar el bloqueo administrativo a '.$alcanceMasivo.'?';
    @endphp

    <div class="se-toolbar mt-6 flex-col !items-stretch gap-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1 max-w-xl">
                <label for="bloqueos-curso" class="form-label">Mostrar alumnos</label>
                <select id="bloqueos-curso"
                        wire:model.live="idCurso"
                        class="form-select mt-1.5">
                    <option value="0">Todos los cursos (orden alfabético)</option>
                    @foreach ($opcionesCurso as $opcion)
                        <option value="{{ $opcion['id'] }}">{{ $opcion['etiqueta'] }}</option>
                    @endforeach
                </select>
            </div>
            <span class="se-pill tabular-nums">{{ $totalAlumnos }} alumno{{ $totalAlumnos === 1 ? '' : 's' }} regular{{ $totalAlumnos === 1 ? '' : 'es' }}</span>
        </div>

        @if ($totalAlumnos > 0)
            <div class="flex flex-col gap-4 border-t border-accent-200 pt-4 sm:flex-row sm:flex-wrap sm:items-end">
                <div>
                    <p class="form-label">Bloqueo pedagógico (masivo)</p>
                    <div class="mt-1.5 flex flex-wrap gap-2">
                        <button type="button"
                                x-on:click="window.seSwalConfirmar(@js($msgBloquearPed), 'Bloqueo pedagógico', { confirmButtonText: 'Sí, bloquear', icon: 'warning' }).then(ok => ok && $wire.aplicarBloqueoMasivo('bloqmatr', true))"
                                wire:loading.attr="disabled"
                                wire:target="aplicarBloqueoMasivo"
                                class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 disabled:opacity-60">
                            Bloquear todos
                        </button>
                        <button type="button"
                                x-on:click="window.seSwalConfirmar(@js($msgDesbloquearPed), 'Desbloqueo pedagógico', { confirmButtonText: 'Sí, desbloquear' }).then(ok => ok && $wire.aplicarBloqueoMasivo('bloqmatr', false))"
                                wire:loading.attr="disabled"
                                wire:target="aplicarBloqueoMasivo"
                                class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-3 py-2 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-500 hover:bg-accent-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 disabled:opacity-60">
                            Desbloquear todos
                        </button>
                    </div>
                </div>
                <div>
                    <p class="form-label">Bloqueo administrativo (masivo)</p>
                    <div class="mt-1.5 flex flex-wrap gap-2">
                        <button type="button"
                                x-on:click="window.seSwalConfirmar(@js($msgBloquearAdm), 'Bloqueo administrativo', { confirmButtonText: 'Sí, bloquear', icon: 'warning' }).then(ok => ok && $wire.aplicarBloqueoMasivo('bloqadmi', true))"
                                wire:loading.attr="disabled"
                                wire:target="aplicarBloqueoMasivo"
                                class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 disabled:opacity-60">
                            Bloquear todos
                        </button>
                        <button type="button"
                                x-on:click="window.seSwalConfirmar(@js($msgDesbloquearAdm), 'Desbloqueo administrativo', { confirmButtonText: 'Sí, desbloquear' }).then(ok => ok && $wire.aplicarBloqueoMasivo('bloqadmi', false))"
                                wire:loading.attr="disabled"
                                wire:target="aplicarBloqueoMasivo"
                                class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-3 py-2 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-500 hover:bg-accent-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 disabled:opacity-60">
                            Desbloquear todos
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="se-card mt-6 overflow-hidden p-0">
        <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Alumnos regulares del nivel</p>
            <p class="mt-1 text-sm text-neutral-600">
                Haga clic en <strong>SÍ</strong> o <strong>NO</strong> para alternar cada bloqueo. Los cambios se guardan al instante.
                Las acciones masivas aplican a todos los alumnos del filtro actual (todas las páginas).
            </p>
        </div>

        @if ($opcionesCurso->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-neutral-600">
                No hay cursos cargados para el ciclo lectivo activo en este nivel.
            </div>
        @elseif ($totalAlumnos === 0)
            <div class="px-6 py-12 text-center text-sm text-neutral-600">
                No hay alumnos regulares
                @if ($idCurso > 0)
                    en el curso seleccionado.
                @else
                    en el nivel activo.
                @endif
            </div>
        @else
            <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                <table class="min-w-[48rem] w-full divide-y divide-accent-200 text-sm">
                    <thead class="bg-white">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Apellido</th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Nombre</th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">DNI</th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso actual</th>
                            <th scope="col" class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500 w-36">Bloq. pedagógico</th>
                            <th scope="col" class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500 w-36">Bloq. administrativo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @foreach ($alumnos as $fila)
                            <tr wire:key="bloqueo-mat-{{ $fila['idMatricula'] }}" class="hover:bg-accent-50/60 transition-colors">
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $fila['apellido'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-neutral-800">{{ $fila['nombre'] ?: '—' }}</td>
                                <td class="px-4 py-3 font-mono text-neutral-700">{{ $fila['dni'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-neutral-700">{{ $fila['curso'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                            wire:click="alternarBloqueo({{ $fila['idMatricula'] }}, 'bloqmatr')"
                                            wire:loading.attr="disabled"
                                            wire:target="alternarBloqueo"
                                            @class([
                                                'inline-flex min-w-[3.25rem] items-center justify-center rounded-lg px-3 py-1.5 text-xs font-bold uppercase tracking-wide transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 disabled:opacity-60',
                                                'bg-red-100 text-red-800 ring-1 ring-red-200 hover:bg-red-200' => $fila['bloqmatr'],
                                                'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 hover:bg-emerald-100' => ! $fila['bloqmatr'],
                                            ])
                                            title="Clic para cambiar bloqueo pedagógico">
                                        {{ $fila['bloqmatr'] ? 'SÍ' : 'NO' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                            wire:click="alternarBloqueo({{ $fila['idMatricula'] }}, 'bloqadmi')"
                                            wire:loading.attr="disabled"
                                            wire:target="alternarBloqueo"
                                            @class([
                                                'inline-flex min-w-[3.25rem] items-center justify-center rounded-lg px-3 py-1.5 text-xs font-bold uppercase tracking-wide transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 disabled:opacity-60',
                                                'bg-red-100 text-red-800 ring-1 ring-red-200 hover:bg-red-200' => $fila['bloqadmi'],
                                                'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 hover:bg-emerald-100' => ! $fila['bloqadmi'],
                                            ])
                                            title="Clic para cambiar bloqueo administrativo">
                                        {{ $fila['bloqadmi'] ? 'SÍ' : 'NO' }}
                                    </button>
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

    @script
    <script>
        $wire.on('se-swal-exito', (e) => { window.seSwalExito?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
        $wire.on('se-swal-error', (e) => { window.seSwalError?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
    </script>
    @endscript
</div>
