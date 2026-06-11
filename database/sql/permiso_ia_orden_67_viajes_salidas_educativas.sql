-- Permiso IA orden 67: Viajes / salidas educativas
-- Equivalente a migración 2026_06_11_140000_add_permiso_ia_orden_67_viajes_salidas_educativas.php

INSERT INTO permisos_ia (id, orden, tema, descripcion)
VALUES (
    67,
    67,
    'VIAJES / SALIDAS EDUCATIVAS',
    'Gestión de salidas educativas, autorizaciones en PDF y exportación Excel de datos para viajes.'
)
ON DUPLICATE KEY UPDATE
    orden = VALUES(orden),
    tema = VALUES(tema),
    descripcion = VALUES(descripcion);
