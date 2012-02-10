<?php

/**
 * Created by Gustavo Falco <comfortablynumb84@gmail.com>
 */

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Form\Type;

use Symfony\Component\Form\AbstractType as SymfonyAbstractType;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Helper\TextHelper;

abstract class AbstractType extends SymfonyAbstractType
{
    protected $formName;

    public function getName()
    {
        if (is_null($this->formName)) {
            $reflClass = new \ReflectionClass($this);
            $this->formName = TextHelper::toUnderscore($reflClass->getShortName());
            $this->formName = substr($this->formName, -5) === '_type' ?
                substr($this->formName, 0, strlen($this->formName) - 5) : $this->formName;
        }

        return $this->formName;
    }
}
