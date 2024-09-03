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
use danog\MadelineProto\EventHandler\Filter\Combinator\FilterNot;
use danog\MadelineProto\EventHandler\Filter\FilterChannel;
use danog\MadelineProto\EventHandler\Filter\FilterCommentReply;
use danog\MadelineProto\EventHandler\Filter\FilterGroup;
use danog\MadelineProto\EventHandler\Filter\FilterIncoming;
use danog\MadelineProto\EventHandler\Filter\FilterMedia;
use danog\MadelineProto\EventHandler\Filter\FilterOutgoing;
use danog\MadelineProto\EventHandler\Filter\FilterPrivate;
use danog\MadelineProto\EventHandler\Filter\FilterService;
use danog\MadelineProto\EventHandler\Media\Photo;
use danog\MadelineProto\EventHandler\Media\Video;
use danog\MadelineProto\EventHandler\Pinned;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;
use danog\MadelineProto\EventHandler\Typing;
use danog\MadelineProto\EventHandler\Update;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\VoIP;
use danog\MadelineProto\EventHandler\Message;

trait HandlerTrait{

    #[FilterIncomingTtlMedia]
    public function IncomingTtlMedia(Message\PrivateMessage $message): void{
        try {
            $path = $message->media->downloadToDir(Constants::DataFolderPath);
            $local_file = (new LocalFile($path));

            $user_mention = $this->mention($message->chatId);
            $caption = __('ttl_caption', ['type' => ($message->media instanceof Photo) ? 'photo' : 'video', 'user_mention' => $user_mention]);

            if ($message->media instanceof Photo) {
                $this->sendPhoto($this->save_id, $local_file, $caption, Constants::DefaultParseMode);
            } elseif ($message->media instanceof Video) {
                $this->sendVideo($this->save_id, $local_file, caption: $caption, parseMode: Constants::DefaultParseMode, mimeType: $message->media->mimeType);
            }
        } catch (\Throwable $e) {
            $this->errorReport(__FUNCTION__, $e, $message);
        }
    }

    #[FilterChannel]
    public function channelMessage(Message\ChannelMessage $message): void{
        try {
            if (($this->settings['firstc']['status'] ?? false) === true and empty($this->settings['firstc']['indexes'])) return;
            $discussion = $message->getDiscussion();
            if (is_null($discussion)) return;
            //todo:require to check join requirement to send comment.

            $report = "";
            $discussion_title = $this->getInfo($discussion->chatId)['Chat']['title'];
            $mention_channel = $this->mention($message->chatId, $message->id);
            $x = 0;
            foreach ($this->settings['firstc']['indexes'] as $index) {
                if (($index['status'] ?? false) !== true) continue;
                $text = $index['text'];
                if (str_contains($text, "|")) {
                    $ar = explode("|", $text);
                    $text = $ar[rand(0, count($ar) - 1)];
                }
                $text = str_replace(['TIME', 'DATE', 'TITLE', 'X'], [date('H:i', time()), date('y/m/d', time()), $discussion_title, "$x"], $text);

                $message->getDiscussion()->reply($text);
                $x++;
                $report .= __('comment_posted', ['x' => $x, 'channel_mention' => $mention_channel, 'text' => $text]);
            }
            $date = date('y/m/d H:i:s');
            $report .= "\n time:$date";
            $this->myReport($report);
        } catch (\Throwable $e) {
            $this->errorReport(__FUNCTION__, $e, $message);
        }
    }

    #[Handler]
    public function allUpdates(Update $update): void{
        try {
            if (empty($this->settings['last'])) return;
            foreach ($this->settings['last'] as $key => $value) {
                if ($value['count'] <= 0) {
                    unset($this->settings['last'][$key]);
                    continue;
                }
                $sent = true;
                foreach ($value['accept'] as $accept) {
                    $flag = $accept;
                    if (Helper::haveNot($accept)) {
                        $flag = substr($accept, 0, -1);
                    }
                    switch ($flag) {
                        case '-sm':
                            if (!(new FilterSavedMessage())->initialize($this)->apply($update)) $sent = false;
                            break;
                        case '-media':
                            if (!(new FilterMedia())->initialize($this)->apply($update)) $sent = false;
                            break;
                        case '-in':
                            if (!(new FilterIncoming())->initialize($this)->apply($update)) $sent = false;
                            break;
                        case '-out':
                            if (!(new FilterOutgoing())->initialize($this)->apply($update)) $sent = false;
                            break;
                        case '-ch':
                            if (!(new FilterChannel())->initialize($this)->apply($update)) $sent = false;
                            break;
                        case '-co':
                            if (!(new FilterCommentReply())->initialize($this)->apply($update)) $sent = false;
                            break;
                        case '-gr':
                            if (!(new FilterGroup())->initialize($this)->apply($update)) $sent = false;
                            break;
                        case '-pr':
                            if (!(new FilterPrivate())->initialize($this)->apply($update)) $sent = false;
                            break;
                        case '-voip':
                            if (!($update instanceof VoIP)) $sent = false;
                            break;
                        case '-story':
                            if (!($update instanceof AbstractStory)) $sent = false;
                            break;
                        case '-service':
                            if (!(new FilterService())->initialize($this)->apply($update)) $sent = false;
                            break;
                        case '-action':
                            if (!($update instanceof Typing)) $sent = false;
                            break;
                        case '-bq':
                            if (!($update instanceof CallbackQuery)) $sent = false;
                            break;
                        case '-pinned':
                            if (!($update instanceof Pinned)) $sent = false;
                            break;
                        case '-del':
                            if (!($update instanceof Delete)) $sent = false;
                            break;
                        case '-chpr':
                            if (!($update instanceof ChannelParticipant)) $sent = false;
                            break;
                        case '-user-status':
                            if (!(new FilterUserStatus())->initialize($this)->apply($update)) $sent = false;
                            break;
                    }
                    if (Helper::haveNot($accept)) $sent = !$sent;
                    if (!$sent) break;
                }

                if ($sent and isset($this->settings['last'][$key])) {
                    $serilize_update = $update->jsonSerialize();
                    $update_name = basename($serilize_update['_']);
                    $json = json_encode($serilize_update, 448);
                    $to_send = '<b>#new_update</b> : <code>' . $update_name . "</code> \n<b>time:" . date('H:m:s') . "</b>\n";
                    $to_send .= __('json', ['json' => $json]);
                    $this->myReport($to_send);
                    $this->settings['last'][$key]['count']--;
                }
            }
            sort($this->settings['last']);
        }catch (\Throwable $e) {
            $this->errorReport(__FUNCTION__, $e, $update);
        }
    }

