<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionLog extends Model
{
    protected $table = 'execution_logs';

    // The execution_logs table in this project does not have Laravel's
    // automatic timestamp columns (created_at / updated_at). Disable
    // Eloquent timestamps to prevent SQL errors on insert.
    public $timestamps = false;

    protected $fillable = [
        'wizard_id',
        'tecnico_user_id',
        'pc_nome_originale',
        'pc_nome_nuovo',
        'hardware_info',
        'stato',
        'step_corrente',
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
        return $this->belongsTo(Wizard::class);
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_user_id');
    }
}
