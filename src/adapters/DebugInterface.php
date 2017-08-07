<?php

namespace alkemann\debug;

interface DebugInterface
{
    public static function dump_array(array $array, $debug): array;
    public static function dump_object($obj, $debug): array;
    public static function dump_other($var): string;
    public static function dump_properties(\ReflectionObject $reflection, $obj, string $type, string $rule, Debug $debug): string;
    public static function locationString(array $location): \stdClass;
}
