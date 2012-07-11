<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Exception;

class InvalidFormException extends BaseException
{
    public static function invalidForm()
    {
        return new self('Form is invalid.');
    }
}
