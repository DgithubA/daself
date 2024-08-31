<?php

namespace APP\Traits;

use Amp\ByteStream\Pipe;
use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use APP\Constants\Constants;
use APP\Helpers\Helper;
use danog\MadelineProto\BotApiFileId;
use danog\MadelineProto\EventHandler\Media;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Update;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\RemoteUrl;
use danog\MadelineProto\StreamDuplicator;
use Throwable;
use function Amp\async;

trait HelperTrait{
    private function myReport(string $message) : Message{
        return $this->sendMessage($this->save_id,$message,parseMode: Constants::DefaultParseMode);
    }

    private function mention(int $peerId, int|string $messageId = null, string $bname = null): string{
        try {
            $get = $this->getInfo($peerId);
            if (($get['type'] === "bot" or $get['type'] === "user") and isset($get['User'])) {
                $name = $get['User']['first_name'];
                $username = $get['User']['username'] ?? null;
                $id = $peerId;
                if (!is_null($username)) {
                    $tg = is_null($messageId) ? "https://t.me/$username" : "https://t.me/$username/$messageId";
                } else $tg = is_null($messageId) ? "tg://openmessage?chat_id=$id" : "tg://openmessage?chat_id=$id&message_id=$messageId";
            } elseif (in_array($get['type'], ["channel", "supergroup", "chat"]) and isset($get['Chat'])) {
                $name = $get['Chat']['title'];
                $username = $get['Chat']['username'] ?? null;
                $id = $get['Chat']['id'];
                if (!is_null($username)) {
                    $tg = is_null($messageId) ? "https://t.me/$username" : "https://t.me/$username/$messageId";
                } else $tg = is_null($messageId) ? "tg://openmessage?chat_id=$id" : "https://t.me/c/$id/$messageId";
            }
            $name = !is_null($bname) ? $bname : $name;
            return "[$name]($tg)";
        } catch (\Throwable $e) {
            $this->errorReport(__function__, $e, json_encode($peerId) . "\n" . json_encode($messageId));
        }
        return "";
    }

