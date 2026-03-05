<?php
// app/Models/Template.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use SoftDeletes;

    protected $table = 'templates';

    protected $fillable = [
        'nome',
        'descrizione',
        'user_id',
        'scope',
        'configurazione',
    ];

    protected $casts = [
        'configurazione' => 'array',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wizards(): HasMany
    {
        return $this->hasMany(Wizard::class, 'template_id');
    }
}
