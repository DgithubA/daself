<?php

namespace App;

use danog\MadelineProto\ParseMode;

final class Constants
{
    public const ADMIN = "@me";
    public const DefaultParseMode = ParseMode::HTML;
    public const GLOBAL_COMMAND = ['/start', '/usage', '/getmessage'];
    public const LAST_FLAG = [
        '-all'         => 'all update.',
        '-sm'          => 'saved message.',
        '-media'       => 'media message.',
        '-in'          => 'incoming message.',
        '-out'         => 'outgoing message.',
        '-ch'          => 'channel message.',
        '-co'          => 'comment reply.',
        '-gr'          => 'group message.',
        '-pr'          => 'private message.',
        '-voip'        => 'voip.',
        '-story'       => 'story update.',
        '-service'     => 'service update.',
        '-action'      => 'user&group user action[typing,send media,etc].',
        '-bq'          => 'button query update.',
        '-pinned'      => 'pinned message.',
        '-del'         => 'delete update.',
        '-chpr'        => 'A participant has left, joined, was banned or admined in a channel or supergroup.',
        '-user-status' => 'user offline/online status.',
    ];

    public const DefaultLocal = 'en';
    public const LocaleFolderPath = __DIR__ . '/Localization/';
    public const DataFolderPath = __DIR__ . '/../data/';
}
