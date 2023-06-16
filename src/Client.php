<?php

namespace Wpjscc\MasterWorker;

use Evenement\EventEmitter;
use React\Promise\Deferred;
use React\Socket\ConnectionInterface;

class Client extends EventEmitter
{
    use \Wpjscc\MasterWorker\Traits\Singleton;
    use \Wpjscc\MasterWorker\Traits\Write;
    use \Wpjscc\MasterWorker\Traits\JsonPromise;

    protected function init()
    {
        // 收到 worker 或 register 的消息 (在 master 中处理)
        $this->on($this->key.'_sendToClient', [$this, '_master_sendToClient']);
        $this->on($this->key.'_sendToGroup', [$this, '_master_sendToGroup']);
        $this->on($this->key.'_broadcast', [$this, '_master_broadcast']);
        $this->on($this->key.'_get_IdData', [$this, '_master_get_IdData']);
        $this->on($this->key.'_bindId', [$this, '_master_bindId']);
        $this->on($this->key.'_unBindId', [$this, '_master_unBindId']);
        $this->on($this->key.'_unBind_Id', [$this, '_master_unBind_Id']);
        $this->on($this->key.'_getOnline_Ids', [$this, '_master_getOnline_Ids']);
        $this->on($this->key.'_isOnline_Id', [$this, '_master_isOnline_Id']);
        $this->on($this->key.'_isInGroupBy_Id', [$this, '_master_isInGroupBy_Id']);
        $this->on($this->key.'_joinGroupBy_Id', [$this, '_master_joinGroupBy_Id']);
        $this->on($this->key.'_leaveGroupBy_Id', [$this, '_master_leaveGroupBy_Id']);
        $this->on($this->key.'_getGroup_IdCount', [$this, '_master_getGroup_IdCount']);
        $this->on($this->key.'_getGroupIdsBy_Id', [$this, '_master_getGroupIdsBy_Id']);
    }


    // 在master 中处理

    protected function _master_sendToClient(ConnectionInterface $connection, $data)
    {
        $message = $data['message'] ?? '';
        $clientKey = $data['client_key'] ?? 'client';
        if (is_array($message)) {
            $message = json_encode($message);
        }
        ConnectionManager::instance($clientKey)->sendToClient(
            $data['_id'], 
            $message,
            $data['id'], 
            $data['exclude__ids']
        );
    }


    protected function _master_sendToGroup(ConnectionInterface $connection, $data)
    {
        $message = $data['message'] ?? '';
        $clientKey = $data['client_key'] ?? 'client';

        if (is_array($message)) {
            $message = json_encode($message);
        }

        ConnectionManager::instance($clientKey)->sendToGroup(
            $data['group_id'] ?? '',
            $message,
            $data['exclude_ids'] ?? [],
            $data['exclude__ids'] ?? []
        );

    }

    protected function _master_broadcast(ConnectionInterface $connection, $data)
    {
        $message = $data['message'] ?? '';
        $clientKey = $data['client_key'] ?? 'client';
        if (is_array($message)) {
            $message = json_encode($message);
        }
        ConnectionManager::instance($clientKey)->broadcast($message, $data['exclude__ids'] ?? []);
    }

    

    protected function _master_get_IdData(ConnectionInterface $connection, $data)
    {
        $clientKey = $data['client_key'] ?? 'client';
        $data['data'] = ConnectionManager::instance($clientKey)->get_IdData($data['_id'] ?? '') ?: [];
        $this->write($connection, [
            'cmd' => 'master_message',
            'data' => [
                'event' => str_replace('_master_', '', __FUNCTION__),
                'data' => $data
            ],
        ]);
    }

    protected function _master_bindId(ConnectionInterface $connection, $data)
    {
        $clientKey = $data['client_key'] ?? 'client';
        $data['data'] = ConnectionManager::instance($clientKey)->bindId($data['id'] ?? '', $data['_id'] ?? '');
        $this->write($connection, [
            'cmd' => 'master_message',
            'data' => [
                'event' => str_replace('_master_', '', __FUNCTION__),
                'data' => $data
            ],
        ]);
    }
    protected function _master_unBindId(ConnectionInterface $connection, $data)
    {
        $clientKey = $data['client_key'] ?? 'client';
        $data['data'] = ConnectionManager::instance($clientKey)->unBindId($data['id'] ?? '');
        $this->write($connection, [
            'cmd' => 'master_message',
            'data' => [
                'event' => str_replace('_master_', '', __FUNCTION__),
                'data' => $data
            ],
        ]);
    }
    protected function _master_unBind_Id(ConnectionInterface $connection, $data)
    {
        $clientKey = $data['client_key'] ?? 'client';
        $data['data'] = ConnectionManager::instance($clientKey)->unBind_Id($data['_id'] ?? '');
        $this->write($connection, [
            'cmd' => 'master_message',
            'data' => [
                'event' => str_replace('_master_', '', __FUNCTION__),
                'data' => $data
            ],
        ]);
    }

