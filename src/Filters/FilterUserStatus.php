<?php

namespace App\Filters;

use Attribute;
use danog\MadelineProto\EventHandler\Update;
use danog\MadelineProto\EventHandler\Filter\Filter;
use danog\MadelineProto\EventHandler\User\Status\Offline;
use danog\MadelineProto\EventHandler\User\Status\Online;

#[Attribute(Attribute::TARGET_METHOD)]
final class FilterUserStatus extends Filter
{
    public function apply(Update $update): bool
    {
        return $update instanceof Offline || $update instanceof Online;
    }
}
