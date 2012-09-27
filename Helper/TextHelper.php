<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Helper;

final class TextHelper
{
    public static function toUnderscore($str)
	{
        $str[0] = strtolower($str[0]);
        $func = create_function('$c', 'return "_" . strtolower($c[1]);');
        
        return preg_replace_callback('/([A-Z])/', $func, $str);
	}

    public static function toCamelCase($str, $capitalizeFirstChar = false)
    {
        if($capitalizeFirstChar) {
            $str[0] = strtoupper($str[0]);
        }

        $func = create_function('$c', 'return strtoupper($c[1]);');

        return preg_replace_callback('/_([a-z])/', $func, $str);
	}

    public static function returnInBytes($val)
    {
        $val = trim($val);

        switch (strtolower(substr($val, -1)))
        {
            case 'm': $val = (int)substr($val, 0, -1) * 1048576; break;
            case 'k': $val = (int)substr($val, 0, -1) * 1024; break;
            case 'g': $val = (int)substr($val, 0, -1) * 1073741824; break;
            case 'b':
                switch (strtolower(substr($val, -2, 1)))
                {
                    case 'm': $val = (int)substr($val, 0, -2) * 1048576; break;
                    case 'k': $val = (int)substr($val, 0, -2) * 1024; break;
                    case 'g': $val = (int)substr($val, 0, -2) * 1073741824; break;
                    default : break;
                } break;
            default: break;
        }
        return $val;
    }
}
