<div class="mx-auto w-full max-w-6xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Cuaderno de seguimiento áulico</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Registro de Situación Áulica</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ $materiaNombre !== '' ? $materiaNombre : 'Materia' }} · {{ $cursoLabel }}
                    · Año {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ route('portalDocente.cuadernoSeguimiento') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver a materias
            </a>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        @if ($alumnos->isEmpty())
            <div class="px-5 py-10 text-center">
                <p class="text-sm text-neutral-600">No hay alumnos matriculados en este curso para el año lectivo actual.</p>
            </div>
        @else
            <div class="w-full overflow-x-auto px-3 py-4 sm:px-4 se-grid-angosta-wrap">
                <table class="se-grid-pocos-campos w-auto table-auto text-sm">
                    <thead class="border-b border-accent-200 bg-accent-50">
                        <tr>
                            <th class="text-left text-[10px] font-semibold uppercase tracking-wider text-neutral-600">Alumno/a</th>
                            <th class="text-left text-[10px] font-semibold uppercase tracking-wider text-neutral-600">DNI</th>
                            <th class="text-right text-[10px] font-semibold uppercase tracking-wider text-neutral-600">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100">
                        @foreach ($alumnos as $a)
                            <tr class="transition-colors hover:bg-accent-50/60">
                                <td class="font-medium text-neutral-800">
                                    {{ trim(($a->apellido ?? '').', '.($a->nombre ?? '')) }}
                                </td>
                                <td class="font-mono text-neutral-600">
                                    {{ $a->dni ?: '—' }}
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <x-nav-contexto-estudiante
                                        destino="portalDocente.cuadernoSeguimiento.alumno"
                                        :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::PORTAL_DOCENTE_CUADERNO"
                                        :matricula="$a->id"
                                        :curso="$cursoId"
                                        :materia="$materiaId"
                                        class="inline">
                                        <span class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm hover:border-primary-500 hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                            Ver
                                        </span>
                                    </x-nav-contexto-estudiante>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
