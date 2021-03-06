<?php
namespace net\razshare\catpaw;

use net\razshare\catpaw\attributes\Body;
use net\razshare\catpaw\attributes\Consumes;
use net\razshare\catpaw\attributes\Filter;
use net\razshare\catpaw\attributes\http\Query;
use net\razshare\catpaw\attributes\http\RequestHeaders;
use net\razshare\catpaw\attributes\http\ResponseHeaders;
use net\razshare\catpaw\attributes\Inject;
use Exception;
use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use net\razshare\catpaw\tools\Status;
use net\razshare\catpaw\attributes\Produces;
use net\razshare\catpaw\tools\Caster;
use net\razshare\catpaw\tools\helpers\Factory;
use net\razshare\catpaw\attributes\metadata\Meta;
use net\razshare\catpaw\attributes\Request;
use net\razshare\catpaw\attributes\sessions\Session;
use net\razshare\catpaw\services\FileReaderService;
use net\razshare\catpaw\sessions\SessionManager;
use net\razshare\catpaw\tools\helpers\parsing\BodyParser;
use net\razshare\catpaw\tools\helpers\Yielder;
use net\razshare\catpaw\tools\response\ByteRangeResponse;
use net\razshare\catpaw\tools\Strings;
use net\razshare\catpaw\tools\XMLSerializer;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Stream\ThroughStream;

use function React\Promise\Stream\buffer;

class HttpInvoker{

    public function __construct(
        private LoopInterface $loop,
        private SessionManager $sm,
        private ?Response $bad_request_no_content_type = null,
        private ?Response $bad_request_cant_consume = null,
    ){
        if(!$this->bad_request_no_content_type)
            $this->bad_request_no_content_type = new Response(Status::BAD_REQUEST,[],'');
        if(!$this->bad_request_cant_consume)
            $this->bad_request_cant_consume = new Response(Status::BAD_REQUEST,[],'');
    }

    public function invoke(
        ServerRequestInterface $request,
        string $http_method,
        string $http_path,
        array $http_params
   ):mixed{
        $ctype = $request->getHeaderLine("Content-Type");
        if(!$ctype || '' === $ctype){
            //throw new Exception("Bad request on \"$http_method $http_path\", no Content-Type specified.");
            $ctype = '*/*';
        }

        $__PATH_PARAMS__ = Meta::$PATH_PARAMS[$http_method][$http_path]??null;

        $__FILTER__ = Meta::$FILTERS[$http_method][$http_path]??null;
        $_recovered_body = null;
        if($__FILTER__){
            foreach($__FILTER__ as $classname => &$filter_item_function){
                $__ARGS__ = Meta::$FILTERS_ARGS[$http_method][$http_path][$classname]??null;
                //$__ARGS_NAMES__ = Meta::$FUNCTIONS_ARGS_NAMES[$http_method][$http_path]??null;
                $__ARGS_ATTRIBUTES__ = Meta::$FILTERS_ARGS_ATTRIBUTES[$http_method][$http_path][$classname]??null;

                $__CONSUMES__ = Meta::$FILTERS_ATTRIBUTES[$http_method][$http_path][$classname][Consumes::class]??null;
                $__PRODUCES__ = Meta::$FILTERS_ATTRIBUTES[$http_method][$http_path][$classname][Produces::class]??null;
                $result = $this->next(
                    $http_method,
                    $http_path,
                    $http_params,
                    $ctype,
                    $_recovered_body,
                    $request,
                    $__CONSUMES__,
                    $__PRODUCES__,
                    $__ARGS__,
                    $__PATH_PARAMS__,
                    $__ARGS_ATTRIBUTES__,
                    $filter_item_function,
                    null,
                    null
                );
                if($result !== null)
                    return $result;
            }
        }
        
        $__FUNCTION__ = Meta::$FUNCTIONS[$http_method][$http_path]??null;
        $__METHOD__ = Meta::$METHODS[$http_method][$http_path]??null;

        if($__FUNCTION__){
            $__ARGS__ = Meta::$FUNCTIONS_ARGS[$http_method][$http_path]??null;
            //$__ARGS_NAMES__ = Meta::$FUNCTIONS_ARGS_NAMES[$http_method][$http_path]??null;
            $__ARGS_ATTRIBUTES__ = Meta::$FUNCTIONS_ARGS_ATTRIBUTES[$http_method][$http_path]??null;

            $__CONSUMES__ = Meta::$FUNCTIONS_ATTRIBUTES[$http_method][$http_path][Consumes::class]??null;
            $__PRODUCES__ = Meta::$FUNCTIONS_ATTRIBUTES[$http_method][$http_path][Produces::class]??null;
        }else{
            $__ARGS__ = Meta::$METHODS_ARGS[$http_method][$http_path]??null;
            //$__ARGS_NAMES__ = Meta::$METHODS_ARGS_NAMES[$http_method][$http_path]??null;
            $__ARGS_ATTRIBUTES__ = Meta::$METHODS_ARGS_ATTRIBUTES[$http_method][$http_path]??null;
            
            $__CONSUMES__ = Meta::$METHODS_ATTRIBUTES[$http_method][$http_path][Consumes::class]??null;
            $__PRODUCES__ = Meta::$METHODS_ATTRIBUTES[$http_method][$http_path][Produces::class]??null;
            
            if(!$__PRODUCES__ && $__CLASS_PRODUCES__ = Meta::$CLASS_ATTRIBUTES[$http_method][$http_path][Produces::class]??null){
                $__PRODUCES__ = $__CLASS_PRODUCES__;
            }
            if(!$__CONSUMES__ && $__CLASS_CONSUMES__ = Meta::$CLASS_ATTRIBUTES[$http_method][$http_path][Consumes::class]??null){
                $__CONSUMES__ = $__CLASS_CONSUMES__;
            }
        }
        

        return $this->next(
            $http_method,
            $http_path,
            $http_params,
            $ctype,
            $_recovered_body,
            $request,
            $__CONSUMES__,
            $__PRODUCES__,
            $__ARGS__,
            $__PATH_PARAMS__,
            $__ARGS_ATTRIBUTES__,
            null,
            $__FUNCTION__,
            $__METHOD__
        );
    }

