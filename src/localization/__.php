<?php

use APP\localization\localization;

function __($key, $replace = []) :string{
    global $localization;
    return $localization->get($key, $replace);
}

$localization = new Localization(\APP\Constants\Constants::DefaultLocal);