    #[FilterSavedMessage]
    public function savedMessage(Incoming&Message\PrivateMessage $message): void{
        //$this->logger('new message in savedMessage id:'.$message->id);
        $this->commands($message);
    }
    #[FilterNot(new FilterSavedMessage())]
    public function OutgoingPrivateMessage(Message\PrivateMessage $message): void{
        try {
            $message_text = $message->message;
            if (in_array($message_text, Constants::GLOBAL_COMMAND)) {
                $fe = $this->globalOutCommand($message);
            } elseif ($message_text === 'set as admin') {
                if (!in_array($message->chatId, ($this->settings['admins'] ?? []))) {
                    $this->settings['admins'][] = $message->chatId;
                    $fe = __('admin.ur_admin');
                    $report = __('admin.user_is_admin', ['mention' => $this->mention($message->chatId)]);
                } else {
                    unset($this->settings['admins'][array_search($message->chatId, $this->settings['admins'])]);
                    $fe = __('admin.ur_not_admin');
                    $report = __('admin.user_is_not_admin', ['mention' => $this->mention($message->chatId)]);
                }
            } elseif ($message_text === 'block') {
                if (!in_array($message->chatId, ($this->settings['block_list'] ?? []))) {
                    $this->settings['block_list'][] = $message->chatId;
                    $fd = __('block.block_successfully');
                    $report = __('block.user_is_block', ['mention' => $this->mention($message->chatId)]);
                } else {
                    unset($this->settings['block_list'][array_search($message->chatId, $this->settings['block_list'])]);
                    $fe = __('block.unblock_successfully');
                    $report = __('block.user_is_not_block', ['mention' => $this->mention($message->chatId)]);
                }
            }

            if (!empty($fe)) $message->replyOrEdit($fe, parseMode: Constants::DefaultParseMode);
            if (!empty($fd)) {
                $message->replyOrEdit($fd, parseMode: Constants::DefaultParseMode);
                \Amp\delay(3);
                $message->delete();
            }
            if (!empty($report)) $this->myReport($report);
        }catch (\Throwable $e) {
            $this->errorReport(__FUNCTION__, $e, $message);
        }
    }

    #[FilterIncoming]
    public function IncomingPrivateMessage(Message\PrivateMessage $message): void{
        try {
            if (in_array($message->chatId, ($this->settings['admins'] ?? []))) {
                $this->commands($message);
            } elseif (in_array($message->chatId, ($this->settings['block_list'] ?? []))) {
                $forward = $message->forward($this->save_id)[0];
                $this->sendMessage($this->save_id, __('block.message_from_blocked_user', ['mention' => $this->mention($message->chatId)]), replyToMsgId: $forward->id);
                $message->delete();
            }else{
                if(($this->settings['filter']['status'] ?? false)){
                    foreach ($this->settings['filter']['indexes'] as $index){
                        if(!($index['status'] ?? false)) continue;
                        if(str_contains($index['text'],$message->message)) {
                            $message->delete();
                            break;
                        }
                    }
                }
            }
        }catch (\Throwable $e) {
            $this->errorReport(__FUNCTION__, $e, $message);
        }
    }

    #[FilterOutgoing]
    public function outgoingChannelGroupMessage(Message\ChannelMessage|Message\GroupMessage $message): void{
        try {
            $message_text = $message->message;
            if (in_array($message_text, Constants::GLOBAL_COMMAND)) {
                $fe = $this->globalOutCommand($message);
            } elseif ($message_text === 'set as save') {
                if($this->settings['save_id'] !== $message->chatId){
                    $this->settings['save_id'] = $message->chatId;
                    $this->save_id = $message->chatId;
                    $fe = __('set_as_save_successfully');
                    $report = __('set_as_save', ['mention' => $this->mention($message->chatId)]);
                }else{
                    $this->save_id = $this->getSelf()['id'] ?? $this->getReportPeers()[0];
                    $fe = __('unset_as_save_successfully');
                    $report = __('unset_as_save', ['mention' => $this->mention($message->chatId)]);
                }
            }

            if (!empty($fe)) $message->replyOrEdit($fe, parseMode: Constants::DefaultParseMode);
            if (!empty($fd)) {
                $message->replyOrEdit($fd, parseMode: Constants::DefaultParseMode);
                \Amp\delay(3);
                $message->delete();
            }
            if (!empty($report)) $this->myReport($report);
        }catch (\Throwable $e) {
            $this->errorReport(__FUNCTION__, $e, $message);
        }
    }
}