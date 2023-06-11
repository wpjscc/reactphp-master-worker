<?php 

namespace Wpjscc\MasterWorker;

use React\Socket\ConnectionInterface;

class ConnectionManager 
{
    use \Wpjscc\MasterWorker\Traits\Singleton;
    use \Wpjscc\MasterWorker\Traits\Write;
    // 连接相关
    protected $connections;
    protected $connection_id_to_connection = [];


    // 分组相关
    protected $groups = [];
    protected $connection_id_to_group_ids = [];


    // 用户相关
    // 用户ID和_ID的绑定关系（一对多）
    protected $id_to_connection_ids = [];
    // _ID 和用户ID的对应关系（多对1）
    protected $connection_id_to_id = [];


    protected function init()
    {
        $this->connections = new \SplObjectStorage;
    }

    public function addConnection($connection, $data = null)
    {
        if (!isset($connection->_id)){
            $connection->_id = bin2hex(openssl_random_pseudo_bytes(16));
        }

        $this->connections->attach($connection, $data);
        $this->connection_id_to_connection[$connection->_id] = $connection;

        // $that = $this;
        // 监听关闭连接事件
        // $connection->on('close', function() use ($connection, $that) {
        //     $that->closeConnection($connection);
        // });

        return $connection->_id;
    }

    // 外部调用关闭连接
    public function closeConnection($connection)
    {
        if ($this->connections->contains($connection)){
            $this->connections->detach($connection);
            unset($this->connection_id_to_connection[$connection->_id]);
            $this->_leaveAllGroup($connection);
            
            // 清除ID和_id 的对应关系
            $id = $this->_getIdBy_Id($connection->_id);
            if ($id) {
                unset($this->connection_id_to_id[$connection->_id]);
                unset($this->id_to_connection_ids[$id][$connection->_id]);
                if (count($this->id_to_connection_ids[$id]) == 0) {
                    unset($this->id_to_connection_ids[$id]);
                }
            }
        }

    }

    public function getConnections()
    {
        return $this->connections;
    }

    public function getConnectionCount()
    {
        return $this->connections->count();
    }

    public function getConnectionData($connection)
    {
        if ($this->connections->contains($connection)) {
            return $this->connections[$connection];
        }
        return null;
    }

    public function getRandConnection()
    {
        if (empty($this->connection_id_to_connection)) {
            return null;
        }
        $_ids = array_keys($this->connection_id_to_connection);
        var_dump($_ids);
        return $this->connection_id_to_connection[$_ids[array_rand($_ids)]] ?? null;
    }



    protected function _leaveAllGroup($connection)
    {
        if ($connection && isset($this->connection_id_to_group_ids[$connection->_id])) {
            foreach ($this->connection_id_to_group_ids[$connection->_id] as $groupId) {
                $this->_leaveGroup($groupId, $connection);
            }
        }
    }

    protected function _leaveGroup($groupId, $connection)
    {
        if ($connection && isset($this->groups[$groupId])) {
            if (isset($this->connection_id_to_group_ids[$connection->_id][$groupId])) {

                $this->groups[$groupId]->detach($connection);
                unset($this->connection_id_to_group_ids[$connection->_id][$groupId]);

                // 避免无效的占用空数据
                if ($this->groups[$groupId]->count() == 0) {
                    unset($this->groups[$groupId]);
                }

                // 避免无效的占用空数据
                if (count($this->connection_id_to_group_ids[$connection->_id]) == 0) {
                    unset($this->connection_id_to_group_ids[$connection->_id]);
                }
            }
        }
    }

    protected function _joinGroup($groupId, $connection)
    {
        if (!isset($this->groups[$groupId])) {
            $this->groups[$groupId] = new \SplObjectStorage;
        }

        // 避免重复加入
        if ($this->groups[$groupId]->contains($connection)) {
            return ;
        }

        $this->groups[$groupId]->attach($connection);
        $this->connection_id_to_group_ids[$connection->_id][$groupId] = $groupId;
    }


    protected function _sendMessageToGroup($groupId, $msg, $excludeIds = [], $exclude_Ids = [])
    {
        if (isset($this->groups[$groupId])) {
            foreach ($this->groups[$groupId] as $connection) {
                $id = $this->_getIdBy_Id($connection->_id);
                if (in_array($id, $excludeIds) || in_array($connection->_id, $exclude_Ids)) {
                    continue;
                }
                $this->send($connection, $msg);
            }
        }
    }

