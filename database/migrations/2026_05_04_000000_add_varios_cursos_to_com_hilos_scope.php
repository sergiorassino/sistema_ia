<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE com_hilos MODIFY COLUMN scope ENUM('alumno','varios_alumnos','curso','varios_cursos','colegio') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE com_hilos MODIFY COLUMN scope ENUM('alumno','varios_alumnos','curso','colegio') NOT NULL");
    }
};