    private function next(
        string &$http_method,
        string &$http_path,
        array &$http_params,
        string &$ctype,
        mixed &$_recovered_body,
        ServerRequestInterface $request,
        ?Consumes $__CONSUMES__,
        ?Produces $__PRODUCES__,
        ?array $__ARGS__,
        ?array $__PATH_PARAMS__,
        ?array $__ARGS_ATTRIBUTES__,
        ?\ReflectionFunction $__FILTER__,
        ?\ReflectionFunction $__FUNCTION__,
        ?\ReflectionMethod $__METHOD__,
    ):mixed{
        if($__CONSUMES__){
            $cconsumed = 0;
            $consumed = static::filterConsumedContentType($__CONSUMES__,$ctype,$cconsumed);

            $can_consumes = false;
            foreach($consumed as &$consumes_item){
                if($ctype === $consumes_item){
                    $can_consumes = true;
                    break;
                }
            }
            if(!$can_consumes){
                $consumes_glued = \implode(',',$consumed);
                throw new Exception("Bad request on \"$http_method $http_path\", can only consume Content-Type \"$consumes_glued\"; provided \"$ctype\".");
            }
        }

        $cookies = $request->getCookieParams();
        $sessionId = $cookies['sessionId']??null;
        $usingSession = false;
        $status = new Status();
        $http_headers = [];
        $params = array();
        if($__ARGS__)
            foreach($__ARGS__ as &$__ARG__){
                if($__ARG__ instanceof \ReflectionParameter){
                    $this->inject(
                        $request,
                        $_recovered_body,
                        $__CONSUMES__,
                        $ctype,
                        $__PATH_PARAMS__,
                        $__ARG__,
                        $__ARGS_ATTRIBUTES__,
                        $status,
                        $http_headers,
                        $http_params,
                        $params,
                        $sessionId,
                        $usingSession
                    );
                }
            }


        if($__FILTER__){
            $body = $__FILTER__->invokeArgs($params);
            if($body == null) 
                return null;
        }else if($__FUNCTION__){
            $body = $__FUNCTION__->invokeArgs($params);
        }else{
            $__CLASS__ = Meta::$KLASS[$http_method][$http_path]??null;

            $__METHOD__->setAccessible(true);
            $instance = Factory::make($__CLASS__->getName());
            //$fname = $reflection_http_method->getName();
            $body =  $__METHOD__->invokeArgs($instance,$params);
            $__METHOD__->setAccessible(false);
        }

        if($usingSession && $sessionId)
            $this->sm->saveSession($this->sm->getSession($sessionId));

        if($body instanceof \Generator)
            return Yielder::toPromise($this->loop,$body)->then(function($result) use(
                &$http_method,
                &$http_path,
                &$__PRODUCES__,
                &$http_headers,
                &$status,
                &$request
            ){
                return $this->generateResponse(
                    $result,
                    $http_method,
                    $http_path,
                    $__PRODUCES__,
                    $http_headers,
                    $status,
                    $request
                );
            });

        return $this->generateResponse(
            $body,
            $http_method,
            $http_path,
            $__PRODUCES__,
            $http_headers,
            $status,
            $request
        );
    }


