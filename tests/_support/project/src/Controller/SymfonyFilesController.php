<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\App\Controller;

use FSi\Component\Files\UploadedWebFile;
use FSi\Tests\App\Form\FormTestType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use function count;

final class SymfonyFilesController
{
    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    public function __construct(Environment $twig, FormFactoryInterface $formFactory)
    {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->formFactory->create(FormTestType::class);
        $form->handleRequest($request);

        if (true === $form->isSubmitted() && true === $form->isValid()) {
            /** @var UploadedWebFile $uploadedFile */
            $uploadedFile = $form->getData()['file'];
            $message = "Uploaded file \"{$uploadedFile->getOriginalName()}\"";
        } elseif (0 !== count($form->getErrors())) {
            $message = $this->errorsToMessage($form->getErrors());
        } else {
            $message = null;
        }

        return new Response(
            $this->twig->render(
                'symfonyForm.html.twig',
                ['form' => $form->createView(), 'message' => $message]
            )
        );
    }

    private function errorsToMessage(FormErrorIterator $errors): string
    {
        $message = '';
        /** @var FormError $message */
        foreach ($errors as $error) {
            $message .= "[{$error->getOrigin()->getName()}]: {$error->getMessage()}\r\n";
        }

        return $message;
    }
}
