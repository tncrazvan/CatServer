<?php
namespace com\github\tncrazvan\CatPaw\Http;

use com\github\tncrazvan\CatPaw\Tools\G;

abstract class HttpController extends G{
    public abstract function &main(HttpEvent &$e,array &$path,string &$content);
    public abstract function onClose():void;
}