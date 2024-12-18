<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Controller\DTO;

use Psr\Http\Message\ServerRequestInterface;

abstract class PostRequestDTO
{
    /**
     * @var array<string, mixed>
     */
    public array $body;
    /**
     * @var array<string, string|null>
     */
    public array $attributes;

    public static function fromRequest(ServerRequestInterface $request): static
    {
        $instance = new static();
        $parsedBody = $request->getParsedBody();
        $instance->body = (null !== $parsedBody) ? ((array) $parsedBody) : [];
        $instance->attributes = $request->getAttributes();

        return $instance;
    }

    final private function __construct()
    {
    }
}
