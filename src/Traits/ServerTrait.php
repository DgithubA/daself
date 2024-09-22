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
            if (!$request->hasQueryParameter('f') and !$request->hasQueryParameter('id')) {
                return new Response(body: Lang::$current_lang["dl.php_powered_by_madelineproto"]);
            }


            if(($file_id = $request->getQueryParameter('f')) !== null) {
                $mime_type = $request->getQueryParameter('m');
                $messageMedia = $this->unpackFileId($file_id);
                $messageMedia['mime_type'] = $mime_type;
            }elseif (($id = $request->getQueryParameter('id')) !== null){
                $messageMedia = $this->ormProperty['downloads'][$id] ?? [];
                $mime_type = null;
            }

            if(!empty($messageMedia)) {
                return $this->downloadToResponse(
                    messageMedia: $messageMedia,
                    request: $request,
                    size: $request->getQueryParameter('s') !== null ? $request->getQueryParameter('s') : null,
                    mime: $mime_type,
                    name: $request->getQueryParameter('n')
                );
            }else return new Response(body: "bad url.");

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