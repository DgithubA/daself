<?php

return '../config.php';

use danog\MadelineProto\API;
$API = new API($session_file,$settings);
$API->start();
$id = (string) $API->getSelf()['id'];
header("Content-length: ".\strlen($id));
echo $id;