<?php
declare(strict_types=1);

namespace APP;


use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlConnectionPool;
use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnectionPool;
use Amp\Redis\RedisClient;
use APP\Filter\FilterSavedMessage;
use APP\Filter\FilterUserStatus;
use APP\Helper\Helper;
use danog\MadelineProto\EventHandler\AbstractStory;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\CallbackQuery;
use danog\MadelineProto\EventHandler\Channel\ChannelParticipant;
use danog\MadelineProto\EventHandler\Delete;
use danog\MadelineProto\EventHandler\Filter\FilterChannel;
use danog\MadelineProto\EventHandler\Filter\FilterCommentReply;
use danog\MadelineProto\EventHandler\Filter\FilterGroup;
use danog\MadelineProto\EventHandler\Filter\FilterIncoming;
use danog\MadelineProto\EventHandler\Filter\FilterMedia;
use danog\MadelineProto\EventHandler\Filter\FilterOutgoing;
use danog\MadelineProto\EventHandler\Filter\FilterPrivate;
use danog\MadelineProto\EventHandler\Filter\FilterService;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Pinned;
use danog\MadelineProto\EventHandler\Plugin\RestartPlugin;
use danog\MadelineProto\EventHandler\Typing;
use danog\MadelineProto\EventHandler\Update;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\ParseMode;
use danog\MadelineProto\SimpleEventHandler;
use danog\MadelineProto\StrTools;
use danog\MadelineProto\Settings;
use danog\MadelineProto\VoIP;
use function Amp\Redis\createRedisClient;


class botHandler extends SimpleEventHandler{
    public const ADMIN = "@me";

    public int $self_id;
    public int $start_time;
    public array $settings = [];

    const LAST_FLAG = ['-all'=>'all update.','-sm'=>'saved message.','-media'=>'media message.','-in'=>'incoming message.','-out'=>'outgoing message.','-ch'=>'channel message.','-co'=>'comment reply.','-gr'=>'group message.','-pr'=>'private message.','-voip'=>'voip.'
        ,'-story'=>'story update.','-service'=>'service update.','-action'=>'user&group user action[typing,send media,etc].','-bq'=>'button query update.','-pinned'=>'pinned message.','-del'=>'delete update.','-chpr'=>'A participant has left, joined, was banned or admined in a channel or supergroup.','-user-status'=>'user offline/online status.'];
    private MysqlConnectionPool|PostgresConnectionPool|RedisClient $connectionPool;
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
        $db_setting = $this->getSettings()->getDb();
        if($db_setting instanceof Settings\Database\Mysql){
            $config = new MysqlConfig($db_setting->getUri(),user: $db_setting->getUsername(),password: $db_setting->getPassword());
            $this->connectionPool = new MysqlConnectionPool($config);
        }elseif ($db_setting instanceof Settings\Database\Postgres){
            $config = new PostgresConfig($db_setting->getUri(), user: $db_setting->getUsername(),password: $db_setting->getPassword());
            $this->connectionPool = new PostgresConnectionPool($config);
        }elseif ($db_setting instanceof Settings\Database\Redis){
            $this->connectionPool = createRedisClient($db_setting->getUri());
        }

