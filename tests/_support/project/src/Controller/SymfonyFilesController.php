<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Tests\FSi\App\Entity\FileEntity;
use Tests\FSi\App\Form\FormTestType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class SymfonyFilesController
{
    private Environment $twig;
    private FormFactoryInterface $formFactory;
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        Environment $twig,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
    }

    public function __invoke(Request $request): Response
    {
        /** @var FileEntity|null $entity */
        $entity = $this->entityManager->getRepository(FileEntity::class)->find(1);
        if (null === $entity) {
            $entity = new FileEntity();
            $entity->setId(1);
        }

        $form = $this->formFactory->create(FormTestType::class, $entity);
        $form->handleRequest($request);

        if (true === $form->isSubmitted() && true === $form->isValid()) {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            return new RedirectResponse($this->urlGenerator->generate('symfony_files'));
        }

        if (true === $form->isSubmitted()) {
            $message = $this->formErrorsToMessage($form->getErrors(true));
        } else {
            $message = null;
        }

        return new Response(
            $this->twig->render(
                'symfonyForm.html.twig',
                ['form' => $form->createView(), 'message' => $message, 'file' => $entity->getFile()]
            )
        );
    }

    private function formErrorsToMessage(FormErrorIterator $errors): string
    {
        $message = '';
        /** @var FormError $error */
        foreach ($errors as $error) {
            /** @var FormInterface<FormInterface> $origin */
            $origin = $error->getOrigin();
            $message .= "[{$origin->getName()}]: {$error->getMessage()}\r\n";
        }

        return $message;
    }
}
