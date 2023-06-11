<?php 

require __DIR__ . '/../vendor/autoload.php';

use Wpjscc\MasterWorker\Worker;
use Wpjscc\MasterWorker\Client;

require __DIR__.'/chat.php';

$worker = Worker::instance();

$worker->on('workerOpen', function () {
    echo 'workerOpen' . PHP_EOL;
});

$worker->on('clientOpen', function ($_id, $data) {
    echo 'clientOpen' . PHP_EOL;
});

$worker->on('clientMessage', function ($_id, $message) {
    echo 'clientMessage' . PHP_EOL;
    // Client::instance()->sendToClient($_id, json_encode([
    //     'event_type' => 'echo',
    //     'data' => [
    //         'client_id' => $_id,
    //         'msg' => '【worker系统消息】已接收到消息-'.$message ?? ''
    //     ]
    // ]));
    try {
        $data = json_decode($message, true);
        if (isset($data['event_type']) && !in_array($data['event_type'], ['open', 'message', 'close'])) {
            Chat::instance()->emit($data['event_type'], [$_id, $data['data'] ?? []]);
        }
    } catch (\Throwable $th) {
        //throw $th;
        Worker::instance()->info($th);
    }

});

$worker->on('clientClose', function ($_id, $data) {
    echo 'clientClose' . PHP_EOL;
});

$worker->run();


$chat = Chat::instance();


$chat->on('echo', function($_id, $data){
    Client::instance()->sendToClient($_id, json_encode([
        'event_type' => 'echo',
        'data' => [
            'client_id' => $_id,
            'msg' => '【worker系统消息】已接收到消息-worker-'.$data['value'] ?? ''
        ]
    ]));
});

$chat->on('getClientId', function($_id, $data){
    Client::instance()->sendToClient($_id, json_encode([
        'event_type' => 'echo',
        'data' => [
            'client_id' => $_id,
            'msg' => '【worker系统消息】您的Client ID 为'."【{$_id}】"
        ]
    ]));
});
$chat->on('getOnlineClientIds', function($_id, $data){
    Client::instance()->sendToClient($_id, [
        'event_type' => 'onOnlineClientIds',
        'data' => [
            'client_id' => $_id,
            'msg' => '【worker系统消息】',
            'data' => Client::instance()->getOnlineClientIds()
        ]
    ]);
});

$chat->on('broadcast', function($_id, $data){
    
    $type = $data['type'] ?? 'all';
    $excludeClient_Ids = [];

    if ($type == 'other') {
        $excludeClient_Ids[] = $_id;
    }
    Client::instance()->broadcast(json_encode([
        'event_type' => 'broadcast',
        'data' => [
            'client_id' => $_id,
            'msg' => '【worker广播消息】已接收到消息-'.$data['value'] ?? ''
        ]
    ]), $excludeClient_Ids);

    if ($type == 'other') {
        Client::instance()->sendToClient($_id, 
            json_encode([
                'event_type' => 'broadcast',
                'data' => [
                    'client_id' => $_id,
                    'msg' => '【worker广播消息】广播成功'
                ]
            ])
        );
    }
});

$chat->on('sendMessage', function($_id, $data){
    $data['group_id'] = $data['group_id'] ?? 1;
    ImClient::joinGroupByClientId($data['group_id'], $_id);
    $data['client_id'] = $_id;
    ImClient::sendMessageToGroupByClientId($data['group_id'], json_encode([
        'event_type' => 'sendMessage',
        'data' => $data
    ]));
});

$chat->on('beforeClose', function($_id){
    Imclient::sendMessageToGroupByOnlyClientId($_id, [
        'event_type' => 'sendMessage',
        'data' => [
            'msg' => '【'.$_id.'】'.'离开了聊天室',
            'id' => 0,
            'from' => [
                'id' => 0,
                'name' => 'worker系统消息',
                'avatar' => [
                    'url' => 'https://picsum.photos/300'
                ],
            ],
            'to' => [
                'id' => 0,
                'name' => 'worker系统消息',
                'avatar' => [
                    'url' => 'https://picsum.photos/300'
                ],
            ],
            'data' => [
                'content' => '【'.$_id.'】'.'离开了聊天室'
            ],
        ]
        
    ]);
});

