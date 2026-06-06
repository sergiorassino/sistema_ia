{{-- Tema Bosque SE: fuente, variables y desplazamiento del contenido (.se-main). Usar con .se-sidebar--bosque en el aside. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    :root {
        --se-primary: #40848d;
        --se-light-blue: #c1d7da;
        --se-hover-bg: rgba(255, 255, 255, 0.12);
        --se-sep: rgba(255, 255, 255, 0.14);
        --se-sidebar-text: rgba(255, 255, 255, 0.9);
        --se-group-text: rgba(255, 255, 255, 0.82);
        --se-group-bg: rgba(255, 255, 255, 0.07);
        --se-group-open-bg: rgba(255, 255, 255, 0.12);
        --se-sidebar-w: 24rem;
        --se-sidebar-w-collapsed: 5rem;
    }
    .se-main {
        width: 100%;
        min-width: 0;
        transition: transform 200ms ease-in-out, width 200ms ease-in-out;
        transform: translateX(0);
    }
    @media (min-width: 768px) {
        .se-main {
            transform: translateX(var(--se-sidebar-w));
            width: calc(100% - var(--se-sidebar-w));
        }
        .se-main.is-collapsed {
            transform: translateX(var(--se-sidebar-w-collapsed));
            width: calc(100% - var(--se-sidebar-w-collapsed));
        }
    }
    @media (max-width: 767px) {
        .se-main.is-mobile-open {
            transform: translateX(var(--se-sidebar-w));
            width: calc(100% - var(--se-sidebar-w));
        }
    }
</style>
