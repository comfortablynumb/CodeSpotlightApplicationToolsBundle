<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Event;

use CodeSpotlight\Bundle\ApplicationToolsBundle\Application\Control\ApplicationControl;

class ApplicationControlEvent extends Event
{
    protected $applicationControl;
    
    public function __construct(ApplicationControl $applicationControl)
    {
        $this->applicationControl = $applicationControl;
    }

    public function getApplicationControl()
    {
        return $this->applicationControl;
    }
}
