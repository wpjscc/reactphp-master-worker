<?php

namespace Wpjscc\MasterWorker;

use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class Register extends Base
{

    use Traits\HttpServer;
    
    protected function init()
    {

        $this->on('open', [$this, '_open']);
        $this->on('master_coming', [$this, '_master_coming']);
        $this->on('master_message', [$this, '_master_message']);
        $this->on('worker_coming', [$this, '_worker_coming']);
        $this->on('close', [$this, '_close']);

        $this->on('ping', [$this, '_ping']);
        $this->on('pong', [$this, '_pong']);
    }

    protected function _open(ConnectionInterface $connection)
    {
        $this->info('open');
    }

    protected function _master_coming(ConnectionInterface $connection, $data)
    {
        $this->info('master_coming');
        $this->reply($connection);
        
        ConnectionManager::instance('register_master')->addConnection($connection, $data);
        ConnectionManager::instance('register_master')->bindId('master', $connection->_id);
        ConnectionManager::instance('register_master')->joinGroupById('master', 'master');

        $this->broadcastToWorkers($connection);
    }
    protected function _master_message(ConnectionInterface $connection, $data)
    {
        $this->info('master_message');
        var_dump($data);

        $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');

        if ($event) {
            $messageId = $data['data']['message_id'] ?? '';
            $messageKey = $data['data']['message_key'] ?? '';
            if ($messageId) {
                $event .= ':' . $messageId;
            }
            if (strlen($messageKey)>0) {
                $event .= ':' . $messageKey;
            }
            Client::instance('register')->emit($event, [$connection, $data['data']['data'] ?? []]);
        }
    }
    

    protected function reply(ConnectionInterface $connection)
    {
        $this->write($connection, [
            'event' => 'register_reply',
            'data' => []
        ]);
    }

    protected function broadcastToWorkers(ConnectionInterface $master)
    {
        // 给所有worker 发送当前的 master 数据
        ConnectionManager::instance('register_worker')->broadcast([
            'event' => 'broadcast_master_address',
            'data' => ConnectionManager::instance('register_master')->getConnectionData($master)
        ]);
    }

    protected function _worker_coming(ConnectionInterface $connection, $data)
    {
        $this->info('worker_coming');
        $this->reply($connection);

        ConnectionManager::instance('register_worker')->addConnection($connection, $data);
        ConnectionManager::instance('register_worker')->bindId('worker', $connection->_id);
        ConnectionManager::instance('register_worker')->joinGroupById('worker', 'worker');

        $this->broadcastMasterToWorkerByWorker($connection);
    }

    protected function broadcastMasterToWorkerByWorker(ConnectionInterface $worker)
    {
        // 给当前连接过来的 worker 发送所有master数据
        ConnectionManager::instance('register_master')->broadcastToConnection($worker, [
            'event' => 'broadcast_master_address',
        ]);
    }

    protected function _close(ConnectionInterface $connection)
    {
        if (ConnectionManager::instance('register_master')->closeConnection($connection)) {
            $this->info('master_close');
        }
        elseif (ConnectionManager::instance('register_worker')->closeConnection($connection)) {
            $this->info('worker_close');
        }
        $this->info('close');
        
    }


    public function run()
    {

        $this->runRegisterCenter();

        if (getParam('--is-http-server')) {
            $this->runHttpServer('register');
        }
    }

    public function runRegisterCenter()
    {
        $socket = new SocketServer(getParam('--register-address') ?: '0.0.0.0:9234');

        $socket->on('connection', function (ConnectionInterface $connection) {
            Register::instance()->emit('open', [$connection]);
            $ndjson = new \Clue\React\NDJson\Decoder($connection, true);
            
            $ndjson->on('data', function ($data) use ($connection) {
                $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');
                if ($event) {
                    Register::instance()->emit($event, [$connection, $data['data'] ?? []]);
                }
            });

            $ndjson->on('close', function () use ($connection) {
                Register::instance()->emit('close', [$connection]);
            });

            $ndjson->on('error', function ($e)  {
                Register::instance()->info($e);
            });

        });

        echo "Register Listen At: ". (getParam('--register-address') ?: '0.0.0.0:9234') ."\n";
    }


}