<?php


namespace APP\Traits;


use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\SocketHttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Socket;
use danog\MadelineProto\Lang;
use Revolt\EventLoop;


trait ServerTrait{
    private SocketHttpServer $server;

    public function handleRequest(Request $request): Response{
        if($request->getUri()->getPath() === '/download') {
            if ($request->hasQueryParameter('login')) {
                $id = (string)$this->getSelf()['id'];
                return new Response(body: $id);
            }
            if (!$request->hasQueryParameter('id')) {
                return new Response(body: Lang::$current_lang["dl.php_powered_by_madelineproto"]);
            }

            if (($id = $request->getQueryParameter('id')) !== null){
                $name = $request->getQueryParameter('n');
                //$mime_type = $request->getQueryParameter('m');
                //$size = $request->getQueryParameter('s');
                if(isset($this->ormProperty['downloads'][$id])){
                    $messageMedia = $this->ormProperty['downloads'][$id];
                    $name ??= pathinfo($messageMedia['name'], PATHINFO_FILENAME);
                }
            }
            if(!isset($name)) return new Response(HttpStatus::UNPROCESSABLE_ENTITY,body: 'parameter `n` is required');
            if(!isset($messageMedia)) return new Response(HttpStatus::NOT_FOUND,body: "media not found.");

            return $this->downloadToResponse(
                messageMedia: $messageMedia,
                request: $request,
                size: $size ?? null,
                mime: $mime_type ?? null,
                name: $name
            );

        }elseif ($request->getUri()->getPath() === '/status') {
            return new Response(body: "bot is run.!");
        }
        return new Response(HttpStatus::NOT_FOUND);
    }
    private function initWebServer():void{
        $this->logger('init web server');
        $this->server = new SocketHttpServer(
            $this->getPsrLogger(),
            new Socket\ResourceServerSocketFactory(),
            new SocketClientFactory($this->getPsrLogger()),
        );
        $expose_port = $_ENV['DL_EXPOSE_PORT'] ?? 8000;
        $this->server->expose("0.0.0.0:$expose_port");
        $this->server->expose("[::]:$expose_port");
    }

    public function startWebServer(): void{
        $this->logger('start web server');
        $this->server->start($this, new DefaultErrorHandler());
    }
    public function stopWebServer(): void{
        $this->logger('stop web server');
        EventLoop::queue(fn()=>$this->server->stop());
    }
}