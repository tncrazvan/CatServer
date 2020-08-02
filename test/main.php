<?php

use app\HomePage;
use app\User;
use app\websockettest\WebSocketTest;
use com\github\tncrazvan\catpaw\websocket\WebSocketEventOnClose;
use com\github\tncrazvan\catpaw\websocket\WebSocketEventOnMessage;
use com\github\tncrazvan\catpaw\websocket\WebSocketEventOnOpen;

return [
    "port" => 80,
    "webRoot" => "./www/public",
    "bindAddress" => "127.0.0.1",
    "events" => [
        "http"=>[
            "/home/{username}" 
                => fn(string $method, ?User &$user = null) 
                    => new HomePage($method,$user)
        ],
        "websocket"=>[
            "/test" 
                => fn(WebSocketEventOnOpen &$onOpen, WebSocketEventOnMessage &$onMessage, WebSocketEventOnClose &$onClose) 
                    => new WebSocketTest($onOpen,$onMessage,$onClose)
        ]
    ],
    "sessionName" => "_SESSION",
    "ramSession" => [
        "allow" => false,
        "size" => "1024M"
    ],
    "compress" => ["deflate"],
    "headers" => [
        "Cache-Control" => "no-store"
    ]
];