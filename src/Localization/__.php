<?php

use App\Localization\Lang;

function __($key, $replace = []): string
{
    return Lang::getInstance()->get($key, $replace);
}
