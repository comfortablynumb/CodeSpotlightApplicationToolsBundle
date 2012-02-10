<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Event;

use CodeSpotlight\Bundle\ApplicationToolsbundle\Application\RequirementsChecker\CheckRunner\CheckRunnerInterface;

class ApplicationRequirementsCheckerEvent extends Event
{
    protected $checkRunner;
    
    public function __construct(CheckRunnerInterface $checkRunner)
    {
        $this->checkRunner = $checkRunner;
    }
    
    public function getCheckRunner()
    {
        return $this->checkRunner;
    }
    
    public function setCheckRunner(CheckRunnerInterface $checkRunner)
    {
        $this->checkRunner = $checkRunner;
    }
}
