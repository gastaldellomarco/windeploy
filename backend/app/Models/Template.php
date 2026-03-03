<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
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
    ];

    public function wizards(): HasMany
    {
        return $this->hasMany(Wizard::class, 'template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
