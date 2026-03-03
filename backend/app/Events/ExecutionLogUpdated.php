<?php

namespace App\Events;

use App\Models\ExecutionLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ExecutionLogUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $executionLog;

    public function __construct(ExecutionLog $executionLog)
    {
        $this->executionLog = $executionLog;
    }

    public function broadcastOn()
    {
        // Canale privato per il tecnico che ha avviato il wizard
        // Oppure pubblico con identificativo del wizard
        return new Channel('wizard.' . $this->executionLog->wizard_id);
    }

    public function broadcastAs()
    {
        return 'execution.updated';
    }

    public function broadcastWith()
    {
        return [
            'execution_log_id' => $this->executionLog->id,
            'wizard_id'        => $this->executionLog->wizard_id,
            'stato'            => $this->executionLog->stato,
            'step_corrente'    => $this->executionLog->step_corrente,
            'log_dettagliato'  => $this->executionLog->log_dettagliato,
            'updated_at'       => $this->executionLog->updated_at,
        ];
    }
}