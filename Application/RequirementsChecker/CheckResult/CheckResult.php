<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Application\RequirementsChecker\CheckResult;

class CheckResult implements CheckResultInterface
{
    const OK        = 'OK';
    const WARNING   = 'WARNING';
    const ERROR     = 'ERROR';
    
    protected $result;
    protected $message;
    
    public function __construct($result, $message = null)
    {
        $this->setResult($result);
        $this->setMessage($message);
    }
    
    public function getResult()
    {
        return $this->result;
    }
    
    public function setResult($result)
    {
        if ($result !== self::OK && $result !== self::WARNING && $result !== self::ERROR) {
            $class = get_class($this);
            
            throw new \InvalidArgumentException(sprintf('Result is not valid. Must be %s::OK, %s::WARNING or %s::ERROR', $class, $class, $class));
        } else {
            $this->result = $result;
        }
        
        return $this;
    }
    
    public function getMessage()
    {
        return $this->message;
    }
    
    public function setMessage($message)
    {
        if (!is_null($message) && !is_string($message)) {
            throw new \InvalidArgumentException('Message for result must be a string.');
        }
        
        $this->message = $message;
        
        return $this;
    }
    
    public function isOk()
    {
        return $this->result === self::OK;
    }
    
    public function isWarning()
    {
        return $this->result === self::WARNING;
    }
    
    public function isError()
    {
        return $this->result === self::ERROR;
    }
    
    public function __toString()
    {
        $result = $this->getResult();
        
        return sprintf('[%s] %s', $result, $this->getMessage());
    }
}
