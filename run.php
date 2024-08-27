<?php

require 'config.php';

APP\botHandler::startAndLoop($session_file, $settings);