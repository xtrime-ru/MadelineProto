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

use danog\MadelineProto\FileRefExtractor\BuildMode\Ast;
use danog\MadelineProto\FileRefExtractor\BuildMode\Flat;
use danog\MadelineProto\FileRefExtractor\FieldExtractorOp;
use danog\MadelineProto\FileRefExtractor\TLContext;
use danog\MadelineProto\FileRefExtractor\TypedOp;

final readonly class ExtractFromParentOp extends FieldExtractorOp
{
    public function normalize(array $stack, string $current, bool $ignoreFlag): ?\danog\MadelineProto\FileRefExtractor\TypedOp
    {
        if ($stack[0][0] !== $this->path[0][0]) {
            return null;
        }
        $new = [];
        $isDifferent = false;
        foreach ($this->path as $i => $part) {
            if ($ignoreFlag && \array_key_exists(2, $part) && \is_int($part[2]) && ($part[2] & CopyOp::FLAG_IF_ABSENT_ABORT)) {
                return null;
            }
            if (isset($part[2]) && $part[2] instanceof TypedOp) {
                $n = $part[2]->normalize($stack, $current, $ignoreFlag);
                if ($n === null) {
                    return null;
                }
                if ($n !== $part[2]) {
                    $isDifferent = true;
                    $part[2] = $n;
                }
            }
            $new[$i] = $part;
        }
        if ($isDifferent) {
            return new CopyOp($new);
        }
        return $this;
    }

    public function build(TLContext $tl): array
    {
        if ($tl->buildMode instanceof Flat) {
        } elseif ($tl->buildMode instanceof Ast) {
            $tl->buildMode->setNeedsParent($this->path[0][0]);
        }
        return [
            '_' => 'typedOp',
            'type' => $this->getType($tl),
            'op' => [
                '_' => 'copyFromParentOp',
                'path' => $this->buildPath($tl),
            ],
        ];
    }
}
