<?php

namespace App\Filters;

use Attribute;

use danog\MadelineProto\EventHandler\Update;
use danog\MadelineProto\EventHandler\Media\Photo;
use danog\MadelineProto\EventHandler\Media\Video;
use danog\MadelineProto\EventHandler\Filter\Filter;
use danog\MadelineProto\EventHandler\Message\PrivateMessage;

#[Attribute(Attribute::TARGET_METHOD)]
final class FilterIncomingTtlMedia extends Filter
{
    public function apply(Update $update): bool
    {
        return (
            $update instanceof PrivateMessage &&
            !$update->out &&
            $update->media !== null &&
            ($update->media instanceof Photo || $update->media instanceof Video) &&
            $update->media->ttl !== null
        );
    }
}
