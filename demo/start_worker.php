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
    Client::instance('worker')->sendToClient($_id, json_encode([
        'event_type' => 'bind',
        'data' => [
            // 'client_id' => $conn->client_id.'-'. $conn->_id,
            'client_id' => $_id,
            'msg' => 'client_id:'. $_id
        ]
    ]));
});

$worker->on('clientMessage', function ($_id, $message) {
    echo 'clientMessage2222' . PHP_EOL;
    // Client::instance('worker')->sendToClient($_id, json_encode([
    //     'event_type' => 'echo',
    //     'data' => [
    //         'client_id' => $_id,
    //         'msg' => '【worker系统消息】已接收到消息-'.$message ?? ''
    //     ]
    // ]));
    try {
        $data = json_decode($message, true);
        if (isset($data['event_type']) && !in_array($data['event_type'], ['open', 'message', 'close'])) {
            var_dump($data);
            Chat::instance()->emit($data['event_type'], [$_id, $data['data'] ?? []]);
        }
    } catch (\Throwable $th) {
        //throw $th;
        Worker::instance()->info($th);
    }

});

$worker->on('clientClose', function ($_id, $data) {
    echo 'clientClose--'.$_id . PHP_EOL;
    $getGroupIdsBy_Id = $data['getGroupIdsBy_Id'] ?? [];

    foreach ($getGroupIdsBy_Id as $group_id) {
        Client::instance('worker')->getJsonPromise([
            'group__id_count' => Client::instance('worker')->getGroup_IdCount($group_id)
        ])->then(function($data) use ($group_id, $_id) {
            $group__id_count = $data['group__id_count'];
            Client::instance('worker')->sendToGroup($group_id, [
                'event_type' => 'leaveGroupByClientId',
                'data' => [
                    'client_id' => $_id,
                    'msg' => "【 worker 房间-$group_id -人数-$group__id_count 】".'【'.$_id.'】'.'离开了房间'
                ]
            ]);
        });
       
    }

});



$chat = Chat::instance();


$chat->on('get_IdData', function ($_id, $data) {
    Client::instance('worker')->sendToClient($_id, [
        'event_type' => 'echo',
        'data' => [
            'client_id' => $_id,
            'data' => Client::instance('worker')->get_IdData($_id),
            'msg' => '【worker系统消息】已接收到消息-worker'
        ]
    ]);
});
$chat->on('echo', function($_id, $data){

    Client::instance('worker')->sendToClient($_id, json_encode([
        'event_type' => 'echo',
        'data' => [
            'client_id' => $_id,
            'msg' => '【worker系统消息】已接收到消息-worker-'.$data['value'] ?? ''
        ]
    ]));
});
$chat->on('bindId', function($_id, $data){
    $id = $data['value'] ?? '';
    $client_id = $data['client_id'] ?? '';

    if (is_array($id)){
        return false;
    }

    if (!$id || !$client_id) {
        Client::instance('worker')->sendToClient($_id, json_encode([
            'event_type' => 'echo',
            'data' => [
                'client_id' => $_id,
                'msg' => '【worker系统消息】id 不能为空'
            ]
        ]));
        return;
    }
    Client::instance('worker')->bindId($id, $client_id)->then(function($res) use ($client_id) {
        $msg = '绑定成功';
        if ($res == 1) {
            $msg = '已绑定过';
        }
        elseif ($res==2) {
            $msg = '绑定失败';
        }
        elseif ($res==3) {
            $msg = '系统错误';
        }
        Client::instance('worker')->sendToClient($client_id, json_encode([
            'event_type' => 'echo',
            'data' => [
                'client_id' => $client_id,
                'msg' => '【worker系统消息】'. $msg
            ]
        ]));
    });
});
$chat->on('unBindId', function($_id, $data){
    $id = $data['value'] ?? '';

    if (is_array($id)){
        return false;
    }

    if (!$id) {
        Client::instance('worker')->sendToClient($_id, json_encode([
            'event_type' => 'echo',
            'data' => [
                'client_id' => $_id,
                'msg' => '【worker系统消息】id 不能为空'
            ]
        ]));
        return ;
    }
    Client::instance('worker')->unBindId($id)->then(function($res) use ($_id)  {
        $msg = '解绑成功';
        if ($res == 1) {
            $msg = '没绑定过该ID';
        }
        Client::instance('worker')->sendToClient($_id, json_encode([
            'event_type' => 'echo',
            'data' => [
                'client_id' => $_id,
                'msg' => '【worker系统消息】'. $msg
            ]
        ]));
    });
});
$chat->on('unBind_Id', function($_id, $data){

    if (!$_id) {
        Client::instance('worker')->sendToClient($_id, json_encode([
            'event_type' => 'echo',
            'data' => [
                'client_id' => $_id,
                'msg' => '【worker系统消息】_id 不能为空'
            ]
        ]));
        return ;
    }
    Client::instance('worker')->unBind_Id($_id)->then(function($res) use ($_id)  {
        $msg = '解绑_id成功';
        if ($res == 1) {
            $msg = '没绑定过该_id';
        }
        Client::instance('worker')->sendToClient($_id, json_encode([
            'event_type' => 'echo',
            'data' => [
                'client_id' => $_id,
                'msg' => '【worker系统消息】'. $msg
            ]
        ]));
    });
});

