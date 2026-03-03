<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $table = 'reports';

    protected $fillable = [
        'execution_log_id',
        'html_content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function executionLog()
    {
        return $this->belongsTo(ExecutionLog::class, 'execution_log_id');
    }
}
