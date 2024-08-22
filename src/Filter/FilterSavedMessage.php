<?php

namespace APP\Filter;

use Attribute;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\Filter\Filter;
use danog\MadelineProto\EventHandler\Filter\FilterOutgoing;
use danog\MadelineProto\EventHandler\Filter\FilterPrivate;
use danog\MadelineProto\EventHandler\Update;

/**
 * Use with #[FilterSavedMessage]
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class FilterSavedMessage extends Filter
{
    private readonly int $self_id;
    public function initialize(EventHandler $API): Filter
    {
        $this->self_id = $API->getSelf()['id'];
        return (new FiltersAnd(new FilterOutgoing(), new FilterPrivate()))->initialize($API);
    }

    public function apply(Update $update): bool
    {
        return ($update->chatId == $this->self_id);
    }
}