$chat->on('sendMessageById', function($_id, $data){
    $id = $data['value'] ?? '';
    if (is_array($id)){
        return false;
    }
    if (!$id) {
        Client::instance('worker')->sendToClient($_id, json_encode([
            'event_type' => 'echo',
            'data' => [
                'client_id' => $_id,
                'msg' => '【worker系统消息】id 不能为空'
            ]
        ]));
    }

    Client::instance('worker')->sendToClient('', json_encode([
        'event_type' => 'echo',
        'data' => [
            'client_id' => $_id,
            'msg' => '【worker系统消息】hello world'
        ]
    ]), $id);




});

$chat->on('getClientId', function($_id, $data){
    Client::instance('worker')->sendToClient($_id, json_encode([
        'event_type' => 'echo',
        'data' => [
            'client_id' => $_id,
            'msg' => '【worker系统消息】您的Client ID 为'."【{$_id}】"
        ]
    ]));
});
$chat->on('getOnlineClientIds', function($_id, $data){
    Client::instance('worker')->sendToClient($_id, [
        'event_type' => 'onOnlineClientIds',
        'data' => [
            'client_id' => $_id,
            'msg' => '【worker系统消息】',
            'data' => Client::instance('worker')->getOnline_Ids()
        ]
    ]);
});

$chat->on('broadcast', function($_id, $data){
    
    $type = $data['type'] ?? 'all';
    $excludeClient_Ids = [];

    if ($type == 'other') {
        $excludeClient_Ids[] = $_id;
    }
    Client::instance('worker')->broadcast([
        'event_type' => 'broadcast',
        'data' => [
            'client_id' => $_id,
            'msg' => '【worker广播消息】已接收到消息-'.$data['value'] ?? '',
            'data' => Client::instance('worker')->getOnline_Ids()
        ]
    ], $excludeClient_Ids);

    if ($type == 'other') {
        Client::instance('worker')->sendToClient($_id, 
            [
                'event_type' => 'broadcast',
                'data' => [
                    'client_id' => $_id,
                    'msg' => '【worker广播消息】广播成功'
                ]
            ]
        );
    }
});


// $chat->on('beforeClose', function($_id){
//     Imclient::sendMessageToGroupByOnlyClientId($_id, [
//         'event_type' => 'sendMessage',
//         'data' => [
//             'msg' => '【'.$_id.'】'.'离开了聊天室',
//             'id' => 0,
//             'from' => [
//                 'id' => 0,
//                 'name' => 'worker系统消息',
//                 'avatar' => [
//                     'url' => 'https://picsum.photos/300'
//                 ],
//             ],
//             'to' => [
//                 'id' => 0,
//                 'name' => 'worker系统消息',
//                 'avatar' => [
//                     'url' => 'https://picsum.photos/300'
//                 ],
//             ],
//             'data' => [
//                 'content' => '【'.$_id.'】'.'离开了聊天室'
//             ],
//         ]
        
//     ]);
// });

