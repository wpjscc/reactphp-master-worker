<?php

namespace Wpjscc\MasterWorker;

use React\Socket\ConnectionInterface;
use React\Socket\TcpConnector;

class Worker extends Base
{
    protected $addressToMaster = [];

    protected $masterAddresses = [];

    protected $retrySecond = 3;


    protected function init()
    {
        // 注册中心
        $this->on('register_reply', [$this, '_register_reply']);
        $this->on('broadcast_master_address', [$this, '_broadcast_master_address']);
        $this->on('register_close', [$this, '_register_close']);

        // master
        $this->on('master_open', [$this, '_master_open']);
        $this->on('master_reply', [$this, '_master_reply']);
        $this->on('master_close', [$this, '_master_close']);

        // client
        $this->on('client_open', [$this, '_client_open']);
        $this->on('client_message', [$this, '_client_message']);
        $this->on('client_close', [$this, '_client_close']);

        $this->on('ping', [$this, '_ping']);
        $this->on('pong', [$this, '_pong']);

    }

    protected function _register_reply(ConnectionInterface $connection, $data)
    {
        $this->info('register_reply');
    }

    protected function _broadcast_master_address(ConnectionInterface $connection, $data)
    {
        $this->info('broadcast_master_address');
        var_dump($data);
        if (isset($data['master_address'])) {
            $this->connectToMaster($data['master_address']);
        }
    }

    protected function connectToMaster($address)
    {

        // 已经在了
        if (in_array($address, $this->masterAddresses)) {
            return;
        }

        $tcpConnector = new TcpConnector();
        $tcpConnector->connect($address)->then(function (ConnectionInterface $connection) use ($address) {

            Worker::instance()->emit('master_open', [$connection]);
            // 给服务端发送消息
            Worker::instance()->write($connection, [
                'event' => 'worker_coming',
                // todo 这里可以发送验证信息
                'data' => []
            ]);
        
            $ndjson = new \Clue\React\NDJson\Decoder($connection, true);
            $ndjson->on('data', function ($data) use ($connection) {
                $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');
                if ($event) {
                    Worker::instance()->emit($event, [$connection, $data['data'] ?? []]);
                }
            });
        
            $ndjson->on('close', function () use ($connection) {
                Worker::instance()->emit('master_close', [$connection]);
            });
            
             // 非本地 和server保持心跳
            if (strpos($address, '127.0.0.1') !== 0) {
                Worker::instance()->ping($connection);
            }
        });

    }

    protected  function _register_close(ConnectionInterface $connection)
    {
        $this->info('register_close');
    }

    // worker 可以在这里初始化
    protected function _master_open(ConnectionInterface $connection)
    {
        $this->info('master_open');
        // todo 
        $this->emit('workerOpen', [$connection]);
    }

    protected function _master_reply(ConnectionInterface $connection, $data)
    {
        $this->info('master_reply');
            $this->addMaster($connection, $data['address'] ?? '');
    }

    protected function _master_close(ConnectionInterface $connection)
    {
        $this->info('master_close');
        $this->removeMaster($connection);
    }

    protected function addMaster($connection, $address)
    {
        if ($address) {
            $this->addressToMaster[$address] = $connection;
        }
    }

    protected function removeMaster($connection)
    {
        $address = array_search($connection, $this->addressToMaster);

        if ($address !== false) {
            unset(static::$addressToMaster[$address]);
            if (($key = array_search($address, $this->masterAddresses)) !== false) {
                unset($this->masterAddresses[$key]);
            }
        }
    }


    protected function _client_open(ConnectionInterface $connection, $data)
    {
        $this->info('client_open');
        // todo 业务处理 client open
        $clientId = $data['client_id'] ?? '';
        $data = $data['message'] ?? '';
        $this->emit('clientOpen', [$clientId, $data]);
    }

    protected function _client_message(ConnectionInterface $connection, $data)
    {
        
        $this->info('client_message');
        // todo client message
        $clientId = $data['client_id'] ?? '';
        $data = $data['message'] ?? '';
        $this->emit('clientMessage', [$clientId, $data]);
    }

    protected function _client_close(ConnectionInterface $connection, $data)
    {
        $this->info('client_close');
        // todo client close
        $clientId = $data['client_id'] ?? '';
        $data = $data['message'] ?? '';
        $this->emit('clientClose', [$clientId, $data]);
    }
    

    public function run()
    {
        $this->connectRegister();
    }

    protected function connectRegister()
    {
        $tcpConnector = new TcpConnector();
        $tcpConnector->connect(getParam('--register-address'))->then(function (ConnectionInterface $connection) {
            Worker::instance()->emit('register_open', [$connection]);
            // 给服务端发送消息
            Worker::instance()->write($connection, [
                'event' => 'worker_coming',
                // todo 这里可以发送验证信息
                'data' => []
            ]);
        
            $ndjson = new \Clue\React\NDJson\Decoder($connection, true);
            $ndjson->on('data', function ($data) use ($connection) {
                $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');
                if ($event) {
                    Worker::instance()->emit($event, [$connection, $data['data'] ?? []]);
                }
            });
        
            $ndjson->on('close', function () use ($connection) {
                Worker::instance()->emit('register_close', [$connection]);
            });

            // 非本地 和注册中心保持心跳
            if (strpos(getParam('--register-address'), '127.0.0.1') !== 0) {
                Worker::instance()->ping($connection);
            }

            $connection->on('close', function() {
                Worker::instance()->retryConnectRegister();
            });


        }, function ($exception) {
            Worker::instance()->info($exception);
            Worker::instance()->retryConnectRegister();
        });
    }

    public function retryConnectRegister()
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