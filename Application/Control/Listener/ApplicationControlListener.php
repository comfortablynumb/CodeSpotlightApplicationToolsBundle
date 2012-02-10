<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Application\Control\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Application\Control\ApplicationControl;

class ApplicationControlListener
{
    protected $applicationControl;
    protected $templateEngine;
    
    public function __construct(ApplicationControl $applicationControl, $templateEngine)
    {
        $this->applicationControl = $applicationControl;
        $this->templateEngine = $templateEngine;
    }
    
    public function getApplicationControl()
    {
        return $this->applicationControl;
    }
    
    public function getTemplateEngine()
    {
        return $this->templateEngine;
    }
    
    public function onKernelRequest(GetResponseEvent $event)
    {
        $applicationControl = $this->getApplicationControl();
        
        if ($applicationControl->isDisabled()) {
            die('The application is under maintenance.');
        }
    }
}