$chat->on('sendMessageByClientId', function($_id, $data){
    $client_id = $data['client_id'] ?? '';
    $msg = $data['value'] ?? '';
    if (!$client_id){
        Client::instance('worker')->sendToClient($_id, json_encode([
            'event_type' => 'sendMessageByClientId',
            'data' => [
                'client_id' => $_id,
                'msg' => 'client_id is empty'
            ]
        ]));
        return;
    }

    Client::instance('worker')->isOnline_Id($client_id)->then(function($res) use ($_id, $msg, $client_id) {
        if ($res) {
            if (!$msg){
                Client::instance('worker')->sendToClient($_id, json_encode([
                    'event_type' => 'sendMessageByClientId',
                    'data' => [
                        'client_id' => $_id,
                        'msg' => '【worker系统消息】'.'【msg is empty】'
                    ]
                ]));
                return;
            }
            Client::instance('worker')->sendToClient($_id, json_encode([
                'event_type' => 'sendMessageByClientId',
                'data' => [
                    'client_id' => $_id,
                    'msg' => '【worker系统消息】'.'【'.$_id.'】'.'信息发送成功'
                ]
            ]));
            Client::instance('worker')->sendToClient($client_id, json_encode([
                'event_type' => 'sendMessageByClientId',
                'data' => [
                    'client_id' => $_id,
                    'msg' => '【worker from】【'.$_id.'】'.$msg
                ]
            ]));
        } else {
            Client::instance('worker')->sendToClient($_id, json_encode([
                'event_type' => 'sendMessageByClientId',
                'data' => [
                    'client_id' => $client_id,
                    'msg' => '【worker系统消息】'.'【'.$client_id.'不存在】'
                ]
            ]));
        }
       
    });
});

$chat->on('joinGroupByClientId', function($_id, $data){
    $group_id = $data['group_id'] ?? '';
    $client_id = $data['client_id'] ?? '';
    if (!$group_id){
        Client::instance('worker')->sendToClient($client_id, json_encode([
            'event_type' => 'joinGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' =>  '【worker系统消息】'.'房间号不能是空'
            ]
        ]));
        return;
    }

    Client::instance('worker')->getJsonPromise([
        'state' => Client::instance('worker')->joinGroupBy_Id($group_id, $client_id),
    ])->then(function($data) use ($group_id, $client_id) {
        $state = $data['state'];
        $msg = "加入房间-$group_id 成功";
        if ($state==2) {
            $msg = "加入房间-$group_id 失败";
        } elseif ($state === 1) {
            $msg = "已经加入房间-$group_id";
        }

        Client::instance('worker')->getJsonPromise([
            'group__id_count' => Client::instance('worker')->getGroup_IdCount($group_id),
            'join_group_ids' => Client::instance('worker')->getGroupIdsBy_Id($client_id),
        ])->then(function($data) use ($client_id, $group_id, $msg) {
            $group__id_count = $data['group__id_count'];
            $join_group_ids = $data['join_group_ids'];

            // 加入房间后发送一条消息（不需要绑定）
            Client::instance('worker')->sendToGroup($group_id, json_encode([
                'event_type' => 'joinGroupByClientId',
                'data' => [
                    'client_id' => $client_id,
                    'msg' => "【 worker 房间-$group_id -人数-$group__id_count 】".'【'.$client_id.'】'.$msg
                ]
            ]));

            // 你已经加入的房间为
            Client::instance('worker')->sendToClient($client_id, json_encode([
                'event_type' => 'joinGroupByClientId',
                'data' => [
                    'client_id' => $client_id,
                    'msg' => "【 worker 房间-$group_id -人数-$group__id_count 】".'【'.$client_id.'】加入的所有房间ID为'.implode(',', $join_group_ids)
                ]
            ]));
        });

       
    });
});

