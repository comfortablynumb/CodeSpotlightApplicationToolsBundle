<?php

namespace CodeSpotlight\Bundle\ApplicationToolsBundle\Helper;

final class FileHelper
{
    private function __construct()
    {
    }

    public static function dirname($path)
    {
        return dirname(self::normalize($path));
    }

    public static function basename($path)
    {
        return basename(self::normalize($path));
    }

    public static function normalize($path)
    {
        return array_reduce(explode('/', $path), create_function('$a, $b', '
			if($a === 0)
				$a = "/";

			if($b === "" || $b === ".")
				return $a;

			if($b === "..")
				return dirname($a);

			return preg_replace("/\/+/", "/", "$a/$b");
		'), 0);
    }

    public static function combine($root, $rel1)
    {
        $arguments = func_get_args();

        return self::normalize(array_reduce($arguments, create_function('$a,$b', '
			if(is_array($a))
				$a = array_reduce($a, "Path::combine");
			if(is_array($b))
				$b = array_reduce($b, "Path::combine");

			return "$a/$b";
		')));
    }
}