    protected function _master_getOnline_Ids(ConnectionInterface $connection, $data)
    {
        $clientKey = $data['client_key'] ?? 'client';
        $data['data'] = ConnectionManager::instance($clientKey)->get_Ids();
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
        $clientKey = $data['client_key'] ?? 'client';
        $data['data'] = ConnectionManager::instance($clientKey)->isOnline_Id($data['_id'] ?? '');
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
        $clientKey = $data['client_key'] ?? 'client';
        $data['data'] = ConnectionManager::instance($clientKey)->isInGroupBy_Id($data['group_id'] ?? '', $data['_id'] ?? '');
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
        $clientKey = $data['client_key'] ?? 'client';
        $data['data'] = ConnectionManager::instance($clientKey)->joinGroupBy_Id($data['group_id'] ?? '', $data['_id'] ?? '');
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
        $clientKey = $data['client_key'] ?? 'client';
        $data['data'] = ConnectionManager::instance($clientKey)->leaveGroupBy_Id($data['group_id'] ?? '', $data['_id'] ?? '');
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
        $clientKey = $data['client_key'] ?? 'client';
        $data['data'] = ConnectionManager::instance($clientKey)->getGroup_IdCount($data['group_id'] ?? '');
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
        $clientKey = $data['client_key'] ?? 'client';
        $data['data'] = ConnectionManager::instance($clientKey)->getGroupIdsBy_Id($data['_id'] ?? '');
        $this->write($connection, [
            'cmd' => 'master_message',
            'data' => [
                'event' => str_replace('_master_', '', __FUNCTION__),
                'data' => $data
            ],
        ]);
    }


    // 以下在 worker 或 register 中调用

    public function sendToClient($_id, $message, $id = 0, $exclude_Ids = [])
    {
        $event = __FUNCTION__;
        $data = [
            '_id' => $_id,
            'id' => $id,
            'exclude__ids' => $exclude_Ids,
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

    public function broadcast($message, $exclude_Ids = [])
    {
        $data = [
            'client_id' => '',
            'message_id' => uniqid(),
            'exclude__ids' => $exclude_Ids,
            'message' => $message,
        ];
        return $this->commonClientMethod(__FUNCTION__, $data);
    }
    

    public function get_IdData($_id)
    {
        return $this->commonMasterMethod(__FUNCTION__, [
            '_id' => $_id
        ])->then(function($data) {
            return array_reduce($data, 'array_merge', []);
        });

    }

    public function bindId($id, $_id)
    {
        return $this->commonMasterMethod(__FUNCTION__, [
            'id' => $id,
            '_id' => $_id
        ])->then(function($data) {
            if (in_array(0, $data)) {
                return 0;
            } 
            elseif (in_array(1, $data)) {
                return 1;
            }
            elseif (in_array(2, $data)) {
                return 2;
            }
            // 不可能出现
            return 3;
        });

    }
    public function unBindId($id)
    {
        return $this->commonMasterMethod(__FUNCTION__, [
            'id' => $id,
        ])->then(function($data) {
            if (in_array(0, $data)) {
                return 0;
            } 
            elseif (in_array(1, $data)) {
                return 1;
            }
            // 不可能出现
            return 2;
        });

    }

    public function unBind_Id($_id)
    {
        return $this->commonMasterMethod(__FUNCTION__, [
            '_id' => $_id,
        ])->then(function($data) {
            if (in_array(0, $data)) {
                return 0;
            } 
            elseif (in_array(1, $data)) {
                return 1;
            }
            // 不可能出现
            return 2;
        });

    }

    public function getOnline_Ids()
    {

        return $this->commonMasterMethod(__FUNCTION__)->then(function($data) {
            return array_reduce($data, 'array_merge', []);
        });

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


    protected function commonClientMethod($event, $data)
    {
        $data = [
            'cmd' => $this->key.'_message',
            'data' =>  [
                "event" => $event,
                "data" => $data
            ],
        ];
        $that = $this;
        return $this->getJsonPromise($data)->then(function($data) use ($that)  {
            return ConnectionManager::instance($that->key.'_master')->broadcastToAllGroupOnce($data);
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
            'cmd' => $this->key.'_message',
            'data' =>  [
                "event" => $event,
                "data" => $data
            ],
        ];

        $that = $this;
        var_dump($this->key,'111111');

        $this->getJsonPromise($data)->then(function($data) use ($event, $messageId, $that, $deferred)  {
            $promises = [];
            $keys = ConnectionManager::instance($that->key.'_master')->broadcastToAllGroupOnce($data);
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