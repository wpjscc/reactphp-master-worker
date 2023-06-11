<?php

namespace Wpjscc\MasterWorker;

use Evenement\EventEmitter;
use React\Promise\Deferred;
use React\Socket\ConnectionInterface;
use WyriHaximus\React\Stream\Json\JsonStream;
use React\Stream\ThroughStream;

class Client extends EventEmitter
{
    use \Wpjscc\MasterWorker\Traits\Singleton;
    use \Wpjscc\MasterWorker\Traits\Write;

    protected function init()
    {
        // 收到 worker 的消息 (在 master 中处理)
        $this->on('worker_sendToClient', [$this, '_master_sendToClient']);
        $this->on('worker_getOnline_Ids', [$this, '_master_getOnline_Ids']);
    }

    protected function _master_sendToClient(ConnectionInterface $connection, $data)
    {
        $client_id = $data['client_id'] ?? '';
        $message = $data['message'] ?? '';
        if (is_array($message)) {
            $message = json_encode($message);
        }
        ConnectionManager::instance('client')->sendMessageTo_Id($client_id, $message);
    }

    // 在master 中处理
    protected function _master_getOnline_Ids(ConnectionInterface $connection, $data)
    {
        $data['data'] = ConnectionManager::instance('client')->get_Ids();
        $this->write($connection, [
            'cmd' => 'master_message',
            'data' => [
                'event' => str_replace('_master_', '', __FUNCTION__),
                'data' => $data
            ],
        ]);
    }

    // 以下在 worker 或 register 中调用

    public function sendToClient($_id, $message)
    {
        $event = __FUNCTION__;
        $data = [
            'client_id' => $_id,
            'message_id' => uniqid(),
            'message' => $message,
        ];

        return $this->commonClientMethod($event, $data);

        if (is_array($message)) {
            $that = $this; 
            $this->getJsonPromise($message)->then(function($data) use ($_id, $that) {
                $that->sendToClient($_id, json_encode($data));
            });
            return ;
        }

        $event = __FUNCTION__;
        $messageId = uniqid();
        $data = [
            'cmd' => 'worker_message',
            'data' =>  [
                "event" => $event,
                "data" => [
                    'client_id' => $_id,
                    'message_id' => $messageId,
                    'message' => $message,
                ]
            ],
        ];
        ConnectionManager::instance('master')->broadcastToAllGroupOnce($data);
    }
    

    public function getOnlineClientIds() 
    {

        return $this->getOnline_Ids();
        return $this->getJsonPromise($this->getOnline_Ids())->then(function($data) {
            $ids = [];
            foreach ($data as $item) {
                $ids = array_merge($ids, $item);
            }
            return $ids;
        });

    }

    public function broadcast($message)
    {
        // $data = [
        //     'cmd' => 'broadcast',
        //     'data' =>  [
        //         'client_id' => $_id,
        //         'message_id' => $msgId,
        //         'message' => $message,
        //     ],
        // ];
        $event = __METHOD__;
        $messageId = uniqid();
        $data = [
            'cmd' => 'worker_message',
            'data' =>  [
                "event" => $event,
                "data" => [
                    'client_id' => '',
                    'message_id' => $messageId,
                    'message' => $message,
                ]
            ],
        ];
        ConnectionManager::instance('master')->broadcastToAllGroupOnce($data);
    }

    protected function getOnline_Ids()
    {

        return $this->commonMasterMethod(__FUNCTION__)->then(function($data) {
            $ids = [];
            foreach ($data as $item) {
                $ids = array_merge($ids, $item);
            }
            return $ids;
        });
        
        $promises = [];

        $event = __FUNCTION__;
        $messageId = uniqid();
        $data = [
            'message_id' => $messageId,
        ];
        
        $keys = ConnectionManager::instance('master')->broadcastToAllGroupOnce([
            'cmd' => 'worker_message',
            'data' =>  [
                "event" => $event,
                "data" => $data
            ],
        ]);

        foreach ($keys as $key) {
            $defer = new Deferred();
            $this->once("$event:$messageId:$key", function(ConnectionInterface $connection, $data) use ($defer) {
                $defer->resolve($data);
            });
            $promises[] = $defer->promise();
        }

        return $promises;
    }

    protected function getJsonPromise($array = [])
    {
        $deferred = new Deferred();
        $buffer = '';
        $jsonStream = new JsonStream();
        $jsonStream->on('data', function($data) use (&$buffer) {
            $buffer .= $data;
        });

        $jsonStream->on('end', function () use (&$buffer,$deferred){
            $deferred->resolve(json_decode($buffer, true));
            $buffer = '';
        });

        $jsonStream->end($array);
        return $deferred->promise();
    }

    protected function commonClientMethod($event, $data)
    {
        $data = [
            'cmd' => 'worker_message',
            'data' =>  [
                "event" => $event,
                "data" => $data
            ],
        ];
        return $this->getJsonPromise($data)->then(function($data)  {
            return ConnectionManager::instance('master')->broadcastToAllGroupOnce($data);
        });
    }

    protected function commonMasterMethod($event, $data = [])
    {
        $deferred = new Deferred();

        $messageId = $data['message_id'] ?? '';
        if (!$messageId) {
            $messageId = uniqid();
        }

        $data['message_id'] = $messageId;

        $data = [
            'cmd' => 'worker_message',
            'data' =>  [
                "event" => $event,
                "data" => $data
            ],
        ];

        $that = $this;

        $this->getJsonPromise($data)->then(function($data) use ($event, $messageId, $that, $deferred)  {
            $promises = [];
            $keys = ConnectionManager::instance('master')->broadcastToAllGroupOnce($data);
            foreach ($keys as $key) {
                $defer = new Deferred();
                // var_dump("$event:$messageId:$key");
                $that->once("$event:$messageId:$key", function(ConnectionInterface $connection, $data) use ($defer) {
                    $defer->resolve($data);
                });
                $promises[] = $defer->promise();
            }
            $that->getJsonPromise($promises)->then(function($data) use ($deferred) {
                $deferred->resolve($data);
            });
         
        });
        return $deferred->promise();
    }

}