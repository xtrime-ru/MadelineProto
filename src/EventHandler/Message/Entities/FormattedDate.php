<?php

namespace danog\MadelineProto\EventHandler\Message\Entities;

final class FormattedDate extends MessageEntity
{
    public function __construct(
        /** Offset of message entity within message (in UTF-16 code units) */
        public readonly int $offset,

        /** Length of message entity within message (in UTF-16 code units) */
        public readonly int $length,

        public readonly int $date,
    ) {
    }

    #[\Override]
    public function toBotAPI(): array
    {
        return ['type' => 'date_time', 'offset' => $this->offset, 'length' => $this->length, 'unix_time' => $this->date];
    }
    #[\Override]
    public function toMTProto(): array
    {
        return ['_' => 'messageEntityFormattedDate', 'offset' => $this->offset, 'length' => $this->length, 'date' => $this->date];
    }
}