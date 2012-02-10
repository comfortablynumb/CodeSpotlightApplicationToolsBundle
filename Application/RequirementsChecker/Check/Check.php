<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Application\RequirementsChecker\Check;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Application\RequirementsChecker\CheckResult\CheckResultInterface;

class Check implements CheckInterface
{
    protected $check;
    protected $description;
    protected $checkResult;
    
    public function __construct($description, $check)
    {
        $this->setDescription($description);
        $this->setCheck($check);
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
    
    public function getCheck()
    {
        return $this->check;
    }
    
    public function setCheck($check)
    {
        if (!is_callable($check)) {
            throw new \InvalidArgumentException('First argument must be a valid callable.');
        }
        
        $this->check = $check;
        
        return $this;
    }
    
    public function getCheckResult()
    {
        if (is_null($this->checkResult)) {
            throw new \RuntimeException('Check wasn\'t run so there\'s no CheckResultInterface instance available yet! you must run() the Check before get its result.');
        }
        
        return $this->checkResult;
    }
    
    public function run()
    {
        $checkResult = call_user_func($this->getCheck());
        
        if (!is_object($checkResult) || !($checkResult instanceof CheckResultInterface)) {
            throw new \RuntimeException('Each check must return an instance of a class implementing CheckResultInterface.');
        }
        
        $this->checkResult = $checkResult;
        
        return $checkResult;
    }
}
