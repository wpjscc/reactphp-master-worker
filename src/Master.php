<?php

namespace Wpjscc\MasterWorker;

use React\Socket\ConnectionInterface;
use React\Socket\TcpConnector;
use React\Socket\SocketServer;
use Clue\React\NDJson\Decoder;

class Master extends Base
{

    protected $workers = [];

    protected $client_id_to_client = [];

    protected $retrySecond = 3;

    protected function init()
    {

        // 注册中心
        $this->on('register_open', [$this, '_register_open']);
        $this->on('register_reply', [$this, '_register_reply']);
        // todo registe message
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

        // worker message
        $this->on('worker_client_message', [$this, '_worker_client_message']);
        $this->on('worker_client_close', [$this, '_worker_client_close']);

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
        // $this->addWorker($connection);
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

    protected function addWorker(ConnectionInterface $connection)
    {
        if (array_search($connection, $this->workers) === false) {
            $this->workers[] = $connection;
        }
    }

    protected function removeWorker(ConnectionInterface $connection)
    {
        $index = array_search($connection, $this->workers);
        if ($index !== false) {
            unset($this->workers[$index]);
        }
    }


    // client
    // 客户端链接打开 转给 worker
    protected function _client_open($connection, $msg)
    {
        // $_worker = $this->getWorker($connection);
        // if ($_worker) {
        //     $this->write($_worker, [
        //         'event' => 'client_open',
        //         'data' => [
        //             'client_id' => $connection->id,
        //             'message' => $msg
        //         ]
        //     ]);
        // }

        // $this->client_id_to_client[$connection->id] = $connection;

        ConnectionManager::instance('client')->addConnection($connection, $msg);
    }
    // 客户端消息 转给 worker
    protected function _client_message($connection, $msg)
    {
        // $_worker = $this->getWorker($connection);
        // if ($_worker) {
        //     $this->write($_worker, [
        //         'event' => 'client_message',
        //         'data' => [
        //             'client_id' => $connection->_id,
        //             // client msg
        //             'message' => $msg
        //         ]
        //     ]);
        //     return true;
        // }
        // return false;

        // todo worker 发送消息 
        ConnectionManager::instance('worker')->randSendToConnection([
            'event' => 'client_message',
            'data' => [
                'client_id' => $connection->_id,
                // client msg
                'message' => $msg
            ]
        ]);

    }
    // 客户端关闭 转给 worker
    protected function _client_close($connection)
    {
        // if (isset($connection->_worker)) {
        //     $this->write($connection->_worker, [
        //         'event' => 'client_close',
        //         'data' => [
        //             'client_id' => $connection->_id
        //         ]
        //     ]);
        // }

        // unset($this->client_id_to_client[$connection->id]);

        ConnectionManager::instance('client')->closeConnection($connection);

    }
    
    // 收到worker 的客户端信息
    protected function _worker_client_message(ConnectionInterface $connection, $data)
    {
        // $client_id = $data['client_id'] ?? '';
        // $client = $this->client_id_to_client[$client_id] ?? '';
        // if ($client) {
        //     $client->write($data['message'] ?? '');
        // }
        // todo todo一个公共的处理消息的方法
    }
    // worker 主动关闭 客户端
    protected function _worker_client_close(ConnectionInterface $connection, $data)
    {
        // $client_id = $data['client_id'] ?? '';
        // $client = $this->client_id_to_client[$client_id] ?? '';
        // if ($client) {
        //     $client->end($data['message'] ?? '');
        //     unset($this->client_id_to_client[$client_id]);
        // }
        // todo 关闭
        ConnectionManager::instance('client')->closeConnection($connection);

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