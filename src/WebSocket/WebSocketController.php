<?php

namespace com\github\tncrazvan\CatPaw\WebSocket;

use com\github\tncrazvan\CatPaw\WebSocket\WebSocketEvent;

abstract class WebSocketController{
    const GROUP_MANAGER = null;
    public abstract function onOpen(WebSocketEvent &$e, array &$args):void;
    public abstract function onMessage(WebSocketEvent &$e,string &$data, array &$args):void;
    public abstract function onClose(WebSocketEvent &$e, array &$args):void;
}
