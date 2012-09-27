<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Gedmo\Tool\Wrapper\EntityWrapper;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Exception\InvalidFormException;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\Response\BaseResponse;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\DataBag\DataBag;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\PersistenceManagerInterface;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\ORM\PersistenceManager;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Exception\EntityNotFoundException;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Exception\InvalidArgumentException;

abstract class AbstractService
{
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_RETRIEVE = 'retrieve';

    protected $container;
    protected $formFactory;

    /** @var \Symfony\Component\Form\FormInterface */
    protected $form;
    protected $originalData;
    protected $data;

    /** @var bool */
    protected $handleExceptions = true;

    /** @var string - Entity (or Document) class that will be handled by this service */
    protected $objectClass;

    /** @var \CodeSpotlight\Bundle\ApplicationToolsBundle\Service\Response\BaseResponse */
    protected $response;

    /** @var \CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\PersistenceManagerInterface */
    protected $persistenceManager;

    /** @var string - Delimiter used for values embedded in a string */
    protected $valueDelimiter = ',';

    /** @var array - Holds the listeners from Gedmo's extensions registered */
    protected $gedmoListeners = array();

    /** @var \CodeSpotlight\Bundle\ApplicationToolsBundle\Service\Databag\DataBag */
    private $options;

    /** @var string - Holds the current action the service is performing */
    private $currentAction = null;
    
    public function __construct(ContainerInterface $container, $objectManagerServiceId, $objectClass, $persistenceManagerServiceId = null)
    {
        $this->container = $container;
        $this->formFactory = $this->container->get('form.factory');
        $this->setPersistenceManager($persistenceManagerServiceId ?
            $this->container->get($persistenceManagerServiceId) :
            new PersistenceManager($this->container->get($objectManagerServiceId), $objectClass));

        $this->setObjectClass($objectClass);
        $this->setOptions($this->getDefaultOptions());
    }

    public function getDefaultOptions()
    {
        return array(
            // Allows partial updates on an entity
            'partial'           => false
        );
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

        $this->setCurrentAction(self::ACTION_RETRIEVE);

        try {
            $this->initialize($config, null, false);

            $dataBag = $this->getData();
            $response = $this->getResponse();
            $result = $this->preGet($dataBag);

            if (is_object($result) && $result instanceof BaseResponse) {
                return $result;
            }

            // Count query
            $response->setTotalRows($this->getSearchTotalRowsResult($dataBag));

            // Normal query
            $response->setData($this->getSearchResults($dataBag));

            $this->postGet($dataBag);

            $this->setCurrentAction(null);

            return $response;
        } catch (\Exception $e) {
            $this->setCurrentAction(null);

            if ($handleExceptions) {
                return $this->handleException($e);
            } else {
                throw $e;
            }
        }
    }

    public function getById($id)
    {
        return $this->getPersistenceManager()->find($id);
    }

    public function getSearchTotalRowsResult(DataBag $dataBag)
    {
        return $this->getPersistenceManager()->get($dataBag, true);
    }

    public function getSearchResults(DataBag $dataBag)
    {
        return $this->getPersistenceManager()->get($dataBag);
    }

    public function create(array $data, $handleExceptions = null)
    {
        $handleExceptions = $handleExceptions === null ? $this->handleExceptions : $handleExceptions;

        $this->setCurrentAction(self::ACTION_CREATE);

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
                $this->save($resultData);
            }

            $response->setData($this->toArray($resultData));

            $this->postCreate($dataBag, $resultData);

            $this->setCurrentAction(null);

