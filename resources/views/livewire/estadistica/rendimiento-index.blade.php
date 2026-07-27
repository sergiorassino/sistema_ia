<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <p class="se-eyebrow">Estadísticas</p>
            <h2 class="text-2xl font-bold tracking-tight">Estadística de Rendimiento Escolar</h2>
            <p class="text-sm text-white/80">Nivel medio — durante el año, Diciembre y Febrero</p>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="se-card p-5 flex flex-col">
            <h3 class="text-base font-semibold text-neutral-800">1) Estadística por Materias</h3>
            <p class="text-sm text-neutral-500 mt-2 flex-1">
                Aprobación durante el año, en Diciembre y en Febrero. Filtros por materia, curso y nivel medio.
            </p>
            <a href="{{ route('estadistica.rendimiento.porMateria') }}" class="btn-primary mt-4 w-fit">Ir a Estadística por Materias</a>
        </div>

        <div class="se-card p-5 flex flex-col">
            <h3 class="text-base font-semibold text-neutral-800">2) Estadística por Docente</h3>
            <p class="text-sm text-neutral-500 mt-2 flex-1">
                Estadísticas de aprobación por docente y materia.
            </p>
            <a href="{{ route('estadistica.rendimiento.porDocente') }}" class="btn-primary mt-4 w-fit">Ir a Estadística por Docente</a>
        </div>

        <div class="se-card p-5 flex flex-col sm:col-span-2 lg:col-span-1">
            <h3 class="text-base font-semibold text-neutral-800">3) Estadística por Estudiante</h3>
            <p class="text-sm text-neutral-500 mt-2 flex-1">
                Desempeño por alumno. Elegí curso o estudiante para consultar.
                Se destaca en rojo cuando tiene 3 o más materias sin aprobar durante el año.
            </p>
            <a href="{{ route('estadistica.rendimiento.porEstudiante') }}" class="btn-primary mt-4 w-fit">Ir a Estadística por Estudiante</a>
        </div>
    </div>
</div>
