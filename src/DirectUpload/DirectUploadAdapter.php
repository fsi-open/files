<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload;

use FSi\Component\Files\DirectUpload\Controller\Response\Multipart;
use FSi\Component\Files\DirectUpload\Controller\Response\Params;
use FSi\Component\Files\DirectUpload\Controller\Response\Part;
use FSi\Component\Files\DirectUpload\Event\WebFileDirectUpload;
use Psr\Http\Message\UriInterface;

interface DirectUploadAdapter
{
    public function prepare(WebFileDirectUpload $event): Params;
    public function multipart(WebFileDirectUpload $event): Multipart;
    /**
     * @return list<Part>
     */
    public function parts(string $uploadId, string $key): array;
    public function part(string $uploadId, string $key, int $number): UriInterface;
    /**
     * @param list<Part> $parts
     */
    public function complete(string $uploadId, string $key, array $parts): void;
    public function abort(string $uploadId, string $key): void;
}
