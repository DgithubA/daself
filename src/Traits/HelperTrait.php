<?php

namespace App\Traits;

use Throwable;
use App\Helpers;
use App\Constants;
use Amp\Http\Client\Request;
use Amp\ByteStream\ReadableBuffer;

use danog\MadelineProto\Exception;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\RemoteUrl;
use danog\MadelineProto\EventHandler\Media;
use danog\MadelineProto\EventHandler\Update;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Media\Gif;
use danog\MadelineProto\EventHandler\Media\Audio;
use danog\MadelineProto\EventHandler\Media\Photo;
use danog\MadelineProto\EventHandler\Media\Video;
use danog\MadelineProto\EventHandler\Media\Document;
use danog\MadelineProto\TL\Conversion\Extension;

trait HelperTrait
{
    private function myReport(string $message, ?int $replyToMsgId = null): Message
    {
        return $this->sendMessage($this->save_id, $message, parseMode: Constants::DefaultParseMode, replyToMsgId: $replyToMsgId, noWebpage: true);
    }

    private function mention(int $peerId, int|string $messageId = null, string $bname = null): string
    {
        try {
            $info = $this->getInfo($peerId);
            if (($info['type'] === "bot" || $info['type'] === "user") && isset($info['User'])) {
                $name = $info['User']['first_name'];
                $username = $info['User']['username'] ?? null;
                $id = $peerId;
                $tg = $username !== null
                    ? ($messageId === null ? "https://t.me/$username" : "https://t.me/$username/$messageId")
                    : ($messageId === null ? "tg://openmessage?chat_id=$id" : "tg://openmessage?chat_id=$id&message_id=$messageId");
            } elseif (in_array($info['type'], ["channel", "supergroup", "chat"]) and isset($info['Chat'])) {
                $name = $info['Chat']['title'];
                $username = $info['Chat']['username'] ?? null;
                $id = $info['Chat']['id'];
                $tg = $username !== null
                    ? ($messageId === null ? "https://t.me/$username" : "https://t.me/$username/$messageId")
                    : ($messageId === null ? "tg://openmessage?chat_id=$id" : "https://t.me/c/$id/$messageId");
            }
            if ($bname !== null) {
                $name = $bname;
            }
            return __('mention', ['name' => $name, 'url' => $tg]);
        } catch (Throwable $e) {
            $this->errorReport(__FUNCTION__, $e, json_encode($peerId) . "\n" . json_encode($messageId));
        }
        return "";
    }