    private function resolveRange(
        string $source,
        array $reqheaders,
        array &$resheaders,
        array $innerResHeaders,
        Status $status,
        LoopInterface $loop,
        FileReaderService $reader
    ){
        if(is_file($source)){
            $contentLength = filesize($source);
            if($contentLength === 0){
                $status->setCode(Status::OK);
                return '';   
            }
        }


        $isRange = isset($reqheaders["Range"][0]);
        if($isRange){
            $rangeString = str_replace('bytes=','',$reqheaders["Range"][0]);
            $ranges = \preg_split('/,\s*/',$rangeString);
            $cranges = count($ranges);
            if($cranges <= 0){
                $status->setCode(Status::REQUESTED_RANGE_NOT_SATISFIABLE);
                return '';
            }

            if($cranges === 1){
                $status->setCode(Status::PARTIAL_CONTENT);
                $range = $ranges[0];
                [$start,$end] = explode('-',$range);
                $start = (int) $start;
                $end = (int) ($end !== ''?$end:$contentLength-1);
                $length = $end-$start;
                
                if($length > $contentLength || $start < 0 || $end < 0 || $end < $start){
                    $status->setCode(Status::REQUESTED_RANGE_NOT_SATISFIABLE);
                    return;
                }

                $resheaders["Content-Range"] = "bytes $start-$end/$contentLength";
                $handle = \fopen($source,'r+');
                \fseek($handle,$start);
                return buffer($reader->stream($handle,$length+1));
            }



            $status->setCode(Status::PARTIAL_CONTENT);
            $boundary = Strings::uuid();
            $resheaders["Content-Type"] = "multipart/byteranges; boundary=$boundary";
            $maxIndex = \count($ranges)-1;

            $handle = \fopen($source,'r+');
            $tstream = new ThroughStream();
            
            $this->ranges(
                index:0,
                maxIndex:$maxIndex,
                ranges: $ranges,
                contentLength:$contentLength,
                status:$status,
                handle:$handle,
                tstream:$tstream,
                boundary:$boundary,
                loop:$loop,
                headers:$innerResHeaders
            );
            return buffer($tstream);
        }

        $status->setCode(Status::OK);
        $resheaders["Accept-Ranges"] = "bytes";
        return $reader->read($source);
    }

    private function ranges(
        int $index,
        array $ranges,
        int $maxIndex,
        int $contentLength,
        Status $status,
        string $boundary,
        $handle,
        ThroughStream $tstream,
        LoopInterface $loop,
        array $headers = []
    ):void{
        $loop->futureTick(function() use(
            &$ranges,
            &$maxIndex,
            &$contentLength,
            &$status,
            &$boundary,
            &$handle,
            &$tstream,
            &$loop,
            &$headers,
            $index,
        ){
            $range = $ranges[$index];
            [$start,$end] = explode('-',$range);
            $start = (int) $start;
            $end = (int) ($end !== ''?$end:$contentLength-1);
            $length = $end-$start+1;
            if($length > $contentLength || $start < 0 || $end < 0 || $end < $start){
                $status->setCode(Status::REQUESTED_RANGE_NOT_SATISFIABLE);
                return;
            }
            
            \fseek($handle,$start);
            $chunk = \fread($handle,$length);
            $message = "--$boundary\r\n";
            //$message .= "Content-Type: audio/mpeg\r\n";
            $message .= implode("\r\n",$headers)."\r\n";
            $message .= "Content-Range: bytes $start-$end/$contentLength\r\n\r\n";
            $message .= $chunk;
            $message .= "\r\n";

            $tstream->write($message);
            if($index >= $maxIndex){
                $tstream->end("--$boundary--");
                return;
            }
            $this->ranges(
                index:++$index,
                ranges: $ranges,
                maxIndex:$maxIndex,
                contentLength:$contentLength,
                status:$status,
                handle:$handle,
                tstream:$tstream,
                boundary:$boundary,
                loop:$loop
            );
        });
    }


