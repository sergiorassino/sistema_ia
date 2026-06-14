<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coop_ingresos')) {
            return;
        }

        Schema::table('coop_ingresos', function (Blueprint $table) {
            if (! Schema::hasColumn('coop_ingresos', 'pagador_vinculo')) {
                $table->enum('pagador_vinculo', ['padre', 'madre', 'tutor'])->nullable()->after('pagador_nombre');
            }
            if (! Schema::hasColumn('coop_ingresos', 'pagador_email')) {
                $table->string('pagador_email', 120)->nullable()->after('pagador_vinculo');
            }
            if (! Schema::hasColumn('coop_ingresos', 'recibo_email_estado')) {
                $table->enum('recibo_email_estado', ['pendiente', 'simulado', 'enviado', 'error'])
                    ->default('pendiente')
                    ->after('pagador_email');
            }
            if (! Schema::hasColumn('coop_ingresos', 'recibo_email_enviado_at')) {
                $table->timestamp('recibo_email_enviado_at')->nullable()->after('recibo_email_estado');
            }
            if (! Schema::hasColumn('coop_ingresos', 'recibo_email_error')) {
                $table->string('recibo_email_error', 500)->nullable()->after('recibo_email_enviado_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coop_ingresos')) {
            return;
        }

        Schema::table('coop_ingresos', function (Blueprint $table) {
            $cols = [];
            foreach (['recibo_email_error', 'recibo_email_enviado_at', 'recibo_email_estado', 'pagador_email', 'pagador_vinculo'] as $col) {
                if (Schema::hasColumn('coop_ingresos', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
