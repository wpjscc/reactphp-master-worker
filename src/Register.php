<?php

namespace Wpjscc\MasterWorker;

use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class Register extends Base
{
    protected $masters;
    protected $workers;

    protected function init()
    {
        $this->workers = new \SplObjectStorage;
        $this->masters = new \SplObjectStorage;

        $this->on('open', [$this, '_open']);
        $this->on('master_coming', [$this, '_master_coming']);
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
        $this->masters->attach($connection, $data);
        $this->broadcastToWorkers($connection);
    }
    

    public function reply(ConnectionInterface $connection)
    {
        $this->write($connection, [
            'event' => 'register_reply',
            'data' => []
        ]);
    }

    protected function broadcastToWorkers(ConnectionInterface $master, $workers = [])
    {
        $workers = $workers ?: $this->workers;

        foreach ($workers as $worker) {
            $this->write($worker, [
                'event' => 'broadcast_master_address',
                'data' => $this->masters[$master]
            ]);
        }
    }

    protected function _worker_coming(ConnectionInterface $connection, $data)
    {
        $this->info('worker_coming');
        $this->reply($connection);
        $this->workers->attach($connection, $data);
        $this->broadcastMasterToWorkerByWorker($connection);
    }

    protected function broadcastMasterToWorkerByWorker(ConnectionInterface $worker)
    {
        foreach ($this->masters as $master) {
            $this->broadcastToWorkers($master, [$worker]);
        }
    }

    protected function _close(ConnectionInterface $connection)
    {
        if ($this->masters->contains($connection)) {
            $this->masters->detach($connection);
            $this->info('master_close');
        }
        elseif ($this->workers->contains($connection)) {
            $this->workers->detach($connection);
            $this->info('worker_close');
        }
    }


    public function run()
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