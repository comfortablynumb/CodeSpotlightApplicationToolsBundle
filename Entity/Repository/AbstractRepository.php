<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Exception\InvalidFilterException;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Service\DataBag\DataBag;

class AbstractRepository extends EntityRepository
{
    const FILTER_FULLTEXT = 'fulltext';

    public function getQueryBuilder(DataBag $config = null, $isCount = false, $returnOne = false)
    {
        $qb = $this->_em->createQueryBuilder();
        $classMetadata = $this->getClassMetadata();
        $alias = 'entity';
        $selectString = str_replace('{ALIAS}', $alias, ($isCount ? 'COUNT({ALIAS})' : implode(', ', $this->getDefaultSelectFields())));

        $qb->select($selectString)
            ->from($classMetadata->name, $alias);

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

        if (!$isCount) {
            $qb->setFirstResult($config->get('start', 0));
            $qb->setMaxResults($config->get('limit', 25));
        }

        return $qb;
    }

    public function getQuery(DataBag $config = null, $isCount = false, $returnOne = false)
    {
        return $this->getQueryBuilder($config, $isCount, $returnOne)->getQuery();
    }

    public function get(DataBag $config = null, $isCount = false, $returnOne = false)
    {
        $query = $this->getQuery($config, $isCount, $returnOne);

        if ($isCount) {
            return (int) $query->getSingleScalarResult();
        }

        return $returnOne ? $query->getSingleResult() : $query->getArrayResult();
    }

    public function save($object)
    {
        $this->_em->persist($object);

        $this->_em->flush();
    }

    public function delete($object)
    {
        $this->_em->remove($object);

        $this->_em->flush();
    }

    protected function getDefaultSelectFields()
    {
        return array('{ALIAS}');
    }
}
