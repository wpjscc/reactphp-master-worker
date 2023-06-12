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
        $this->on('worker_sendToGroup', [$this, '_master_sendToGroup']);
        $this->on('worker_broadcast', [$this, '_master_broadcast']);
        $this->on('worker_getOnline_Ids', [$this, '_master_getOnline_Ids']);
        $this->on('worker_isOnline_Id', [$this, '_master_isOnline_Id']);
        $this->on('worker_isInGroupBy_Id', [$this, '_master_isInGroupBy_Id']);
        $this->on('worker_joinGroupBy_Id', [$this, '_master_joinGroupBy_Id']);
        $this->on('worker_leaveGroupBy_Id', [$this, '_master_leaveGroupBy_Id']);
        $this->on('worker_getGroup_IdCount', [$this, '_master_getGroup_IdCount']);
        $this->on('worker_getGroupIdsBy_Id', [$this, '_master_getGroupIdsBy_Id']);
    }


    // 在master 中处理

    protected function _master_sendToClient(ConnectionInterface $connection, $data)
    {
        $client_id = $data['client_id'] ?? '';
        $message = $data['message'] ?? '';
        if (is_array($message)) {
            $message = json_encode($message);
        }
        ConnectionManager::instance('client')->sendMessageTo_Id($client_id, $message);
    }


    protected function _master_sendToGroup(ConnectionInterface $connection, $data)
    {
        $message = $data['message'] ?? '';
        if (is_array($message)) {
            $message = json_encode($message);
        }

        ConnectionManager::instance('client')->sendToGroup(
            $data['group_id'] ?? '',
            $message,
            $data['exclude_ids'] ?? [],
            $data['exclude__ids'] ?? []
        );

    }

    protected function _master_broadcast(ConnectionInterface $connection, $data)
    {
        $message = $data['message'] ?? '';
        if (is_array($message)) {
            $message = json_encode($message);
        }
        ConnectionManager::instance('client')->broadcast($message, $data['exclude__ids'] ?? []);
    }

    

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

    protected function _master_isOnline_Id(ConnectionInterface $connection, $data)
    {
        $data['data'] = ConnectionManager::instance('client')->isOnline_Id($data['_id'] ?? '');
        $this->write($connection, [
            'cmd' => 'master_message',
            'data' => [
                'event' => str_replace('_master_', '', __FUNCTION__),
                'data' => $data
            ],
        ]);
    }
    protected function _master_isInGroupBy_Id(ConnectionInterface $connection, $data)
    {
        $data['data'] = ConnectionManager::instance('client')->isInGroupBy_Id($data['group_id'] ?? '', $data['_id'] ?? '');
        $this->write($connection, [
            'cmd' => 'master_message',
            'data' => [
                'event' => str_replace('_master_', '', __FUNCTION__),
                'data' => $data
            ],
        ]);
    }
    protected function _master_joinGroupBy_Id(ConnectionInterface $connection, $data)
    {
        $data['data'] = ConnectionManager::instance('client')->joinGroupBy_Id($data['group_id'] ?? '', $data['_id'] ?? '');
        $this->write($connection, [
            'cmd' => 'master_message',
            'data' => [
                'event' => str_replace('_master_', '', __FUNCTION__),
                'data' => $data
            ],
        ]);
    }
    protected function _master_leaveGroupBy_Id(ConnectionInterface $connection, $data)
    {
        $data['data'] = ConnectionManager::instance('client')->leaveGroupBy_Id($data['group_id'] ?? '', $data['_id'] ?? '');
        $this->write($connection, [
            'cmd' => 'master_message',
            'data' => [
                'event' => str_replace('_master_', '', __FUNCTION__),
                'data' => $data
            ],
        ]);
    }

    protected function _master_getGroup_IdCount(ConnectionInterface $connection, $data)
    {
        $data['data'] = ConnectionManager::instance('client')->getGroup_IdCount($data['group_id'] ?? '');
        $this->write($connection, [
            'cmd' => 'master_message',
            'data' => [
                'event' => str_replace('_master_', '', __FUNCTION__),
                'data' => $data
            ],
        ]);
    }

    protected function _master_getGroupIdsBy_Id(ConnectionInterface $connection, $data)
    {
        $data['data'] = ConnectionManager::instance('client')->getGroupIdsBy_Id($data['_id'] ?? '');
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

    }

    public function sendToGroup($group_id, $message, $excludeIds = [], $exclude_Ids = [])
    {
        $data = [
            'group_id' => $group_id,
            'exclude_ids' => $excludeIds,
            'exclude__ids' => $exclude_Ids,
            'message' => $message,
        ];
        return $this->commonClientMethod(__FUNCTION__, $data);
    }
    

    public function getOnline_Ids()
    {

        return $this->commonMasterMethod(__FUNCTION__)->then(function($data) {
            return array_reduce($data, 'array_merge', []);
        });

    }

    public function broadcast($message, $exclude_Ids = [])
    {
        $data = [
            'client_id' => '',
            'message_id' => uniqid(),
            'exclude__ids' => $exclude_Ids,
            'message' => $message,
        ];
        return $this->commonMasterMethod(__FUNCTION__, $data);
    }

    public function isOnline_Id($_id)
    {
        return $this->commonMasterMethod(__FUNCTION__, ['_id' => $_id])->then(function($data){
            return empty(array_filter($data)) ? false : true;
        });
    }

    public function isInGroupBy_Id($group_id, $_id)
    {
        return $this->commonMasterMethod(__FUNCTION__, [
            'group_id' => $group_id,
            '_id' => $_id,
        ])->then(function($data){
            return empty(array_filter($data)) ? false : true;
        });
    }

    public function joinGroupBy_Id($group_id, $_id)
    {
        $data = [
            '_id' => $_id,
            'group_id' => $group_id,
        ];
        return $this->commonMasterMethod(__FUNCTION__, $data)->then(function($data){
            // 加入成功
            if (in_array(0, $data)) {
                return 0;
            } 
            // 已经加入过
            elseif (in_array(1, $data)) {
                return 1;
            }
            // 加入失败
            elseif (in_array(2, $data)) {
                return 2;
            }
            // 不可能出现这个
            return 3;
        });
    }

    public function leaveGroupBy_Id($group_id, $_id)
    {
        $data = [
            '_id' => $_id,
            'group_id' => $group_id,
        ];
        return $this->commonMasterMethod(__FUNCTION__, $data)->then(function($data){
            // 离开成功
            if (in_array(0, $data)) {
                return 0;
            } 
            // 没在群中
            elseif (in_array(1, $data)) {
                return 1;
            }
            // 没有该链接
            elseif (in_array(2, $data)) {
                return 2;
            }
            // 不可能出现这个
            return 3;
        });
    }

    public function getGroup_IdCount($group_id)
    {
        $data = [
            'group_id' => $group_id,
        ];

        return $this->commonMasterMethod(__FUNCTION__, $data)->then(function($data){

            return array_sum($data);
        });
    }

    public function getGroupIdsBy_Id($_id)
    {
        $data = [
            '_id' => $_id,
        ];
        return $this->commonMasterMethod(__FUNCTION__, $data)->then(function($data){
            return array_reduce($data, 'array_merge', []);
        });
    }

    public function getJsonPromise($array = [])
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

    protected function commonMasterMethod($event, $data = null)
    {
        $deferred = new Deferred();

        $data = $data ?: [];

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

                $that->once("$event:$messageId:$key", function(ConnectionInterface $connection, $data) use ($defer, $event) {
                    var_dump($event, $data, "11111111");

                    $defer->resolve($data);
                });
                $promises[] = $defer->promise();
            }
            $that->getJsonPromise($promises)->then(function($data) use ($deferred) {
                var_dump($data,3333333);
                $deferred->resolve($data);
            });
         
        });
        return $deferred->promise();
    }

}