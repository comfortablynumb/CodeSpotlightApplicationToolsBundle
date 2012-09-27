<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\ORM;

use Doctrine\ORM\EntityManager;
use Gedmo\Tree\Entity\Repository\AbstractTreeRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager\AbstractPersistenceManager;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Exception\InvalidFilterException;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\DataBag\DataBag;

class PersistenceManager extends AbstractPersistenceManager
{
    const FILTER_FULLTEXT = 'fulltext';
    const FILTER_OR = '{OR}';
    const FILTER_AND = '{AND}';
    const FILTER_MEMBER_OF = '{MEMBER_OF}';
    const DEFAULT_ALIAS = 'entity';

    protected $defaultOptions = array(
        'getTree'               => false,
        'treeIncludeNode'       => true,
        'treeRoot'              => false,
        'treeDirectChildren'    => false,
        'returnOne'             => false,
        'returnAs'              => self::RETURN_AS_ARRAY,
        'qbModifier'            => false
    );
    protected $objectClass;


    public function __construct(EntityManager $em, $objectClass)
    {
        $this->objectManager = $em;
        $this->classMetadata = $em->getClassMetadata($objectClass);
        $this->objectClass = $objectClass;

        $this->setRepository($em->getRepository($objectClass));
    }

    public function getQueryBuilder(DataBag $config = null, $isCount = false)
    {
        $defaults = $this->defaultOptions;
        $options = $config->all();
        $options = array_merge($defaults, $options);
        $classMetadata = $this->getClassMetadata();
        $alias = self::DEFAULT_ALIAS;
        $meta = $this->classMetadata;

        if ($options['getTree']) {
            if (!($this->repository instanceof AbstractTreeRepository)) {
                $msg = sprintf('If you use the option "getTree", your repository "%s" should extend any of the tree repositories of the Gedmo extensions.',
                    get_class($this->repository)
                );

                throw new \RuntimeException($msg);
            }

            $root = null;

            if ($options['treeRoot']) {
                $root = $options['treeRoot'];

                if (is_scalar($root)) {
                    $root = $this->find($root);
                }
            }

            /** @var $qb \Doctrine\ORM\QueryBuilder */
            $qb = $this->repository->getNodesHierarchyQueryBuilder($root, $options['treeDirectChildren'], array(), $options['treeIncludeNode']);
            $rootAliases = $qb->getRootAliases();
            $alias = $rootAliases[0];
            $qb->resetDQLPart('select');
        } else {
            /** @var $qb \Doctrine\ORM\QueryBuilder */
            $qb = $this->objectManager->createQueryBuilder();
            $qb->from($classMetadata->name, $alias);
        }

        if ($options['qbModifier'] && is_object($options['qbModifier']) && $options['qbModifier'] instanceof \Closure) {
            $options['qbModifier']($qb, $options, $isCount);
        }

        $selectString = $isCount ? 'COUNT({ALIAS})' :
            ($config->get('select', implode(', ', $this->getDefaultSelectFields())));

        // Additional fields to select
        if (!$isCount && ($addSelect = $config->get('addSelect'))) {
            $addSelect = is_array($addSelect) ? $addSelect: (array) json_decode($addSelect);

            $selectString .= ', '.implode(', ', $addSelect);
        }

        $selectString = str_replace('{ALIAS}', $alias, $selectString);

        $qb->select($selectString);

        // Joins
        if ($joins = $config->get('join')) {
            $joins = is_array($joins) ? $joins: (array) json_decode($joins);
            $joinConfigDefault = array(
                'type'          => 'inner',
                'join'          => '',
                'alias'         => '',
                'conditionType' => null,
                'condition'     => null
            );

            foreach ($joins as $joinConfig) {
                $joinConfig = is_array($joinConfig) ? $joinConfig : (array) $joinConfig;
                $joinConfig = array_merge($joinConfigDefault, $joinConfig);
                $joinMethod = $joinConfig['type'].'Join';

                $qb->$joinMethod(
                    str_replace('{ALIAS}', $alias, $joinConfig['join']),
                    $joinConfig['alias'],
                    $joinConfig['conditionType'],
                    $joinConfig['condition']
                );
            }
        }

        // Filters
        if ($filters = $config->get('filter')) {
            $filters = is_array($filters) ? $filters : (array) json_decode($filters);
            $defaultFilterOptions = array(
                'defaultConditionalOperator'        => 'AND'
            );
            $filterOptions = $config->get('filterOptions', array());
            $filterOptions = is_array($filterOptions) ? $filterOptions : (array) json_decode($filterOptions);
            $filterOptions = array_merge($defaultFilterOptions, $filterOptions);
            $expr = $filterOptions['defaultConditionalOperator'] === 'AND' ?
                $qb->expr()->andX() :
                $qb->expr()->orX();

            foreach ($filters as $filterContainer) {
                if (is_array($filterContainer) || is_object($filterContainer)) {
                    foreach ($filterContainer as $filter => $value) {
                        switch ($filter) {
                            case self::FILTER_FULLTEXT:
                                $expr = $qb->expr()->orX();

                                foreach ($classMetadata->getFieldNames() as $fieldName) {
                                    $expr->add($qb->expr()->like($alias.'.'.$fieldName, $qb->expr()->literal('%'.$value.'%')));
                                }

                                break;
                            case self::FILTER_MEMBER_OF:
                                if (!is_array($value) || !isset($value['value']) || !isset($value['collection'])) {
                                    $msgTemplate = 'For filter "%s" you need to pass an array with both: "value" and ';
                                    $msgTemplate .= '"collection" indexes.';
                                    $msg = sprintf($msgTemplate, self::FILTER_MEMBER_OF);

                                    throw new InvalidFilterException($msg);
                                }

                                $expr->add(sprintf(':member_of_value MEMBER OF %s',
                                    str_replace('{ALIAS}', $alias, $value['collection'])
                                ));

                                $qb->setParameter('member_of_value', $value['value']);

                                break;
                            default:
                                $expr->add($qb->expr()->eq((strpos($filter, '.') !== false ? $filter : $alias.'.'.$filter), $value));
                        }
                    }
                }
            }

            $qb->where($expr);
        }

        // Sorting
        if (!$options['returnOne'] && $sortInfo = $config->get('sort')) {
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

        // Limit Results
        if (!$options['returnOne'] && !$isCount && !$options['getTree']) {
            $qb->setFirstResult($config->get('start', 0));
            $qb->setMaxResults($config->get('limit', $options['returnOne'] ? 1 : 25));
        }

        return $qb;
    }

    public function getQuery(DataBag $config = null, $isCount = false)
    {
        return $this->getQueryBuilder($config, $isCount)->getQuery();
    }

    public function get(DataBag $config = null, $isCount = false)
    {
        $defaults = $this->defaultOptions;
        $options = $config->all();
        $options = array_merge($defaults, $options);

        $query = $this->getQuery($config, $isCount);

        if ($isCount) {
            return (int) $query->getSingleScalarResult();
        }

        if ($options['getTree']) {
            return $this->repository->buildTree($query->getArrayResult());
        }

        return $options['returnOne'] ?
            $query->getSingleResult(($options['returnAs'] === self::RETURN_AS_OBJECT ? Query::HYDRATE_OBJECT : Query::HYDRATE_ARRAY)) :
            ($options['returnAs'] === self::RETURN_AS_OBJECT ? $query->getResult() : $query->getArrayResult());
    }

    public function getDefaultQueryBuilder()
    {
        return $this->objectManager->createQueryBuilder($this->objectClass);
    }

    /**
     * @return array - Array of default select fields
     */
    protected function getDefaultSelectFields()
    {
        /** @var $meta \Doctrine\ORM\Mapping\ClassMetadata */
        $meta = $this->getClassMetadata();
        $fields = array();

        foreach ($meta->getFieldNames() as $fieldName) {
            $fields[] = '{ALIAS}.'.$fieldName;
        }

        $fields = array_merge($fields, $this->getDefaultSelectRelationFields());

        return $fields;
    }

    /**
     * @return array - Array of default select relation fields
     */
    protected function getDefaultSelectRelationFields()
    {
        /** @var $meta \Doctrine\ORM\Mapping\ClassMetadata */
        $meta = $this->getClassMetadata();
        $fields = array();

        // Now the foreign keys of many-to-one relations
        foreach ($meta->getAssociationMappings() as $field => $mapping) {
            if ($mapping['type'] === ClassMetadata::MANY_TO_ONE) {
                $fields[] = 'IDENTITY({ALIAS}.'.$field.') AS '.$field;
            }
        }

        return $fields;
    }

    /**
     * @return \Doctrine\ORM\Configuration;
     */
    public function getConfiguration()
    {
        return $this->objectManager->getConfiguration();
    }

    /**
     * @return \Doctrine\Common\EventManager
     */
    public function getEventManager()
    {
        return $this->objectManager->getEventManager();
    }
}