    private function generateResponse(
        &$body,
        &$http_method,
        &$http_path,
        &$__PRODUCES__,
        &$http_headers,
        &$status,
        ServerRequestInterface &$request
    ):mixed{
        if($body instanceof Response)
            return $body;

        if($body instanceof ByteRangeResponse){
            $body = $this->resolveRange(
                source:$body->getSource(),
                reqheaders:$request->getHeaders(),
                resheaders:$http_headers,
                innerResHeaders:[
                    "Content-Type" => isset($http_headers["Content-Type"])?$http_headers["Content-Type"]:"text/plain"
                ],
                status:$status,
                loop:$this->loop,
                reader:Factory::make(FileReaderService::class)
            );
        }

        if($body instanceof PromiseInterface){
            return $body->then(
                fn(&$b)=>$this->resolve(
                    $b,
                    $http_method,
                    $http_path,
                    $__PRODUCES__,
                    $http_headers,
                    $status,
                    $request
                ),
                fn(&$b)=>$this->resolve(
                    $b,
                    $http_method,
                    $http_path,
                    $__PRODUCES__,
                    $http_headers,
                    $status,
                    $request
                )
            );
        }else{
            return $this->reply(
                $http_method,
                $http_path,
                $__PRODUCES__,
                $http_headers,
                $status,
                $request,
                $body
            );
        }
    }

    private function resolve(
        mixed &$b,
        string &$http_method,
        string &$http_path,
        Produces &$__PRODUCES__,
        array &$http_headers,
        Status &$status,
        ServerRequestInterface &$request
    ){
        if($b instanceof Response)
            return $b;
        return $this->reply(
            $http_method,
            $http_path,
            $__PRODUCES__,
            $http_headers,
            $status,
            $request,
            $b
        );
    }

    private function reply(
        string &$http_method,
        string &$http_path,
        ?Produces $__PRODUCES__,
        array &$http_headers,
        ?Status $status,
        ServerRequestInterface $request,
        &$body
    ):Response{
        if($__PRODUCES__ && $__PRODUCES__ instanceof Produces && !isset($http_headers['Content-Type'])){
            $http_headers['Content-Type'] = $__PRODUCES__->getContentType();
        }

        $http_status = $status->getCode();
        $accepts = \explode(",",($request->getHeaderLine("Accept")));
            
        $this->adaptResponse(
            $__PRODUCES__,
            $http_status,
            $http_headers,
            $accepts,
            $body,
            $http_method,
            $http_path
        );

        return new Response($http_status,$http_headers,$body?:'');
    }

    private static function &filterConsumedContentType(
        ?Consumes $__CONSUMES__,
        ?string $ctype,
        int &$len
    ):array{
        if( $__CONSUMES__ )
            $consumed = \preg_split( '/\s*,\s*/',$__CONSUMES__->getContentType() );
        else
            $consumed = [];
        
        $len = 0;
        $consumed = array_filter($consumed,function($type) use(&$len){
            if(empty($type))
                return false;
            $len++;
            return true;
        });
        return $consumed;
    }

