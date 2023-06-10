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
        $this->on('register_close', [$this, '_register_close']);

        // worker
        $this->on('worker_open', [$this, '_worker_open']);
        $this->on('worker_coming', [$this, '_worker_coming']);
        $this->on('worker_close', [$this, '_worker_close']);

        // client
        $this->on('client_open', [$this, '_client_open']);
        $this->on('client_message', [$this, '_client_message']);
        $this->on('client_close', [$this, '_client_close']);

        // worker message
        $this->on('worker_message', [$this, '_worker_message']);

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
        // todo  $data 验证信息
        $this->addWorker($connection);
    }

    protected function _worker_close(ConnectionInterface $connection)
    {
        $this->removeWorker($connection);
        $this->info('worker_close');
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

    public function getWorkerCount()
    {
        return count($this->workers);
    }

    public function getWorker(ConnectionInterface $connection)
    {
       if (isset($connection->_worker)) {
           return $connection->_worker;
       }

       if ($this->getWorkerCount() == 0) {
           return null;
       }

       $connection->_worker = $this->workers[array_rand($this->workers)];
       $connection->_worker->on('close', function() use ($connection) {
           unset($connection->_worker);
       });

       return $connection->_worker;

    }


    // client
    // 客户端链接打开 转给 worker
    protected function _client_open(ConnectionInterface $connection, $msg)
    {
        $_worker = $this->getWorker($connection);
        if ($_worker) {
            $this->write($_worker, [
                'event' => 'client_open',
                'data' => [
                    'client_id' => $connection->id,
                    'message' => $msg
                ]
            ]);
        }

        $this->client_id_to_client[$connection->id] = $connection;
    }
    // 客户端消息 转给 worker
    protected function _client_message(ConnectionInterface $connection, $msg)
    {
        $_worker = $this->getWorker($connection);
        if ($_worker) {
            $this->write($_worker, [
                'event' => 'client_message',
                'data' => [
                    'client_id' => $connection->id,
                    // client msg
                    'message' => $msg
                ]
            ]);
            return true;
        }
        return false;
    }
    // 客户端关闭 转给 worker
    protected function _client_close(ConnectionInterface $connection)
    {
        if (isset($connection->_worker)) {
            $this->write($connection->_worker, [
                'event' => 'client_close',
                'data' => [
                    'client_id' => $connection->id
                ]
            ]);
        }

        unset($this->client_id_to_client[$connection->id]);
    }
    
    // 收到worker 的客户端信息
    protected function _worker_client_message(ConnectionInterface $connection, $data)
    {
        $client_id = $data['client_id'] ?? '';
        $client = $this->client_id_to_client[$client_id] ?? '';
        if ($client) {
            $client->write($data['message'] ?? '');
        }
    }
    // worker 主动关闭 客户端
    protected function _worker_client_close(ConnectionInterface $connection, $data)
    {
        $client_id = $data['client_id'] ?? '';
        $client = $this->client_id_to_client[$client_id] ?? '';
        if ($client) {
            $client->end($data['message'] ?? '');
            unset($this->client_id_to_client[$client_id]);
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