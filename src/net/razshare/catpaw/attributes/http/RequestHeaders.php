<?php
namespace net\razshare\catpaw\attributes\http;

use net\razshare\catpaw\attributes\interfaces\AttributeInterface;
use net\razshare\catpaw\attributes\traits\CoreAttributeDefinition;

#[\Attribute]
class RequestHeaders implements AttributeInterface{
    use CoreAttributeDefinition;
}