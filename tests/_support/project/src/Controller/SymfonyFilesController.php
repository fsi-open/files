<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\App\Controller;

use Assert\Assertion;
use Doctrine\ORM\EntityManagerInterface;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\UploadedWebFile;
use FSi\Tests\App\Entity\FileEntity;
use FSi\Tests\App\Form\FormTestType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
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

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var FilePropertyConfigurationResolver
     */
    private $filePropertyConfigurationResolver;

    public function __construct(
        Environment $twig,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $entityManager,
        FilePropertyConfigurationResolver $filePropertyConfigurationResolver
    ) {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
        $this->entityManager = $entityManager;
        $this->filePropertyConfigurationResolver = $filePropertyConfigurationResolver;
    }

    public function __invoke(Request $request): Response
    {
        /** @var FileEntity|null $entity */
        $entity = $this->entityManager->getRepository(FileEntity::class)->findOneBy([]);
        if (null === $entity) {
            $entity = new FileEntity();
        }

        $form = $this->formFactory->create(FormTestType::class, $entity);
        $form->handleRequest($request);

        if (true === $form->isSubmitted() && true === $form->isValid()) {
            /** @var UploadedWebFile $file */
            $file = $entity->getFile();
            /** @var UploadedWebFile $anotherFile */
            $anotherFile = $entity->getAnotherFile();
            $message = "Uploaded file \"{$file->getOriginalName()}\"\r\n";
            $message .= "Another uploaded file \"{$anotherFile->getOriginalName()}\"";
        } elseif (0 !== count($form->getErrors())) {
            $message = $this->formErrorsToMessage($form->getErrors());
        } else {
            $message = null;
        }

        return new Response(
            $this->twig->render(
                'symfonyForm.html.twig',
                [
                    'form' => $form->createView(),
                    'message' => $message,
                    'configuration' => $this->createEntityConfigurationDump($entity)
                ]
            )
        );
    }

    private function createEntityConfigurationDump(FileEntity $entity): array
    {
        return array_map(
            function (FilePropertyConfiguration $configuration): array {
                return [
                    'filePropertyName' => $configuration->getFilePropertyName(),
                    'fileSystemName' => $configuration->getFileSystemName(),
                    'pathPropertyName' => $configuration->getPathPropertyName(),
                    'pathPrefix' => $configuration->getPathPrefix()
                ];
            },
            $this->filePropertyConfigurationResolver->resolveEntity($entity)
        );
    }

    private function formErrorsToMessage(FormErrorIterator $errors): string
    {
        $message = '';
        /** @var FormError $error */
        foreach ($errors as $error) {
            /** @var FormInterface $origin */
            $origin = $error->getOrigin();
            $message .= "[{$origin->getName()}]: {$error->getMessage()}\r\n";
        }

        return $message;
    }
}
