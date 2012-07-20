<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;

abstract class AbstractPersistenceManager implements PersistenceManagerInterface
{
    /** @var \Doctrine\Common\Persistence\ObjectManager */
    protected $objectManager;

    /** @var \Doctrine\Common\Persistence\Mapping\ClassMetadata */
    protected $classMetadata;

    /** @var \Doctrine\Common\Persistence\ObjectRepository */
    protected $repository;


    public function save($object)
    {
        $this->objectManager->persist($object);

        $this->objectManager->flush();
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

}