    protected function _sendMessageToGroupBy_Id($groupId, $_id, $msg)
    {
        if ($this->_isInGroup($groupId, $_id)) {
            $connection = $this->getConnectionBy_Id($_id);
            $this->send($connection, $msg);
        }     
    }

    protected function _sendMessageToGroupByOnly_Id($_id, $msg)
    {
        $groupIds = $this->_getGroupIdsBy_Id($_id);
        foreach ($groupIds as $groupId) {
            $this->_sendMessageToGroupBy_Id($groupId, $_id, $msg);
        }
    }

    protected function _getGroupIdsBy_Id($_id)
    {
        return $this->connection_id_to_group_ids[$_id] ?? [];
    }

    protected function _isInGroup($groupId, $_id)
    {
        return $this->connection_id_to_group_ids[$_id][$groupId] ?? false;
    }

    protected function _sendMessageTo_Id($_id, $msg)
    {
        $connection = $this->getConnectionBy_Id($_id);
        if ($connection) {
            var_dump($msg,222);
        }
        $this->send($connection, $msg);
    }

    public function getConnectionBy_Id($_id)
    {
        return $this->connection_id_to_connection[$_id] ?? null;
    }

    public function send($connection, $data)
    {
        if ($connection) {
            // 服务端的信息流通
            if ($connection instanceof ConnectionInterface && is_array($data)) {
                $this->write($connection, $data);
            }
            // 客户端的信息流通
            elseif (method_exists($connection, 'send')) {
                $connection->send($data);
            }
            else {
                throw new \Exception("not exist method send or write", 1);
            }
        }
    }




    // 以下是对用户ID和connectionId的绑定关系的操作
    public function bindId($id, $_id)
    {
        $connection = $this->getConnectionBy_Id($_id);
        $this->_bindIdAndConnection($id, $connection);
    }

    protected function _bindIdAndConnection($id, $connection)
    {
        if ($connection) {
            // 避免被重复绑定（一个ID可以对应多个_id, 但一个_id 只能对应一个ID）

            if (!isset($this->connection_id_to_id[$connection->_id])) {
                $this->id_to_connection_ids[$id][$connection->_id] = $connection->_id;
                $this->connection_id_to_id[$connection->_id] = $id;
            }
        }
       
    }

    public function unBindId($id)
    {
        $_ids = $this->_get_IdsById($id);
        foreach ($_ids as $_id) {
            $this->_unBindIdAnd_Id($id, $_id);
        }
    }

    public function unBind_Id($_id)
    {
        $id = $this->_getIdBy_Id($_id);
        $this->_unBindIdAnd_Id($id, $_id);
    }

    protected function _get_IdsById($id)
    {
        return $this->id_to_connection_ids[$id] ?? [];
    }

    protected function _getIdBy_Id($_id)
    {
        return $this->connection_id_to_id[$_id] ?? 0;
    }

    protected function _unBindIdAnd_Id($id, $_id)
    {
        if (isset($this->id_to_connection_ids[$id][$_id])) {
            unset($this->id_to_connection_ids[$id][$_id]);
            unset($this->connection_id_to_id[$_id]);

            if ($this->_getIdTo_IdsCount($id) == 0) {
                unset($this->id_to_connection_ids[$id]);
            }
        }
    }

    protected function _getIdTo_IdsCount($id)
    {
        return count($this->id_to_connection_ids[$id] ?? []);
    }


    public function joinGroupById($groupId, $id)
    {
        $_ids = $this->_get_IdsById($id);
        foreach ($_ids as $_id) {
            $this->joinGroupBy_Id($groupId, $_id);
        }
    }

    public function joinGroupBy_Id($groupId, $_id)
    {
        $connection = $this->getConnectionBy_Id($_id);
        $this->_joinGroup($groupId, $connection);
    }

    public function leaveGroupById($groupId, $id)
    {
        $_ids = $this->_get_IdsById($id);
        foreach ($_ids as $_id) {
            $this->leaveGroupBy_Id($groupId, $_id);
        }
    }

    public function leaveGroupBy_Id($groupId, $_id)
    {
        $connection = $this->getConnectionBy_Id($_id);
        $this->_leaveGroup($groupId, $connection);
    }

    public function leaveAllGroupById($id)
    {
        $_ids = $this->_get_IdsById($id);
        foreach ($_ids as $_id) {
            $this->leaveAllGroupBy_Id($_id);
        }
    }

    public function leaveAllGroupBy_Id($_id)
    {
        $connection = $this->getConnectionBy_Id($_id);
        $this->_leaveAllGroup($connection);
    }

