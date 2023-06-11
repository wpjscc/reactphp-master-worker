<?php

namespace Wpjscc\MasterWorker;

class WorkerClient
{
    use \Wpjscc\MasterWorker\Traits\Singleton;

    public function sendToClient($_id, $message)
    {
        $data = [
            'cmd' => '_sendToClient',
            'data' =>  [
                'client_id' => $_id,
                'message' => $message,
            ],
        ];
        ConnectionManager::instance('master')->broadcastToAllGroupOnce($data);
    }
}