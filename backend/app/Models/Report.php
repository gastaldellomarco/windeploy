<?php
// app/Models/Report.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $table = 'reports';

    // Report immutabile: ha solo created_at (non updated_at).
    // Disabilitiamo timestamps standard e gestiamo solo created_at via migration.
    public $timestamps = false;

    protected $fillable = [
        'execution_log_id',
        'html_content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function executionLog(): BelongsTo
    {
        return $this->belongsTo(ExecutionLog::class, 'execution_log_id');
    }
}
