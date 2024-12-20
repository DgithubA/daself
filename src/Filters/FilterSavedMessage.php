<?php

namespace App\Filters;

use Attribute;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\EventHandler\Update;
use danog\MadelineProto\EventHandler\Filter\Filter;
use danog\MadelineProto\EventHandler\Message\PrivateMessage;

#[Attribute(Attribute::TARGET_METHOD)]
final class FilterSavedMessage extends Filter
{
    private readonly int $selfId;

    public function initialize(EventHandler $API): Filter
    {
        $this->selfId = $API->getId('me');
        return $this;
    }

    public function apply(Update $update): bool
    {
        return (
            $update instanceof PrivateMessage &&
            $update->out &&
            $update->chatId === $this->selfId
        );
    }
}
