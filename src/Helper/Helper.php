<?php

namespace APP\Helper;


use Amp\Mysql\MysqlResult;
use Amp\Postgres\PostgresResult;
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
}