            return $response;
        } catch (\Exception $e) {
            $this->setCurrentAction(null);

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

        $this->setCurrentAction(self::ACTION_UPDATE);

        try {
            $pm = $this->getPersistenceManager();
            $object = $pm->find($id);
            $options = $this->getOptions();

            if ($options->get('partial', false)) {
                $tmp = $this->get(array('filter' => array(
                    array('id'      => $id)
                )));
                $tmp = $tmp->getData();
                $tmp = $tmp[0];

                $data = array_merge($tmp, $data);

                // We need this so we ensure all types of data are serialized correctly (DateTimes, etc).
                $data = $this->toArray($data);
            }

            $this->initialize($data, $object);

            $dataBag = $this->getData();

            $response = $this->getResponse();
            $result = $this->preUpdate($dataBag, $object);

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
                $this->save($resultData);
            }

            $response->setData($this->toArray($resultData));

            $this->postUpdate($dataBag, $resultData);

            $this->setCurrentAction(null);

            return $response;
        } catch (\Exception $e) {
            $this->setCurrentAction(null);

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

        $this->setCurrentAction(self::ACTION_DELETE);

        try {
            $pm = $this->getPersistenceManager();
            $object = $pm->find($id);

            $this->initialize();

            $response = $this->getResponse();
            $result = $this->preDelete($object);

            if (is_object($result) && $result instanceof BaseResponse) {
                return $result;
            }

            $pm->delete($object);

            $this->postDelete($id);

            $this->setCurrentAction(null);

            return $response;
        } catch (\Exception $e) {
            $this->setCurrentAction(null);

            if ($handleExceptions) {
                return $this->handleException($e);
            } else {
                throw $e;
            }
        }
    }

    // Exclusive Tree Methods
    public function moveNodeUp($nodeId, $howManyPositions, $newParent = false)
    {
        return $this->moveNode('Up', $nodeId, $howManyPositions, $newParent);
    }

    public function moveNodeDown($nodeId, $howManyPositions, $newParent = false)
    {
        return $this->moveNode('Down', $nodeId, $howManyPositions, $newParent);
    }

    public function moveNode($where, $nodeId, $howManyPositions, $newParent = false, $handleExceptions = null)
    {
        $handleExceptions = $handleExceptions === null ? $this->handleExceptions : $handleExceptions;

        try {
            $repo = $this->getRepository();

            if (!($repo instanceof NestedTreeRepository)) {
                throw new \RuntimeException('To be able to move nodes, your repository must extend "Gedmo\\Tree\\Entity\\Repository\\NestedTreeRepository".');
            }

            if ($where !== 'Up' && $where !== 'Down') {
                throw new \RuntimeException('Parameter $where must be "Down" or "Up".');
            }

            $this->initialize();

            $node = $repo->find($nodeId);

            if (!$node) {
                throw new \RuntimeException(sprintf('Node with ID "%s" does not exist.', $node));
            }

            $this->preMoveNode($node, $where, $howManyPositions, $newParent);

            // First we change the parent if needed
            $pm = $this->getPersistenceManager();

            if ($newParent) {
                if ($newParent !== 'ROOT') {
                    $parentNode = $repo->find($newParent);

                    if (!$parentNode) {
                        throw new \RuntimeException(sprintf('Parent with ID "%s" does not exist.', $newParent));
                    }

                    $repo->persistAsFirstChildOf($node, $parentNode);

                    $where = 'Up';
                } else {
                    $config = $this->getGedmoTreeListener()->getConfiguration($pm->getObjectManager(), $this->objectClass);
                    $wrapped = new EntityWrapper($node, $pm->getObjectManager());
                    $wrapped->setPropertyValue($config['parent'], null);

                    $pm->persist($node);
                }

                $pm->flush();
                $pm->refresh($node);
            }

            if ($newParent !== 'ROOT') {
                $method = 'move'.$where;

                $result = $repo->$method($node, $howManyPositions);
            }

            $response = $this->getResponse();

            $response->setMsgAsSuccess('Node has been moved successfully.');

            $this->postMoveNode($node, $where, $howManyPositions, $newParent);

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

    public function updateTransactional($data, $id, $partial = false)
    {
        $service = $this;

        return $this->transactional(function() use ($service, $data, $id, $partial) {
            return $service->update($data, $id, $partial, false);
        });
    }

    public function deleteTransactional($id)
    {
        $service = $this;

        return $this->transactional(function() use ($service, $id) {
            return $service->delete($id, false);
        });
    }

    public function save($object)
    {
        $pm = $this->getPersistenceManager();

        $pm->persist($object);

        $this->postPersist($object);

        $pm->save($object);

        $this->postFlush($object);
    }

    // Tree Specific Methods
    public function moveNodeUpTransactional($nodeId, $howManyPositions, $newParent = false)
    {
        $service = $this;

        return $this->transactional(function() use ($service, $nodeId, $howManyPositions, $newParent) {
            return $service->moveNodeUp($nodeId, $howManyPositions, $newParent);
        });
    }

    public function moveNodeDownTransactional($nodeId, $howManyPositions, $newParent = false)
    {
        $service = $this;

        return $this->transactional(function() use ($service, $nodeId, $howManyPositions, $newParent) {
            return $service->moveNodeDown($nodeId, $howManyPositions, $newParent);
        });
    }

    public function moveNodeTransactional($where, $nodeId, $howManyPositions, $newParent = false)
    {
        $service = $this;

        return $this->transactional(function() use ($service, $where, $nodeId, $howManyPositions, $newParent) {
            return $service->moveNode($where, $nodeId, $howManyPositions, $newParent);
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
                $this->preCommit();

                $conn->commit();

                $this->postCommit();
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

    public function preUpdate(DataBag $data, $object)
    {
    }

    public function preDelete($object)
    {
    }

    public function preBind(DataBag $data = null, FormInterface $form = null)
    {
    }

    public function preCommit()
    {
    }

    public function preMoveNode($node, $where, $howManyPositions, $newParent)
    {
    }

    // "Post" methods
    public function postInitialize(DataBag $data, $object = null)
    {

    }

    public function postGet(DataBag $config)
    {
    }

    public function postCreate(DataBag $data, $result)
    {
    }

    public function postUpdate(DataBag $data, $result)
    {
    }

    public function postDelete($id)
    {
    }

    public function postBind(array $data = null, FormInterface $form = null)
    {
        return $data;
    }

    public function postPersist($object)
    {
    }

    public function postCommit()
    {
    }

    public function postFlush($object)
    {
    }

    public function postMoveNode($node, $where, $howManyPositions, $newParent)
    {
    }

    public function initialize($data = null, $object = null, $createForm = true)
    {
        $data = $this->preInitialize($data, $object);

        $response = $this->createResponse();
        $this->setResponse($response);

        if ($createForm) {
            $form = $this->createForm($object);
            $this->setForm($form);
            $response->setForm($form);
        }

        if ($data) {
            $this->getLogger()->addInfo('[Data Received by Service]', array('data' => $data));

            $data = is_object($data) && $data instanceof Request ? $data->get($this->form->getName()) :
                $data;
        } else {
            $data = array();
        }

        $options = $this->getOptions()->all();
        $requestOptions = $this->getRequest()->query->all();
        $options = array_merge($options, $requestOptions);

        if ($this->getCurrentAction() === self::ACTION_RETRIEVE) {
            $data = array_merge($data, $options);
        }

        // We don't allow sensitive PersistenceManager options coming from the request for now
        unset($data['addSelect']);
        unset($data['join']);

        $this->setOptions($options);

        $data = new DataBag($data);

        $this->setData($data);
        $this->parseData($this->getData());
        $this->postInitialize($data, $object);

        return $this;
    }

    public function validateField($field, array $config)
    {
        if (!is_string($field) || empty($field)) {
            throw new InvalidArgumentException('The "field" is mandatory.');
        }
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

        $files = array();

        if (!$this->isCli()) {
            $files = $this->getRequest()->files->all();
        }

        if (is_array($files) && !empty($files)) {
            $data = array_replace_recursive($data, $files);
        }

        $form->bind($data);

        $this->postBind($data, $form);

        return $this;
    }

    public function handleException(\Exception $e)
    {
        $this->getLogger()->addError($e);

        if (!($response = $this->getResponse())) {
            $this->setResponse($this->createResponse());

            $response = $this->getResponse();
        }

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
        /** @var $form FormInterface */
        $form = $this->getForm();
        $formErrors = $form->getErrors();

        $this->getLogger()->addError($formErrors);

        $msg = $this->getService('templating')->render('CodeSpotlightApplicationToolsBundle:Form:errors.html.twig', array(
            'form'          => $this->getForm()->createView()
        ));

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

    public function getRepository()
    {
        return $this->getPersistenceManager()->getRepository();
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

    protected function getGedmoTreeListener()
    {
        return $this->getGedmoListener('\\Gedmo\\Tree\\TreeListener');
    }

    protected function getGedmoListener($class)
    {
        $class = $class{0} === '\\' ? substr($class, 1) : $class;

        if (!isset($this->gedmoListeners[$class])) {
            $pm = $this->getPersistenceManager();

            foreach ($pm->getEventManager()->getListeners() as $event => $listeners) {
                foreach ($listeners as $hash => $listener) {
                    $listenerClass = get_class($listener);

                    if ($listenerClass === $class || is_subclass_of($class, $listenerClass)) {
                        $this->gedmoListeners[$class] = $listener;

                        break 2;
                    }
                }
            }

            if (!isset($this->gedmoListeners[$class])) {
                throw new \RuntimeException(sprintf('Listener of class "%s" is not registered!', $class));
            }
        }

        return $this->gedmoListeners[$class];
    }

    public function getEnvironment()
    {
        return $this->container->get('kernel')->getEnvironment();
    }

    public function isDev()
    {
        return $this->getEnvironment() === 'dev';
    }

    public function getLogger()
    {
        return $this->container->get('logger');
    }

    public function toArray($entity)
    {
        return (array) json_decode($this->container->get('serializer')->serialize($entity, 'json'));
    }

    /**
     * @param $options
     * @return AbstractService
     */
    public function setOptions($options)
    {
        if (is_array($options)) {
            $options = new DataBag($options);
        }

        $this->options = $options;

        return $this;
    }

    /**
     * @return \CodeSpotlight\Bundle\ApplicationToolsBundle\Service\DataBag\DataBag
     */
    public function getOptions()
    {
        if (is_array($this->options)) {
            $this->options = new DataBag($this->options);
        }

        return $this->options;
    }

    abstract function getObjectFormTypeClass();

    /**
     * @param string $currentAction
     */
    public function setCurrentAction($currentAction)
    {
        $this->currentAction = $currentAction;
    }

    /**
     * @return string
     */
    public function getCurrentAction()
    {
        return $this->currentAction;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->container->get('request');
    }

    public function getService($serviceId)
    {
        return $this->container->get($serviceId);
    }

    public function isCli()
    {
        return php_sapi_name() === 'cli';
    }

    public function isNew($object)
    {
        return $this->getPersistenceManager()->getPersistenceManager()->contains($object);
    }
}
