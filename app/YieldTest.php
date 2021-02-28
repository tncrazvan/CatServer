<?php
namespace app;

use com\github\tncrazvan\catpaw\attributes\http\methods\GET;
use com\github\tncrazvan\catpaw\attributes\http\Path;
use React\Promise\Promise;

#[Path("/yield")]
class YieldTest{
    #[GET]
    public function test():\Generator|string{
        $user = yield new Promise(fn($r)=>$r("my cool username"));
        return $user;
    }
}