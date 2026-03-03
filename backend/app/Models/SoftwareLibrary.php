<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftwareLibrary extends Model
{
    use SoftDeletes;

    protected $table = 'software_library';

    protected $fillable = [
        'nome',
        'versione',
        'publisher',
        'tipo',
        'identificatore',
        'categoria',
        'icona_url',
        'aggiunto_da',
        'attivo',
    ];

    protected $casts = [
        'attivo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
