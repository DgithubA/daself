<?php

namespace App;

use Amp\Mysql\MysqlResult;
use Amp\Postgres\PostgresResult;

use danog\MadelineProto\EventHandler\Media\Gif;
use danog\MadelineProto\EventHandler\Media\Audio;
use danog\MadelineProto\EventHandler\Media\Photo;
use danog\MadelineProto\EventHandler\Media\Video;
use danog\MadelineProto\EventHandler\Media\Document;

class Helpers
{
    public static function queryResult2String($result): string
    {
        $text = '';
        if ($result instanceof MysqlResult || $result instanceof PostgresResult) {
            $rows = $result->fetchRow();
            $text .= "-------\n";
            foreach ($rows as $row) {
                $text .= "$row\n";
            }
            $text .= '-------';
        } elseif (is_array($result)) {
            $text .= '[';
            foreach ($result as $row) {
                $text .= (string)$row;
            }
            $text .= ']';
        } elseif ($result === null) {
            $text .= "empty result.";
        } else {
            $text .= (string)$result;
        }
        return $text;
    }

    public static function formatSeconds(int $sec, string $format = "%02d:%02d:%02d:%02d"): string
    {
        $days = floor($sec / 86400);
        $sec -= $days * 86400;

        $hours = floor($sec / 3600);
        $sec -= $hours * 3600;

        $minutes = floor($sec / 60);
        $sec -= $minutes * 60;

        $seconds = $sec;
        return sprintf($format, $days, $hours, $minutes, $seconds);
    }

    public static function justifyFlags(array $flags, int $peerLine = 2, string $sperator = '   '): string
    {
        $text = '';
        foreach ($flags as $flag => $about) {
            $text .= $sperator . $flag;
        }
        return $text;
    }

    public static function haveNot(string $text, string $not = '!')
    {
        return str_ends_with($text, $not);
    }

    public static function myTrace(array $trace, string $files_q = null): string
    {
        $str = "";
        foreach ($trace as $k => $frame) {
            if ($files_q != null and isset($frame['file']) and !str_contains($frame['file'], $files_q))
                continue;

            $args_str = !empty($frame['args']) ? implode(',', $frame['args']) : '';
            $str .= "#$k " .
                ("\t" . basename($frame['file'])) . ('(' . $frame['line'] . ')') . "\n\t" .
                (isset($frame['function']) ? "" . $frame['function'] . '(' . $args_str . ')' : '');
            $str .= PHP_EOL;
        }
        return $str;
    }

    public static function humanFileSize($size, $unit = ""): string
    {
        if ((!$unit && $size >= 1 << 30) || $unit === "GB")
            return number_format($size / (1 << 30), 2) . "GB";
        if ((!$unit && $size >= 1 << 20) || $unit === "MB")
            return number_format($size / (1 << 20), 2) . "MB";
        if ((!$unit && $size >= 1 << 10) || $unit === "KB")
            return number_format($size / (1 << 10), 2) . "KB";
        return number_format($size) . " bytes";
    }

    public static function myJson(string|array $data, ?int $flags = null): string
    {
        $def_flags = JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_THROW_ON_ERROR;
        if (is_array($data)) {
            $data = json_encode($data, $flags ?? $def_flags);
        }
        if (json_validate($data)) {
            return __('json', ['json' => $data]);
        }
        return __('code', ['code' => $data]);
    }

    public static function secondsToNext(int $sec = 60): int
    {
        $now = time();
        $next_minute = ceil($now / 60) * 60;
        $diff = $next_minute - $now - (60 - $sec);
        $diff <= 0 && $diff += 60;
        return $diff;
    }

    public static function mime2type(string $mime_type): string
    {
        switch (strtolower($mime_type)) {
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
        return $type;
    }

    /** Generate new unique id.
     *
     * @param callable(string $uniq_id): bool $is_uniq Function to check if the generated ID is unique. (return `true` if unique, `false` otherwise.)
     * @param int $length The length of the ID.
     * @return string The unique ID.
     */
    public static function newItemWithRandomId(callable $is_uniq, int $length = 5): string
    {
        $all_chars = array_merge(range('a', 'z'), range('A', 'Z'), range(0, 9));
        do {
            $uniq_id = "";
            for ($i = 0; $i < $length; $i++) {
                $uniq_id .= $all_chars[array_rand($all_chars)];
            }
        } while (!$is_uniq($uniq_id));
        return $uniq_id;
    }
}