    private static function &filterProducedContentType(
        ?Produces $__PRODUCES__,
        ?string $ctype,
        int &$len
    ):array{
        if( $__PRODUCES__ && (!$ctype || $ctype === '') )
            $produced = \preg_split( '/\s*,\s*/',$__PRODUCES__->getContentType() );
        else
            $produced = \preg_split( '/\s*,\s*/',$ctype??'text/plain' );
        
        $len = 0;
        $produced = array_filter($produced,function($type) use(&$len){
            if(empty($type))
                return false;
            $len++;
            return true;
        });
        return $produced;
    }

    private function adaptResponse(
        ?Produces $__PRODUCES__,
        int &$http_status,
        array &$http_headers,
        array &$accepts,
        mixed &$body,
        string $http_method,
        string $http_path,
    ):void{
        $cproduced = 0;

        $produced = static::filterProducedContentType($__PRODUCES__,$http_headers["Content-Type"]??null,$cproduced);

        if($cproduced === 0){
            $http_status = Status::NO_CONTENT;
            unset($http_headers['Content-Type']);
            if($http_method === 'GET')
                echo "The resource \"$http_method $http_path\" is not configured to produce any type of content.\n";
            return;
        }
        
        $any = null;

        foreach($accepts as &$accepts_item){
            if(!$any && str_starts_with($accepts_item,'*/*'))
                $any = $accepts_item;
            else if(\in_array($accepts_item,$produced)){
                $this->transform($body,$http_headers,$accepts_item,$produced);
                return;
            }
        }

        if($any){
            if($cproduced === 0)
                $produced[0] = 'text/plain';
                
            $this->transform($body,$http_headers,$produced[0],$produced);
            return;
        }

        $http_status = Status::BAD_REQUEST;
        $http_headers['Content-Type'] = 'text/plain';
        $body = 'This resource produces types ['.\implode(',',$produced).'], which don\'t match with any types accepted by the request ['.\implode(',',$accepts).'].';
    }


    private function transform(
        mixed &$body,
        array &$http_headers,
        string &$ctype,
        array &$fallback_ctypes
    ):void{
        switch($ctype){
            case 'application/json':
                $body = \json_encode($body);
                $http_headers['Content-Type'] = $ctype;
            return;
            case 'application/xml':
            case 'text/xml':
                if(\is_array($body)){
                    $body = XMLSerializer::generateValidXmlFromArray($body);
                }else{
                    $cast = Caster::cast($body,\stdClass::class);
                    $body = XMLSerializer::generateValidXmlFromObj($cast);
                }
                $http_headers['Content-Type'] = $ctype;
            return;
            case 'text/plain':
                if(\is_array($body) || \is_object($body))
                    $body = \json_encode($body);
                
                $http_headers['Content-Type'] = 'text/plain';
            return;
            case '*/*':
            case '':
                if(\is_array($body) || \is_object($body)){
                    $body = \json_encode($body);
                    
                    if(\in_array('application/json',$fallback_ctypes))
                        $http_headers['Content-Type'] = 'application/json';
                    else
                        $http_headers['Content-Type'] = 'text/plain';
                }else 
                    $http_headers['Content-Type'] = 'text/plain';
                
            return;
            default:
                if(\is_array($body) || \is_object($body))
                    $body = \json_encode($body);
                $http_headers['Content-Type'] = $ctype;
            return;
        }
    }

    public static function __could_not_inject(string $name, string $classname, string $extra = ''):string{        
        return 
        "Parameter \"$name\" could not be injected as \"$classname\".".( '' === $extra?'':"\n$extra");
    }

