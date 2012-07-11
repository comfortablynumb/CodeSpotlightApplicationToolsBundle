<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormInterface;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\Common\Persistence\ObjectManager;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Exception\InvalidFormException;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\Response\BaseResponse;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\DataBag\DataBag;

abstract class AbstractService
{
    protected $container;
    protected $formFactory;

    /** @var $form \Symfony\Component\Form\FormInterface */
    protected $form;
    protected $originalData;
    protected $data;
    protected $handleExceptions = true;

    /** @var string Entity (or Document) class that will be handled by this service */
    protected $objectClass;

    /** @var $response \CodeSpotlight\Bundle\ApplicationToolsBundle\Service\Response\BaseResponse */
    protected $response;

    /** @var $objectManager \Doctrine\Common\Persistence\ObjectManager */
    protected $objectManager;

    /** @var $repository \Doctrine\Common\Persistence\ObjectRepository */
    protected $repository;
    
    public function __construct(ContainerInterface $container, $objectManagerId, $objectClass)
    {
        $this->container = $container;
        $this->formFactory = $this->container->get('form.factory');

        $this->setObjectManager($this->container->get($objectManagerId));
        $this->setObjectClass($objectClass);
        $this->setRepository($this->getObjectManager()->getRepository($objectClass));
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

    public function get(array $config = array(), $handleExceptions = null)
    {
        $handleExceptions = $handleExceptions === null ? $this->handleExceptions : $handleExceptions;

        try {
            $this->initialize($config);

            $dataBag = $this->getData();
            $response = $this->getResponse();
            $result = $this->preGet($dataBag);

            if (is_object($result) && $result instanceof BaseResponse) {
                return $result;
            }

            // Count query
            $response->setTotalRows($this->getRepository()->get($dataBag, true));

            // Normal query
            $response->setData($this->getRepository()->get($dataBag));

            $this->postGet($dataBag);

            return $response;
        } catch (\Exception $e) {
            if ($handleExceptions) {
                return $this->handleException($e);
            } else {
                throw $e;
            }
        }
    }

    public function create(array $data, $handleExceptions = null)
    {
        $handleExceptions = $handleExceptions === null ? $this->handleExceptions : $handleExceptions;

        try {
            $this->initialize($data);

            $dataBag = $this->getData();
            $response = $this->getResponse();
            $result = $this->preCreate($dataBag);

            if (is_object($result) && $result instanceof BaseResponse) {
                return $result;
            }

            // We bind the data to the form
            $this->bindDataToForm($dataBag, $this->getForm());

            if (!$this->form->isValid()) {
                throw InvalidFormException::invalidForm();
            }

            $resultData = $this->form->getData();

            if (is_object($resultData)) {
                $this->getRepository()->save($resultData);
            }

            $this->postCreate($dataBag);

            return $response;
        } catch (\Exception $e) {
            if ($handleExceptions) {
                return $this->handleException($e);
            } else {
                throw $e;
            }
        }
    }

    public function update(array $data, $id, $handleExceptions = null)
    {
        $handleExceptions = $handleExceptions === null ? $this->handleExceptions : $handleExceptions;

        try {
            $repository = $this->getRepository();
            $object = $repository->find($id);

            $this->initialize($data, $object);

            $dataBag = $this->getData();
            $response = $this->getResponse();
            $result = $this->preUpdate($dataBag);

            if (is_object($result) && $result instanceof BaseResponse) {
                return $result;
            }

            // We bind the data to the form
            $this->bindDataToForm($dataBag, $this->getForm());

            if (!$this->form->isValid()) {
                throw InvalidFormException::invalidForm();
            }

            $resultData = $this->form->getData();

            if (is_object($resultData)) {
                $repository->save($resultData);
            }

            $this->postUpdate($dataBag);

            return $response;
        } catch (\Exception $e) {
            if ($handleExceptions) {
                return $this->handleException($e);
            } else {
                throw $e;
            }
        }
    }

    public function delete($id, $handleExceptions = null)
    {
        $handleExceptions = $handleExceptions === null ? $this->handleExceptions : $handleExceptions;

        try {
            $this->initialize();

            $repository = $this->getRepository();
            $object = $repository->find($id);
            $response = $this->getResponse();
            $result = $this->preDelete($object);

            if (is_object($result) && $result instanceof BaseResponse) {
                return $result;
            }

            $repository->delete($object);

            $this->postDelete($id);

            return $response;
        } catch (\Exception $e) {
            if ($handleExceptions) {
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
            return $service->create($data, false);
        });
    }

    public function updateTransactional($data, $id)
    {
        $service = $this;

        return $this->transactional(function() use ($service, $data, $id) {
            return $service->update($data, $id, false);
        });
    }

    public function deleteTransactional($id)
    {
        $service = $this;

        return $this->transactional(function() use ($service, $id) {
            return $service->delete($id, false);
        });
    }

    public function transactional(\Closure $closure, $handleExceptions = null)
    {
        $om = $this->getObjectManager();
        $conn = $om->getConnection();
        $handleExceptions = $handleExceptions === null ? $this->handleExceptions : $handleExceptions;

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

            if ($handleExceptions) {
                return $this->handleException($e);
            } else {
                throw $e;
            }

            return $this->getResponse();
        }
    }

