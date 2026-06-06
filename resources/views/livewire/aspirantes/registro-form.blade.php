<div>
    @if ($enviado)
        <div class="se-card p-8 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-50 text-green-700">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-neutral-900">Registro recibido</h2>
            <p class="mt-2 text-sm text-neutral-600">
                ¡Gracias! Tus datos quedaron registrados. El colegio se comunicará para continuar el proceso.
            </p>
        </div>
    @else
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4 space-y-1">
                @if (! empty($instancia->insti))
                    <p class="text-base font-bold text-neutral-900">{{ $instancia->insti }}</p>
                @endif
                <h2 class="text-lg font-bold text-neutral-900">
                    {{ $instancia->titulo ?: 'Registro de aspirante' }}
                </h2>
                @if (! empty($instancia->titulo3))
                    <p class="text-sm font-medium text-neutral-700">{{ $instancia->titulo3 }}</p>
                @endif
                @if (! empty($instancia->mensaje_publico))
                    <p class="mt-2 text-sm text-neutral-600 whitespace-pre-line">{{ $instancia->mensaje_publico }}</p>
                @endif
                @if ($instancia->fechhasta)
                    <p class="mt-2 text-xs text-neutral-500">
                        Las inscripciones están abiertas hasta el {{ $instancia->fechhasta->format('d/m/Y') }}.
                    </p>
                @endif
            </div>

            <form wire:submit.prevent="registrar" class="space-y-5 px-5 py-5">
                {{-- Honeypot: campo invisible para bots --}}
                <div aria-hidden="true" class="absolute -left-[10000px] top-auto h-px w-px overflow-hidden">
                    <label>Si sos humano, dejá este campo vacío:
                        <input type="text" wire:model.defer="sitio_web" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                @if ($cursos->isEmpty())
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        En este momento no hay cursos disponibles para inscripción.
                    </div>
                    @if (config('app.debug'))
                        <details class="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-xs text-neutral-700">
                            <summary class="cursor-pointer font-semibold">Diagnóstico (solo APP_DEBUG)</summary>
                            <pre class="mt-2 whitespace-pre-wrap">{{ json_encode([
                                'instancia_id' => $instancia?->getKey(),
                                'instancia_idNivel' => $instancia?->idNivel,
                                'instancia_activa' => $instancia?->activo,
                                'instancia_acepta_registros' => $instancia?->aceptaRegistros(),
                                'token' => $instancia?->token,
                                'aspicursos_total' => \App\Models\Aspicurso::where('idAspiento', $instancia?->getKey())->count(),
                                'aspicursos_con_modelo' => \App\Models\Aspicurso::where('idAspiento', $instancia?->getKey())->whereNotNull('idCursoModelo')->count(),
                                'aspicursos_habilitados' => \App\Models\Aspicurso::where('idAspiento', $instancia?->getKey())
                                    ->whereNotNull('idCursoModelo')
                                    ->where(function ($q) { $q->where('activo', 1)->orWhere('habilitado', 1); })
                                    ->count(),
                                'ids_modelo_referenciados' => \App\Models\Aspicurso::where('idAspiento', $instancia?->getKey())->whereNotNull('idCursoModelo')->pluck('idCursoModelo')->all(),
                                'modelos_existentes' => \App\Models\AspiCursoModelo::whereIn('id', \App\Models\Aspicurso::where('idAspiento', $instancia?->getKey())->whereNotNull('idCursoModelo')->pluck('idCursoModelo')->all())->pluck('nombre', 'id')->all(),
                            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    @endif
                @else
                    <div>
                        @php
                            $anioDestino = $instancia->anoLectivo();
                            $etiquetaCursoDestino = 'Sala / Grado / Curso de Destino'
                                . ($anioDestino ? ' - Año '.$anioDestino : '');
                        @endphp
                        <label class="se-label">{{ $etiquetaCursoDestino }} <span class="text-red-600">*</span></label>
                        <select wire:model.defer="idCursoModelo" class="form-select w-full">
                            <option value="">— Elegir curso —</option>
                            @foreach ($cursos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                        @error('idCursoModelo')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    @foreach ($campos as $campo)
                        @php
                            $fieldId = 'asp-campo-'.$campo['columna'];
                            $ayudaId = $fieldId.'-ayuda';
                            $fechaId = $fieldId.'-fecha-formato';
                            $describedBy = collect([
                                ! empty($campo['ayuda']) ? $ayudaId : null,
                                ! empty($campo['es_fecha']) ? $fechaId : null,
                            ])->filter()->implode(' ');
                        @endphp
                        <div>
                            <label class="se-label" for="{{ $fieldId }}">
                                {{ $campo['etiqueta'] }}
                                @if ($campo['obligatorio'])
                                    <span class="text-red-600">*</span>
                                @endif
                            </label>
                            @if (! empty($campo['ayuda']))
                                <p id="{{ $ayudaId }}"
                                   class="mt-1.5 flex gap-2 rounded-xl border border-accent-200 bg-accent-50/70 px-3 py-2 text-xs leading-relaxed text-neutral-600">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="whitespace-pre-line">{{ $campo['ayuda'] }}</span>
                                </p>
                            @endif
                            @if (! empty($campo['opciones']))
                                <select id="{{ $fieldId }}"
                                        wire:model.defer="datos.{{ $campo['columna'] }}"
                                        @required($campo['obligatorio'])
                                        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                                        class="form-select mt-2 w-full">
                                    <option value="">— Elegir —</option>
                                    @foreach ($campo['opciones'] as $opcion)
                                        <option value="{{ $opcion }}">{{ $opcion }}</option>
                                    @endforeach
                                </select>
                            @elseif (! empty($campo['es_fecha']))
                                <input type="date"
                                       id="{{ $fieldId }}"
                                       wire:model.defer="datos.{{ $campo['columna'] }}"
                                       @required($campo['obligatorio'])
                                       @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                                       class="form-input mt-2 w-full">
                                <p id="{{ $fechaId }}" class="mt-1 text-[11px] text-neutral-500">Formato: día / mes / año</p>
                            @else
                                <input type="text"
                                       id="{{ $fieldId }}"
                                       wire:model.defer="datos.{{ $campo['columna'] }}"
                                       maxlength="255"
                                       @required($campo['obligatorio'])
                                       @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                                       class="form-input mt-2 w-full">
                            @endif
                            @error('datos.'.$campo['columna'])<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach

                    @error('_registro')
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="pt-2">
                        <button type="submit" class="btn-primary w-full sm:w-auto">
                            <span wire:loading.remove wire:target="registrar">Enviar registro</span>
                            <span wire:loading wire:target="registrar">Enviando…</span>
                        </button>
                    </div>
                @endif
            </form>
        </div>
    @endif
</div>
