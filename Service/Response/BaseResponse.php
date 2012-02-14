<?php

/**
 * Created by Gustavo Falco <comfortablynumb84@gmail.com>
 */

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service\Response;

use Symfony\Component\Form\Form;

class BaseResponse 
{
    const TYPE_SUCCESS = 'SUCCESS';
    const TYPE_WARNING = 'WARNING';
    const TYPE_ERROR = 'ERROR';

    protected $isSuccess = true;
    protected $msg = 'Action was executed successfully!';
    protected $type = self::TYPE_SUCCESS;
    protected $form;
    protected $exception;


    public function setIsSuccess($isSuccess)
    {
        $this->isSuccess = $isSuccess;

        return $this;
    }

    public function isSuccess()
    {
        return $this->isSuccess;
    }

    public function setMsg($msg)
    {
        $this->msg = $msg;

        return $this;
    }

    public function getMsg()
    {
        return $this->msg;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setAsError($msg)
    {
        $this->setMsg($msg)
            ->setIsSuccess(false)
            ->setType(self::TYPE_ERROR);

        return $this;
    }

    public function setAsSuccess($msg)
    {
        $this->setMsg($msg)
            ->setIsSuccess(true)
            ->setType(self::TYPE_SUCCESS);

        return $this;
    }

    public function setForm(Form $form = null)
    {
        $this->form = $form;

        return $this;
    }

    public function getForm()
    {
        return $this->form;
    }

    public function setException(\Exception $exception)
    {
        $this->exception = $exception;

        return $this;
    }

    public function getException()
    {
        return $this->exception;
    }
}
