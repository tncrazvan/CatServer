<?php
namespace com\github\tncrazvan\catpaw\http;

use com\github\tncrazvan\catpaw\tools\Status;
use com\github\tncrazvan\catpaw\tools\ServerFile;
use com\github\tncrazvan\catpaw\http\HttpResponse;
use com\github\tncrazvan\catpaw\tools\Strings;

class HttpDefaultEvents{
    public static \Closure $notFound;
    public static \Closure $file;
    public static function init():void{
        
        $requestsByIp = [];

        self::$notFound = function(HttpEvent $e){
            $filename = [$e->listener->so->webRoot,$e->listener->path];
            if(!ServerFile::exists(...$filename)){
                $php = [ServerFile::dirname(...$filename),"index.php"];
                if(!ServerFile::exists(...$php)){
                    $html = [ServerFile::dirname(...$filename),"index.php"];
                    if(!ServerFile::exists(...$html)){
                        return new HttpResponse([
                            "Status" => Status::NOT_FOUND
                        ]);
                    }else $filename = $html;
                }else $filename = $php;
            }else if(ServerFile::isDir(...$filename)){
                $php = [...$filename,"index.php"];
                if(!ServerFile::exists(...$php)){
                    $html = [...$filename,"index.html"];
                    if(!ServerFile::exists(...$html)){
                        return new HttpResponse([
                            "Status" => Status::NOT_FOUND
                        ]);
                    }else $filename = $html;
                }else $filename = $php;
            }
            if(Strings::endsWith($filename[2],'.php'))
                return ServerFile::include(join('/',$filename));
            return ServerFile::response($e,...$filename);
        };



        self::$file = function(HttpEvent $e) use(&$requestsByIp){
            
            switch($e->getRequestMethod()){
                case "GET":
                    if(Strings::endsWith($e->listener->path,'.php'))
                        return ServerFile::include(join('/',[$e->listener->so->webRoot,$e->listener->path]));
                    return ServerFile::response($e,$e->listener->so->webRoot,$e->listener->path);
                break;
                default:
                    return new HttpResponse([
                        "Status"=>Status::METHOD_NOT_ALLOWED
                    ]);
                break;
            }
        };

        


    }
}