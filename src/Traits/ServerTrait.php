<?php


namespace APP\Traits;


use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\RequestHandler;
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


trait ServerTrait{
    private SocketHttpServer $server;

    public function handleRequest(Request $request): Response{
        if ($request->hasQueryParameter('login')) {
            $id = (string) $this->getSelf()['id'];
            return new Response(body: $id);
        }
        if (!$request->hasQueryParameter('f')) {
            return new Response(body: Lang::$current_lang["dl.php_powered_by_madelineproto"]);
        }

        return $this->downloadToResponse(
            messageMedia: $request->getQueryParameter('f'),
            request: $request,
            size: (int) $request->getQueryParameter('s'),
            mime: $request->getQueryParameter('m'),
            name: $request->getQueryParameter('n')
        );
    }
    private function initWebServer():void{
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

        $this->server->expose("0.0.0.0:8000");
        $this->server->expose("[::]:8000");
    }

    public function startWebServer(): void{
        $this->server->start(new class implements RequestHandler{
            public function handleRequest(Request $request): Response
            {
                return $this->handleRequest($request);
            }
        }, new DefaultErrorHandler());
    }
    public function stopWebServer(): void{
        $this->server->stop();
    }
}