<?php


return [
    'json' => '<pre language="json"><code>:json</code></pre>',
    'code' => '<code>:code</code>',
    'bold' => '<b>:bold</b>',
    'start_message' => "the bot uptime is:\n:counter",
    'mention' => '<a href=":url">:name</a>',
    'memory_usage' => "Memory now Usage : :usage <b>/</b>:real_usage <b>MB</b>\nMemory peak Usage :  :peak_usage <b>/</b>:real_peak_usage  <b>MB</b>",
    'restarting' => "restarting...",
    'shutdown' => "goodbye :)",
    'bad_command' => 'bad command.!',
    'reset_successfully' => 'reset successfully!',
    'bad_command_see_help' => 'bad command see /help',
    'ok_set' => 'ok set!',
    'is_empty' => 'is empty',
    'canceled' => 'canceled.!',
    'replayed_no_media' => 'The replayed message has no media.',
    'flag_not_exist' => 'flag <b>:flag</b> not exist!',
    'ttl_caption' => 'self-Destructing :type from :user_mention',
    'error_reporting_message' => "<b>#error as :function:</b>\n<b>in line:</b> :line\n<b>Message:</b>:message\n<b>on File:</b> :file",
    'status' => [
        'on' => 'status: on',
        'off' => 'status: off',
    ],
    'firstc' => [
        'comment_posted' => "<b>comment :x successfully posted in</b> :channel_mention \n <b>text:</b>\":text\" \n_-_-_-_-_-_-_-_-_-_\n",
        'comment_required_join_chat' => 'i\'ll try to send comment at :channel_mention, but I failed because of required membership. ',
    ],
    'filter'=>[
        'report_message_from' => 'filter message from :from at :date :time',
    ],
    'admin' => [
        'ur_admin' => "You are an admin.\nTry: <code>/start</code>",
        'ur_not_admin' => 'You are no longer an admin.',
        'user_is_admin' => 'user :mention set as admin.',
        'user_is_not_admin' => 'user :mention is no longer an admin.',
    ],
    'block' => [
        'block_successfully' => 'blocking successfully.',
        'user_block' => 'user :mention blocked successfully.',
        'unblock_successfully' => 'unblocked successfully.',
        'user_is_not_block' => 'user :mention is no longer a block.',
        'message_from_blocked_user' => 'message from :mention blocked user at :date :time',
    ],
    'set_as_save' => [
        'set_successfully' => 'set successfully!',
        'set' => 'chat :mention set as save.',
        'unset_successfully' => 'unset successfully!',
        'unset' => 'chat :mention unset as save. default save chat (savedMessage) was set.',
    ],
    'commands' => [
        'add_successfully' => 'add successfully',
        'bad_input_use_like' => 'incorrect command input. use like : <code>:like</code>',
        'not_exist' => 'not exist any element with key :key',
        'remove_successfully' => 'remove successfully.',
        'change_successfully' => 'change successfully',
        'help' => ':command (new|add|rm|remove|ls|list|off|on|help) [INDEX|TEXT]'
    ],
    'dlUp' => [
        'downloading' => 'downloading...',
        'uploading' => 'uploading...',
        'file_is_too_small' => 'file is too small. file size: <b>:size</b> | minimum <b>:minimumSize</b>',
        'download_script_url_wrong' => 'please set DOWNLOAD_SCRIPT_URL to use download link feature.',
        'url_not_found' => 'url not found. use /upload {URL} [FILENAME] or reply to messages contain url of file',
        'upload_percent' => 'uploading <b>%:percent</b>',
        'upload_successfully' => "upload successfully.👇👇\nTotal upload time: <b>:time</b>\nTotal upload speed: <b>:speed</b> mbps",
        'download_link' => "<a href=':link'>download link:</a>\n <code>:link</code>",
    ],
    'runner' => [
        'running' => 'running...',
        'results' => "<b>Results :</b>\n :data",
        'without_database_connection' => 'without database connection.',
        'code_not_found' => "code not found.\n use /php [CODE] or reply to messages contain php code pre",
    ],
    'getmessagelink' => [
        'bad_command' => "bad command. reply to message contain url or /getmessagelink [link]\nurl sample: https://t.me/telegram/2|https://t.me/example/5-10",
        'in_progress' => 'get Link Message...',
        'channel_is_private' => 'channel is private. join with join link.',
    ],
    'story' => [
        'saving' => 'saving story...',
        'saving_story_tag' => "saving :tag story...",
        'no_story_exist' => 'There are no stories from :tag',
        'get_story_success' => 'save story from :tag successfully.👇👇',
        'cant_find_user_from_message' => 'the account was hidden by user.',
    ],



    'convert'=>[
        'converting' => 'start converting...🔁',
        'not_possible'=> 'convert from :from to :to is not possible.',
        'success'=>'converted successfully.👇👇',
    ],
    'change'=>[
        'changing' => 'changing...🔁',
        'not_possible'=> 'changing `:key` for Media type `:type` is not possible.',
        'success'=>'changed successfully.👇👇',
        'image_size_bad'=>'size write be like X*Y(ex. 800*800)',
        'picture_format_not_support'=>'picture format not support.',
        'gd_not_supported'=>'gd_not_supported'
    ]
];