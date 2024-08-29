<?php


namespace APP\Traits;

use APP\Constants\Constants;
use APP\Filters\FilterIncomingTtlMedia;
use APP\Filters\FilterSavedMessage;
use APP\Filters\FilterUserStatus;
use APP\Helpers\Helper;
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
use danog\MadelineProto\EventHandler\Media;
use danog\MadelineProto\EventHandler\Media\Audio;
use danog\MadelineProto\EventHandler\Media\Gif;
use danog\MadelineProto\EventHandler\Media\Photo;
use danog\MadelineProto\EventHandler\Media\Video;
use danog\MadelineProto\EventHandler\Pinned;
use danog\MadelineProto\EventHandler\Typing;
use danog\MadelineProto\EventHandler\Update;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\RemoteUrl;
use danog\MadelineProto\StrTools;
use danog\MadelineProto\VoIP;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Media\Document;
use Throwable;
use function APP\localization\__;

trait HandlerTrait{
    #[FilterIncomingTtlMedia]
    public function IncomingTtlMedia(Message\PrivateMessage $message):void{
        if(!\Amp\File\exists(Constants::DataFolderPath)) \Amp\File\createDirectory(Constants::DataFolderPath);
        $path = $message->media->downloadToDir(Constants::DataFolderPath);
        $user_mention = $this->mention($message->chatId);

        $caption = __('ttl_caption',['type'=>($message->media instanceof Photo) ? 'photo' : 'video','user_mention'=>$user_mention]);

        $local_file = (new LocalFile($path));
        $local_file = $message->media;
        if($message->media instanceof Photo){
            $this->sendPhoto($this->save_id,$local_file,$caption,Constants::DefaultParseMode);
        }elseif ($message->media instanceof Video){
            $this->sendVideo($this->save_id ,$local_file,caption: $caption,parseMode: Constants::DefaultParseMode,mimeType: $message->media->mimeType);
        }
    }

    #[FilterChannel]
    public function channelMessage(Message\ChannelMessage $message): void{

        if(($this->settings['firstc']['status'] ?? false) === true and empty($this->settings['firstc']['indexes'])) return;
        $discussion = $message->getDiscussion();
        if(is_null($discussion)) return;
        //todo:require to check join requirement to send comment.

        $report = "";
        $discussion_title = $this->getInfo($discussion->chatId)['Chat']['title'];
        $mention_channel = $this->mention($message->chatId,$message->id);
        $x = 0;
        foreach($this->settings['firstc']['indexes'] as $index){
            if(($index['status'] ?? false) !== true) continue;
            $text = $index['text'];
            if (str_contains($text, "|")) {
                $ar = explode("|", $text);
                $text = $ar[rand(0, count($ar) - 1)];
            }
            $text = str_replace(['TIME', 'DATE', 'TITLE', 'X'], [date('H:i', time()), date('y/m/d', time()), $discussion_title, "$x"], $text);

            $message->getDiscussion()->reply($text);
            $x++;
            $report .= __('comment_posted',['x'=>$x,'channel_mention'=>$mention_channel,'text'=>$text]);
        }
        $date = date('y/m/d H:i:s');
        $report .= "\n time:$date";
        $this->myReport($report);
    }

    #[Handler]
    public function allUpdates(Update $update):void{
        if(empty($this->settings['last'])) return;
        foreach ($this->settings['last'] as $key => $value){
            if($value['count'] <= 0) {
                unset($this->settings['last'][$key]);
                continue;
            }
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

            if($sent and isset($this->settings['last'][$key])){
                $serilize_update = $update->jsonSerialize();
                $update_name = basename($serilize_update['_']);
                $json = json_encode($serilize_update,448);
                $to_send = '<b>#new_update</b> : <code>'.$update_name."</code> \n<b>time:".date('H:m:s')."</b>\n";
                $to_send .= __('json',['json'=>$json]);
                $this->myReport($to_send);
                $this->settings['last'][$key]['count']--;
            }
        }
        sort($this->settings['last']);
    }

