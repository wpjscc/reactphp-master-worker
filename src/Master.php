<?php

namespace Wpjscc\MasterWorker;

use React\Socket\ConnectionInterface;
use React\Socket\TcpConnector;
use React\Socket\SocketServer;
use Clue\React\NDJson\Decoder;

class Master extends Base
{

    protected $retrySecond = 3;

    protected function init()
    {

        // 注册中心
        $this->on('register_open', [$this, '_register_open']);
        $this->on('register_reply', [$this, '_register_reply']);
        $this->on('register_message', [$this, '_register_message']);
        $this->on('register_close', [$this, '_register_close']);

        // worker
        $this->on('worker_open', [$this, '_worker_open']);
        $this->on('worker_coming', [$this, '_worker_coming']);
        $this->on('worker_message', [$this, '_worker_message']);
        $this->on('worker_close', [$this, '_worker_close']);

        // client
        $this->on('client_open', [$this, '_client_open']);
        $this->on('client_message', [$this, '_client_message']);
        $this->on('client_close', [$this, '_client_close']);

        // // worker message
        // $this->on('worker_client_message', [$this, '_worker_client_message']);
        // $this->on('worker_client_close', [$this, '_worker_client_close']);

        $this->on('ping', [$this, '_ping']);
        $this->on('pong', [$this, '_pong']);


    }

    // 注册中心
    protected function _register_open(ConnectionInterface $connection)
    {
        $this->info('register_open');
    }

    protected function _register_reply(ConnectionInterface $connection)
    {
        $this->info('register_reply');
    }


    protected function _register_message(ConnectionInterface $connection, $data)
    {
        $this->info('register_message');
        var_dump($data);
        $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');
        if ($event) {
            Client::instance('register')->emit('register_'.$event, [$connection, $data['data'] ?? []]);
        }

    }

    protected function _register_close(ConnectionInterface $connection)
    {
        $this->info('register_close');
    }

    // worker
    protected function _worker_open(ConnectionInterface $connection)
    {
        $this->info('worker_open');

    }


    protected function _worker_coming(ConnectionInterface $connection, $data)
    {
        $this->info('worker_coming');
        $this->replyWorker($connection);
        // todo  $data 验证信息
        ConnectionManager::instance('worker')->addConnection($connection, $data);

        $this->info('worker_count:'. ConnectionManager::instance('worker')->getConnectionCount());

    }

    protected function _worker_message(ConnectionInterface $connection, $data)
    {
        $this->info('worker_message');
        var_dump($data);
        $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');
        if ($event) {
            Client::instance('worker')->emit('worker_'.$event, [$connection, $data['data'] ?? []]);
        }

    }

    protected function _worker_close(ConnectionInterface $connection)
    {
        // $this->removeWorker($connection);
        ConnectionManager::instance('worker')->closeConnection($connection);
        $this->info('worker_close');
        $this->info('worker_count:'. ConnectionManager::instance('worker')->getConnectionCount());


    }
    

    protected function replyWorker(ConnectionInterface $connection)
    {
        $this->write($connection, [
            'event' => 'master_reply',
            'data' => [
                'master_address' => getParam('--master-address')
            ]
        ]);
    }


