<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
class GatewayTest extends TestCase {
    public function test_nested_gateway_preserves_rooted_request_paths():void {
        $before=$_SERVER;
        try {foreach(['/admin/login','/api/booking-request/site-dffd4cb9c3aa47fd','/appointment/example/token'] as $uri){$prefix=explode('/',$uri)[1];$_SERVER=['REQUEST_URI'=>$uri,'REQUEST_METHOD'=>'GET','HTTP_HOST'=>'tightenupyourlocs.com','SERVER_NAME'=>'tightenupyourlocs.com','SERVER_PORT'=>'443','HTTPS'=>'on','SCRIPT_NAME'=>'/'.$prefix.'/index.php','PHP_SELF'=>'/'.$prefix.'/index.php','SCRIPT_FILENAME'=>'/home/nineoo/customer-sites/tighten-up-your-locs/public/'.$prefix.'/index.php'];require __DIR__.'/../../bootstrap/normalize-request.php';$r=Request::createFromGlobals();$this->assertSame($uri,$r->getPathInfo());$this->assertSame('',$r->getBaseUrl());}}
        finally {$_SERVER=$before;}
    }
}