    // "Pre" methods
    public function preGet(DataBag $config)
    {
    }

    public function preCreate(DataBag $data)
    {
    }

    public function preUpdate(DataBag $data)
    {
    }

    public function preDelete($object)
    {
    }

    // "Post" methods
    public function postGet(DataBag $config)
    {
    }

    public function postCreate(DataBag $data)
    {
    }

    public function postUpdate(DataBag $data)
    {
    }

    public function postDelete($id)
    {
    }

    public function initialize($data = null, $object = null)
    {
        $response = $this->createResponse();
        $this->setResponse($response);

        $form = $this->createForm($object);
        $this->setForm($form);

        if ($data) {
            $this->getLogger()->addInfo('[Data Received by Service]', array('data' => $data));
            $this->setData($data);
        }

        $response->setForm($form);

        return $this;
    }

    public function bindDataToForm($data, $form = null)
    {
        $bindMethod = 'bind';
        $form = $form === null ? $this->getForm() : $form;

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
        $this->getLogger()->addError($e);

        if ($e instanceof InvalidFormException) {
            $msg = $this->handleFormException($e);
        } else {
            $msg = $e->getMessage();
        }

        $response = $this->getResponse();

        $response->setMsgAsError($msg)
            ->setForm($this->getForm())
            ->setException($e);

        return $response;
    }

    public function handleFormException(\Exception $e)
    {
        $msg = 'Global Errors: <br /><br />';

        foreach ($this->getForm()->getErrors() as $error) {
            $msg .= '- '.$error->getMessageTemplate().'<br />&nbsp;&nbsp;&nbsp;. Details: '.implode(', ', $error->getMessageParameters());
        }

        $msg .= '<br /><br />Field Errors: <br /><br />';

        foreach ($this->getForm()->getChildren() as $children) {
            foreach ($children->getErrors() as $error) {
                $msg .= '- '.$error->getMessageTemplate().'<br />';
            }
        }

        return $msg;
    }

    public function createForm($object = null)
    {
        $object = $object === null ? $this->createObjectInstance() : $object;

        return $this->createObjectFormType($object);
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

    public function setResponse(BaseResponse $response)
    {
        $this->response = $response;

        return $this;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setObjectManager(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;

        return $this;
    }

    public function getObjectManager()
    {
        return $this->objectManager;
    }

    public function setRepository(ObjectRepository $repository)
    {
        $this->repository = $repository;

        return $this;
    }

    public function getRepository()
    {
        return $this->repository;
    }

    public function setObjectClass($objectClass)
    {
        $this->objectClass = $objectClass;

        return $this;
    }

    public function getObjectClass()
    {
        return $this->objectClass;
    }

    public function createObjectInstance()
    {
        $class =  $this->getObjectClass();
        
        return new $class;
    }

    public function createObjectFormType($data = null, array $options = array())
    {
        $class = $this->getObjectFormTypeClass();

        return $this->formFactory->create(new $class, $data, $options);
    }

    public function createResponse()
    {
        return new BaseResponse();
    }

    public function isDev()
    {
        return $this->container->get('kernel')->getEnvironment() === 'dev';
    }

    public function getLogger()
    {
        return $this->container->get('logger');
    }

    abstract function getObjectFormTypeClass();
}
