<?php

/**
 * Created by Gustavo Falco <comfortablynumb84@gmail.com>
 */

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormFactoryInterface;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\Data\DataHolder;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Exception\InvalidArgumentException;

abstract class AbstractService
{
    protected $formFactory;
    protected $request;
    
    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function createObjectInstance()
    {
        $class =  $this->getObjectClass();
        
        return new $class;
    }

    abstract function getObjectClass();
}
