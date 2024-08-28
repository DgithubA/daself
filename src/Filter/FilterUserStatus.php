<?php


namespace APP\Filter;

use Attribute;
use danog\MadelineProto\EventHandler\Filter\Filter;
use danog\MadelineProto\EventHandler\Update;
use danog\MadelineProto\EventHandler\User\Status\Offline;
use danog\MadelineProto\EventHandler\User\Status\Online;

/**
 * Use with #[FilterSavedMessage]
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class FilterUserStatus extends Filter
{

    public function apply(Update $update): bool
    {
        if($update instanceof Offline || $update instanceof Online) return true;
        return false;
    }
}
