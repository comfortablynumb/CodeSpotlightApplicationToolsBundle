<?php

/**
 * Created by Gustavo Falco <comfortablynumb84@gmail.com>
 */

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Service\Response;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

class BaseResponse extends Response
{
    const TYPE_SUCCESS = 'SUCCESS';
    const TYPE_WARNING = 'WARNING';
    const TYPE_ERROR = 'ERROR';

    protected $isSuccess = true;
    protected $msg = '';
    protected $type = self::TYPE_SUCCESS;
    protected $form;
    protected $data = array();
    protected $exception;
    protected $totalRows = 0;


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

    public function setForm(FormInterface $form = null)
    {
        $this->form = $form;

        return $this;
    }

    public function getForm()
    {
        return $this->form;
    }

    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setException(\Exception $exception)
    {
        $this->exception = $exception;

        if ($this->isSuccess()) {
            $this->setAsError();
        }

        return $this;
    }

    public function getException()
    {
        return $this->exception;
    }

    public function setTotalRows($totalRows)
    {
        $this->totalRows = $totalRows;

        return $this;
    }

    public function getTotalRows()
    {
        return $this->totalRows;
    }

    public function toArray()
    {
        return array(
            'success'       => $this->isSuccess(),
            'type'          => $this->getType(),
            'msg'           => $this->getMsg(),
            'totalRows'     => $this->getTotalRows(),
            'data'          => $this->getData()
        );
    }
}
