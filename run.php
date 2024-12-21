<?php

require __DIR__ . '/config.php';

App\BotHandler::startAndLoop($session_file, $settings);
