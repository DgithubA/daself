<?php

namespace APP;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Participant\MySelf;
use danog\MadelineProto\EventHandler\Plugin\RestartPlugin;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;
use danog\MadelineProto\EventHandler\SimpleFilter\Outgoing;
use danog\MadelineProto\SimpleEventHandler;

class botHandler extends SimpleEventHandler{
    public const ADMIN = "@me";

    public $self_id;
    public int $start_time;
    /**
     * Get peer(s) where to report errors.
     */
    public function getReportPeers(): array
    {
        return [self::ADMIN];
    }

    /**
     * Returns a set of plugins to activate.
     *
     * See here for more info on plugins: https://docs.madelineproto.xyz/docs/PLUGINS.html
     */
    public static function getPlugins(): array
    {
        return [
            // Offers a /restart command to admins that can be used to restart the bot, applying changes.
            // Make sure to run in a bash while loop when running via CLI to allow self-restarts.
            RestartPlugin::class,
        ];
    }

    public function onStart(): void
    {
        $this->logger("The bot was started!");
        $this->logger($this->getFullInfo('MadelineProto'));

        $this->sendMessageToAdmins("The bot was started!");
        $this->self_id = $this->getSelf()['id'];
        $this->start_time = time();
    }

    #[Handler]
    public function handleMessage(Outgoing&Message\PrivateMessage $message): void{
        if ($message->chatId === $this->self_id){
            if($message->message === '/start'){
                $this->sendMessage($message->chatId,"the bot uptime is:" . (time() - $this->start_time));
            }elseif ($message->message === '/shutdown'){
                $this->sendMessage($message->chatId,"goodbye :)");
                $this->stop();
            }
        }
    }
}
