<?php

namespace Wpjscc\MasterWorker;

use React\Socket\ConnectionInterface;
use React\Socket\TcpConnector;

class Worker extends Base
{

    use Traits\HttpServer;

    protected $masterAddresses = [];

    protected $retrySecond = 3;

    protected $workerStartCount = 5;


    protected function init()
    {
        // 注册中心
        $this->on('register_reply', [$this, '_register_reply']);
        $this->on('broadcast_master_address', [$this, '_broadcast_master_address']);
        $this->on('register_close', [$this, '_register_close']);

        // master
        $this->on('master_open', [$this, '_master_open']);
        $this->on('master_reply', [$this, '_master_reply']);
        $this->on('master_message', [$this, '_master_message']);
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
        if (isset($data['master_address'])) {
            $this->connectMaster($data['master_address']);
        }
    }

    protected function connectMaster($address)
    {

        // 已经在连接了
        if (isset($this->masterAddresses[$address])) {
            if ($this->masterAddresses[$address]>=$this->workerStartCount) {
                return;
            }
        } else {
            $this->masterAddresses[$address] = 0;
        }

        $count = $this->workerStartCount-$this->masterAddresses[$address];

        for ($i=0; $i < $count; $i++) { 
            $this->masterAddresses[$address]++;
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
            }, function($e) use ($address) {
                $this->info('connect master error: ' . $e->getMessage());
                $this->masterAddresses[$address]--;
                if ($this->masterAddresses[$address] <= 0) {
                    unset($this->masterAddresses[$address]);
                }
            });
        }

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
    }

    protected function _master_reply(ConnectionInterface $connection, $data)
    {
        $this->info('master_reply');
        $this->addMaster($connection, $data);
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
            Client::instance('worker')->emit($event, [$connection, $data['data']['data'] ?? []]);
        }

    }

    protected function _master_close(ConnectionInterface $connection)
    {
        $this->info('master_close');
        $this->removeMaster($connection);
    }

    protected function addMaster($connection, $data)
    {
        $address = $data['master_address'] ?? '';
        if ($address) {
            // $this->addressToMaster->attach($connection, $address);
            ConnectionManager::instance('worker_master')->addConnection($connection, $data);
            ConnectionManager::instance('worker_master')->bindId($address, $connection->_id);
            ConnectionManager::instance('worker_master')->joinGroupById($address, $address);
        }
    }

    protected function removeMaster($connection)
    {
        $data = ConnectionManager::instance('worker_master')->getConnectionData($connection);
        $address = $data['master_address'] ?? null;
        if ($address) {
            if (isset($this->masterAddresses[$address])) {
                $this->masterAddresses[$address]--;
                if ($this->masterAddresses[$address] <= 0) {
                    unset($this->masterAddresses[$address]);
                }
            }
        }
        ConnectionManager::instance('worker_master')->closeConnection($connection);
    }


    protected function _client_open(ConnectionInterface $connection, $data)
    {
        $this->info('client_open');
        // todo 业务处理 client open
        $clientId = $data['client_id'] ?? '';
        $data = $data['data'] ?? [];
        $this->emit('clientOpen', [$clientId, $data]);
    }

    protected function _client_message(ConnectionInterface $connection, $data)
    {
        
        $this->info('client_message');
        // todo client message
        $clientId = $data['client_id'] ?? '';
        $message = $data['message'] ?? '';
        var_dump($data);
        $this->emit('clientMessage', [$clientId, $message]);
    }

    protected function _client_close(ConnectionInterface $connection, $data)
    {
        $this->info('client_close');
        // todo client close
        $clientId = $data['client_id'] ?? '';
        $data = $data['data'] ?? [];
        $this->emit('clientClose', [$clientId, $data]);
    }
    

    public function run()
    {
        $this->emit('workerOpen', []);

        $this->connectRegister();
        if (getParam('--is-http-server')) {
            $this->runHttpServer('worker');
        }

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