$chat->on('sendMessageByClientId', function(WebSocketConnection $from, $data){
    $client_id = $data['client_id'] ?? '';
    $msg = $data['value'] ?? '';
    if (!$client_id){
        ImClient::sendMessageByClientId($from->client_id, json_encode([
            'event_type' => 'sendMessageByClientId',
            'data' => [
                'client_id' => $from->client_id,
                'msg' => 'client_id is empty'
            ]
        ]));
        return;
    }

    if (ImClient::isExistByClientId($client_id) === false) {
        ImClient::sendMessageByClientId($from->client_id, json_encode([
            'event_type' => 'sendMessageByClientId',
            'data' => [
                'client_id' => $from->client_id,
                'msg' => 'client_id is not exist'
            ]
        ]));
        return;
    }

    if (!$msg){
        ImClient::sendMessageByClientId($from->client_id, json_encode([
            'event_type' => 'sendMessageByClientId',
            'data' => [
                'client_id' => $from->client_id,
                'msg' => 'msg is empty'
            ]
        ]));
        return;
    }
    ImClient::sendMessageByClientId($from->client_id, json_encode([
        'event_type' => 'sendMessageByClientId',
        'data' => [
            'client_id' => $from->client_id,
            'msg' => '【worker系统消息】'.'【'.$from->client_id.'】'.'信息发送成功'
        ]
    ]));
    ImClient::sendMessageByClientId($client_id, json_encode([
        'event_type' => 'sendMessageByClientId',
        'data' => [
            'client_id' => $from->client_id,
            'msg' => '【from】【'.$client_id.'】'.$msg
        ]
    ]));
});

$chat->on('joinGroupByClientId', function(WebSocketConnection $from, $data){
    $group_id = $data['group_id'] ?? '';
    $client_id = $data['client_id'] ?? '';
    if (!$group_id){
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'joinGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => '房间号不能是空'
            ]
        ]));
        return;
    }
    $state = ImClient::joinGroupByClientId($group_id, $client_id);

    $msg = "加入房间-$group_id 成功";
    if (!$state) {
        $msg = "加入房间-$group_id 失败";
    } elseif ($state === 1) {
        $msg = "已经加入房间-$group_id";
    } 

    $groupCount = ImClient::getGroupClientCount($group_id);

    // 加入房间后发送一条消息（不需要绑定）
    ImClient::sendMessageToGroupByClientId($group_id, json_encode([
        'event_type' => 'joinGroupByClientId',
        'data' => [
            'client_id' => $client_id,
            'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'.$msg
        ]
    ]));
    // 你已经加入的房间为
    $groupIds = ImClient::getGroupIdsByClientId($client_id);

    ImClient::sendMessageByClientId($client_id, json_encode([
        'event_type' => 'joinGroupByClientId',
        'data' => [
            'client_id' => $client_id,
            'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】加入的所有房间ID为'.implode(',', $groupIds)
        ]
    ]));

});

$chat->on('leaveGroupByClientId', function(WebSocketConnection $from, $data){
    $group_id = $data['group_id'] ?? '';
    $client_id = $data['client_id'] ?? '';
    if (!$group_id){
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'leaveGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => '房间号不能是空'
            ]
        ]));
        return;
    }
    $state = ImClient::leaveGroupByClientId($group_id, $client_id);

    $msg = "离开房间-$group_id 成功";
    if (!$state) {
        $msg = "离开房间-$group_id 失败";
    } elseif ($state === 1) {
        $msg = "已经离开房间-$group_id";
    } 
    $groupCount = ImClient::getGroupClientCount($group_id);

    // 离开房间后发送一条消息（不需要绑定）
    ImClient::sendMessageToGroupByClientId($group_id, json_encode([
        'event_type' => 'leaveGroupByClientId',
        'data' => [
            'client_id' => $client_id,
            'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'.$msg
        ]
    ]));

    // 你已经加入的房间为
    $groupIds = ImClient::getGroupIdsByClientId($client_id);
    ImClient::sendMessageByClientId($client_id, json_encode([
        'event_type' => 'leaveGroupByClientId',
        'data' => [
            'client_id' => $client_id,
            'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'.$msg.'您加入的所有房间ID为'.implode(',', $groupIds)
        ]
    ]));
});

$chat->on('sendMessageToGroupByClientId', function(WebSocketConnection $from, $data){
    $group_id = $data['group_id'] ?? '';
    $client_id = $data['client_id'] ?? '';
    $msg = $data['value'] ?? '';
    if (!$group_id){
        $from->send(json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => '房间号 不能为空'
            ]
        ]));
        return;
    }
    $groupCount = ImClient::getGroupClientCount($group_id);

    if (!ImClient::isInGroupByClientId($group_id, $client_id)) {
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => "【 房间-$group_id -人数-$groupCount 】".'你不在 '.$group_id.' 中'
            ]
        ]));
        return;
    }

    $type = $data['type'] ?? 'all';
    $excludeClientIds = [];
    if ($type === 'other') {
        $excludeClientIds = [
            $client_id
        ];
    }
    $groupCount = ImClient::getGroupClientCount($group_id);


    $state = ImClient::sendMessageToGroupByClientId($group_id, json_encode([
        'event_type' => 'sendMessageToGroupByClientId',
        'data' => [
            'client_id' => $client_id,
            'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'.$msg
        ]
    ]), $excludeClientIds);

    if ($type === 'other') {
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => "【 房间-$group_id -人数-$groupCount 】给其他人发送成功"
            ]
        ]));
    }

    $msg = "send $group_id success";
    if (!$state) {
        $msg = "发送 $group_id 失败";
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'.$msg
            ]
        ]));
    } elseif ($state === 1) {
        $msg = "没有人在 $group_id";
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' =>"【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'. $msg
            ]
        ]));
    }

   
});