    public function commands(Message\PrivateMessage $message):void{
        $message_text = trim($message->message);
        $lower_case_message = mb_strtolower($message_text);
        if(isset($message->replyToMsgId)) $reply_to_message_id = $message->replyToMsgId;
        $chat = $message->chatId;
        if(in_array($lower_case_message,['/start','/usage','/restart','/help','/shutdown','/getsettings'])) {
            switch ($lower_case_message) {
                case '/start':
                case '/usage':
                    $fs = $this->startUsage($message);
                    break;
                case '/restart':
                    $fs = __('restarting');
                    $this->sendMessage($chat, $fs);
                    $this->restart();
                    break;
                case '/help':
                    $fs = "<code>/start</code> : get bot status and uptime.\n<code>/restart</code> : restart bot.\n<code>/usage</code> : get Memory Usage.\n<code>/shutdown</code> : shutdown bot.\n<code>/getSettings</code> : json encoded settings.\n<code>/shutdown</code> : shutdown bot.\n";
                    $fs .= "\n<code>/last [FLAGS] [COUNT] [reset]</code> : get new message json encoded.";
                    $x = 0;
                    foreach (Constants::LAST_FLAG as $key => $value) {
                        $fs .= ($x % 2 == 0 ? "\n" : '')."   <code>$key</code> : $value";
                        $x++;
                    }
                    $fs .= "\n<code>/run [CODE]</code> run php code.(access to \$message, \$chat,\$reply_to_message_id variable)";
                    $fs .= "\n<code>/query [QUERY]</code> run database query.";
                    break;
                case '/shutdown':
                    $fs = __('shutdown');
                    $this->sendMessage($chat, $fs);
                    $this->stop();
                    break;
                case '/getsettings':
                    $fs = __('json',['json'=>json_encode($this->settings, 448)]);
                    break;
                default:
                    $fs = __('bad_command');
            }
            $fe = $fs;
            unset($fe);
        }elseif (preg_match("/^\/last\s+((?:-[\w!]+\s*)+)?(\d+)?\s*(reset)?$/", $message_text, $matches)){
            $fs = '';
            $flags = trim($matches[1] ?? '');
            $count = $matches[2] ?? null;
            var_dump($count);
            $reset = isset($matches[3]);
            $last = $this->settings['last'] ?? [];

            if($reset){
                $last = [];
                $fs = __('reset_successfully');
            }else{
                if(is_numeric($count)) {
                    $to_accept = [];
                    if (!empty($flags)) {
                        foreach (explode(' ', $flags) as $flag) {
                            if (empty(trim($flag))) continue;
                            $flag_without_not = $flag;
                            if(Helper::haveNot($flag)) $flag_without_not = substr($flag,0,-1);

                            if (in_array($flag_without_not, array_keys(Constants::LAST_FLAG))) {
                                $to_accept[] = $flag;
                            } else $fs .= __('flag_not_exist',['flag'=>$flag]);
                        }
                    } else $to_accept = ['-all'];
                    if (!empty($to_accept)) {
                        $last[] = ['accept' => $to_accept, 'count' => (int)$count];
                        $fs .= __('ok_set');
                    }
                }else $fs = __('bad_command_see_help');
            }
            $this->settings['last'] = $last;

        }elseif (preg_match("/^\/(php|cli|run)\s?(.+)$/su", $message_text, $match)) {
            $mid = $message->reply(__('running'));
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
            } catch (Throwable $e) {
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
                $mid->editText("#error for edit result:\n<code>" . $e->getMessage() . "</code>\nResult file:👇🏻",parseMode: Constants::DefaultParseMode);
                $file_path = './data/run-result.txt';
                \Amp\File\write($file_path,$text);
                $this->sendDocument($chat, (new LocalFile($file_path)), caption: '<code>' . $match[2] . '</code>', parseMode: Constants::DefaultParseMode, fileName: 'run-result.txt', mimeType: 'text/plain', replyToMsgId: $mid->id);
                \Amp\File\deleteFile($file_path);
            }
            $this->logger("end runner");
        }elseif (preg_match("/^\/query\s?(.+)$/su", $message_text, $match)){
            $message->reply(__('running'),parseMode: Constants::DefaultParseMode);
            if(!is_null($this->connectionPool)){
                $result = $this->connectionPool->execute($match[1]);
                $reply_text = "result:\n". Helper::queryResult2String($result);
            }else $reply_text = "without database connection.";
            $message->reply($reply_text,Constants::DefaultParseMode);
        }elseif (preg_match_all("/^(firstc|filter)\s+(new|add|rm|remove|ls|list|off|on|help)\s?([\w ]*)$/m",$message_text,$matches,PREG_SET_ORDER)){
            foreach ($matches as $match){
                $command = $match[1];

                switch ($match[2]){
                    case 'new':
                    case 'add':
                        if(!empty($match[3])) {
                            $this->settings[$command]['indexes'][] = ['status'=>true,'text' => $match[3]];
                            $fs = __('commands.add_successfully');
                        }else $fs = __('commands.bad_input_use_like',['like'=>$command." ".$match[2] . " [TEXT]" ]);
                        break;
                    case 'rm':
                    case 'remove':
                        if(is_numeric($match[3])) {
                            if(isset($this->settings[$command]['indexes'][(int)$match[3]])) {
                                unset($this->settings[$command]['indexes'][(int)$match[3]]);
                                sort($this->settings[$command]['indexes'][(int)$match[3]]);
                                $fs = __('commands.remove_successfully');
                            }else $fs = __('commands.not_exist',['key'=>$match[3]]);
                        }elseif (is_string($match[3])){
                            $found = false;
                            foreach ($this->settings[$command]['indexes'] as $key => $index){
                                if($index['text'] == $match[3]){
                                    unset($this->settings[$command]['indexes'][$key]);
                                    sort($this->settings[$command]['indexes'][$key]);
                                    $fs = __('commands.remove_successfully');
                                    $found = true;
                                    break;
                                }
                            }
                            if(!$found) $fs = __('commands.not_exist',['key'=>$match[3]]);
                        }else $fs = __('commands.bad_input_use_like',['like'=>$command." ".$match[2] . " [TEXT|INDEX]" ]);
                        break;
                    case 'ls':
                    case 'list':
                        $status_string = ($this->settings[$command]['status'] ?? false) === true ? __('status.on') : __('status.off');
                        $fs = $status_string . "\n";
                        if(!empty($this->settings[$command]['indexes'])) {
                            foreach ($this->settings[$command]['indexes'] as $key => $index) {
                                $status_string = ($index['status'] ?? false) === true ? __('status.on') : __('status.off');
                                $fs .= $key . ": '" . $index['text'] . "' ($status_string)\n";
                            }
                        }else $fs .= __('is_empty');
                        break;
                    case 'off':
                    case 'on':
                        $set = $match[2] === 'on';
                        if(!empty($match[3])){
                            if(is_numeric($match[3])) {
                                if(isset($this->settings[$command]['indexes'][(int)$match[3]])) {
                                    $this->settings[$command]['indexes'][(int)$match[3]]['status'] = $set;
                                    $fs = __('commands.change_successfully');
                                }else $fs = __('commands.not_exist',['key'=>$match[3]]);
                            }elseif (is_string($match[3])){
                                $found = false;
                                foreach ($this->settings[$command]['indexes'] as $key => $index){
                                    if($index['text'] == $match[3]){
                                        $this->settings[$command]['indexes'][(int)$match[3]]['status'] = $set;
                                        $fs = __('commands.change_successfully');
                                        $found = true;
                                        break;
                                    }
                                }
                                if(!$found) $fs = __('commands.not_exist',['key'=>$match[3]]);
                            }else $fs = __('commands.bad_input_use_like',['like'=>$command." ".$match[2] . " [TEXT|INDEX]" ]);
                        }else{
                            $this->settings[$command]['status'] = $set;
                            $fs = __('commands.change_successfully');
                        }
                        break;
                    case 'help':
                        $fs = __('commands.help',['command'=>$command]);
                        break;
                    default:
                        $fs = __('bad_command');
                }
            }
        }elseif (str_starts_with($message_text,'download') or str_starts_with($message_text,'upload')) {
            if($message_text == 'download' and $message->replyToMsgId != null){//replayed media to link
                $replayed_message = $message->getReply();
                if(isset($replayed_message->media) and $replayed_message->media instanceof Media){
                    $media = $replayed_message->media;
                    if($media->size > 10 * 1024 *1024 ){//size is less than 10MB
                        $download_script_url = is_null($_ENV['DOWNLOAD_SCRIPT_URL']) ? null : $_ENV['DOWNLOAD_SCRIPT_URL'];
                        try {
                            $download_link = $this->getDownloadLink($media,$download_script_url);
                            $fs = $download_link;
                        }catch (\Exception $e){
                            $fs = __('download_script_url_wrong');
                        }
                    }else $fs = __('file_is_too_small',['size'=>round($media->size / (1024^2),2),'minimumSize'=>'10MB']);
                }else $fs = __('replayed_no_media');
            }elseif (preg_match('/^upload:(https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\\+.~#?&\/=]*))\s?(.*)$/', $message_text,$matches)) {
                $url = $matches[1];
                $file_name = !empty($matches[2]) ? $matches[2] : null;
                $remote_file = new RemoteUrl($url);
                $mime_type = $this->extractMime(false,$remote_file,null,null,null);
                switch (strtolower($mime_type)){
                    case 'video/mpeg':
                    case 'video/mp4':
                    case 'video/mpv':
                        $type = Video::class;
                        break;
                    case 'image/jpeg':
                    case 'image/png':
                        $type = Photo::class;
                        break;
                    case 'image/gif':
                        $type = Gif::class;
                        break;
                    case 'audio/flac':
                    case 'audio/ogg':
                    case 'audio/mpeg':
                    case 'audio/mp4':
                        $type = Audio::class;
                        break;
                    default:
                        $type = Document::class;
                        break;
                }
                switch ($type){
                    case Document::class:
                        $this->sendDocument($chat,$remote_file,fileName: $file_name);
                        break;
                    case Video::class:
                        $this->sendVideo($chat,$remote_file,fileName: $file_name);
                        break;
                    case Photo::class:
                        $this->sendPhoto($chat,$remote_file,fileName: $file_name);
                        break;
                    case Audio::class:
                        $this->sendAudio($chat,$remote_file,fileName: $file_name);
                        break;
                    case Gif::class:
                        $this->sendGif($chat,$remote_file,fileName: $file_name);
                }

            }
        }

