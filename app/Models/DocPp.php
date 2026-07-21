<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocPp extends Model
{
    protected $table = 'doc_pp';

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'idNivel',
        'idTerlec',
        'idMaterias',
        'idCursos',
        'tipo',
        'nombre_archivo',
        'aprobado',
        'observaciones',
        'subido_por',
        'subido_en',
    ];

    protected $casts = [
        'idNivel' => 'integer',
        'idTerlec' => 'integer',
        'idMaterias' => 'integer',
        'idCursos' => 'integer',
        'aprobado' => 'integer',
        'subido_por' => 'integer',
        'subido_en' => 'datetime',
    ];

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMaterias', 'id');
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'idCursos', 'Id');
    }

    public function terlec()
    {
        return $this->belongsTo(Terlec::class, 'idTerlec', 'id');
    }
}