    private function inject(
        ServerRequestInterface $request,
        mixed &$_recovered_body,
        ?Consumes $__CONSUMES__,
        string &$ctype,
        ?array $__PATH_PARAMS__,
        \ReflectionParameter $__ARG__,
        ?array $__ARGS_ATTRIBUTES__,
        Status &$status,
        array &$http_headers,
        array &$http_params,
        array &$args,
        ?string &$sessionId,
        bool &$usingSession
   ):void{
        $optional = $__ARG__->isOptional();
        $name = $__ARG__->getName();
        $type = $__ARG__->getType();
        if(!$type){
            $args[] = null;
            return;
        }
        $classname = $type->getName();
        if($__PATH_PARAMS__ && isset($__PATH_PARAMS__[$name])){
            switch($classname){
                case 'bool':
                    $args[] = \filter_var($http_params[$name] || false, FILTER_VALIDATE_BOOLEAN);
                break;
                case 'string':
                    if(!isset($http_params[$name])){
                        if($optional)
                            $args[] = $__ARG__->getDefaultValue();
                        else
                            throw new Exception(static::__could_not_inject($name,$classname,"Name \"$name\" does not math with any path parameter."));
                    }else
                        $args[] = &$http_params[$name];
                break;
                case 'int':
                    if(!isset($http_params[$name])){
                        if($optional)
                            $args[] = $__ARG__->getDefaultValue();
                        else
                            throw new Exception(static::__could_not_inject($name,$classname,"Name \"$name\" does not math with any path parameter."));
                    }else{
                        if(\is_numeric($http_params[$name]))
                            $args[] = (int) $http_params[$name];
                        else{
                            throw new Exception('Parameter {'.$name.'} was expected to be numeric, but non numeric value has been provided instead:'.$http_params[$name]);
                        }
                    }
                break;
                case 'float':
                    if(!isset($http_params[$name])){
                        if($optional)
                            $args[] = $__ARG__->getDefaultValue();
                        else
                            throw new Exception(static::__could_not_inject($name,$classname,"Name \"$name\" does not math with any path parameter."));
                    }else{
                        if(\is_numeric($http_params[$name]))
                            $args[] = (float) $http_params[$name];
                        else{
                            throw new Exception('Parameter {'.$name.'} was expected to be numeric, but non numeric value has been provided instead:'.$http_params[$name]);
                        }
                    }
                break;
                default:
                    throw new Exception(static::__could_not_inject($name,$classname));
                break;
            }
        }else{
            if($__ARGS_ATTRIBUTES__ && ($attributes = $__ARGS_ATTRIBUTES__[$name]??false)){
                switch($classname){
                    case 'array':
                        if($attributes[ResponseHeaders::class]??false){
                            if($optional)
                                $http_headers = $__ARG__->getDefaultValue();
                            $args[] = &$http_headers;
                        }else if($attributes[RequestHeaders::class]??false){
                            $args[] = $request->getHeaders();
                        }else if($attributes[Session::class]??false) {
                            $usingSession = true;
                            $args[] = &$this->session($http_headers, $sessionId);
                        }else if($attributes[Body::class]??false){
                            if( $__CONSUMES__ ){
                                if($_recovered_body === null)
                                    $_recovered_body = $request->getBody()->getContents();
                                    
                                $args[] = BodyParser::parse($_recovered_body,$ctype,null,true);
                            }else
                                throw new Exception(static::__could_not_inject($name,$classname,'Specify a Content-Type to consume.'));
                        }else
                            throw new Exception(static::__could_not_inject($name,$classname,"Could not find any valid attribute on \"$name\"."));
                    break;
                    case 'string':
                        if($attributes[Body::class]??false){
                            if( $__CONSUMES__ ){
                                if($_recovered_body === null)
                                    $_recovered_body = $request->getBody()->getContents();
    
                                $args[] = &$_recovered_body;
                            }else
                                throw new Exception(static::__could_not_inject($name,$classname,'Specify a Content-Type to consume.'));
                        }else if(($query = $attributes[Query::class])){
                            $key = $query->getName();
                            $queries = $request->getQueryParams();
                            if(!isset($queries[$key]) && $optional)
                                $queries[$key] = $__ARG__->getDefaultValue();
                                
                            $args[] = &$queries[$key];
                        }else
                            throw new Exception(static::__could_not_inject($name,$classname,"Could not find any valid attribute on \"$name\"."));
                    break;
                    case 'int':
                        if($attributes[Body::class]??false){
                            if( $__CONSUMES__ ){
                                if($_recovered_body === null)
                                    $_recovered_body = $request->getBody()->getContents();
                                if(\is_numeric($_recovered_body)){
                                    $args[] = (int) $_recovered_body;
                                }else{
                                    throw new Exception('Body was expected to be numeric, but non numeric value has been provided instead:'.$http_params[$name]);
                                }
                            }else
                                throw new Exception(static::__could_not_inject($name,$classname,'Specify a Content-Type to consume.'));
                        }else if(($query = $attributes[Query::class])){
                            $key = $query->getName();
                            $queries = $request->getQueryParams();
                            if(!isset($queries[$key]) && $optional)
                                $queries[$key] = $__ARG__->getDefaultValue();

                            $value = &$queries[$key];
                            if(\is_numeric($value))
                                $args[] = (int) $value;
                            else
                                throw new Exception("Query $name was expected to be numeric, but non numeric value has been provided instead:$value");
                            
                        }else
                            throw new Exception(static::__could_not_inject($name,$classname,"Could not find any valid attribute on \"$name\"."));
                    break;
                    case 'bool':
                        if(($query = $attributes[Query::class]??false)){
                            $key = $query->getName();
                            $queries = $request->getQueryParams();
                            if(!isset($queries[$key]) && $optional)
                                $queries[$key] = $__ARG__->getDefaultValue();

                            $value = \filter_var($queries[$key] || false, FILTER_VALIDATE_BOOLEAN);
                            $args[] = $value;
                            
                        }else
                            throw new Exception(static::__could_not_inject($name,$classname,"Could not find any valid attribute on \"$name\"."));
                    break;
                    case 'float':
                        if($attributes[Body::class]??false){
                            if( $__CONSUMES__ ){
                                if($_recovered_body === null)
                                    $_recovered_body = $request->getBody()->getContents();
                                if(\is_numeric($_recovered_body)){
                                    $args[] = (float) $_recovered_body;
                                }else{
                                    throw new Exception('Body was expected to be numeric, but non numeric value has been provided instead:'.$http_params[$name]);
                                }
                            }else
                                throw new Exception(static::__could_not_inject($name,$classname,'Specify a Content-Type to consume.'));                            
                        }else if(($query = $attributes[Query::class])){
                            $key = $query->getName();
                            $queries = $request->getQueryParams();
                            if(!isset($queries[$key]) && $optional)
                                $queries[$key] = $__ARG__->getDefaultValue();
                            
                            $value = &$queries[$key];
                            if(\is_numeric($value))
                                $args[] = (float) $value;
                            else
                                throw new Exception("Query $name was expected to be numeric, but non numeric value has been provided instead:$value");
                            
                        }else
                            throw new Exception(static::__could_not_inject($name,$classname,"Could not find any valid attribute on \"$name\"."));
                    break;
                    case Status::class:
                        if($attributes[Status::class]??false){
                            if($optional)
                                $status = $__ARG__->getDefaultValue();
                            $args[] = &$status;
                        }else
                            throw new Exception(static::__could_not_inject($name,$classname,"Could not find any valid attribute on \"$name\"."));
                        break;
                    case ServerRequestInterface::class:
                        if($attributes[Request::class]??false){
                            $args[] = $request;
                        }else
                            throw new Exception(static::__could_not_inject($name,$classname,"Could not find any valid attribute on \"$name\"."));
                    break;
                    case LoopInterface::class:
                        $loop = Factory::make(LoopInterface::class);
                        if($loop){
                            $args[] = $loop;
                        }else{
                            throw new Exception(static::__could_not_inject($name,$classname,"The loop onject doesn't seem to be set as an application singleton."));
                        }
                    break;
                    default:
                        if($attributes[Body::class]??false){
                            if( $__CONSUMES__ ){
                                if($_recovered_body === null)
                                    $_recovered_body = $request->getBody()->getContents();
    
                                $args[] = BodyParser::parse($_recovered_body,$ctype,$classname);
                            } else
                                throw new Exception(static::__could_not_inject($name,$classname,'Specify a Content-Type to consume.'));
                        }else if($attributes[Inject::class]??false){
                            $item = Factory::make($classname);
                            if($item)
                                $args[] = &$item;
                            else
                                throw new Exception(static::__could_not_inject($name,$classname));
                        } else
                            throw new Exception(static::__could_not_inject($name,$classname,"Could not find any valid attribute on \"$name\"."));
                    break;
                }
            }else
                throw new Exception(static::__could_not_inject($name,$classname,"Could not find any valid attribute on \"$name\"."));
        }
    }

    private function &session(array &$http_headers, ?string &$sessionId):array{
        return $this->sm->startSession($http_headers,$sessionId);
    }
}