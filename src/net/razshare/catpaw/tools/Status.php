<?php
namespace net\razshare\catpaw\tools;

use net\razshare\catpaw\attributes\interfaces\AttributeInterface;
use net\razshare\catpaw\attributes\traits\CoreAttributeDefinition;

#[\Attribute]
class Status implements AttributeInterface{
    use CoreAttributeDefinition;

    public function __construct(
        private int $code = 200
    ) {}

    public function getCode():int{
        return $this->code;
    }

    public function setCode(int $value):void{
        $this->code = $value;
    }


    const 
        //INFORMATINOAL RESPONSES
        CONTINUE = 100,
        SWITCHING_PROTOCOLS = 101,
        PROCESSING = 102,

        //SUCCESS
        OK = 200,
        CREATED = 201,
        ACCEPTED = 202,
        NON_AUTHORITATIVE_INFORMATION = 203,
        NO_CONTENT = 204,
        RESET_CONTENT = 205,
        PARTIAL_CONTENT = 206,
        MULTI_STATUS = 207,
        ALREADY_REPORTED = 208,
        IM_USED = 226,

        //REDIRECTIONS
        MULTIPLE_CHOICES = 300,
        MOVED_PERMANENTLY = 301,
        FOUND = 302,
        SEE_OTHER = 303,
        NOT_MODIFIED = 304,
        USE_PROXY = 305,
        SWITCH_PROXY = 306,
        TEMPORARY_REDIRECT = 307,
        PERMANENT_REDIRECT = 308,

        //CLIENT ERRORS
        BAD_REQUEST = 400,
        UNAUTHORIZED = 401,
        PAYMENT_REQUIRED = 402,
        FORBIDDEN = 403,
        NOT_FOUND = 404,
        METHOD_NOT_ALLOWED = 405,
        NOT_ACCEPTABLE = 406,
        PROXY_AUTHENTICATION_REQUIRED = 407,
        REQUEST_TIMEOUT = 408,
        CONFLICT = 409,
        GONE = 410,
        LENGTH_REQUIRED = 411,
        PRECONDITION_FAILED = 412,
        PAYLOAD_TOO_LARGE = 413,
        URI_TOO_LONG = 414,
        UNSUPPORTED_MEDIA_TYPE = 415,
        REQUESTED_RANGE_NOT_SATISFIABLE = 416,
        EXPECTATION_FAILED = 417,
        IM_A_TEAPOT = 418,
        MISDIRECTED_REQUEST = 421,
        UNPROCESSABLE_ENTITY = 422,
        LOCKED = 423,
        FAILED_DEPENDENCY = 426,
        UPGRADE_REQUIRED = 428,
        PRECONDITION_REQUIRED = 429,
        TOO_MANY_REQUESTS = 429,
        REQUEST_HEADER_FIELDS_TOO_LARGE = 431,
        UNAVAILABLE_FOR_LEGAL_REASONS = 451,

        //SERVER ERRORS
        INTERNAL_SERVER_ERROR = 500,
        NOT_IMPLEMENTED = 501,
        BAD_GATEWAY = 502,
        SERVICE_UNAVAILABLE = 503,
        GATEWAY_TIMEOUT = 504,
        HTTP_VERSION_NOT_SUPPORTED = 505,
        VARIANT_ALSO_NEGOTATIES = 506,
        INSUFFICIENT_STORAGE = 507,
        LOOP_DETECTED = 508,
        NOT_EXTENDED = 510,
        NETWORK_AUTHENTICATION_REQUIRED = 511;
}