<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service\PersistenceManager;

interface PersistenceManagerInterface
{
    public function save($object);
    public function delete($object);
}
