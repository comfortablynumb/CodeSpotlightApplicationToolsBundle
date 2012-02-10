<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Application\Control;

use Symfony\Component\HttpKernel\Util\Filesystem;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Event\ApplicationPreEnableEvent;
use CodeSpotlight\Bundle\ApplicationToolsBundle\Event\ApplicationPreDisableEvent;

class ApplicationControl
{
    protected $lockFileName;
    protected $kernelRootDir;
    protected $environment;
    protected $eventDispatcher;
    
    public function __construct(EventDispatcherInterface $eventDispatcher, $kernelRootDir, $environment)
    {
        $this->kernelRootDir = $kernelRootDir;
        $this->environment = $environment;
        $this->lockFile = $kernelRootDir.'/app_'.$environment.'.lck';
        $this->eventDispatcher = $eventDispatcher;
    }
    
    public function getLockFile()
    {
        return $this->lockFile;
    }
    
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }
    
    public function isEnabled()
    {
        $lockFile = $this->getLockFile();
        
        if (is_file($lockFile)) {
            return false;
        } else {
            return true;
        }
    }
    
    public function isDisabled()
    {
        return !$this->isEnabled();
    }
    
    public function enable()
    {
        $lockFile = $this->getLockFile();
        
        if ($this->isDisabled()) {
            $event = new ApplicationPreEnableEvent($this);
            $this->getEventDispatcher()->dispatch(ApplicationPreEnableEvent::NAME, $event);
            
            $filesystem = $this->getFilesystem();
            
            $filesystem->remove($lockFile);
            
            return true;
        } else {
            return false;
        }
    }
    
    public function disable()
    {
        $lockFile = $this->getLockFile();
        
        if ($this->isEnabled()) {
            $event = new ApplicationPreDisableEvent($this);
            $this->getEventDispatcher()->dispatch(ApplicationPreDisableEvent::NAME, $event);
            
            $filesystem = $this->getFilesystem();
            
            $filesystem->touch($lockFile);
            
            return true;
        } else {
            return false;
        }
    }
    
    public function getFilesystem()
    {
        return new Filesystem();
    }
}
