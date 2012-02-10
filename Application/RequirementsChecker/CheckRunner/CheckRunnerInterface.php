<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Application\RequirementsChecker\CheckRunner;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Application\RequirementsChecker\Check\CheckInterface;

interface CheckRunnerInterface
{
    public function run();
}
