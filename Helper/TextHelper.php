<?php

/**
 * Created by Gustavo Falco <comfortablynumb84@gmail.com>
 */

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Helper;

class TextHelper
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
}
