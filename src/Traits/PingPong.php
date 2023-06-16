<?php

namespace Wpjscc\MasterWorker\Traits;

use React\Socket\ConnectionInterface;

trait PingPong
{
    public $pingRate = 5;

    public function ping(ConnectionInterface $connection)
    {
        $timer = null;
        $connection->on('close', function() use (&$timer) {
            if ($timer) {
                \React\EventLoop\Loop::get()->cancelTimer($timer);
            }
        });
        $that = $this;
        $timer = \React\EventLoop\Loop::get()->addPeriodicTimer($this->pingRate, function() use ($connection, $that) {
            $that->write($connection, [
                'cmd' => 'ping',
                'data' => [
                    'class' => get_class($that),
                ]
            ]);
        });
    }

    public function pong(ConnectionInterface $connection)
    {
        $this->write($connection, [
            'cmd' => 'pong',
            'data' => [
                'class' => get_class($this),
            ]
        ]);
    }
    
    protected function _ping(ConnectionInterface $connection, $data)
    {
        $this->info('ping', $data);
        $this->pong($connection);
    }

    protected function _pong(ConnectionInterface $connection, $data)
    {
        $this->info('pong', $data);
    }
}