$chat->on('leaveGroupByClientId', function($_id, $data){
    $group_id = $data['group_id'] ?? '';
    $client_id = $data['client_id'] ?? '';
    if (!$group_id){
        Client::instance('worker')->sendToClient($client_id, json_encode([
            'event_type' => 'leaveGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => 'worker 房间号不能是空'
            ]
        ]));
        return;
    }
    Client::instance('worker')->getJsonPromise([
        'state' => Client::instance('worker')->leaveGroupBy_Id($group_id, $client_id),
    ])->then(function($data) use ($group_id, $client_id) {
        $state = $data['state'];

        $msg = "离开房间-$group_id 成功";
        if ($state==2) {
            $msg = "离开房间-$group_id 失败";
        } elseif ($state === 1) {
            $msg = "已经离开房间-$group_id";
        }
        Client::instance('worker')->getJsonPromise([
            'group__id_count' => Client::instance('worker')->getGroup_IdCount($group_id),
            'join_group_ids' => Client::instance('worker')->getGroupIdsBy_Id($client_id),
        ])->then(function($data) use ($client_id, $group_id, $msg) {
            $group__id_count = $data['group__id_count'];
            $join_group_ids = $data['join_group_ids'];
            // 离开房间后发送一条消息（不需要绑定）
            Client::instance('worker')->sendToGroup($group_id, json_encode([
                'event_type' => 'leaveGroupByClientId',
                'data' => [
                    'client_id' => $client_id,
                    'msg' => "【 worker 房间-$group_id -人数-$group__id_count 】".'【'.$client_id.'】'.$msg
                ]
            ]));
            Client::instance('worker')->sendToClient($client_id, json_encode([
                'event_type' => 'leaveGroupByClientId',
                'data' => [
                    'client_id' => $client_id,
                    'msg' => "【 worker 房间-$group_id -人数-$group__id_count 】".'【'.$client_id.'】'.$msg.'您加入的所有房间ID为'.implode(',', $join_group_ids)
                ]
            ]));
        });

    });
    
});

$chat->on('sendMessageToGroupByClientId', function($_id, $data){
    $group_id = $data['group_id'] ?? '';
    $client_id = $data['client_id'] ?? '';
    $msg = $data['value'] ?? '';
    $type = $data['type'] ?? 'all';

    if (!$group_id){
        Client::instance('worker')->sendToClient($_id, json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $_id,
                'msg' => 'worker 房间号 不能为空'
            ]
        ]));
        return;
    }

    Client::instance('worker')->getJsonPromise([
        'group__id_count' => Client::instance('worker')->getGroup_IdCount($group_id),
        'is_in_group_by__id' => Client::instance('worker')->isInGroupBy_Id($group_id, $client_id)
    ])->then(function($data) use ($client_id, $group_id, $msg, $type) {
        $group__id_count = $data['group__id_count'];
        $is_in_group_by__id = $data['is_in_group_by__id'];

        if (!$is_in_group_by__id) {
            Client::instance('worker')->sendToClient($client_id, json_encode([
                'event_type' => 'sendMessageToGroupByClientId',
                'data' => [
                    'client_id' => $client_id,
                    'msg' => "【 worker 房间-$group_id -人数-$group__id_count 】".'你不在 '.$group_id.' 中'
                ]
            ]));
            return ;
        }

        $exclude_Ids = [];
        if ($type === 'other') {
            $exclude_Ids = [
                $client_id
            ];
        }

        Client::instance('worker')->sendToGroup($group_id, json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => "【 worker 房间-$group_id -人数-$group__id_count 】".'【'.$client_id.'】'.$msg
            ]
        ]), [], $exclude_Ids);


        if ($type === 'other') {
            Client::instance('worker')->sendToClient($client_id, json_encode([
                'event_type' => 'sendMessageToGroupByClientId',
                'data' => [
                    'client_id' => $client_id,
                    'msg' => "【 worker 房间-$group_id -人数-$group__id_count 】给其他人发送成功"
                ]
            ]));
        }


        // $msg = "send $group_id success";
        // if (!$state) {
        //     $msg = "发送 $group_id 失败";
        //     ImClient::sendMessageByClientId($client_id, json_encode([
        //         'event_type' => 'sendMessageToGroupByClientId',
        //         'data' => [
        //             'client_id' => $client_id,
        //             'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'.$msg
        //         ]
        //     ]));
        // } elseif ($state === 1) {
        //     $msg = "没有人在 $group_id";
        //     ImClient::sendMessageByClientId($client_id, json_encode([
        //         'event_type' => 'sendMessageToGroupByClientId',
        //         'data' => [
        //             'client_id' => $client_id,
        //             'msg' =>"【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'. $msg
        //         ]
        //     ]));
        // }


    });




   
});

$worker->run();
