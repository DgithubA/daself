<?php

use APP\localization\localization;

function __($key, $replace = []) :string{
    return localization::getInstance()->get($key, $replace);
}