<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Module;

use Codeception\Module;
use Codeception\Module\Symfony;
use Codeception\TestInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class FormModule extends Module
{
    /**
     * @var Symfony
     */
    private $symfony;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @phpcs:disable
     */
    public function _before(TestInterface $test)
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        $this->symfony = $symfony;

        /** @var FormFactoryInterface $formFactory */
        $formFactory = $symfony->_getContainer()->get('form.factory');
        $this->formFactory = $formFactory;
    }

    /**
     * @param string $class
     * @return FormInterface
     */
    public function createForm(string $class): FormInterface
    {
        return $this->formFactory->create($class);
    }
}
