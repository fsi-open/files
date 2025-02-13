<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Controller\DTO;

use Assert\Assertion;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

final class LocalUploadDTO
{
    private StreamInterface $body;
    /**
     * @var array<string, string|null>
     */
    public array $attributes;

    public static function fromRequest(ServerRequestInterface $request): static
    {
        $instance = new static();
        $instance->body = $request->getBody();
        $instance->attributes = $request->getAttributes();

        return $instance;
    }

    public function getFileSystemName(): string
    {
        Assertion::keyExists($this->attributes, 'filesystem');
        Assertion::notNull($this->attributes['filesystem']);

        return $this->attributes['filesystem'];
    }

    public function getPath(): string
    {
        Assertion::keyExists($this->attributes, 'path');
        Assertion::notNull($this->attributes['path']);

        return $this->attributes['path'];
    }

    public function getFileContents(): StreamInterface
    {
        return $this->body;
    }
}
