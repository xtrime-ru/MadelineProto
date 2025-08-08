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

use danog\MadelineProto\FileRefExtractor\FieldExtractorOp;
use danog\MadelineProto\FileRefExtractor\TLContext;
use danog\MadelineProto\FileRefExtractor\TypedOp;
use Webmozart\Assert\Assert;

final readonly class CopyOp extends FieldExtractorOp
{
    public function normalize(array $stack, string $current, bool $ignoreFlag): ?\danog\MadelineProto\FileRefExtractor\TypedOp
    {
        $new = [];
        foreach ($this->path as $i => $part) {
            if ($ignoreFlag && \array_key_exists(2, $part) && \is_int($part[2]) && ($part[2] & CopyOp::FLAG_IF_ABSENT_ABORT)) {
                return null;
            }
            if (isset($part[2]) && $part[2] instanceof TypedOp) {
                $n = $part[2]->normalize($stack, $current, $ignoreFlag);
                if ($n === null) {
                    return null;
                }
                $part[2] = $n;
            }
            $new[$i] = $part;
        }
        Assert::eq($current, $this->path[0][0]);
        return new self(
            [...$stack, ...$new],
        );
    }

    public function build(TLContext $tl): array
    {
        return [
            '_' => 'typedOp',
            'type' => $this->getType($tl),
            'op' => [
                '_' => 'copyOp',
                'path' => $this->buildPath($tl),
            ],
        ];
    }
}