        if(!empty($fs)) $this->sendMessage($chat, $fs,parseMode: Constants::DefaultParseMode);
        if(!empty($fe)) $message->replyOrEdit($fe,parseMode: Constants::DefaultParseMode);
    }
    #[FilterSavedMessage]
    public function savedMessage(Message\PrivateMessage $message): void{
        $this->commands($message);
    }

    #[FilterOutgoing]
    public function OutgoingPrivateMessage(Message\PrivateMessage $message): void{
        $message_text = $message->message;
        if(in_array($message_text,['/start','/usage'])){
            $fe = $this->startUsage($message);
        }elseif ($message_text === 'set as admin'){
            if(!in_array($message->chatId,($this->settings['admins'] ?? []))){
                $this->settings['admins'][] = $message->chatId;
                $fe = __('admin.ur_admin');
                $report = __('admin.user_is_admin',['mention'=>$this->mention($message->chatId) ]);
            }else {
                unset($this->settings['admins'][array_search($message->chatId,$this->settings['admins'])]);
                $fe = __('admin.ur_not_admin');
                $report = __('admin.user_is_not_admin',['mention'=>$this->mention($message->chatId) ]);
            }
        }elseif ($message_text === 'block'){
            if(!in_array($message->chatId,($this->settings['block_list'] ?? []))){
                $this->settings['block_list'][] = $message->chatId;
                $fd = __('block.block_successfully');
                $report = __('block.user_is_block',['mention'=>$this->mention($message->chatId) ]);
            }else {
                unset($this->settings['block_list'][array_search($message->chatId,$this->settings['block_list'])]);
                $fe = __('block.unblock_successfully');
                $report = __('block.user_is_not_block',['mention'=>$this->mention($message->chatId) ]);
            }
        }

        if(!empty($fe)) $message->replyOrEdit($fe,parseMode: Constants::DefaultParseMode);
        if(!empty($fd)){
            $message->replyOrEdit($fd,parseMode: Constants::DefaultParseMode);
            \Amp\delay(3);
            $message->delete();
        }
        if(!empty($report)) $this->myReport($report);
    }

    #[FilterIncoming]
    public function IncomingPrivateMessage(Message\PrivateMessage $message): void{
        if(in_array($message->chatId,($this->settings['admins'] ?? []))){
            $this->commands($message);
        }elseif(in_array($message->chatId,($this->settings['block_list'] ?? []))){
            $forward = $message->forward($this->save_id)[0];
            $this->sendMessage($this->save_id,__('block.message_from_blocked_user',['mention'=>$this->mention($message->chatId)]) , replyToMsgId: $forward->id);
            $message->delete();
        }
    }

    #[FilterOutgoing]
    public function outgoingChannelGroupMessage(Message\ChannelMessage|Message\GroupMessage $message): void{
        $message_text = $message->message;
        if(in_array($message_text,['/start','/usage'])){
            $fe = $this->startUsage($message);
        }elseif ($message_text === 'set as save'){
            $this->settings['save_id'] = $message->chatId;
            $this->save_id = $message->chatId;
            $fe = __('set_as_save_successfully');
            $report = __('set_as_save',['mention'=>$this->mention($message->chatId)]);
        }

        if(!empty($fe)) $message->replyOrEdit($fe,parseMode: Constants::DefaultParseMode);
        if(!empty($fd)){
            $message->replyOrEdit($fd,parseMode: Constants::DefaultParseMode);
            \Amp\delay(3);
            $message->delete();
        }
        if(!empty($report)) $this->myReport($report);
    }
}