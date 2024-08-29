<?php


return [
    'json'=>'<pre language="json"><code>:json</code></pre>',
    'start_message'=>"the bot uptime is:\n:counter",
    'memory_usage'=>"Memory now Usage : :usage <b>/</b>:usage_real <b>MB</b>\nMemory peak Usage :  :peak_usage <b>/</b>:peak_usage_real  <b>MB</b>",
    'restarting'=>"restarting...",
    'shutdown'=>"goodbye :)",
    'bad_command'=>'bad command.!',
    'reset_successfully'=>'reset successfully!',
    'bad_command_see_help'=>'bad command see /help',
    'running'=>'running...',
    'ok_set'=>'ok set!',
    'flag_not_exist'=>'flag <b>:flag</b> not exist!',
    'comment_posted'=>'<b>comment :x successfully posted in</b> :mention_channel \n <b>text:</b>":text" \n_-_-_-_-_-_-_-_-_-_\n',
    'ttl_caption'=>'self-Destructing :type from :user_mention',
    'admin' => [
        'ur_admin'=> 'You are an admin. Try: /start',
        'ur_not_admin'=>'You are no longer an admin.',
        'user_is_admin' => 'user :mention set as admin.',
        'user_is_not_admin' => 'user :mention is no longer an admin.',
    ],
    'block'=>[
        'block_successfully'=>'blocking successfully.',
        'user_block'=>'user :mention blocked successfully.',
        'unblock_successfully' => 'unblocked successfully.',
        'user_is_not_block' => 'user :mention is no longer a block.',
        'message_from_blocked_user'=> 'message from :mention blocked user.',
    ],
    'set_as_save_successfully'=>'set successfully!',
    'set_as_save'=> 'chat :mention set as save.',
    'commands' => [
        'add_successfully'=>'add successfully',
        'bad_input_use_like' => 'incorrect command input. use like : <code>:like</code>',
        'not_exist'=> 'not exist any element with key :key',
        'remove_successfully'=>'remove successfully.',
        'change_successfully'=>'change successfully',
        'help'=>':command (new|add|rm|remove|ls|list|off|on|help) [INDEX|TEXT]'
    ],
    'status'=> [
        'on' => 'status: on',
        'off' => 'status: off',
    ],
    'is_empty'=>'is empty'

];