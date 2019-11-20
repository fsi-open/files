<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\App\Form;

use FSi\Component\Files\Integration\Symfony\Form\WebFileType;
use FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedWebFile;
use FSi\Tests\App\Entity\FileEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class FormTestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('file', WebFileType::class, [
            'label' => 'File',
            'constraints' => [new UploadedWebFile()],
            'removable' => true,
            'required' => false
        ]);

        $builder->add('anotherFile', WebFileType::class, [
            'label' => 'Another file',
            'constraints' => [new NotBlank(), new UploadedWebFile()],
            'required' => false
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', FileEntity::class);
        $resolver->setDefault('method', Request::METHOD_POST);
    }
}
