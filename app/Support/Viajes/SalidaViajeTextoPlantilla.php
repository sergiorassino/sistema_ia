<?php

namespace App\Support\Viajes;

/**
 * Texto HTML por defecto al crear una nueva salida educativa.
 */
final class SalidaViajeTextoPlantilla
{
    public static function paraNuevoViaje(): string
    {
        return <<<'HTML'
<p style="line-height: 1.3; text-align: justify;"><span style="font-size: 8pt;">A realizar <strong>.......................................... Educativ/oa, </strong> desde el <strong> IPEM N° 206 FERNANDO FADER</strong>, Gregorio de Laferrere 2435, B° Parque Corema - Córdoba<strong>, </strong>hasta<strong> .......................................................................................</strong>, para participar de la  visita a la: “<strong>..................................................................”</strong>, desde el día <strong>..................................................</strong> de <strong>..................................</strong> de <strong>202...</strong> con <strong>regreso</strong> desde <strong>..................................................................</strong> el dia <strong>............................................... de .......................... del 202...,</strong>  acompañado/a por la docente responsable <strong>...................................................................... </strong>y el equipo de docentes, según agenda, desde el inicio al final del viaje. Cronograma de Actividades:<br /></span><span style="font-size: 8pt; font-family: arial, helvetica, sans-serif;">07:30 hs. Concentración en la Sede de la Escuela IPEM N° 206 FERNANDO FADER<br />07:40: otra linea pegada<br /></span><span style="font-size: 8pt; font-family: arial, helvetica, sans-serif;">07:40 hs Salida desde la institución <strong>Gregorio de Laferrere 2435, B° Parque Corema. Córdoba.</strong></span><br /><span style="font-size: 8pt; font-family: arial, helvetica, sans-serif;">09:40 hs Llegada a la Central Nuclear de Embalse. <strong>Ruta Pcial 23 s/n Embalse. Calamuchita<br /></strong></span><span style="font-size: 8pt; font-family: arial, helvetica, sans-serif;">10:00 hs Charla técnica.<br /></span><span style="font-size: 8pt; font-family: arial, helvetica, sans-serif;">11:00 hs. Recorrido por la Central.<br /></span><span style="font-size: 8pt;">12:00 hs Salida de la central nuclear. Almuerzo. Zona de playa Maldonado.<br /></span><span style="font-size: 8pt;">15:00 hs Regreso hacia la Ciudad de Córdoba.<br /></span><span style="font-size: 8pt;">17:00 hs Llegada a la sede de la Escuela del IPEM N° 206 Fernando Fader.<br /></span></p>
<p style="line-height: 1.3; text-align: justify;"><span style="font-size: 8pt;">El traslado de los estudiantes hasta el punto de salida  el día <strong>................/.........../202...</strong>...., al igual que el regreso el día .<strong>......./.........../202.........</strong>,  estará a cargo de la familia de cada estudiante. <br /></span><span style="font-size: 8pt;"><strong>SALIDA:</strong> IPEM N° 206 FERNANDO FADER, Gregorio de Laferrere 2435, B° Parque Corema - Córdoba<br /></span><span style="font-size: 8pt;"><strong>REGRESO: </strong>IPEM N° 206 FERNANDO FADER, Gregorio de Laferrere 2435, B° Parque Corema - Córdoba<br /></span><span style="font-size: 8pt;"><strong>TRANSPORTE: </strong>DANIMAR S.R.L.<br /><strong>ALOJAMIENTO:</strong> <br /><strong>TRANSPORTE A UTILIZAR EN EL LUGAR DE DESTINO:</strong> No</span></p>
HTML;
    }
}