    private function errorReport(string $function, Throwable $e, Update|string $update = null)
    {
        try {
            $do_report = true;
            $error_report_setting = [
                "to_id"      => $this->data['error_report']['to_id'] ?? $this->save_id ?? "me",
                "repto_s_m"  => $this->data['error_report']['repto_s_m'] ?? true,
                "sendupdate" => $this->data['error_report']['sendupdate'] ?? false,
                "sendtrace"  => $this->data['error_report']['sendtrace'] ?? true,
                "anti_spam"  => [
                    "status"          => $this->data['error_report']['anti_spam']["status"] ?? false,
                    "stopafterspam"   => $this->data['error_report']["anti_spam"]['stopafterspam'] ?? true,
                    "stopafterhmspam" => $this->data['error_report']["anti_spam"]['stopafterhmspam'] ?? 5,
                    "secondsdifspam"  => $this->data['error_report']["anti_spam"]['secondsdifspam'] ?? 3,
                ],
            ];
            $message = __('error_reporting_message', ['function' => $function, 'line' => $e->getLine(), 'message' => $e->getMessage(), 'file' => $e->getFile()]);
            //$message = (string)$e;
            if ($error_report_setting['sendtrace']) {
                $message .= PHP_EOL . "<b>Trace:</b>" . PHP_EOL;
                $is_bot_source = str_contains($e->getFile(), '\App\\');
                if ($is_bot_source) {
                    $message .= Helpers::myTrace($e->getTrace(), '\App\\');
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
                        $this->sendMessage($report_to, "this\n$message\n spam and i stopped.");
                        $this->stop();
                    }
                    $do_report = false;
                }
            }
            if (!is_null($update) and $update instanceof Message and $update->message === '/shutdown')
                $this->stop();

            if ($do_report) {
                if (!is_null($update) and ($error_report_setting['sendupdate'] ?? true)) {
                    $update_string = $update instanceof Update ? json_encode($update, 448) : $update;
                }

                try {
                    if (isset($update_string))
                        $this->sendMessage($report_to, Helpers::myJson($update_string), parseMode: Constants::DefaultParseMode);
                    $mid = $this->sendMessage($report_to, $message, parseMode: Constants::DefaultParseMode);
                    if ($report_to != $this->getId('me') and $error_report_setting['repto_s_m']) {
                        $mention = $this->mention($mid->chatId, $mid->id, "check it!");
                        $this->sendMessage($report_to, "some #error reported.$mention", parseMode: Constants::DefaultParseMode);
                    }
                } catch (Throwable $g) {
                    $put = "$message\n\n$e";
                    if (isset($update_string))
                        $put .= "\n Update:" . "\n\nfor this i cant send report:" . $g->getMessage();
                    $put .= "\n\nfor this i cant send report:" . $g->getMessage();
                    $this->sendDocument(peer: $report_to, file: new ReadableBuffer($put), caption: "<b>#error as $function</b>", parseMode: Constants::DefaultParseMode);
                }
            }
            if ($anti_spam_status) {
                $last_error['spam'] = 0;
                $last_error['time'] = time();
                $last_error['message'] = $message;
                $this->settings['last_error'] = $last_error;
            }
        } catch (Throwable $e) {
            $this->logger($e);
            $this->stop();
        }
    }

    private function reUploadMedia(int|string $peer, array|Media $media, int $replyToMsgId = null, ?string $caption = '', ?callable $cb = null): void
    {
        $path = $this->downloadToDir($media, Constants::DataFolderPath);
        $file = new LocalFile($path);
        $this->smartSendMedia($peer, $file, $replyToMsgId, $caption, cb: $cb);
        \Amp\File\deleteFile($path);
    }
    private function extractFileMime(LocalFile|RemoteUrl $file): string|null
    {
        $cancellation = $this->cancellation->getCancellation();
        if ($file instanceof RemoteUrl) {
            $url = $file->url;
            $request = new Request($url);
            $request->setTransferTimeout(INF);
            $request->setBodySizeLimit(512 * 1024 * 8000);
            $response = $this->wrapper->getAPI()->datacenter->getHTTPClient()->request($request, $cancellation);
            if (($status = $response->getStatus()) !== 200) {
                throw new Exception("Wrong status code: {$status} " . $response->getReason());
            }
            return trim(explode(';', $response->getHeader('content-type') ?? 'application/octet-stream')[0]);
            //$mime = Extension::getMimeFromBuffer($response->getBody());
        } elseif ($file instanceof LocalFile) {
            return Extension::getMimeFromFile($file->file);
        }
        return null;
    }
    private function smartSendMedia(int|string $peer, string|RemoteUrl|LocalFile $file, int $replyToMsgId = null, ?string $caption = '', string $file_name = null, callable $cb = null): void
    {
        if (is_string($file)) {
            $file = filter_var($file, FILTER_VALIDATE_URL) ? new RemoteUrl($file) : new LocalFile($file);
        }

        $mime_type = $this->extractFileMime($file);
        $type = Helpers::mime2type($mime_type);
        if (!empty($file_name) and !preg_match("~^(.+)\_(\d+)\.(\w{2,5})$~", $file_name)) {//filename not have file extension
            $file_name .= '.' . Extension::getExtensionFromMime($mime_type);
        }
        $this->sendMediaByType($type, $peer, $file, $caption, $replyToMsgId, $file_name, $cb);
    }
    private function sendMediaByType(string $type, int|string $peer, \danog\MadelineProto\EventHandler\Message|\danog\MadelineProto\EventHandler\Media|\danog\MadelineProto\LocalFile|\danog\MadelineProto\RemoteUrl|\danog\MadelineProto\BotApiFileId|\Amp\ByteStream\ReadableStream $file, string $caption = '', int $replyToMsgId = null, string $file_name = null, callable $cb = null): void
    {
        $cancellation = $this->cancellation->getCancellation();
        switch ($type) {
            case Document::class:
                $this->sendDocument($peer, $file, caption: $caption, parseMode: Constants::DefaultParseMode, callback: $cb, fileName: $file_name, replyToMsgId: $replyToMsgId, cancellation: $cancellation);
                break;
            case Video::class:
                $this->sendVideo($peer, $file, caption: $caption, parseMode: Constants::DefaultParseMode, callback: $cb, fileName: $file_name, replyToMsgId: $replyToMsgId, cancellation: $cancellation);
                break;
            case Photo::class:
                $this->sendPhoto($peer, $file, caption: $caption, parseMode: Constants::DefaultParseMode, callback: $cb, fileName: $file_name, replyToMsgId: $replyToMsgId, cancellation: $cancellation);
                break;
            case Audio::class:
                $this->sendAudio($peer, $file, caption: $caption, parseMode: Constants::DefaultParseMode, callback: $cb, fileName: $file_name, replyToMsgId: $replyToMsgId, cancellation: $cancellation);
                break;
            case Gif::class:
                $this->sendGif($peer, $file, caption: $caption, parseMode: Constants::DefaultParseMode, callback: $cb, fileName: $file_name, replyToMsgId: $replyToMsgId, cancellation: $cancellation);
                break;
        }
    }

    /**
     * @param Media $media
     * @param callable(LocalFile $local_file): void $after_download
     * @return void
     */
    private function downloadAndReUpload(Media $media, callable $after_download): void
    {
        $output_file_name = $media->downloadToDir(Constants::DataFolderPath);
        $local_file = new LocalFile($output_file_name);
        $after_download($local_file);
        \Amp\File\deleteFile($output_file_name);
    }
}
