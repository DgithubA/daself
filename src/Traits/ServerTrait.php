<?php


namespace APP\Traits;


use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\SocketHttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Socket;
use Amp\ByteStream;
use danog\MadelineProto\Lang;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Logger;
use Revolt\EventLoop;


trait ServerTrait{
    private SocketHttpServer $server;

    public function handleRequest(Request $request): Response{
        if($request->getUri()->getPath() === '/download') {
            if ($request->hasQueryParameter('login')) {
                $id = (string)$this->getSelf()['id'];
                return new Response(body: $id);
            }
            if (!$request->hasQueryParameter('f')) {
                return new Response(body: Lang::$current_lang["dl.php_powered_by_madelineproto"]);
            }

            return $this->downloadToResponse(
                messageMedia: $request->getQueryParameter('f'),
                request: $request,
                size: (int)$request->getQueryParameter('s'),
                mime: $request->getQueryParameter('m'),
                name: $request->getQueryParameter('n')
            );
        }elseif ($request->getUri()->getPath() === '/status') {
            return new Response(body: "bot is run.!");
        }
        return new Response(HttpStatus::NOT_FOUND);
    }
    private function initWebServer():void{
        $this->logger('init web server');
        $logHandler = new StreamHandler(ByteStream\getStdout());
        $logHandler->pushProcessor(new PsrLogMessageProcessor());
        $logHandler->setFormatter(new ConsoleFormatter());
        $logger = new Logger('server');
        $logger->pushHandler($logHandler);
        $logger->useLoggingLoopDetection(false);


        $this->server = new SocketHttpServer(
            $logger,
            new Socket\ResourceServerSocketFactory(),
            new SocketClientFactory($logger),
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