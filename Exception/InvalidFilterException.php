<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Exception;

class InvalidFilterException extends BaseException
{
    public static function invalidFilter($filter)
    {
        return new self(sprintf('Requested filter "%s" is invalid.', $filter));
    }
}
