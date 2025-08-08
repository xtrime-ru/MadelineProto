<?php

declare(strict_types=1);

/**
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2025 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

namespace danog\MadelineProto\FileRefExtractor\Ops;

use danog\MadelineProto\FileRefExtractor\ActionOp;
use danog\MadelineProto\FileRefExtractor\BuildMode\Ast;
use danog\MadelineProto\FileRefExtractor\FieldExtractorOp;
use danog\MadelineProto\FileRefExtractor\TLContext;
use Webmozart\Assert\Assert;

final readonly class GetMessageOp implements ActionOp
{
    public function __construct(
        private readonly FieldExtractorOp $peer,
        private readonly FieldExtractorOp $id,
        private readonly ?FieldExtractorOp $fromScheduled,
    ) {
    }
    public function normalize(array $stack, string $current, bool $ignoreFlag): ?ActionOp
    {
        $peer = $this->peer->normalize($stack, $current, $ignoreFlag);
        if ($peer === null) {
            return null;
        }
        $id = $this->id->normalize($stack, $current, $ignoreFlag);
        if ($id === null) {
            return null;
        }
        $fromScheduled = $this->fromScheduled?->normalize($stack, $current, $ignoreFlag);
        if ($fromScheduled === null && $this->fromScheduled !== null) {
            return null;
        }
        if ($peer !== $this->peer || $id !== $this->id || $fromScheduled !== $this->fromScheduled) {
            return new self($peer, $id, $fromScheduled);
        }
        return $this;
    }
    public function getType(TLContext $tl): string
    {
        return 'messages.Messages';
    }

    public function build(TLContext $tl): void
    {
        Assert::eq($this->peer->getType($tl), 'Peer');
        Assert::eq($this->id->getType($tl), 'int');
        if ($this->fromScheduled !== null) {
            Assert::eq($this->fromScheduled->getType($tl), 'true');
        }
        $extra = [];
        if ($this->fromScheduled !== null) {
            $extra['from_scheduled'] = $this->fromScheduled->build($tl);
        }
        if ($tl->buildMode instanceof Ast) {
            $tl->buildMode->addNode($tl, [
                '_' => 'getMessageOp',
                'peer' => $this->peer->build($tl),
                'id' => $this->id->build($tl),
                ...$extra,
            ]);
        }
    }
}
