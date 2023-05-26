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
use FSi\Component\Files\UploadedWebFile;
use FSi\Component\Files\WebFile;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class FileExtension extends AbstractExtension
{
    private FileUrlResolver $fileUrlResolver;
    private FileManager $fileManager;

    public function __construct(FileUrlResolver $fileUrlResolver, FileManager $fileManager)
    {
        $this->fileUrlResolver = $fileUrlResolver;
        $this->fileManager = $fileManager;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('file_url', function (WebFile $file): string {
                if (true === $file instanceof UploadedWebFile) {
                    return '';
                }

                return (string) $this->fileUrlResolver->resolve($file);
            }),
            new TwigFilter('file_name', function (WebFile $file): string {
                if (true === $file instanceof UploadedWebFile) {
                    return '';
                }

                return $this->fileManager->filename($file);
            })
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_web_file', static fn($value): bool => $value instanceof WebFile)
        ];
    }
}
