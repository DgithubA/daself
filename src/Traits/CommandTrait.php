<?php

namespace APP\Traits;
use Amp\ByteStream\ReadableBuffer;
use APP\Constants\Constants;
use APP\Filters\FilterSavedMessage;
use APP\Helpers\Helper;
use danog\MadelineProto\EventHandler\Media;
use danog\MadelineProto\EventHandler\Message\Entities\Pre;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\RPCErrorException;
use danog\MadelineProto\EventHandler\Message;

Trait CommandTrait{

    public function testCommand(Message $message):bool{
        $message_text = trim($message->message);
        if($message_text !== "/test") return false;
        $chat = $message->chatId;
        $fs = "test message";
        //==============================================
        $data = array_keys(\danog\MadelineProto\TL\Conversion\Extension::ALL_MIMES);
        $this->ormProperty['test']['test'] = "test";
        //=====================
        if(isset($data)) $fs = Helper::myJson($data);
        //===============================================
        return $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
    }
    public function commands(Message\PrivateMessage $message): bool{
        if($message->editDate !== null) return false;
        try {
            $is_command = false;
            $is_command &= $this->addsCommand($message);
            $is_command &= $this->lastCommand($message);
            $is_command &= $this->codeRunner($message);
            $is_command &= $this->queryRunner($message);
            $is_command &= $this->features($message);
            $is_command &= $this->downloaderUploader($message);
            $is_command &= $this->getLinkMessage($message);
            $is_command &= $this->saveStory($message);
            $is_command &= $this->convertMedia($message);
            $is_command &= $this->changeMediaAttrs($message);
            return $is_command;
        }catch (\Throwable $e) {
            $this->errorReport(__FUNCTION__, $e, $message);
        }
        return false;
    }

    public function addsCommand(Message $message): bool{
        $message_text = trim($message->message);
        $lower_case_message = mb_strtolower($message_text);
        $chat = $message->chatId;
        $reply2id = $message->id;
        if (!in_array($lower_case_message, ['/start', '/usage', '/restart', '/help', '/shutdown', '/getsettings','/getmessage','/cancel','/test'])) return false;

        switch ($lower_case_message) {
            case '/start':
            case '/usage':
            case '/getmessage':
                $this->globalAddsCommand($message);
                break;
            case '/restart':
                $this->sendMessage($chat, __('restarting'),replyToMsgId: $reply2id);
                $this->restart();
                break;
            case '/help':
                $answer = "<code>/start</code> : get bot status and uptime.\n<code>/restart</code> : restart bot.\n<code>/usage</code> : get Memory Usage.\n<code>/shutdown</code> : shutdown bot.\n<code>/getSettings</code> : json encoded settings.\n<code>/shutdown</code> : shutdown bot.\n";
                $answer .= "\n<code>/last [FLAGS] [COUNT] [reset]</code> : get new message json encoded.";
                $x = 0;
                foreach (Constants::LAST_FLAG as $key => $value) {
                    $answer .= ($x % 2 == 0 ? "\n" : '') . "   <code>$key</code> : $value";
                    $x++;
                }
                $answer .= "\n<code>/run [CODE]</code> run php code.(access to \$message, \$chat,\$reply_to_message_id variable)";
                $answer .= "\n<code>/query [QUERY]</code> run database query.";
                break;
            case '/shutdown':
                $this->sendMessage($chat, __('shutdown'));
                $this->stop();
                break;
            case '/getsettings':
                $answer = __('json', ['json' => json_encode($this->settings, 448)]);
                break;
            case '/cancel':
                $this->cancellation->cancel();
                $this->cancellation = new \Amp\DeferredCancellation();
                $answer = __('canceled');
                break;
            case '/test':
                return $this->testCommand($message);
            default:
                $answer = __('bad_command');
        }
        if(isset($answer)) {
            if ((new FilterSavedMessage())->initialize($this)->apply($message)){//send new message in SavedMessage
                $fs = $answer;
            }else $fe = $answer;//other
            return $this->answer($chat, $fs ?? null, $fe ?? null, $message_to_edit ?? $message, $reply2id ?? null);
        }
        return false;
    }

    public function lastCommand(Message $message): bool{
        $message_text = $message->message;
        $chat = $message->chatId;
        $reply2id = $message->id;
        if (!preg_match("/^\/last\s+((?:-[\w!]+\s*)+)?(\d+)?\s*(reset)?$/", $message_text, $matches)) return false;
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

        return $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
    }

    public function codeRunner(Message $message):bool{
        $message_text = $message->message;
        $lower_case_message = strtolower($message_text);
        $chat = $message->chatId;
        if (!(str_starts_with($lower_case_message,'/php') or str_starts_with($lower_case_message,'/code') or str_starts_with($lower_case_message,'/cli'))) return false;

        if(preg_match("/^\/(php|code|cli|run)\s?(.+)$/sui", $message_text, $match)){
            $entities = $message->entities;
            $code = $match[2];
            $message_to_edit = $message->reply(__('running'));
        }elseif (in_array($lower_case_message , ['/run','/php','/cli','/code']) and $message->getReply() !== null){
            $message_to_edit = $message->replyOrEdit(__('running'));
            $reply = $message->getReply();
            $entities = $reply->entities ?? null;
        }
        if(!empty($entities)) {
            foreach ($entities as $entity) {
                if ($entity instanceof Pre && $entity->language === 'php') {
                    $code = substr($reply->message ?? $message_text, $entity->offset, $entity->length);
                    break;
                }
            }
        }
        if(empty($code)) return $this->answer($chat,fe: __('code_not_found'),message_to_edit: $message_to_edit ?? $message);

        $this->logger("start runner: ".$code);
        $torun = "return (function () use 
                        (&\$message ,&\$chat ,&\$message_to_edit,&\$reply_to_message_id){
                        {$code}
                        \Revolt\EventLoop::queue(\$this->answer(...),\$chat,\$fs ?? null,\$fe ?? null,\$message_to_edit ?? \$message,\$reply2id ?? null);
                        if(isset(\$json)) echo \APP\Helpers\Helper::myJson(\$json);
                        }
                   )();";
        $result = "";
        $error = "";
        ob_start();
        try {
            (eval($torun));
            $result .= ob_get_contents() . "\n";
        } catch (\Throwable $e) {
            $line = $e->getLine();
            $code_exp = explode("\n",$code);
            $error = $e->getMessage();
            if(isset($code_exp[$line - 3])){
                $quote_text = $code_exp[$line-3];
                $quote = ['_'=>'inputReplyToMessage','reply_to_msg_id'=>$reply->id ?? $message->id,'quote_text'=>$quote_text];
            }
        }
        ob_end_clean();
        $result = trim($result);
        $error = trim($error);
        $code_result = false;
        if($code_result){
            $result = __('code',['code'=>$result]);
        }
        $text = !empty($result) ? $result : "empty result.";
        if(!isset($quote)) $text .= !empty($error) ? __('bold',['bold'=>"\n---------------\nErrors :\n"]) . $error : "";
        try {
            $text = __('runner.results', ['data' => trim($text)]);
            $message_to_edit->editText($text, parseMode: Constants::DefaultParseMode);
            if(isset($quote)) $this->messages->sendMessage(peer: $chat, reply_to: $quote, message:  __('bold',['bold'=>"Errors :\n"]).$error,parse_mode: Constants::DefaultParseMode);
        } catch (\Throwable $e) {
            $message_to_edit->editText("#error for edit result:\n<code>" . $e->getMessage() . "</code>\nResult file:👇🏻", parseMode: Constants::DefaultParseMode);
            $this->sendDocument(peer: $chat, file: new ReadableBuffer($text),caption: '<code>' . $code . '</code>', parseMode: Constants::DefaultParseMode, fileName: 'run-result.txt', mimeType: 'text/plain', replyToMsgId: $message_to_edit->id);
        }
        $this->logger("end runner");
        return $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
    }

    public function queryRunner(Message $message):bool{
        $message_text = $message->message;
        $chat = $message->chatId;
        if (!preg_match("/^\/query\s?(.+)$/su", $message_text, $match)) return false;
        $message_to_edit = $message->reply(__('runner.running'), parseMode: Constants::DefaultParseMode);
        if (!is_null($this->databaseService)) {
            try {
                $result = $this->databaseService->execute($match[1]);
                $fe = __('runner.results',['data'=>Helper::queryResult2String($result)]);
            } catch (\Amp\Redis\Protocol\QueryException $exception) {
                $fe = $exception->getMessage();
            } catch (\Amp\Sql\SqlQueryError $e) {
                $fe = $e->getMessage();
            }
        } else $fe = __('runner.without_database_connection');
        return $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
    }

    public function features(Message $message):bool{
        $message_text = $message->message;
        $chat = $message->chatId;
        if (!preg_match_all("/^(firstc|filter)\s+(new|add|rm|remove|ls|list|off|on|help|status)\s?([\w\d ]*)$/m", $message_text, $matches, PREG_SET_ORDER)) return false;
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
        return $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
    }

    public function downloaderUploader(Message $message):bool{
        $message_text = $message->message;
        $chat = $message->chatId;
        if (!(str_starts_with($message_text, '/download') or str_starts_with($message_text, '/upload'))) return false;

        if (preg_match('/^\/download(?:(?:\s([\w\.\s-]+))?)$/',$message_text,$matches) and $message->replyToMsgId != null) {//replayed media to link
            $name = $matches[1] ?? null;
            $message_to_edit = $message->replyOrEdit(__('dlUp.downloading'));
            $replayed_message = $message->getReply();
            if (!isset($replayed_message->media) or $replayed_message->media instanceof Media) $error_fe = __('replayed_no_media');
            $media = $replayed_message->media;
            if ($media->size <= 10 * 1024 * 1024) $error_fe = __('dlUp.file_is_too_small', ['size' => Helper::humanFileSize($media->size), 'minimumSize' => '10MB']);//size is less than 10MB

            $download_script_url = !empty($_ENV['DL_SERVER_HOST']) ? $_ENV['DL_SERVER_HOST'] : null;
            if (isset($this->settings['DL_SERVER_HOST'])) $download_script_url = $this->settings['DL_SERVER_HOST'];
            if(empty($download_script_url)) $error_fe = __('dlUp.download_script_url_wrong');

            $name ??= preg_replace("~^(.+)\_(\d+)\.(\w{2,5})$~", '$1.$3', $media->fileName);
            try {
                $download_link = $this->getDownloadLink($media, $download_script_url, name: $name);
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'downloadServer(')) {
                    $error_fe = __('dlUp.download_script_url_wrong');
                } else $error_fe = $e->getMessage();
            }
            if(isset($error_fe)) return $this->answer($chat,fe: $error_fe ,message_to_edit: $message_to_edit ?? $message);

            $downloads = $this->ormProperty['downloads'] ?? [];
            $id = Helper::newItemWithRandomId(function ($uniq_id) use ($downloads) {
                return (!isset($downloads[$uniq_id]));
            });
            $downloads[$id] = $media->getDownloadInfo();
            if(isset($name)) $downloads[$id]['name'] = $name;
            $this->ormProperty['downloads'] = $downloads;

            $my_custom_url = $download_script_url . '?' . http_build_query(['id' => $id]);
            $fe = __('dlUp.download_link', ['link' => htmlspecialchars($my_custom_url)]);
        } elseif (str_starts_with($message_text, '/upload')) {
            if (preg_match('/^\/upload\s?(.*)$/mi', $message_text, $match) and ($replayed_message = $message->getReply()) !== null and is_string(($replayed_message_message = $replayed_message->message)) and preg_match('~(https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\\+\~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\\+.\~#?&\/=]*))\s?(\w*)~im', $replayed_message_message, $matches)) {
                $url = $matches[1];
                $file_name = $matches[2];
                if (!empty($match[1])) $file_name = $match[1];
                $message_to_edit = $message->replyOrEdit(__('dlUp.uploading'));
            } elseif (preg_match('/^\/upload\s(https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\\+\~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\\+.\~#?&\/=]*))\s?([\w\.]*)$/im', $message_text, $matches)) {
                $url = $matches[1];
                $file_name = $matches[2];
                $message_to_edit = $message->reply(__('dlUp.uploading'));
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
                    if (($now - $prev_time <= 2 || $prev_percent == $percent) and (int)$percent !== 100) return;//edit text every 2 sec and when percent was changed

                    $prev_time = $now;
                    $prev_percent = (int)$percent;

                    if ($percent != 100) {
                        $text = __('dlUp.upload_percent', ['percent' => (int)$percent, 'speed' => $speed, 'time' => $time]);
                    } else $text = __('dlUp.upload_successfully', ['time' => $time, 'speed' => $speed]);
                    try {
                        $message_to_edit->editText($text, parseMode: Constants::DefaultParseMode);
                    } catch (RPCErrorException $e) {
                        if ($e->rpc !== 'MESSAGE_NOT_MODIFIED') $status = false;
                    }
                };
                $caption = __('code',['code'=>htmlspecialchars($url)]);
                $this->smartSendMedia($chat, $url, ($message->replyToMsgId ?? $message->id),$caption, $file_name, $cb);
            } else {
                $text = __('dlUp.url_not_found');
                $reply2id = $message->getReply() ?? $message;
                if ($message_text === '/upload') {
                    $fe = $text;
                } else $fs = $text;
            }
        }
        return $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
    }
    public function getLinkMessage(Message $message):bool{
        $message_text = $message->message;
        $chat = $message->chatId;
        if (!str_starts_with($message_text,'/getlinkmessage')) return false;
        if(preg_match('/^\/getlinkmessage\s(.+)$/m',$message_text,$matches)){
            $url = $matches[1];
            $reply2id = $message->id;
            $message_to_edit = $message->reply(__('getlinkmessage.in_progress'));
        }elseif($message_text === '/getlinkmessage' and ($reply = $message->getReply()) !== null) {
            $url = $reply->message;
            $reply2id = $reply->id;
            $message_to_edit = $message->replyOrEdit(__('getlinkmessage.in_progress'));
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
        if(!isset($channel) or !isset($message_ids)) return $this->answer($chat,fe:  __('getmessagelink.bad_command'),message_to_edit: $message_to_edit??$message);
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
        return $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
    }

    public function saveStory(Message $message):bool{
        $message_text = $message->message;
        $chat = $message->chatId;
        if(!str_starts_with($message_text,'/savestory')) return false;
        if (preg_match('#^\/savestory\shttps://t\.me/(\w+)/s/(\d+)$#', $message_text, $matches)) {
            $user_story = $matches[1];
            $story_id = [$matches[2]];
            $message_to_edit = $message->reply(__('story.saving'));
        }elseif(($reply = $message->getReply()) !== null and ($reply instanceof Message\PrivateMessage or $reply instanceof Message\ChannelMessage)) {
            if(isset($reply->fromId) and $reply->fromId != null){
                $user_story = $reply->fromId;
                $message_to_edit = $message->reply(__('saving_story'));
            }else $fe = __('story.cant_find_user_from_message');
        }elseif($message_text === '/savestory' and $message->out and $message->chatId != $this->getSelf()['id']){//send to private to get story
            $user_story = $message->chatId;
            $message_to_edit = $this->sendMessage($this->save_id,__('saving_story'));
            $message->delete();
        }else $fe = __('bad_command');

        if(isset($user_story)){
            $user_story = $this->getId($user_story);
            $tag = $this->mention($user_story);
            $message_to_edit->editText(__('story.saving_story_tag',['tag'=>$tag]),parseMode: Constants::DefaultParseMode,noWebpage: true);
            if(isset($story_id)){
                $stories = $this->stories->getStoriesByID(peer: $user_story,id: $story_id);
            }else $stories = $this->stories->getPeerStories(peer: $user_story);
            if(!empty($stories['stories'])){
                $fe = __('story.get_story_success',['tag'=>$tag]);
                foreach ($stories['stories'] as $story) {
                    if(empty($story['media'])) continue;
                    $this->reUploadMedia($chat, $story['media'] , replyToMsgId: ($reply ?? $message)->id, caption: $story['caption'] ?? '');
                }
            }else $fe = __('story.no_story_exist',['tag'=>$tag]);
        }

        return $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
    }
    public function convertMedia(Message $message):bool{
        $message_text = $message->message;
        $chat = $message->chatId;
        if(!preg_match('~\/convert\s(video|gif|voice|audio|sticker|photo)~',$message_text,$matches)) return false;
        if(is_null($reply = $message->getReply())) return false;
        if(!isset($reply->media)) return $this->answer($chat,fs: __('replayed_no_media'));

        $to_what = $matches[1];
        $media = $reply->media;
        $file_info = $this->getFileInfo($media);
        $attr = $file_info['document']['attributes'];
        $mime = $media->mimeType;
        $message_to_edit = $message->reply(__('convert.convert'));

        switch ($media::class){
            case Media\Voice::class://voice to audio
                if($to_what === 'audio'){
                    $this->sendAudio($chat,$media);
                }else $not_possible = true;
                break;
            case Media\Audio::class://audio to voice
                if($to_what === 'voice'){
                    $this->sendVoice($chat,$media);
                }else $not_possible = true;
                break;
            case Media\Photo::class://photo to sticker
                if($to_what === 'sticker'){
                    $this->sendSticker($chat,$media,$mime);
                }else $not_possible = true;
                break;
            case Media\Gif::class://git to video
                if($to_what === 'video'){
                    $this->sendVideo($chat,$media);
                }else $not_possible = true;
                break;
            case Media\Sticker::class://sticker to video/photo
                if($to_what === 'photo') {
                    $this->sendPhoto($chat, $media);
                }elseif($to_what === 'video'){
                    $this->sendVideo($chat,$media);
                }else $not_possible = true;
                break;
        }
        if(isset($not_possible)){
            $fe = __('convert.not_possible',['from'=>$media::class,'to'=>$to_what]);
        }else $fe = __('convert.success');
        return $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
    }

    public function changeMediaAttrs(Message $message):bool{
        $message_text = $message->message;
        $chat = $message->chatId;
        if(!preg_match("/^change\s(filename|title|performer|mime|size)\sto\s(.+)$/sui",$message_text,$matches)) return false;
        if(is_null(($reply = $message->getReply()))) return false;
        if(is_null($media = $reply->media)) return $this->answer($chat,fe: __('replayed_no_media'),message_to_edit: $message_to_edit ?? $message);
        $message_to_edit = $message->replyOrEdit(__('change.changing'));
        $to_edit = $matches[1];
        $new_val = $matches[2];
        switch ($to_edit){
            case 'mime':
            case 'filename':
                $new_file_name = $to_edit === 'filename' ? $new_val : $to_edit;
                $new_mime = $to_edit === 'mime' ? $new_val : $to_edit;
                if($media instanceof Media\Document){
                    $this->sendDocument($chat,$media,fileName: $new_file_name , mimeType: $new_mime);
                }else $not_possible = true;
                break;
            case 'title':
            case 'performer':
                $title = $to_edit === 'title' ? $new_val : null;
                $performer = $to_edit === 'performer' ? $new_val : null;
                if($media instanceof Media\Audio){
                    $this->sendAudio($chat,$media,title: $title,performer: $performer);
                }else $not_possible = true;
                break;
            case 'size':
                //todo:resize image & cut audio/voice/video
                if($media instanceof Media\Photo){
                    if (!preg_match("/^(\d+)\*(\d+)$/sui", $new_val, $matches)) $error_fe = __('change.image_size_bad');
                    if(!in_array($media->fileExt,['.jpg','.png'])) $error_fe = __('change.picture_format_not_support');
                    if(!extension_loaded('gd')) $error_fe = __('change.gd_not_supported');
                    if(isset($error_fe)) return $this->answer($chat,fe:$error_fe,message_to_edit: $message_to_edit ?? $message);

                    $fileExt = $media->fileExt;

                    $w = (int)$matches[1];
                    $h = (int)$matches[2];
                    $output_file_name = $media->downloadToDir(Constants::DataFolderPath);
                    $sourceImage = ($fileExt === '.png' ? imagecreatefrompng($output_file_name) : imagecreatefromjpeg($output_file_name));
                    $oldw = imagesx($sourceImage);
                    $oldh = imagesy($sourceImage);
                    $resizedImage = imagecreatetruecolor($w, $h);
                    imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $w, $h, $oldw, $oldh);
                    $new_file_name = Constants::DataFolderPath. 'resized-photo.' . $media->fileExt;
                    $save_success = $fileExt === '.png' ? imagepng($resizedImage, $new_file_name,90) : imagejpeg($resizedImage, $new_file_name,90);
                    if (!$save_success) throw new \Exception('failed to save resized image');
                    imagedestroy($sourceImage);
                    imagedestroy($resizedImage);
                    $this->sendPhoto($chat,file: new LocalFile($new_file_name),caption: $message_text);

                }elseif ($media instanceof Media\Audio or $media instanceof Media\Voice){
                    $fs = "not implemented";
                }elseif ($media instanceof Media\Video){
                    $fs = "not implemented";
                }else $not_possible = true;
        }

        if(isset($not_possible)){
            $fe = __('change.not_possible',['key'=>$to_edit,'type'=>basename($media::class)]);
        }else $fe = __('success');

        return $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
    }
    private function answer(int|string $peer,string $fs = null,string $fe = null,Message $message_to_edit = null,int|string|Message $reply2id = null):bool{
        $reply2id = $reply2id instanceof Message ? $reply2id->id : $reply2id;
        $this->logger("new answer fs: ".(!empty($fs) ? "`$fs`" : 'null')."  fe: " . (!empty($fe) ? "`$fe`" : 'null'));
        if (!empty($fs)) $this->sendMessage($peer, $fs, parseMode: Constants::DefaultParseMode, replyToMsgId: $reply2id,noWebpage: true);
        if (!empty($fe)){
            if($message_to_edit->out or $message_to_edit->senderId == $this->getSelf()['id']) {
                $message_to_edit->editText($fe, parseMode: Constants::DefaultParseMode,noWebpage: true);
            }else $message_to_edit->reply($fe, parseMode: Constants::DefaultParseMode,noWebpage: true);
        }
        return true;
    }
    private function globalOutMessage(Message $message) : bool{
        if(!$message->out) return false;
        $this->globalAddsCommand($message);
        $message_text = $message->message;
        $chat = $message->chatId;
        if(preg_match('/^doexp(?:(?:\s(\d{1,2}))?:(.+))$/',$message_text,$m)){
            $default_sleep_time = 1;
            $sleep_time = (!empty($m[1]) ? (float)($m[1] / 10) : $default_sleep_time);
            $this->messages->getMessages();
            $words = explode(" ", trim($m[2]));
            $text = "";
            foreach ($words as $word) {
                $text .= " " . $word;
                if ($sleep_time != 0) $this->sleep($sleep_time);
                try {
                    $message->editText($text,parseMode: Constants::DefaultParseMode,noWebpage: true);
                } catch (\Throwable $e) {
                    if($e instanceof RPCErrorException){
                        if (preg_match("~FLOOD_WAIT_(\d+)~", $e->rpc, $m)) {
                            $this->sleep($m[1]);
                        }
                    }
                }
            }
        }
        return true;
    }

    public function globalAddsCommand(Message $message):bool{
        $message_text = $message->message;
        $chat = $message->chatId;

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
            case '/savestory':
                $this->saveStory($message);
                break;
        }
        if($message_text !== '/getmessage' and $chat === $this->getSelf()['id'] and !empty($fe)) {//send new message instead edit in SV expect /getmessage
            $fs = $fe;
            unset($fe);
        }
        $this->answer($chat,$fs ?? null,$fe ?? null,$message_to_edit ?? $message,$reply2id ?? null);
        return true;
    }
}