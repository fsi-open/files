<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Twig;

use FSi\Component\Files\FileManager;
use FSi\Component\Files\FileUrlResolver;
use FSi\Component\Files\WebFile;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class FileExtension extends AbstractExtension
{
    /**
     * @var FileUrlResolver
     */
    private $fileUrlResolver;

    /**
     * @var FileManager
     */
    private $fileManager;

    public function __construct(FileUrlResolver $fileUrlResolver, FileManager $fileManager)
    {
        $this->fileUrlResolver = $fileUrlResolver;
        $this->fileManager = $fileManager;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('file_url', function (WebFile $file): string {
                return (string) $this->fileUrlResolver->resolve($file);
            }),
            new TwigFilter('file_name', function (WebFile $file): string {
                return $this->fileManager->filename($file);
            })
        ];
    }
}
