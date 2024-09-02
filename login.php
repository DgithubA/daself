<?php


require 'config.php';

use danog\MadelineProto\API;

try {
    $api = new API($session_file, $settings);
} catch (\Exception $e) {
    echo $e;
    exit(1);
}

if ($api->getAuthorization() !== API::LOGGED_IN) {
    $get_self = $api->start();
}

if (!$api->isSelfUser()) {
    echo "Please login as user.";
    $api->logout();
    rmdir($session_dir);
} else echo "Successfully login in as user.";
