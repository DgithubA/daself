<?php

namespace APP\Helpers;


use Amp\ByteStream\Pipe;
use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Mysql\MysqlResult;
use Amp\Postgres\PostgresResult;
use danog\MadelineProto\BotApiFileId;
use danog\MadelineProto\EventHandler\Media;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\RemoteUrl;
use danog\MadelineProto\StreamDuplicator;
use function Amp\async;

class Helper{

    public static function queryResult2String($result):string{
        $text = '';
        if($result instanceof MysqlResult or $result instanceof PostgresResult){
            $rows = $result->fetchRow();
            $text .= "-------\n";
            foreach($rows as $row){
                $text .= "$row\n";
            }
            $text .= '-------';
        }else{
            if(is_array($result)){
                $text .= '[';
                foreach ($result as $row){
                    $text .= "$row";
                }
                $text .= ']';
            }elseif (is_null($result)){
                $text .= "empty result.";
            }else $text .= "$result";
        }
        return $text;
    }

    public static function formatSeconds(int $sec, string $format = "%02d:%02d:%02d:%02d"): string
    {
        $days = floor($sec / 86400);
        $sec -= $days * (24 * 60 * 60);//100
        $hours = ($sec / 3600);
        $hours = floor($hours);
        $sec -= $hours * (60 * 60);//100
        $minutes = ($sec / 60);//1.223
        $minutes = floor($minutes);//1
        $sec -= $minutes * (60);//40
        $seconds = $sec;
        return sprintf($format, $days, $hours, $minutes, $seconds);
    }
    public static function justifyFlags(array $flags, int $peerLine = 2,string $sperator = '   '): string{
        $text  = '';
        foreach ($flags as $flag => $about) {
            $text .= $sperator . $flag;
        }
        return $text;
    }

    public static function haveNot(string $text,string $not = '!'){
        return str_ends_with($text,$not);
    }

    public static function myTrace(array $trace,string $files_q = null):string{
        $str = "";
        foreach ($trace as $k => $frame) {
            if($files_q != null and isset($frame['file']) and !str_contains($frame['file'],$files_q)) continue;

            $args_str = !empty($frame['args']) ? implode(',',$frame['args']) : '';
            $str .= ('#'.$k.' ').
                ("\t".basename($frame['file'])).('('.$frame['line'].')')."\n\t".
                (isset($frame['function']) ? "".$frame['function'].'('.$args_str.')' :'');
            $str .= PHP_EOL;
        }
        return $str;
    }

    public static function humanFileSize($size,$unit="") :string{
        if( (!$unit && $size >= 1<<30) || $unit == "GB")
            return number_format($size/(1<<30),2)."GB";
        if( (!$unit && $size >= 1<<20) || $unit == "MB")
            return number_format($size/(1<<20),2)."MB";
        if( (!$unit && $size >= 1<<10) || $unit == "KB")
            return number_format($size/(1<<10),2)."KB";
        return number_format($size)." bytes";
    }
    public static function myJson(string $text):string{
        if(json_validate($text)){
            return __('json',['json'=>$text]);
        }
        return __('code',['code'=>$text]);
    }
}