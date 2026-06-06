<?php

namespace App\Support\Listados;

/**
 * Columnas del PDF Libro de Matrícula.
 * DomPDF: anchos en % en th y td (misma clase); membrete sin colspan en la tabla.
 */
final class LibroMatriculaPdfColumnas
{
    /**
     * @return list<array{
     *   field: string,
     *   cls: string,
     *   label: string,
     *   width: string,
     *   align: 'left'|'center',
     *   nowrap: bool
     * }>
     */
    public static function todas(): array
    {
        return [
            [
                'field' => 'fecha_matricula',
                'cls' => 'col-fecha-matr',
                'label' => 'Fecha Matr.',
                'width' => '5%',
                'align' => 'center',
                'nowrap' => true,
            ],
            [
                'field' => 'estudiante',
                'cls' => 'col-estudiante',
                'label' => 'Estudiante',
                'width' => '16%',
                'align' => 'left',
                'nowrap' => false,
            ],
            [
                'field' => 'edad',
                'cls' => 'col-edad',
                'label' => 'Edad',
                'width' => '2.5%',
                'align' => 'center',
                'nowrap' => true,
            ],
            [
                'field' => 'dni',
                'cls' => 'col-dni',
                'label' => 'DNI',
                'width' => '5.5%',
                'align' => 'center',
                'nowrap' => true,
            ],
            [
                'field' => 'domicilio',
                'cls' => 'col-domicilio',
                'label' => 'Domicilio',
                'width' => '18%',
                'align' => 'left',
                'nowrap' => false,
            ],
            [
                'field' => 'fecha_nac',
                'cls' => 'col-fecha-nac',
                'label' => 'Fecha Nac.',
                'width' => '5%',
                'align' => 'center',
                'nowrap' => true,
            ],
            [
                'field' => 'lugar_nac',
                'cls' => 'col-lugar',
                'label' => 'Lugar de Nacimiento',
                'width' => '13%',
                'align' => 'left',
                'nowrap' => false,
            ],
            [
                'field' => 'padre',
                'cls' => 'col-padre',
                'label' => 'Padre',
                'width' => '12%',
                'align' => 'left',
                'nowrap' => false,
            ],
            [
                'field' => 'madre',
                'cls' => 'col-madre',
                'label' => 'Madre',
                'width' => '12%',
                'align' => 'left',
                'nowrap' => false,
            ],
            [
                'field' => 'cur',
                'cls' => 'col-cur',
                'label' => 'Cur',
                'width' => '11%',
                'align' => 'center',
                'nowrap' => true,
            ],
        ];
    }
}