        $this->myReport("The bot was started!");
        $this->self_id = $this->getSelf()['id'];
        $this->start_time = time();
    }

    private function myReport(string $message) : array{
        return $this->sendMessageToAdmins($message,parseMode: ParseMode::HTML);
    }
    #[Handler]
    public function allUpdates(Update $update){
        $last_flag_keys = array_keys(self::LAST_FLAG);
        if(!empty($this->settings['last'])){

            foreach ($this->settings['last'] as $key => $value){
                if($value['count'] > 0){
                    $sent = true;
                    foreach ($value['accept'] as $accept){
                        $flag = $accept;
                        if(Helper::haveNot($accept)){
                            $flag = substr($accept,0,-1);
                        }
                        switch ($flag){
                            case '-sm':
                                if(!(new FilterSavedMessage())->initialize($this)->apply($update)) $sent = false;
                                break;
                            case '-media':
                                if(!(new FilterMedia())->initialize($this)->apply($update)) $sent = false;
                                break;
                            case '-in':
                                if(!(new FilterIncoming())->initialize($this)->apply($update)) $sent = false;
                                break;
                            case '-out':
                                if(!(new FilterOutgoing())->initialize($this)->apply($update)) $sent = false;
                                break;
                            case '-ch':
                                if(!(new FilterChannel())->initialize($this)->apply($update)) $sent = false;
                                break;
                            case '-co':
                                if(!(new FilterCommentReply())->initialize($this)->apply($update)) $sent = false;
                                break;
                            case '-gr':
                                if(!(new FilterGroup())->initialize($this)->apply($update)) $sent = false;
                                break;
                            case '-pr':
                                if(!(new FilterPrivate())->initialize($this)->apply($update)) $sent = false;
                                break;
                            case '-voip':
                                if(!($update instanceof VoIP)) $sent = false;
                                break;
                            case '-story':
                                if(!($update instanceof AbstractStory)) $sent = false;
                                break;
                            case '-service':
                                if(!(new FilterService())->initialize($this)->apply($update)) $sent = false;
                                break;
                            case '-action':
                                if(!($update instanceof Typing)) $sent =false;
                                break;
                            case '-bq':
                                if(!($update instanceof CallbackQuery)) $sent = false;
                                break;
                            case '-pinned':
                                if(!($update instanceof Pinned)) $sent = false;
                                break;
                            case '-del':
                                if(!($update instanceof Delete)) $sent = false;
                                break;
                            case '-chpr':
                                if(!($update instanceof ChannelParticipant)) $sent = false;
                                break;
                            case '-user-status':
                                if(!(new FilterUserStatus())->initialize($this)->apply($update)) $sent = false;
                                break;
                        }
                        if(Helper::haveNot($accept)) $sent = !$sent;
                        if(!$sent) break;
                    }

                    if($sent){
                        $serilize_update = $update->jsonSerialize();
                        $update_name = basename($serilize_update['_']);
                        $this->logger($update_name);
                        $json = json_encode($serilize_update,448);
                        $to_send = '<b>#new_update</b> : <code>'.$update_name."</code> \n<b>time:".date('H:m:s')."</b>\n";
                        $to_send .= "<pre language='json'><code>".$json."</code></pre>";
                        $this->myReport($to_send);
                        $this->settings['last'][$key]['count']--;
                    }
                }else {
                    unset($this->settings['last'][$key]);
                    sort($this->settings['last']);
                }
            }
        }
    }

    #[Handler]
    public function handleAllMessages(Message $message): void{
        if(isset($this->settings['save_message']) && $this->settings['save_message'] ?? false){
            if($message->message != "turned on."){
                $this->sendMessage($message->chatId,'<pre language="json" ><code>'.json_encode($message,448).'</code></pre>',ParseMode::HTML);
                $this->settings['save_message'] = false;
            }
        }
    }
    #[FilterSavedMessage]
    public function savedMessage(Message\PrivateMessage $message): void{
        $message_text = trim($message->message);
        $lower_case_message = mb_strtolower($message_text);
        if(isset($message->replyToMsgId)) $reply_to_message_id = $message->replyToMsgId;
        $chat = $message->chatId;
        if(in_array($message_text,['/start','/usage','/restart','/help','/shutdown','/getSettings'])) {
            switch ($message_text) {
                case '/start':
                    $fs = "the bot uptime is:" . Helper::formatSeconds((time() - $this->start_time));
                    break;
                case '/usage':
                    $fs = 'Memory now Usage : ' . round(memory_get_usage() / 1024 / 1024, 2) . '**/**' . round(memory_get_usage(true) / 1024 / 1024, 2) . ' **MB**';
                    $fs .= "\nMemory peak Usage : " . round(memory_get_peak_usage() / 1024 / 1024, 2) . '**/**' . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' **MB**';
                    break;
                case '/restart':
                    $fs = "restarting...";
                    $this->sendMessage($chat, $fs);
                    $this->restart();
                    break;
                case '/help':
                    $fs = "<code>/start</code> : get bot status and uptime.\n<code>/restart</code> : restart bot.\n<code>/usage</code> : get Memory Usage.\n<code>/shutdown</code> : shutdown bot.\n<code>/getSettings</code> : json encoded settings.\n<code>/shutdown</code> : shutdown bot.\n";
                    $fs .= "\n<code>/last [FLAGS] [COUNT] [reset]</code> : get new message json encoded.";
                    $x = 0;
                    foreach (self::LAST_FLAG as $key => $value) {
                        $fs .= ($x % 2 == 0 ? "\n" : '')."   <code>$key</code> : $value";
                        $x++;
                    }
                    $fs .= "\n<code>/run [CODE]</code> run php code.(access to \$message, \$chat,\$reply_to_message_id variable)";
                    $fs .= "\n<code>/query [QUERY]</code> run database query.";
                    break;
                case '/shutdown':
                    $fs = "goodbye :)";
                    $this->sendMessage($chat, $fs);
                    $this->stop();
                    break;
                case '/getSettings':
                    $fs = '<pre language="json"><code>' . json_encode($this->settings, 448) . '</code></pre>';
                    break;
                default:
                    $fs = "bad command.!";
            }
        }elseif (preg_match("/^\/last\s+((?:-[\w!]+\s*)+)?(\d+)?\s*(reset)?$/", $message_text, $matches)){
            $fs = '';
            $flags = trim($matches[1] ?? '');
            $count = $matches[2] ?? null;
            var_dump($count);
            $reset = isset($matches[3]);
            $last = $this->settings['last'] ?? [];

            if($reset){
                $last = [];
                $fs = 'ok. reset.';
            }else{
                if(is_numeric($count)) {
                    $to_accept = [];
                    if (!empty($flags)) {
                        foreach (explode(' ', $flags) as $flag) {
                            if (empty(trim($flag))) continue;
                            $flag_without_not = $flag;
                            if(Helper::haveNot($flag)) $flag_without_not = substr($flag,0,-1);

                            if (in_array($flag_without_not, array_keys(self::LAST_FLAG))) {
                                $to_accept[] = $flag;
                            } else $fs .= "flag $flag not exist.\n";
                        }
                    } else $to_accept = ['-all'];
                    if (!empty($to_accept)) {
                        $last[] = ['accept' => $to_accept, 'count' => (int)$count];
                        $fs .= 'ok. set';
                    }
                }else $fs = 'bad command. see /help';
            }
            $this->settings['last'] = $last;

        }elseif (preg_match("/^\/(php|cli|run)\s?(.+)$/su", $message_text, $match)) {
            $mid = $message->reply("running...");
            $this->logger("start runner:");
            $code = $match[2];
            $torun = "return (function () use 
                                (&\$message ,&\$chat ,&\$reply_to_message_id){
                                {$code}
                                }
                           )();";
            $result = "";
            $error = "";
            ob_start();
            try {
                (eval($torun));
                $result .= ob_get_contents() . "\n";
            } catch (\Throwable $e) {
                $error .= $e->getMessage() . "\n";
            }
            ob_end_clean();
            $result = trim($result);
            $error = trim($error);
            $text = !empty($result) ? $result : "empty result.";
            $text .= !empty($error) ? "\n---------------\n" . "Errors :\n" . $error : "";
            try {
                $text = "Results :\n" . trim($text);
                try {
                    $length = (StrTools::mbStrlen($text));
                } catch (\Throwable $e) {
                    $length = mb_strlen($text);
                }
                $entities = [
                    ["_" => "messageEntityBold", "offset" => 0, "length" => 9],
                    ["_" => "messageEntityCode", "offset" => 10, "length" => $length]];
                $this->messages->editMessage(peer: $chat, id: $mid->id, message: $text, entities: $entities);
            } catch (\Throwable $e) {
                $mid->editText("#error for edit result:\n<code>" . $e->getMessage() . "</code>\nResult file:👇🏻",parseMode: ParseMode::HTML);
                $file_path = './data/run-result.txt';
                \Amp\File\write($file_path,$text);
                $this->sendDocument($chat, (new LocalFile($file_path)), caption: '<code>' . $match[2] . '</code>', parseMode: ParseMode::HTML, fileName: 'run-result.txt', mimeType: 'text/plain', replyToMsgId: $mid->id);
                \Amp\File\deleteFile($file_path);
            }
            $this->logger("end runner");
        }elseif (preg_match("/^\/query\s?(.+)$/su", $message_text, $match)){
            $result = $this->connectionPool->execute($match[1]);
            $text = "result:\n". Helper::queryResult2String($result);
            $message->reply($text);
        }

        if(!empty($fs)) $this->sendMessage($chat, $fs,parseMode: ParseMode::HTML);
    }

    public function __sleep(): array{
        return ["settings"];
    }

}