    public function errorReport(string $function, \Throwable $e,Update|string $update = null){
        try {
            $do_report = true;
            $error_report_setting = ["to_id" => $this->data['error_report']['to_id'] ?? $this->saveid ?? "me",
                "repto_s_m" => $this->data['error_report']['repto_s_m'] ?? true,
                "sendupdate" => $this->data['error_report']['sendupdate'] ?? false,
                "sendtrace" => $this->data['error_report']['sendtrace'] ?? true,
                "anti_spam" => [
                    "status" => $this->data['error_report']['anti_spam']["status"] ?? false,
                    "stopafterspam" => $this->data['error_report']["anti_spam"]['stopafterspam'] ?? true,
                    "stopafterhmspam" => $this->data['error_report']["anti_spam"]['stopafterhmspam'] ?? 5,
                    "secondsdifspam" => $this->data['error_report']["anti_spam"]['secondsdifspam'] ?? 3,
                ]
            ];

            $message = "<b>#error as " . $function . ":</b>\n<b>in line:</b>" . $e->getLine() . "\n<b>Message:</b>" . $e->getMessage() . "\n<b>on File:</b>" . basename($e->getFile());
            //$message = (string)$e;
            if ($error_report_setting['sendtrace']) {
                $message .= PHP_EOL . "Trace:" . PHP_EOL;
                $nowfile = preg_replace("/^(.+)\.php(.*)$/", "$1.php", __FILE__);
                if (basename($e->getFile()) === basename($nowfile)) {
                    $message .= Helper::myTrace($e->getTrace());
                } else {
                    if (method_exists($e, 'getTLTrace')) {
                        $message .= $e->getTLTrace();
                    } elseif (method_exists($e, 'getTraceAsString')) {
                        $message .= $e->getTraceAsString();
                    } else {
                        foreach ($e->getTrace() as $key => $trace) {
                            $message .= '#' . $key . "\t";
                            $message .= json_encode($trace, 448 - 128);
                            $message .= PHP_EOL;
                        }
                    }
                }
            }
            $report_to = $this->getId($error_report_setting['to_id'] ?? $this->saveid ?? 'me');
            $anti_spam_status = $error_report_setting['anti_spam']["status"];

            if ($anti_spam_status) {
                $last_error = $this->settings['last_error'] ?? ['time' => 1, 'spam' => 0];
                if ($last_error['time'] + $error_report_setting["anti_spam"]['secondsdifspam'] >= time()) {
                    $last_error['spam'] += 1;
                    if ($error_report_setting["anti_spam"]['stopafterspam'] and $last_error['spam'] >= $error_report_setting["anti_spam"]['stopafterhmspam']) {
                        $this->sendMessage($report_to, "this\n" . $message . "\n spam and i stopped.");
                        $this->stop();
                    }
                    $do_report = false;
                }
            }
            if (!is_null($update) and $update instanceof Message and $update->message === '/shutdown') $this->stop();

            if ($do_report) {
                if (!is_null($update) and ($error_report_setting['sendupdate'] ?? true)) {
                    $update_string = $update instanceof  Update ? json_encode($update, 448) : $update;
                }

                try {
                    if (isset($update_string)) {
                        $this->sendMessage($report_to, $update_string, parseMode: Constants::DefaultParseMode);
                    }
                    $mid = $this->sendMessage($report_to,$message, parseMode: Constants::DefaultParseMode);
                    if ($report_to != $this->getId('me') and $error_report_setting['repto_s_m']) {
                        $mention = $this->mention($mid->chatId, $mid->id, "check it!");
                        $this->sendMessage($report_to, 'some #error reported.' . $mention, parseMode: Constants::DefaultParseMode);
                    }
                } catch (\Throwable $g) {
                    $put = $message . "\n\n" . $e;
                    if (isset($update_string)) $put .= "\n Update:" . "\n\nfor this i cant send report:" . $g->getMessage();
                    $put .= "\n\nfor this i cant send report:" . $g->getMessage();
                    $file_path = Constants::DataFolderPath . 'error.txt';
                    \Amp\File\write($file_path, $put);
                    $this->sendDocument($report_to,new LocalFile($file_path),caption: "<b>#error as " . $function . "</b>", parseMode: Constants::DefaultParseMode);
                }
            }
            if ($anti_spam_status) {
                $last_error['spam'] = 0;
                $last_error['time'] = time();
                $last_error['message'] = $message;
                $this->settings['last_error'] = $last_error;
            }
        }catch (Throwable $e){
            $this->logger($e);
            $this->stop();
        }
    }

    public function startUsage(Message $message) : string{
        $message_text = $message->message;
        switch ($message_text) {
            case '/start':
                $fe = __('start_message', ['counter' => Helper::formatSeconds((time() - $this->start_time))]);
                break;
            case '/usage':
                $fe = __('memory_usage', ['usage' => round(memory_get_usage() / 1024 / 1024, 2), 'usage_real' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    'peak_usage' => round(memory_get_peak_usage() / 1024 / 1024, 2), 'peak_usage_real' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)]);
                break;
            default:
                $fe = __('bad_command');
        }
        return $fe;
    }
    private function extractMime(bool $secret, Message|Media|LocalFile|RemoteUrl|BotApiFileId|ReadableStream &$file, ?string $fileName, ?callable $callback, ?Cancellation $cancellation): string
    {
        $size = 0;
        $file = $this->getStream($file, $cancellation, $size);
        $p = new Pipe(1024*1024);
        $fileFuture = async(fn () => $this->uploadFromStream(
            new StreamDuplicator($file, $p->getSink()),
            $size,
            'application/octet-stream',
            $fileName ?? '',
            $callback,
            $secret,
            $cancellation
        ));

        $buff = '';
        while (\strlen($buff) < 1024*1024 && null !== $chunk = $p->getSource()->read($cancellation)) {
            $buff .= $chunk;
        }
        $p->getSink()->close();
        $p->getSource()->close();
        unset($p);

        $file = $fileFuture->await();
        return (new finfo())->buffer($buff, FILEINFO_MIME_TYPE);
    }
}