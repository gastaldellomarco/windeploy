<?php
// app/Models/ExecutionLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExecutionLog extends Model
{
    protected $table = 'execution_logs';

    // La tabella usa started_at/completed_at, NON created_at/updated_at standard.
    // Disabilitare timestamps Eloquent evita errori "Unknown column 'updated_at'" su save().
    public $timestamps = false;

    protected $fillable = [
        'wizard_id',
        'pc_nome_originale',
        'pc_nome_nuovo',
        'tecnico_user_id',
        'hardware_info',
        'stato',
        'log_dettagliato',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'hardware_info'   => 'array',
        'log_dettagliato' => 'array',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public function wizard(): BelongsTo
    {
        return $this->belongsTo(Wizard::class, 'wizard_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_user_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class, 'execution_log_id');
    }
}
