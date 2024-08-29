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

    public static function myTrace(array $trace):string{
        $str = "";
        $nowfile = preg_replace("/^(.+)\.php(.*)$/", "$1.php", __FILE__);
        foreach ($trace as $k => $frame) {
            if(isset($frame['file']) and basename($frame['file']) === basename($nowfile)){
                $str .= ('`#'.$k.'` ').
                    (isset($frame['function']) ? '**'.$frame['function'].'**' :'') .
                    ('('.$frame['line'].')').
                    (isset($frame['args']) ? '('.json_encode($frame['args']).')' : '');
            }
            $str .= PHP_EOL;
        }
        return $str;
    }
}