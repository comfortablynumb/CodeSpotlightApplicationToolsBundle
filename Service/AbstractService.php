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
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\PersistenceManagerInterface;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\ORM\PersistenceManager;

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

    /** @var $persistenceManager \CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\PersistenceManagerInterface */
    protected $persistenceManager;

    /** @var string Delimiter used for values embedded in a string */
    protected $valueDelimiter = ',';
    
    public function __construct(ContainerInterface $container, $objectManagerServiceId, $objectClass, $persistenceManagerServiceId = null)
    {
        $this->container = $container;
        $this->formFactory = $this->container->get('form.factory');
        $this->setPersistenceManager($persistenceManagerServiceId ?
            $this->container->get($persistenceManagerServiceId) :
            new PersistenceManager($this->container->get($objectManagerServiceId), $objectClass));

        $this->setObjectClass($objectClass);
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

            $pm = $this->getPersistenceManager();
            $dataBag = $this->getData();
            $response = $this->getResponse();
            $result = $this->preGet($dataBag);

            if (is_object($result) && $result instanceof BaseResponse) {
                return $result;
            }

            // Count query
            $response->setTotalRows($pm->get($dataBag, true));

            // Normal query
            $response->setData($pm->get($dataBag));

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
                $this->getPersistenceManager()->save($resultData);
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
            $pm = $this->getPersistenceManager();
            $object = $pm->find($id);

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
                $pm->save($resultData);
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

            $pm = $this->getPersistenceManager();
            $object = $pm->find($id);
            $response = $this->getResponse();
            $result = $this->preDelete($object);

            if (is_object($result) && $result instanceof BaseResponse) {
                return $result;
            }

            $pm->delete($object);

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
        $om = $this->getPersistenceManager()->getObjectManager();
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
        }
    }

    // "Pre" methods
    public function preInitialize($data = null, $object = null)
    {
        return $data;
    }

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

    public function preBind(DataBag $data = null, FormInterface $form = null)
    {
    }

    // "Post" methods
    public function postInitialize(DataBag $data, $object = null)
    {

    }

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

    public function postBind(array $data = null, FormInterface $form = null)
    {
        return $data;
    }

    public function initialize($data = null, $object = null)
    {
        $data = $this->preInitialize($data, $object);

        $response = $this->createResponse();
        $this->setResponse($response);

        $form = $this->createForm($object);
        $this->setForm($form);

        if ($data) {
            $this->getLogger()->addInfo('[Data Received by Service]', array('data' => $data));

            $data = is_object($data) && $data instanceof Request ? $data->get($this->form->getName()) :
                $data;
        } else {
            $data = array();
        }

        $data = new DataBag($data);

        $this->setData($data);
        $this->parseData($this->getData());

        $response->setForm($form);

        $this->postInitialize($data, $object);

        return $this;
    }

    protected function parseData(DataBag $data)
    {
    }

    public function bindDataToForm(DataBag $data, FormInterface $form = null)
    {
        $form = $form === null ? $this->getForm() : $form;

        $this->preBind($data, $form);

        if (is_object($data) && $data instanceof DataBag) {
            $data = $data->all();
        }
        
        $form->bind($data);

        $this->postBind($data, $form);

        return $this;
    }

    public function handleException(\Exception $e)
    {
        $this->getLogger()->addError($e);

        $response = $this->getResponse();
        $msg = '';

        if ($e instanceof InvalidFormException) {
            $this->handleFormException($e);
        } else {
            $response->setMsgAsError($e->getMessage());
        }

        $response->setForm($this->getForm())
            ->setException($e);

        return $response;
    }

    public function handleFormException(\Exception $e)
    {
        $msg = 'Global Errors: <br /><br />';

        /** @var $form FormInterface */
        $form = $this->getForm();
        $formErrors = $form->getErrors();

        foreach ($formErrors as $error) {
            $msg .= '['.$form->getPropertyPath().'] - '.$error->getMessageTemplate().'<br />';
        }

        $msg .= '<br /><br />Field Errors: <br /><br />';

        foreach ($form->getChildren() as $child) {
            foreach ($child->getErrors() as $property => $error) {
                $msg .= '['.$child->getPropertyPath().'] - '.$error->getMessageTemplate().'<br />';
            }

            foreach ($child->getChildren() as $child2) {
                foreach ($child2->getErrors() as $property => $error) {
                    $msg .= '['.$child2->getPropertyPath().'] - '.$error->getMessageTemplate().'<br />';
                }
            }
        }

        $response = $this->getResponse();

        $response->setMsgAsError($msg);
        $response->setData($formErrors);
    }

    public function createForm($object = null)
    {
        $object = $object === null ? $this->createObjectInstance() : $object;

        return $this->createObjectFormType($object);
    }

    public function setData(DataBag $data)
    {
        $this->data = $data;

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

    public function setObjectClass($objectClass)
    {
        $this->objectClass = $objectClass;

        return $this;
    }

    public function getObjectClass()
    {
        return $this->objectClass;
    }

    public function setValueDelimiter($delimiter)
    {
        $this->valueDelimiter = $delimiter;
    }

    public function getValueDelimiter()
    {
        return $this->valueDelimiter;
    }

    /**
     * @param \CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\PersistenceManagerInterface $persistenceManager
     */
    public function setPersistenceManager(PersistenceManagerInterface $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * @return \CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\PersistenceManagerInterface
     */
    public function getPersistenceManager()
    {
        return $this->persistenceManager;
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
