#!/usr/bin/env php
<?php declare(strict_types=1);

require 'vendor/autoload.php';


use Amp\ByteStream;
use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Socket;
use danog\MadelineProto\API;
use danog\MadelineProto\Lang;
use danog\MadelineProto\Settings\AppInfo;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use function Amp\trapSignal;



$cert = new Socket\Certificate(__DIR__ . '/../test/server.pem');

$context = (new Socket\BindContext)
    ->withTlsContext((new Socket\ServerTlsContext)
        ->withDefaultCertificate($cert));

$logHandler = new StreamHandler(ByteStream\getStdout());
$logHandler->pushProcessor(new PsrLogMessageProcessor());
$logHandler->setFormatter(new ConsoleFormatter());

$logger = new Logger('server');
$logger->pushHandler($logHandler);
$logger->useLoggingLoopDetection(false);



$server = new SocketHttpServer(
    $logger,
    new Socket\ResourceServerSocketFactory(),
    new SocketClientFactory($logger),
);

$server->expose("0.0.0.0:8000");
$server->expose("[::]:8000");

$server->start(new class implements RequestHandler {
    public function handleRequest(Request $request): Response
    {
        global $session_file, $settings;
        if($request->getUri()->getPath() === '/download'){

            if ($request->getAttribute('login') === 1) {
                $API = new API($session_file,$settings);
                $API->start();
                $id = (string) $API->getSelf()['id'];
                return new Response(
                    status: HttpStatus::OK,
                    headers: ["Content-length" => strlen($id)],
                    body: $id,
                );
            }
            if (!$request->hasAttribute('f')) {
                new AppInfo;
                return new Response(
                    status: HttpStatus::FORBIDDEN,
                    body: Lang::$current_lang["dl.php_powered_by_madelineproto"],
                );
            }
            //what should i do here?
        }
        return new Response(
            status: HttpStatus::NOT_FOUND
        );
    }
}, new DefaultErrorHandler());

// Await a termination signal to be received.
$signal = trapSignal([\SIGHUP, \SIGINT, \SIGQUIT, \SIGTERM]);

$logger->info(sprintf("Received signal %d, stopping HTTP server", $signal));

$server->stop();