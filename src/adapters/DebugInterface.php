<?php

namespace alkemann\debug\adapters;

use alkemann\debug\Debug;

interface DebugInterface
{
    public static function dump_array(array $array, Debug $debug);
    public static function dump_object($obj, Debug $debug);
    public static function dump_other($var);
    public static function dump_properties(\ReflectionObject $reflection, $obj, string $type, string $rule, Debug $debug);
    public static function locationString(array $location);
}
