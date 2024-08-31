<?php


namespace APP\Filters;

use Attribute;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\Filter\Filter;
use danog\MadelineProto\EventHandler\Filter\FilterIncoming;
use danog\MadelineProto\EventHandler\Filter\FilterMedia;
use danog\MadelineProto\EventHandler\Filter\FilterPrivate;
use danog\MadelineProto\EventHandler\Media\Photo;
use danog\MadelineProto\EventHandler\Media\Video;
use danog\MadelineProto\EventHandler\Update;

/**
 * Use with #[FilterIncomingTtlMedia]
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class FilterIncomingTtlMedia extends Filter{

    public function initialize(EventHandler $API): Filter{
        return (new FiltersAnd(new FilterMedia(), new FilterIncoming(),new FilterPrivate()))->initialize($API);
    }

    public function apply(Update $update): bool{
        return (($update->media instanceof Photo || $update->media instanceof Video) and $update->media->ttl !== null);
    }
}
