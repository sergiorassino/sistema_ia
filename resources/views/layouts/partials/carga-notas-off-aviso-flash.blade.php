@if (session('carga_notas_off_aviso'))
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.seSwalAviso === 'function') {
                window.seSwalAviso(@js(session('carga_notas_off_aviso')), 'Carga de calificaciones');
            }
        });
    </script>
@endif
