<?php

/**
 * Created by Gustavo Falco <comfortablynumb84@gmail.com>
 */

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service\Response;

use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Response;

class BaseResponse extends Response
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
        return $this->type === self::TYPE_SUCCESS;
    }

    public function isWarning()
    {
        return $this->type === self::TYPE_WARNING;
    }

    public function isError()
    {
        return $this->type === self::TYPE_ERROR;
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

    public function setAsError()
    {
        $this->setType(self::TYPE_ERROR);

        return $this;
    }

    public function setAsWarning()
    {
        $this->setType(self::TYPE_WARNING);

        return $this;
    }

    public function setAsSuccess()
    {
        $this->setType(self::TYPE_SUCCESS);

        return $this;
    }

    public function setMsgAsError($msg)
    {
        $this->setMsg($msg)
            ->setAsError();

        return $this;
    }

    public function setMsgAsWarning($msg)
    {
        $this->setMsg($msg)
            ->setAsWarning();

        return $this;
    }

    public function setMsgAsSuccess($msg)
    {
        $this->setMsg($msg)
            ->setAsSuccess();

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