    public function sendMessageToId($id, $msg, $exclude_Ids = [])
    {
        $_ids = $this->_get_IdsById($id);
        foreach (array_diff($_ids, $exclude_Ids) as $_id) {
            $this->sendMessageTo_Id($_id, $msg);
        }
    }

    public function sendMessageTo_Id($_id, $msg)
    {
        $this->_sendMessageTo_Id($_id, $msg);
    }

    public function sendMessageToGroupExceptIds($groupId, $msg, $excludeIds = [])
    {
        $this->_sendMessageToGroup($groupId, $msg, $excludeIds);
    }

    public function sendMessageToGroupExcept_Ids($groupId, $msg, $exclude_Ids = [])
    {
        $this->_sendMessageToGroup($groupId, $msg, [], $exclude_Ids);
    }



    public function isInGroupById($groupId, $id)
    {
        $_ids = $this->_get_IdsById($id);
        foreach ($_ids as $_id) {
            if ($this->_isInGroup($groupId, $_id)) {
                return true;
            }
        }
        return false;
    }

    public function isInGroupBy_Id($groupId, $_id)
    {
        return $this->_isInGroup($groupId, $_id);
    }

    // 给ID下的所有房间发送消息 除了 _ids
    public function sendMessageToGroupByOnlyId($id, $msg, $exclude_Ids)
    {
        $_ids = $this->_get_IdsById($id);
        foreach ($_ids as $_id) {
            if (in_array($_id, $exclude_Ids)) {
                continue;
            }
            $this->_sendMessageToGroupByOnly_Id($_id, $msg);
        }
    }

    // 给_id下的所有房间发送消息

    public function sendMessageToGroupByOnly_Id($_id, $msg)
    {
        $this->_sendMessageToGroupByOnly_Id($_id, $msg);
    }

    // 广播所有消息给客户端
    public function broadcast($msg, $exclude_Ids = [])
    {
        foreach ($this->connections as $connection) {
            if (in_array($connection->_id, $exclude_Ids)) {
                continue;
            }
            $this->send($connection, $msg);
        }
    }

    // 广播所有组信息一次
    
    public function broadcastToAllGroupOnce($data)
    {
        $keys = [];
        foreach (array_keys($this->groups) as $key => $groupId) {
            $data['data']['data']['message_key'] = $key;
            $this->broadcastToGroupOnce($groupId, $data);
            $keys[] = $key;
        }
        return $keys;
    }

    public function broadcastToGroupOnce($groupId, $data)
    {
        $group = $this->groups[$groupId] ?? [];
        $index = array_rand(array_fill(0, count($group), 0));
        foreach ($group as $key => $connection) {
            if ($index === $key) {
                // 仅仅发送一次
                $this->send($connection, $data);
                break;
            }

        }
    }

    public function randSendToConnection($data)
    {
        $connection = $this->getRandConnection();
        if ($connection) {
            $this->send($connection, $data);
        }
    }


    public function isOnlineId($id)
    {
        return $this->_getIdTo_IdsCount($id) > 0;
    }

    public function isOnline_Id($_id)
    {
        return isset($this->connection_id_to_connection[$_id]);
    }


    public function getIds()
    {
        return array_keys($this->id_to_connection_ids);
    }
    public function getBindIds()
    {
        return array_keys($this->id_to_connection_ids);
    }

    public function get_Ids()
    {
        return array_keys($this->connection_id_to_connection);
    }
    public function getBind_Ids()
    {
        return array_keys($this->connection_id_to_id);
    }

    public function getIdCount()
    {
        return count($this->getIds());
    }
    public function getBindIdCount()
    {
        return count($this->getIds());
    }

    public function get_IdCount()
    {
        return count($this->get_Ids());
    }

    public function getBind_IdCount()
    {
        return count($this->getBind_Ids());
    }

    // id 加入房间
    public function getGroupIdsById($id)
    {
        $_ids = $this->_get_IdsById($id);
        $groupIds = [];
        foreach ($_ids as $_id) {
            $groupIds = array_merge($groupIds, $this->_getGroupIdsBy_Id($_id));
        }
        return array_values(array_unique($groupIds));
    }

    // _id 加入房间
    public function getGroupIdsBy_Id($_id)
    {
        return $this->_getGroupIdsBy_Id($_id);
    }

    // id 加入房间数量
    public function getGroupCountById($id)
    {
        return count($this->getGroupIdsById($id));
    }

    // _id 加入房间数量
    public function getGroupCountBy_Id($_id)
    {
        return count($this->getGroupIdsBy_Id($_id));
    }

}