<?php

namespace APP\Filters;

use Attribute;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\EventHandler\AbstractMessage;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\Filter\Filter;
use danog\MadelineProto\EventHandler\Filter\FilterOutgoing;
use danog\MadelineProto\EventHandler\Filter\FilterPrivate;
use danog\MadelineProto\EventHandler\Message\PrivateMessage;
use danog\MadelineProto\EventHandler\Update;
use danog\MadelineProto\VoIP;
use danog\MadelineProto\VoIP\CallState;

/**
 * Use with #[FilterSavedMessage]
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class FilterSavedMessage extends Filter
{
    private readonly int $self_id;
    public function initialize(EventHandler $API): Filter
    {
        $this->self_id = $API->getId('me');
        return $this;
        //return (new FiltersAnd(new FilterOutgoing(), new FilterPrivate()))->initialize($API);
    }

    public function apply(Update $update): bool
    {
        $outgoing = ($update instanceof AbstractMessage && $update->out)
            || ($update instanceof VoIP && $update->getCallState() === CallState::REQUESTED);
        $private = $update instanceof PrivateMessage;
        return $outgoing and $private and ($update->chatId === $this->self_id);
    }
}
