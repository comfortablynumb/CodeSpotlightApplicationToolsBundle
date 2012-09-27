<?php

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
    protected $additional = array();
    protected $exception;
    protected $totalRows = 0;
    protected $dataIndex = 'data';
    protected $successIndex = 'success';
    protected $msgIndex = 'msg';
    protected $typeIndex = 'type';
    protected $totalRowsIndex = 'totalRows';
    protected $additionalIndex = 'additional';
    protected $returnOnlyData = false;

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

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setAdditional($additional)
    {
        $this->additional = $additional;

        return $this;
    }

    public function getAdditional()
    {
        return $this->additional;
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

    public function setDataIndex($dataIndex)
    {
        $this->dataIndex = $dataIndex;

        return $this;
    }

    public function getDataIndex()
    {
        return $this->dataIndex;
    }

    public function setMsgIndex($msgIndex)
    {
        $this->msgIndex = $msgIndex;

        return $this;
    }

    public function getMsgIndex()
    {
        return $this->msgIndex;
    }

    public function setSuccessIndex($successIndex)
    {
        $this->successIndex = $successIndex;

        return $this;
    }

    public function getSuccessIndex()
    {
        return $this->successIndex;
    }

    public function setTotalRowsIndex($totalRowsIndex)
    {
        $this->totalRowsIndex = $totalRowsIndex;

        return $this;
    }

    public function getTotalRowsIndex()
    {
        return $this->totalRowsIndex;
    }

    public function setTypeIndex($typeIndex)
    {
        $this->typeIndex = $typeIndex;

        return $this;
    }

    public function getTypeIndex()
    {
        return $this->typeIndex;
    }

    public function setReturnOnlyData($bool)
    {
        $this->returnOnlyData = $bool;

        return $this;
    }

    public function getReturnOnlyData()
    {
        return $this->returnOnlyData;
    }

    public function toArray()
    {
        return $this->returnOnlyData ? $this->getData() : array(
            $this->successIndex         => $this->isSuccess(),
            $this->typeIndex            => $this->getType(),
            $this->msgIndex             => $this->getMsg(),
            $this->totalRowsIndex       => $this->getTotalRows(),
            $this->dataIndex            => $this->getData(),
            $this->additionalIndex      => $this->getAdditional()
        );
    }
}
