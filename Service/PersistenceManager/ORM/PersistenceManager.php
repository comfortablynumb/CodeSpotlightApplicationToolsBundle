<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\ORM;

use Doctrine\ORM\EntityManager;
use Gedmo\Tree\Entity\Repository\AbstractTreeRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\AbstractPersistenceManager;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Exception\InvalidFilterException;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\DataBag\DataBag;

class PersistenceManager extends AbstractPersistenceManager
{
    const FILTER_FULLTEXT = 'fulltext';
    const RETURN_AS_OBJECT = 'object';
    const RETURN_AS_ARRAY = 'array';


    public function __construct(EntityManager $em, $objectClass)
    {
        $this->objectManager = $em;
        $this->classMetadata = $em->getClassMetadata($objectClass);

        $this->setRepository($em->getRepository($objectClass));
    }

    public function find($id)
    {
        return $this->getRepository()->find($id);
    }

    public function getQueryBuilder(DataBag $config = null, $isCount = false, $returnOne = false)
    {
        $defaults = array(
            'getTree'        => false
        );
        $options = $config->get('options', array());
        $defaults = array_merge($defaults, $options);
        $classMetadata = $this->getClassMetadata();
        $alias = 'entity';

        if ($defaults['getTree']) {
            if (!($this->repository instanceof AbstractTreeRepository)) {
                $msg = sprintf('If you use the option "getTree", your repository "%s" should extend any of the tree repositories of the Gedmo extensions.',
                    get_class($this->repository)
                );

                throw new \RuntimeException($msg);
            }

            /** @var $qb \Doctrine\ORM\QueryBuilder */
            $qb = $this->repository->getNodesHierarchyQueryBuilder();
            $rootAliases = $qb->getRootAliases();
            $alias = $rootAliases[0];

            if ($isCount) {
                $qb->select('COUNT('.$alias.')');
            }
        } else {
            /** @var $qb \Doctrine\ORM\QueryBuilder */
            $qb = $this->objectManager->createQueryBuilder();
            $selectString = str_replace('{ALIAS}', $alias, ($isCount ? 'COUNT({ALIAS})' : implode(', ', $this->getDefaultSelectFields())));

            $qb->select($selectString)
                ->from($classMetadata->name, $alias);
        }

        if ($filters = $config->get('filter')) {
            $filters = is_array($filters) ? $filters : json_decode($filters);

            if (is_array($filters) && !empty($filters)) {
                foreach ($filters as $filterContainer) {
                    if (is_object($filterContainer)) {
                        foreach ($filterContainer as $filter => $value) {
                            switch ($filter) {
                                case self::FILTER_FULLTEXT:
                                    $orExpr = $qb->expr()->orX();

                                    foreach ($classMetadata->getFieldNames() as $fieldName) {
                                        $orExpr->add($qb->expr()->like($alias.'.'.$fieldName, $qb->expr()->literal('%'.$value.'%')));
                                    }

                                    $qb->where($orExpr);

                                    break;
                                default:
                                    InvalidFilterException::invalidFilter($filter);
                            }
                        }
                    }
                }
            }
        }

        if ($sortInfo = $config->get('sort')) {
            $sortInfo = is_array($sortInfo) ? $sortInfo : json_decode($sortInfo);

            if (is_array($sortInfo) && !empty($sortInfo)) {
                foreach ($sortInfo as $fieldInfo) {
                    if (is_object($fieldInfo) &&
                        isset($fieldInfo->property) && is_string($fieldInfo->property) && $fieldInfo->property &&
                        isset($fieldInfo->direction) && is_string($fieldInfo->direction) && $fieldInfo->direction) {
                        $qb->orderBy(
                            $alias.'.'.$fieldInfo->property,
                            strtolower($fieldInfo->direction) === 'asc' ? 'asc' : 'desc'
                        );
                    }
                }
            }
        }

        if (!$isCount && !$defaults['getTree']) {
            $qb->setFirstResult($config->get('start', 0));
            $qb->setMaxResults($config->get('limit', $returnOne ? 1 : 25));
        }

        return $qb;
    }

    public function getQuery(DataBag $config = null, $isCount = false, $returnOne = false)
    {
        return $this->getQueryBuilder($config, $isCount, $returnOne)->getQuery();
    }

    public function get(DataBag $config = null, $isCount = false, $returnOne = false)
    {
        $defaults = array(
            'getTree'       => false,
            'returnAs'      => 'array'
        );
        $options = $config->get('options', array());
        $defaults = array_merge($defaults, $options);

        $query = $this->getQuery($config, $isCount, $returnOne);

        if ($isCount) {
            return (int) $query->getSingleScalarResult();
        }

        if ($defaults['getTree']) {
            return $this->repository->buildTree($query->getArrayResult());
        }

        return $returnOne ?
            $query->getSingleResult() :
            ($defaults['returnAs'] === self::RETURN_AS_OBJECT ? $query->getResult() : $query->getArrayResult());
    }

    protected function getDefaultSelectFields()
    {
        $fields = array();
        /** @var $meta \Doctrine\ORM\Mapping\ClassMetadata */
        $meta = $this->getClassMetadata();

        foreach ($meta->getFieldNames() as $fieldName) {
            $fields[] = '{ALIAS}.'.$fieldName;
        }

        // Now the foreign keys of many-to-one relations
        foreach ($meta->getAssociationMappings() as $field => $mapping) {
            if ($mapping['type'] === ClassMetadata::MANY_TO_ONE) {
                $fields[] = 'IDENTITY({ALIAS}.'.$field.') AS '.$field;
            }
        }

        return $fields;
    }
}
