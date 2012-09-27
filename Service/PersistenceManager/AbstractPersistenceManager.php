<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Exception\EntityNotFoundException;

abstract class AbstractPersistenceManager implements PersistenceManagerInterface
{
    const RETURN_AS_OBJECT = 'object';
    const RETURN_AS_ARRAY = 'array';

    /** @var \Doctrine\Common\Persistence\ObjectManager */
    protected $objectManager;

    /** @var \Doctrine\Common\Persistence\Mapping\ClassMetadata */
    protected $classMetadata;

    /** @var \Doctrine\Common\Persistence\ObjectRepository */
    protected $repository;


    public function find($id)
    {
        $object = $this->getRepository()->find($id);

        if (!$object) {
            throw new EntityNotFoundException(sprintf('Entity with ID "%s" could not be found.',
                $id
            ));
        }

        return $object;
    }

    public function save($object)
    {
        $this->objectManager->persist($object);

        $this->objectManager->flush();
    }

    public function persist($object)
    {
        $this->objectManager->persist($object);
    }

    public function flush()
    {
        $this->objectManager->flush();
    }

    public function refresh($object)
    {
        $this->objectManager->refresh($object);
    }

    public function delete($object)
    {
        $this->objectManager->remove($object);

        $this->objectManager->flush();
    }

    /**
     * @param \Doctrine\Common\Persistence\ObjectManager $objectManager
     */
    public function setObjectManager(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectManager
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    /**
     * @param \Doctrine\Common\Persistence\Mapping\ClassMetadata $classMetadata
     */
    public function setClassMetadata(ClassMetadata $classMetadata)
    {
        $this->classMetadata = $classMetadata;
    }

    /**
     * @return \Doctrine\Common\Persistence\Mapping\ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->classMetadata;
    }

    /**
     * @param \Doctrine\Common\Persistence\ObjectRepository $repository
     */
    public function setRepository(ObjectRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    public function getOriginalObjectData($object)
    {
        return $this->objectManager->getUnitOfWork()->getOriginalEntityData($object);
    }

    public function getObjectIdentifier($object)
    {
        return $this->objectManager->getEntityIdentifier($object);
    }
}
