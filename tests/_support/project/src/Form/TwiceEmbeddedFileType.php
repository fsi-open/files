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
use FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedImage;
use FSi\Tests\App\Entity\TwiceEmbeddedFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class TwiceEmbeddedFileType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', WebFileType::class, [
            'label' => 'Removable twice embedded image',
            'constraints' => [new NotBlank(), new UploadedImage()],
            'image' => true,
            'removable' => true,
            'required' => false
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('data_class', TwiceEmbeddedFile::class);
        $resolver->setDefault('label', false);
    }
}
