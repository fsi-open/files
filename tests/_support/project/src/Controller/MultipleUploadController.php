<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\App\Controller;

use FSi\Component\Files\UploadedWebFile;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\FSi\App\Form\MultipleFileType;
use Twig\Environment;

final class MultipleUploadController
{
    private Environment $twig;
    private FormFactoryInterface $formFactory;

    public function __construct(Environment $twig, FormFactoryInterface $formFactory)
    {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->formFactory->create(MultipleFileType::class);
        $form->handleRequest($request);

        $message = null;
        if (true === $form->isSubmitted() && true === $form->isValid()) {
            $data = $form->getData();
            $message = sprintf(
                'Uploaded %d files: "%s"',
                count($data['files']),
                implode(
                    '", "',
                    array_map(static fn(UploadedWebFile $file): string => $file->getOriginalName(), $data['files'])
                )
            );
        }

        return new Response(
            $this->twig->render(
                'multipleSymfonyForm.html.twig',
                ['form' => $form->createView(), 'message' => $message]
            )
        );
    }
}