    // client
    // 客户端链接打开 转给 worker
    protected function _client_open($connection, $data)
    {
        
        ConnectionManager::instance('client')->addConnection($connection, $data);

        $state = ConnectionManager::instance('worker')->randSendToConnection([
            'event' => 'client_open',
            'data' => [
                'client_id' => $connection->_id,
                // client msg
                'data' => [
                    'get_IdData' => ConnectionManager::instance('client')->get_IdData($connection->_id),
                ]
            ]
        ]);

        // 说明没有worker--没必要在再去实现，理论上会有worker
        // if (!$state) {
        //     Worker::instance('master')->emit('clientOpen', [
        //         $connection->_id,
        //         [
        //             'get_IdData' => ConnectionManager::instance('client')->get_IdData($connection->_id),
        //         ] 
        //     ]);
        // }
    }
    // 客户端消息 转给 worker
    protected function _client_message($connection, $msg)
    {
        // todo worker 发送消息 
        $state = ConnectionManager::instance('worker')->randSendToConnection([
            'event' => 'client_message',
            'data' => [
                'client_id' => $connection->_id,
                // client msg
                'message' => $msg
            ]
        ]);

        // 说明没有worker，没必要在再去实现，理论上会有worker
        // if (!$state) {
        //     Worker::instance('master')->emit('clientMessage', [
        //         $connection->_id,
        //         $msg
        //     ]);
        // }

    }
    // 客户端关闭 转给 worker
    protected function _client_close($connection)
    {

        if (ConnectionManager::instance('client')->getConnectionBy_Id($connection->_id)) {
            ConnectionManager::instance('client')->closeConnection($connection);

            $state = ConnectionManager::instance('worker')->randSendToConnection([
                'event' => 'client_close',
                'data' => [
                    'client_id' => $connection->_id,
                    // client msg
                    'data' => [
                        'getIdBy_Id' => ConnectionManager::instance('client')->getIdBy_Id($connection->_id),
                        'getGroupIdsBy_Id' => ConnectionManager::instance('client')->getGroupIdsBy_Id($connection->_id)
                    ]
                ]
            ]);

            // // 说明没有worker，没必要在再去实现，理论上会有worker
            // if (!$state) {
            //     Worker::instance('master')->emit('clientClose', [
            //         $connection->_id,
            //         [
            //             'getIdBy_Id' => ConnectionManager::instance('client')->getIdBy_Id($connection->_id),
            //             'getGroupIdsBy_Id' => ConnectionManager::instance('client')->getGroupIdsBy_Id($connection->_id)
            //         ]
            //     ]);
            // }
    
        }
        

    }

    public function run()
    {
        $this->runMaster();
        $this->connectRegister();
    }

    protected function runMaster()
    {
        $socket = new SocketServer(getParam('--master-address'));

        $socket->on('connection', function (ConnectionInterface $connection) {
            Master::instance()->emit('worker_open', [$connection]);
            $ndjson = new \Clue\React\NDJson\Decoder($connection, true);
            $ndjson->on('data', function ($data) use ($connection) {
                $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');

                if ($event) {
                    Master::instance()->emit($event, [$connection, $data['data'] ?? []]);
                }
            });

            $ndjson->on('close', function () use ($connection) {
                Master::instance()->emit('worker_close', [$connection]);
            });

        });
        echo "Master Listen At: ". getParam('--master-address') ."\n";
    }

    protected function connectRegister()
    {
        $tcpConnector = new TcpConnector();
        $tcpConnector->connect(getParam('--register-address'))->then(function (ConnectionInterface $connection) {
            try {
                Master::instance()->emit('register_open', [$connection]);
                Master::instance()->write($connection, [
                    'event' => 'master_coming',
                    'data' => [
                        'master_address' => getParam('--master-address')
                    ]
                ]);
    
                $ndjson = new Decoder($connection, true);
                $ndjson->on('data', function ($data) use ($connection) {
                    $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');
                    if ($event) {
                        Master::instance()->emit($event, [$connection, $data['data'] ?? []]);
                    }
                });
        
                $ndjson->on('close', function () use ($connection) {
                    Master::instance()->emit('register_close', [$connection]);
                });
    
                // 非本地 和注册中心保持心跳
                if (strpos(getParam('--register-address'), '127.0.0.1') !== 0) {
                    Master::instance()->ping($connection);
                }
    
                $connection->on('close', function() {
                    Master::instance()->retryConnectRegister();
                });
            } catch (\Throwable $th) {
                Master::instance()->info($th);

            }
            


        }, function ($exception) {
            Master::instance()->info($exception);
            Master::instance()->retryConnectRegister();
        });

    }

    protected function retryConnectRegister()
    {
        $retrySecond = $this->getRetrySecond();
        $this->info($retrySecond .' 秒后重新连接');
        \React\EventLoop\Loop::get()->addTimer($retrySecond, function() {
            $this->connectRegister();
        });
    }
    

    public function getRetrySecond()
    {
        return $this->retrySecond;
    }


}