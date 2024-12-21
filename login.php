<?php

use danog\MadelineProto\API;

require __DIR__ . '/config.php';

try {
    $api = new API($session_file, $settings);
} catch (\Exception $e) {
    echo $e;
    exit(1);
}

if ($api->getAuthorization() !== API::LOGGED_IN) {
    $api->start();
}

if (!$api->isSelfUser()) {
    echo "Please login as a user.\n";
    $api->logout();
    // TODO: recursively remove folder tree.
    rmdir($session_dir);
} else {
    echo "Successfully logged-in as a user.\n";
}
