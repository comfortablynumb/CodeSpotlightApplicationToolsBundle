<?php

/**
 * Created by Gustavo Falco <comfortablynumb84@gmail.com>
 */

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormInterface;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Exception\InvalidFormException;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\Response\BaseResponse;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\DataBag\DataBag;

abstract class AbstractService
{
    protected $container;
    protected $formFactory;
    protected $form;
    protected $originalData;
    protected $data;
    protected $handleExceptions = true;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->formFactory = $this->container->get('form.factory');
    }

    public function getHandleExceptions()
    {
        return $this->handleExceptions;
    }

    public function setHandleExceptions($bool)
    {
        $this->handleExceptions = $bool;

        return $this;
    }

    public function create($data)
    {
        try {
            $this->initialize($data);

            $response = $this->createResponse();
            $response->setForm($this->getForm());
            
            $result = $this->preCreate($data, $response);

            if (is_object($result) && $result instanceof BaseResponse) {
                return $result;
            }

            // We bind the data to the form
            $this->bindDataToForm($this->getData(), $this->getForm());

            if (!$this->form->isValid()) {
                throw InvalidFormException::invalidForm();
            }

            $data = $this->form->getData();

            if (is_object($data)) {
                $om = $this->getObjectManager();

                $om->persist($data);
                $om->flush();
            }

            $this->postCreate($data, $response);

            return $response;
        } catch (\Exception $e) {
            if ($this->handleExceptions) {
                return $this->handleException($e);
            } else {
                throw $e;
            }
        }
    }

    public function createTransactional($data)
    {
        $service = $this;

        return $this->transactional(function() use ($service, $data) {
            return $service->create($data);
        });
    }

    public function transactional(\Closure $closure)
    {
        $om = $this->getObjectManager();
        $conn = $om->getConnection();

        try {
            $conn->beginTransaction();

            $response = $closure();

            if ($response->isSuccess()) {
                $conn->commit();
            } else {
                throw $response->getException();
            }

            return $response;
        } catch (\Exception $e) {
            $conn->rollback();
            $om->close();

            if ($this->handleExceptions) {
                return $this->handleException($e);
            } else {
                throw $e;
            }
        }
    }

    // "Pre" methods
    public function preCreate($data, BaseResponse $response)
    {
    }

    // "Post" methods
    public function postCreate($data, BaseResponse $response)
    {
    }

    public function initialize($data)
    {
        $this->setForm($this->createForm());
        $this->setData($data);

        $response = $this->createResponse();
        $response->setForm($this->getForm());

        return $this;
    }

    public function bindDataToForm($data, $form)
    {
        $bindMethod = 'bind';

        if (is_object($data)) {
            if ($data instanceof Request) {
                $bindMethod = 'bindRequest';
            } else if ($data instanceof DataBag) {
                $data = $data->all();
            }
        }
        
        $form->$bindMethod($data);

        return $this;
    }

    public function handleException(\Exception $e)
    {
        $response = $this->createResponse();

        $response->setAsError($e->getMessage())
            ->setForm($this->getForm())
            ->setException($e);

        return $response;
    }

    public function createForm()
    {
        return $this->createObjectFormType($this->createObjectInstance());
    }

    public function setData($data)
    {
        $data = is_object($data) && $data instanceof Request ? $data->get($this->form->getName()) :
            $data;
        $this->data = new DataBag($data);

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setForm(FormInterface $form)
    {
        $this->form = $form;

        return $this;
    }

    public function getForm()
    {
        return $this->form;
    }

    public function createObjectInstance()
    {
        $class =  $this->getObjectClass();
        
        return new $class;
    }

    public function createObjectFormType($data = null, array $options = array())
    {
        $class = $this->getObjectFormType();

        return $this->formFactory->create(new $class, $data, $options);
    }

    public function createResponse()
    {
        return new BaseResponse();
    }

    public function getRepository()
    {
        return $this->getObjectManager()->getRepository($this->getObjectClass());
    }

    abstract function getObjectManager();
    abstract function getObjectClass();
    abstract function getObjectFormType();
}
