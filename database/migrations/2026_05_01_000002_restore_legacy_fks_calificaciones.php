<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $dbName = (string) (DB::getDatabaseName() ?? '');
        if ($dbName === '') {
            return;
        }

        // Estas FKs existen en el `schema.sql` legacy para `calificaciones`.
        // En MySQL, si no se especifica ON DELETE/ON UPDATE, el default es RESTRICT/NO ACTION.
        $fks = [
            [
                'name' => 'FK_calificaciones_cursos',
                'column' => 'idCursos',
                'refTable' => 'cursos',
                'refColumn' => 'Id',
            ],
            [
                'name' => 'FK_calificaciones_legajos',
                'column' => 'idLegajos',
                'refTable' => 'legajos',
                'refColumn' => 'id',
            ],
            [
                'name' => 'FK_calificaciones_materias',
                'column' => 'idMaterias',
                'refTable' => 'materias',
                'refColumn' => 'id',
            ],
            [
                'name' => 'FK_calificaciones_matplan',
                'column' => 'idMatPlan',
                'refTable' => 'matplan',
                'refColumn' => 'id',
            ],
            [
                'name' => 'FK_calificaciones_terlec',
                'column' => 'idTerlec',
                'refTable' => 'terlec',
                'refColumn' => 'id',
            ],
        ];

        foreach ($fks as $fk) {
            $exists = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', $dbName)
                ->where('CONSTRAINT_NAME', $fk['name'])
                ->exists();

            if ($exists) {
                continue;
            }

            // Si hay datos inconsistentes, se deja el valor en NULL (las columnas legacy son nullable)
            // para poder restituir estructura sin borrado masivo de datos.
            DB::statement(sprintf(
                'UPDATE `calificaciones` c
                 LEFT JOIN `%s` r ON r.`%s` = c.`%s`
                 SET c.`%s` = NULL
                 WHERE c.`%s` IS NOT NULL
                   AND r.`%s` IS NULL',
                $fk['refTable'],
                $fk['refColumn'],
                $fk['column'],
                $fk['column'],
                $fk['column'],
                $fk['refColumn'],
            ));

            DB::statement(sprintf(
                'ALTER TABLE `calificaciones`
                   ADD CONSTRAINT `%s`
                   FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`)',
                $fk['name'],
                $fk['column'],
                $fk['refTable'],
                $fk['refColumn'],
            ));
        }
    }

    public function down(): void
    {
        $dbName = (string) (DB::getDatabaseName() ?? '');
        if ($dbName === '') {
            return;
        }

        $names = [
            'FK_calificaciones_cursos',
            'FK_calificaciones_legajos',
            'FK_calificaciones_materias',
            'FK_calificaciones_matplan',
            'FK_calificaciones_terlec',
        ];

        foreach ($names as $name) {
            $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', $dbName)
                ->where('TABLE_NAME', 'calificaciones')
                ->where('CONSTRAINT_NAME', $name)
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->exists();

            if (! $exists) {
                continue;
            }

            DB::statement(sprintf('ALTER TABLE `calificaciones` DROP FOREIGN KEY `%s`', $name));
        }
    }
};
