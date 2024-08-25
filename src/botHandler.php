<?php
declare(strict_types=1);

namespace APP;
use APP\Filter\FilterSavedMessage;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Plugin\RestartPlugin;
use danog\MadelineProto\ParseMode;
use danog\MadelineProto\SimpleEventHandler;

class botHandler extends SimpleEventHandler{
    public const ADMIN = "@me";

    public $self_id;
    public int $start_time;
    public array $settings = [];

    public function getReportPeers(): array{
        return [self::ADMIN];
    }

    public static function getPlugins(): array{
        return [
            RestartPlugin::class,
        ];
    }

    public function onStart(): void{
        $this->logger("The bot was started!");
        $this->sendMessageToAdmins("The bot was started!");
        $this->self_id = $this->getSelf()['id'];
        $this->start_time = time();
    }

    #[Handler]
    public function handleAllMessages(Message $message): void{
        if(isset($this->settings['save_message']) && $this->settings['save_message'] ?? false){
            if($message->message != "turned on."){
                $this->sendMessage($message->chatId,'```'.json_encode($message,448).'```',ParseMode::MARKDOWN);
                $this->settings['save_message'] = false;
            }
        }
    }
    #[FilterSavedMessage]
    public function savedMessage(Message\PrivateMessage $message): void{
        $message_text = trim($message->message);
        $lower_case_message = mb_strtolower($message_text);
        switch ($message_text){
            case '/start':
                $this->sendMessage($message->chatId,"the bot uptime is:" . (time() - $this->start_time));
                break;
            case '/restart':
                $this->sendMessage($message->chatId,"restarting...");
                $this->restart();
                break;
            case '/help':
                $this->sendMessage($message->chatId,"the bot help!");
                break;
            case '/shutdown':
                $this->sendMessage($message->chatId,"goodbye :)");
                $this->stop();
                break;
            case '/getSetting':
                $this->sendMessage($message->chatId,'```'.json_encode($this->settings,448).'```',ParseMode::MARKDOWN);
                break;
            case '/setSettings':
                $this->settings = ['save_message'=>false];
                $this->sendMessage($message->chatId,"setting updated.");
                break;
            case '/saveMessage':
                $this->sendMessage($message->chatId,"turned on.");
                $this->settings['save_message'] = true;
        }
    }
    public function __sleep(): array{
        return ["settings"];
    }
}
