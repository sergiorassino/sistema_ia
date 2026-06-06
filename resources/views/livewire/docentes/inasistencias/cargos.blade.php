<div class="se-page">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="se-soft-card border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
            <div>
                <p class="se-eyebrow">Cargos por docente</p>
                <h2 class="text-2xl font-bold tracking-tight">{{ $profesor->apellido }} {{ $profesor->nombre }}</h2>
            </div>
            <a href="{{ route('docentes.inasistencias') }}" class="rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white">← Listado</a>
        </div>
    </section>

    <div class="se-card mb-6 w-full min-w-0 max-w-lg overflow-hidden">
        <form wire:submit="save" class="space-y-3 p-4">
            <h3 class="font-semibold text-neutral-900">{{ $this->idCxp ? 'Editar cargo' : 'Agregar cargo' }}</h3>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="form-label">Nivel</label>
                    <input type="text" readonly class="form-input mt-1 w-full max-w-[12rem] bg-accent-50" value="{{ $nivelNombre }}">
                </div>

                <div>
                    <label class="form-label">Cantidad (horas) *</label>
                    <input type="number" min="0" wire:model="cant" class="form-input mt-1 w-full max-w-[6rem] tabular-nums">
                    @error('cant') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2 min-w-0">
                    <label class="form-label">Cargo *</label>
                    <select wire:model="idCargos" class="form-input mt-1 w-full min-w-0">
                        <option value="">— Seleccionar —</option>
                        @foreach ($catalogoCargos as $c)
                            <option value="{{ $c->id }}">{{ $c->cargo }}</option>
                        @endforeach
                    </select>
                    @error('idCargos') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn-primary">Guardar</button>
                @if ($this->idCxp)
                    <button type="button" wire:click="delete" wire:confirm="¿Eliminar este cargo?"
                            class="btn-danger">Eliminar</button>
                @endif
            </div>
        </form>
    </div>

    <div class="se-card overflow-hidden max-w-2xl">
        <table class="min-w-full text-sm">
            <thead class="bg-accent-50">
                <tr>
                    <th class="table-header">Nivel</th>
                    <th class="table-header">Cargo</th>
                    <th class="table-header text-center">Horas</th>
                    <th class="table-header w-20"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-accent-200">
                @forelse ($listado as $row)
                    <tr>
                        <td class="table-cell">{{ $row->nivel_nombre }}</td>
                        <td class="table-cell">{{ $row->cargo }}</td>
                        <td class="table-cell text-center">{{ $row->cant }}</td>
                        <td class="table-cell text-right">
                            <a href="{{ route('docentes.inasistencias.cargos', ['idProfesor' => $profesor->id, 'idCxp' => $row->id]) }}"
                               class="btn-secondary btn-sm">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="table-cell py-6 text-center text-neutral-500">Sin cargos cargados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
