<?php

namespace Wpjscc\MasterWorker;

use Evenement\EventEmitter;

class Base extends EventEmitter
{
    use Traits\Singleton;
    use Traits\Write;
    use Traits\PingPong;
    use Traits\JsonPromise;

    public function info($msg, $data = [])
    {
        if ($msg instanceof \Exception) {
            echo json_encode([
                'file' => $msg->getFile(),
                'line' => $msg->getLine(),
                'msg' => $msg->getMessage(),
            ]);
        } else {
            echo $msg."\n";
        }

        if ($data) {
            echo json_encode($data)."\n";
        }
    }
}