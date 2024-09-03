<?php

namespace APP\Traits;
use APP\Constants\Constants;
use APP\Helpers\Helper;
use danog\MadelineProto\EventHandler\Filter\FilterNotEdited;
use danog\MadelineProto\EventHandler\Media;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\RPCErrorException;
use danog\MadelineProto\EventHandler\Message;
Trait CommandTrait{
    #[FilterNotEdited]
    public function commands(Message $message): void{
        //if($message->editDate !== null) return;
        try {
            $message_text = trim($message->message);
            $lower_case_message = mb_strtolower($message_text);
            if (in_array($lower_case_message, ['/start', '/usage', '/restart', '/help', '/shutdown', '/getsettings','/getmessage','/cancel','/test'])) {
               $this->addsCommand($message);
            }
            elseif (preg_match("/^\/last\s+((?:-[\w!]+\s*)+)?(\d+)?\s*(reset)?$/", $message_text)) {
                $this->lastCommand($message);
            }
            elseif (preg_match("/^\/(php|cli|run)\s?(.+)$/su", $message_text)) {
                $this->codeRunner($message);
            }
            elseif (preg_match("/^\/query\s?(.+)$/su", $message_text)) {
                $this->queryRunner($message);
            }
            elseif (preg_match_all("/^(firstc|filter)\s+(new|add|rm|remove|ls|list|off|on|help|status)\s?([\w ]*)$/m", $message_text,flags:  PREG_SET_ORDER)) {
                $this->features($message);
            }
            elseif (str_starts_with($message_text, '/download') or str_starts_with($message_text, '/upload')) {
                $this->downloaderUploader($message);
            }
            elseif (str_starts_with($message_text,'/getlinkmessage')){
                $this->getLinkMessage($message);
            }
        }catch (\Throwable $e) {
            $this->errorReport(__FUNCTION__, $e, $message);
        }
    }

    public function addsCommand(Message $message): void{
        $message_text = trim($message->message);
        $lower_case_message = mb_strtolower($message_text);
        $chat = $message->chatId;
        $reply2id = $message->id;
        if (in_array($lower_case_message, ['/start', '/usage', '/restart', '/help', '/shutdown', '/getsettings','/getmessage','/cancel','/test'])) {
            switch ($lower_case_message) {
                case '/start':
                case '/usage':
                case '/getmessage':
                    $fe = $this->globalOutCommand($message);
                    break;
                case '/restart':
                    $fs = __('restarting');
                    $this->sendMessage($chat, $fs);
                    $this->restart();
                    break;
                case '/help':
                    $fe = "<code>/start</code> : get bot status and uptime.\n<code>/restart</code> : restart bot.\n<code>/usage</code> : get Memory Usage.\n<code>/shutdown</code> : shutdown bot.\n<code>/getSettings</code> : json encoded settings.\n<code>/shutdown</code> : shutdown bot.\n";
                    $fe .= "\n<code>/last [FLAGS] [COUNT] [reset]</code> : get new message json encoded.";
                    $x = 0;
                    foreach (Constants::LAST_FLAG as $key => $value) {
                        $fs .= ($x % 2 == 0 ? "\n" : '') . "   <code>$key</code> : $value";
                        $x++;
                    }
                    $fe .= "\n<code>/run [CODE]</code> run php code.(access to \$message, \$chat,\$reply_to_message_id variable)";
                    $fe .= "\n<code>/query [QUERY]</code> run database query.";
                    break;
                case '/shutdown':
                    $fs = __('shutdown');
                    $this->sendMessage($chat, $fs);
                    $this->stop();
                    break;
                case '/getsettings':
                    $fe = __('json', ['json' => json_encode($this->settings, 448)]);
                    break;
                case '/cancel':
                    $this->cancellation->cancel();
                    $this->cancellation = new \Amp\DeferredCancellation();
                    $fe = __('canceled');
                    break;
                case '/test':
                    break;
                default:
                    $fe = __('bad_command');
            }
            $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
        }
    }

    public function lastCommand(Message $message): void{
        $message_text = $message->message;
        $chat = $message->chatId;
        $reply2id = $message->id;
        if (preg_match("/^\/last\s+((?:-[\w!]+\s*)+)?(\d+)?\s*(reset)?$/", $message_text, $matches)) {
            $fs = '';
            $flags = trim($matches[1] ?? '');
            $count = $matches[2] ?? null;
            var_dump($count);
            $reset = isset($matches[3]);
            $last = $this->settings['last'] ?? [];

            if ($reset) {
                $last = [];
                $fs = __('reset_successfully');
            } else {
                if (is_numeric($count)) {
                    $to_accept = [];
                    if (!empty($flags)) {
                        foreach (explode(' ', $flags) as $flag) {
                            if (empty(trim($flag))) continue;
                            $flag_without_not = $flag;
                            if (Helper::haveNot($flag)) $flag_without_not = substr($flag, 0, -1);

                            if (in_array($flag_without_not, array_keys(Constants::LAST_FLAG))) {
                                $to_accept[] = $flag;
                            } else $fs .= __('flag_not_exist', ['flag' => $flag]);
                        }
                    } else $to_accept = ['-all'];
                    if (!empty($to_accept)) {
                        $last[] = ['accept' => $to_accept, 'count' => (int)$count];
                        $fs .= __('ok_set');
                    }
                } else $fs = __('bad_command_see_help');
            }
            $this->settings['last'] = $last;

            $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
        }
    }

    public function codeRunner(Message $message):void{
        $message_text = $message->message;
        $chat = $message->chatId;
        if (preg_match("/^\/(php|cli|run)\s?(.+)$/su", $message_text, $match)) {
            $message_to_edit = $message->reply(__('running'));
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
                $error .= $e->getTraceAsString() . "\n";
            }
            ob_end_clean();
            $result = trim($result);
            $error = trim($error);
            $text = !empty($result) ? $result : "empty result.";
            $text .= !empty($error) ? "\n---------------\n" . "Errors :\n" . $error : "";
            try {
                $text = __('results', ['data' => trim($text)]);
                $message_to_edit->editText($text, parseMode: Constants::DefaultParseMode);
            } catch (\Throwable $e) {
                $message_to_edit->editText("#error for edit result:\n<code>" . $e->getMessage() . "</code>\nResult file:👇🏻", parseMode: Constants::DefaultParseMode);
                $file_path = './data/run-result.txt';
                \Amp\File\write($file_path, $text);
                $this->sendDocument($chat, (new LocalFile($file_path)), caption: '<code>' . $match[2] . '</code>', parseMode: Constants::DefaultParseMode, fileName: 'run-result.txt', mimeType: 'text/plain', replyToMsgId: $message_to_edit->id);
                \Amp\File\deleteFile($file_path);
            }
            $this->logger("end runner");
        }
    }

    public function queryRunner(Message $message):void{
        $message_text = $message->message;
        $chat = $message->chatId;
        if (preg_match("/^\/query\s?(.+)$/su", $message_text, $match)) {
            $message_to_edit = $message->reply(__('running'), parseMode: Constants::DefaultParseMode);
            if (!is_null($this->databaseService)) {
                try {
                    $result = $this->databaseService->execute($match[1]);
                    $fe = __('results',['data'=>Helper::queryResult2String($result)]);
                } catch (\Amp\Redis\Protocol\QueryException $exception) {
                    $fe = $exception->getMessage();
                } catch (\Amp\Sql\SqlQueryError $e) {
                    $fe = $e->getMessage();
                }
            } else $fe = __('without_database_connection');
            $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
        }
    }

    public function features(Message $message):void{
        $message_text = $message->message;
        $chat = $message->chatId;
        if (preg_match_all("/^(firstc|filter)\s+(new|add|rm|remove|ls|list|off|on|help|status)\s?([\w ]*)$/m", $message_text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $command = $match[1];
                switch ($match[2]) {
                    case 'new':
                    case 'add':
                        if (!empty($match[3])) {
                            $this->settings[$command]['indexes'][] = ['status' => true, 'text' => $match[3]];
                            $fs = __('commands.add_successfully');
                        } else $fs = __('commands.bad_input_use_like', ['like' => $command . " " . $match[2] . " [TEXT]"]);
                        break;
                    case 'rm':
                    case 'remove':
                        if (is_numeric($match[3])) {
                            if (isset($this->settings[$command]['indexes'][(int)$match[3]])) {
                                unset($this->settings[$command]['indexes'][(int)$match[3]]);
                                sort($this->settings[$command]['indexes'][(int)$match[3]]);
                                $fs = __('commands.remove_successfully');
                            } else $fs = __('commands.not_exist', ['key' => $match[3]]);
                        } elseif (is_string($match[3])) {
                            $found = false;
                            foreach ($this->settings[$command]['indexes'] as $key => $index) {
                                if ($index['text'] == $match[3]) {
                                    unset($this->settings[$command]['indexes'][$key]);
                                    sort($this->settings[$command]['indexes'][$key]);
                                    $fs = __('commands.remove_successfully');
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) $fs = __('commands.not_exist', ['key' => $match[3]]);
                        } else $fs = __('commands.bad_input_use_like', ['like' => $command . " " . $match[2] . " [TEXT|INDEX]"]);
                        break;
                    case 'ls':
                    case 'list':
                    case 'status':
                    $status_string = ($this->settings[$command]['status'] ?? false) === true ? __('status.on') : __('status.off');
                        $fs = $status_string . "\n";
                        if (!empty($this->settings[$command]['indexes'])) {
                            foreach ($this->settings[$command]['indexes'] as $key => $index) {
                                $status_string = ($index['status'] ?? false) === true ? __('status.on') : __('status.off');
                                $fs .= $key . ": '" . $index['text'] . "' ($status_string)\n";
                            }
                        } else $fs .= __('is_empty');
                        break;
                    case 'off':
                    case 'on':
                        $set = $match[2] === 'on';
                        if (!empty($match[3])) {
                            if (is_numeric($match[3])) {
                                if (isset($this->settings[$command]['indexes'][(int)$match[3]])) {
                                    $this->settings[$command]['indexes'][(int)$match[3]]['status'] = $set;
                                    $fs = __('commands.change_successfully');
                                } else $fs = __('commands.not_exist', ['key' => $match[3]]);
                            } elseif (is_string($match[3])) {
                                $found = false;
                                foreach ($this->settings[$command]['indexes'] as $key => $index) {
                                    if ($index['text'] == $match[3]) {
                                        $this->settings[$command]['indexes'][(int)$match[3]]['status'] = $set;
                                        $fs = __('commands.change_successfully');
                                        $found = true;
                                        break;
                                    }
                                }
                                if (!$found) $fs = __('commands.not_exist', ['key' => $match[3]]);
                            } else $fs = __('commands.bad_input_use_like', ['like' => $command . " " . $match[2] . " [TEXT|INDEX]"]);
                        } else {
                            $this->settings[$command]['status'] = $set;
                            $fs = __('commands.change_successfully');
                        }
                        break;
                    case 'help':
                        $fs = __('commands.help', ['command' => $command]);
                        break;
                    default:
                        $fs = __('bad_command');
                }
            }
            $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
        }
    }

    public function downloaderUploader(Message $message):void{
        $message_text = $message->message;
        $chat = $message->chatId;
        if (str_starts_with($message_text, '/download') or str_starts_with($message_text, '/upload')) {
            if ($message_text == '/download' and $message->replyToMsgId != null) {//replayed media to link
                $message_to_edit = $message->replyOrEdit(__('downloading'));
                $replayed_message = $message->getReply();
                if (isset($replayed_message->media) and $replayed_message->media instanceof Media) {
                    $media = $replayed_message->media;
                    if ($media->size > 10 * 1024 * 1024) {//size is less than 10MB
                        $download_script_url = !empty($_ENV['DL_SERVER_HOST']) ? $_ENV['DL_SERVER_HOST'] : null;
                        if (isset($this->settings['DL_SERVER_HOST'])) $download_script_url = $this->settings['DL_SERVER_HOST'];
                        try {
                            $download_link = $this->getDownloadLink($media, $download_script_url);
                            $fe = __('code', ['code' => htmlspecialchars($download_link)]);
                        } catch (\Exception $e) {
                            if (str_contains($e->getMessage(), 'downloadServer(')) {
                                $fe = __('download_script_url_wrong');
                            } else throw $e;
                        }
                    } else $fe = __('file_is_too_small', ['size' => Helper::humanFileSize($media->size), 'minimumSize' => '10MB']);
                } else $fe = __('replayed_no_media');
            } elseif (str_starts_with($message_text, '/upload')) {
                if (preg_match('/^\/upload\s?(.*)$/mi', $message_text, $match) and ($replayed_message = $message->getReply()) !== null and is_string(($replayed_message_message = $replayed_message->message)) and preg_match('~(https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\\+\~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\\+.\~#?&\/=]*))\s?(\w*)~im', $replayed_message_message, $matches)) {
                    $url = $matches[1];
                    $file_name = $matches[2];
                    if (!empty($match[1])) $file_name = $match[1];
                    $message_to_edit = $message->replyOrEdit(__('uploading'));
                } elseif (preg_match('/^\/upload\s(https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\\+\~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\\+.\~#?&\/=]*))\s?([\w\.]*)$/im', $message_text, $matches)) {
                    $url = $matches[1];
                    $file_name = $matches[2];
                    $message_to_edit = $message->reply(__('uploading'));
                }

                if (isset($url)) {
                    $file_name = !empty($file_name) ? $file_name : null;
                    $this->logger("upload url:$url filename:$file_name");
                    $cb = function ($percent, $speed, $time) use ($message_to_edit) {
                        static $status = true;
                        if (!$status) return;
                        static $prev_time = 0;
                        static $prev_percent = -1;
                        $now = microtime(true);
                        if (($now - $prev_time <= 2 || $prev_percent == $percent) and $percent != 100) return;//edit text every 2 sec and when percent was changed

                        $prev_time = $now;
                        $prev_percent = (int)$percent;

                        if ($percent != 100) {
                            $text = __('upload_percent', ['percent' => (int)$percent, 'speed' => $speed, 'time' => $time]);
                        } else $text = __('upload_successfully', ['time' => $time, 'speed' => $speed]);
                        try {
                            $message_to_edit->editText($text, parseMode: Constants::DefaultParseMode);
                        } catch (RPCErrorException $e) {
                            if ($e->rpc !== 'MESSAGE_NOT_MODIFIED') $status = false;
                        }
                    };

                    $this->myUpload($chat, $url, ($message->replyToMsgId ?? $message->id), $file_name, $cb);
                } else {
                    $text = __('url_not_found');
                    $reply2id = $message->getReply() ?? $message;
                    if ($message_text === '/upload') {
                        $fe = $text;
                    } else $fs = $text;
                }
            }
            $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
        }
    }
    public function getLinkMessage(Message $message):void{
        $message_text = $message->message;
        $chat = $message->chatId;
        if (str_starts_with($message_text,'/getlinkmessage')){
            if(preg_match('/^\/getlinkmessage\s(.+)$/m',$message_text,$matches)){
                $url = $matches[1];
                $reply2id = $message->id;
                $message_to_edit = $message->reply(__('getlinkmessage_in_progress'));
            }elseif($message_text === '/getlinkmessage' and ($reply = $message->getReply()) !== null) {
                $url = $reply->message;
                $reply2id = $reply->id;
                $message_to_edit = $message->replyOrEdit(__('getlinkmessage_in_progress'));
            }
            if(isset($url) and preg_match('~https:\/\/t\.me\/?c?\/([\d\w]+)\/([\d-]+)~m',$url,$matches)){
                $channel = $matches[1];
                $message_ids = $matches[2];
                if(is_numeric($matches[2])){
                    $message_ids = [(int)$matches[2]];
                }elseif(preg_match('/^(\d+)-(\d+)$/',$matches[2],$matches2)){
                    if($matches2[1] < $matches[2]){
                        $message_ids = [];
                        for ($i = $matches2[1] ; $i <= $matches[2]; $i++) {
                            $message_ids[] = $i;
                        }
                    }
                }
            }

            if(isset($channel) and isset($message_ids)){
                try {
                    $messages = $this->channels->getMessages(channel: $this->getId($channel),id: $message_ids);
                    foreach ($messages['messages'] as $message_item) {
                        if(isset($message_item['media'])){
                            $path = $this->downloadToDir($message_item['media'],Constants::DataFolderPath);
                            $local_file = (new LocalFile($path));

                            switch ($message_item['media']['_']) {
                                case 'messageMediaPhoto':
                                    $this->sendPhoto($chat,$local_file,$message_item['message'],replyToMsgId: $reply2id);
                                    break;
                                case 'messageMediaAudio':
                                    $this->sendAudio($chat,$local_file,caption: $message_item['message'],replyToMsgId: $reply2id,cancellation: $this->cancellation->getCancellation());
                                    break;
                                case 'messageMediaVideo':
                                    $this->sendVideo($chat,$local_file,caption: $message_item['message'],replyToMsgId: $reply2id,cancellation: $this->cancellation->getCancellation());
                                    break;
                                case 'messageMediaFile':
                                    $this->sendDocument($chat,$local_file,caption: $message_item['message'],replyToMsgId: $reply2id,cancellation: $this->cancellation->getCancellation());
                                    break;
                            }
                            \Amp\File\deleteFile($path);
                        }else $this->sendMessage($chat,$message_item['message'],replyToMsgId: $reply2id);
                    }
                }catch (RPCErrorException $e){
                    $fe = $e->getMessage();
                }
            }else $fe = __('getmessagelink_bad_command');
            $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
        }
    }

    private function answer(int|string $peer,string $fs = null,string $fe = null,Message $message_to_edit = null,int|string|Message $reply2id = null):void{
        $reply2id = $reply2id instanceof Message ? $reply2id->id : $reply2id;
        $this->logger("new answer fs: ".(!empty($fs) ? "`$fs`" : 'null')."  fe: " . (!empty($fe) ? "`$fe`" : 'null'));
        if (!empty($fs)) $this->sendMessage($peer, $fs, parseMode: Constants::DefaultParseMode, replyToMsgId: $reply2id);
        if (!empty($fe)) $message_to_edit->replyOrEdit($fe, parseMode: Constants::DefaultParseMode);
    }
    private function globalOutCommand(Message $message) : string{
        $message_text = $message->message;
        switch ($message_text) {
            case '/start':
                $fe = __('start_message', ['counter' => Helper::formatSeconds((time() - $this->start_time))]);
                break;
            case '/usage':
                $fe = __('memory_usage', ['usage' => round(memory_get_usage() / 1024 / 1024, 2), 'real_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    'peak_usage' => round(memory_get_peak_usage() / 1024 / 1024, 2), 'real_peak_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)]);
                break;
            case '/getmessage':
                if(($reply = $message->getReply())!==null) $fe = __('json', ['json' => json_encode($reply, 448)]);
                break;
            default:
                $fe = __('bad_command');
        }
        return $fe;
    }
}