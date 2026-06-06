<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Restituye la FK original del schema legacy:
        // calificaciones.idMatricula -> matricula.id (ON DELETE CASCADE)
        //
        // Nota: muchas instalaciones legacy se crearon sin FKs por sanitización del dump.
        // Esta migración es aditiva y solo actúa si la constraint no existe.

        $dbName = (string) (DB::getDatabaseName() ?? '');
        if ($dbName === '') {
            return;
        }

        $exists = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $dbName)
            ->where('CONSTRAINT_NAME', 'FK_calificaciones_matricula')
            ->exists();

        if ($exists) {
            return;
        }

        // Si hay datos inconsistentes (calificaciones con idMatricula inexistente),
        // MySQL rechazará la FK. Para poder restaurar la estructura legacy sin borrar
        // datos, se "desengancha" el vínculo dejando idMatricula en NULL.
        // (En el schema original, idMatricula permite NULL.)
        DB::statement(<<<'SQL'
UPDATE `calificaciones` c
LEFT JOIN `matricula` m ON m.`id` = c.`idMatricula`
SET c.`idMatricula` = NULL
WHERE c.`idMatricula` IS NOT NULL
  AND m.`id` IS NULL
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE `calificaciones`
  ADD CONSTRAINT `FK_calificaciones_matricula`
  FOREIGN KEY (`idMatricula`) REFERENCES `matricula` (`id`)
  ON DELETE CASCADE
SQL);
    }

    public function down(): void
    {
        $dbName = (string) (DB::getDatabaseName() ?? '');
        if ($dbName === '') {
            return;
        }

        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $dbName)
            ->where('TABLE_NAME', 'calificaciones')
            ->where('CONSTRAINT_NAME', 'FK_calificaciones_matricula')
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if (! $exists) {
            return;
        }

        DB::statement('ALTER TABLE `calificaciones` DROP FOREIGN KEY `FK_calificaciones_matricula`');
    }
};
