<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Application\RequirementsChecker\CheckRunner;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Application\RequirementsChecker\Check\CheckInterface;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Application\RequirementsChecker\CheckResult\CheckResultInterface;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Event\ApplicationRequirementsCheckerPreRunEvent;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Event\ApplicationRequirementsCheckerPostRunEvent;

class CheckRunner implements CheckRunnerInterface
{
    protected $checks = array();
    protected $checkResults = array();
    protected $description;
    protected $eventDispatcher;
    protected $okResults = 0;
    protected $warningResults = 0;
    protected $errorResults = 0;
    
    public function __construct(EventDispatcherInterface $eventDispatcher, $description = null, array $checks = array())
    {
        $this->setEventDispatcher($eventDispatcher);
        $this->setDescription($description);
        $this->setChecks($checks);
    }
    
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }
    
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
        
        return $this;
    }
    
    public function getDescription()
    {
        return $this->description;
    }
    
    public function setDescription($description)
    {
        $this->description = $description;
        
        return $this;
    }
    
    public function addCheck(CheckInterface $check)
    {
        if ($this->isCheckAlreadyLoaded($check)) {
            throw new \RuntimeException(sprintf('Check with message "%s" was already loaded!', $check->getDescription()));
        }
        
        $this->checks[] = $check;
        
        return $this;
    }
    
    public function setChecks(array $checks)
    {
        foreach ($checks as $check) {
            $this->addCheck($check);
        }
        
        return $this;
    }
    
    public function getChecks()
    {
        return $this->checks;
    }
    
    public function isCheckAlreadyLoaded(CheckInterface $check)
    {
        foreach ($this->getChecks() as $alreadyAddedCheck) {
            if ($check === $alreadyAddedCheck) {
                return true;
            }
        }
        
        return false;
    }
    
    public function run()
    {
        // We reset the results counters
        $this->okResults = 0;
        $this->warningResults = 0;
        $this->errorResults = 0;
        
        $eventDispatcher = $this->getEventDispatcher();
        $preRunEvent = new ApplicationRequirementsCheckerPreRunEvent($this);
        
        $eventDispatcher->dispatch(ApplicationRequirementsCheckerPreRunEvent::NAME, $preRunEvent);
        
        foreach ($this->getChecks() as $check) {
            $checkResult = $check->run();
            
            if ($checkResult->isOk()) {
                ++$this->okResults;
            } else if ($checkResult->isWarning()) {
                ++$this->warningResults;
            } else {
                ++$this->errorResults;
            }
            
            $this->addCheckResult($checkResult);
        }
        
        $postRunEvent = new ApplicationRequirementsCheckerPostRunEvent($this);
        
        $eventDispatcher->dispatch(ApplicationRequirementsCheckerPostRunEvent::NAME, $postRunEvent);
        
        return $this->getCheckResults();
    }
    
    public function getCheckResults()
    {
        return $this->checkResults;
    }
    
    protected function addCheckResult(CheckResultInterface $checkResult)
    {
        $this->checkResults[] = $checkResult;
    }
    
    public function getOkResults()
    {
        return $this->okResults;
    }
    
    public function getWarningResults()
    {
        return $this->warningResults;
    }
    
    public function getErrorResults()
    {
        return $this->errorResults;
    }
}
