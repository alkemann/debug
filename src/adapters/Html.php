<?php

namespace alkemann\debug\adapters;

use alkemann\debug\Debug;

class Html implements DebugInterface
{

    public static function dump_array(array $array, Debug $debug)
    {
        $debug->current_depth++;
        $count = count($array);
        $ret = ' <span class="class">array</span>';
        $ret .= '[<span class="count">' . $count . '</span>]</li>';
        if ($count > 0) {
            $ret .= '<ul class="array">';
            if (in_array('array', $debug->options['avoid'])) {
                $ret .= '<li><span class="empty"> -- Array Type Avoided -- </span></li>';
            } else {
                foreach ($array as $key => $value) {
                    $ret .= '<li>[ <span class="key">' . $key . '</span> ] => ';
                    if (is_string($key) && in_array($key, $debug->options['blacklist']['key'])) {
                        $ret .= '<span class="empty"> -- Blacklisted Key Avoided -- </span></li>';
                        continue;
                    }
                    if ((is_array($value) || is_object($value)) && $debug->current_depth >= $debug->options['depth']) {
                        $ret .= ' <span class="class">array</span> ';
                        $ret .= '[<span class="count">' . count($value) . '</span>]</li>';
                        $ret .= '<ul class="array"><li><span class="empty"> -- Debug Depth reached -- </span></li></ul>';
                        continue;
                    }
                    $ret .= $debug->dump_it($value);
                }
            }
            $ret .= '</ul>';
        }
        $debug->current_depth--;
        return $ret;
    }

    public static function dump_object($obj, Debug $debug)
    {
        $debug->current_depth++;
        $hash = spl_object_hash($obj);
        $id = substr($hash, 9, 7);
        $class = get_class($obj);
        $ret = ' object[ <span class="class-id"> ' . $id . ' </span> ] ';
        $ret .= ' class[ <span class="class">' . $class . '</span> ] </li>';
        $ret .= '<ul class="properties">';
        if (in_array(get_class($obj), $debug->options['blacklist']['class'])) {
            $debug->current_depth--;
            return $ret . '<li><span class="empty"> -- Blacklisted Object Avoided -- </span></li></ul>';
        }
        if (isset($debug->object_references[$hash])) {
            $debug->current_depth--;
            return $ret . '<li><span class="empty"> -- Object Recursion Avoided -- </span></li></ul>';
        }
        if (in_array('object', $debug->options['avoid'])) {
            $debug->current_depth--;
            return $ret . '<li><span class="empty"> -- Object Type Avoided -- </span></li></ul>';
        }
        if ($debug->current_depth > $debug->options['depth']) {
            $debug->current_depth--;
            return $ret . '<li><span class="empty"> -- Debug Depth reached -- </span></li></ul>';
        }
        $debug->object_references[$hash] = true;
        $reflection = new \ReflectionObject($obj);
        $props = '';
        foreach (array(
                     'public' => \ReflectionProperty::IS_PUBLIC,
                     'protected' => \ReflectionProperty::IS_PROTECTED,
                     'private' => \ReflectionProperty::IS_PRIVATE
                 ) as $type => $rule) {
            $props .= self::dump_properties($reflection, $obj, $type, $rule, $debug);
        }
        $debug->current_depth--;
        if ($props == '') {
            return $ret .= '<li><span class="empty"> -- No properties -- </span></li></ul>';
        } else {
            $ret .= $props;
        }
        $ret .= '</ul>';
        return $ret;
    }

    public static function dump_closure(\Closure $c, Debug $debug, bool $include_body = false): string
    {
        $str = '<span class="value">Closure</span> <span class="">function(';
        $r = new \ReflectionFunction($c);
        $params = [];
        foreach($r->getParameters() as $p) {
            $s = '';
            if($p->getClass()) {
                $s .= '<span class="class">' . $p->getClass()->name . ' </span> ';
            } elseif ($p->hasType()) {
                $s .= '<span class="class">' . $p->getType() . '</span> ';
            }

            if($p->isPassedByReference()){
                $s .= '&';
            }
            $s .= '$' . $p->name;
            if($p->isDefaultValueAvailable()) {
                $v = $p->getDefaultValue();
                if (is_array($v)) {
                    $s .= ' = [ ]';
                } else {
                    $s .= ' = ' . static::dump_other($v, TRUE);
                }
            }
            $params []= $s;
        }
        $str .= implode(', ', $params);
        $str .= ')';
        if ($r->hasReturnType()) {
            $t = $r->getReturnType();
            if (class_exists($t)) {
                $t = '<span class="class">' . $t . ' </span> ';
            }
            $str .= ' : ' . $t;
        }
        $str .= '</span>';
        [$start, $end] = [$r->getStartLine(), $r->getEndLine()];
        if ($include_body || $start == $end) {
            // @TODO this is a bit .. naive
            $str .= '<span class="value closure body"><br />';
            $lines = file($r->getFileName());
            if ($start == $end) {
                $c = $lines[$start - 1];
                $fat = strpos($c, '{');
                $len = strpos($c, '}') - $fat + 1;
                $c = substr($c, $fat, $len);
                $str .= '&nbsp;' . $c . '<br />';
            } else {
                if (strpos($lines[$start], '{') === false) {
                    $str .= '&nbsp;{<br />';
                }
                for($l = $start; $l < $end; $l++) {
                    $str .= '&nbsp;' . $lines[$l] . '<br />';
                }
            }
            $str .= '</span>';
        }
        return $str;
    }

    public static function dump_properties(\ReflectionObject $reflection, $obj, string $type, string $rule, Debug $debug)
    {
        $vars = $reflection->getProperties($rule);
        $i = 0;
        $ret = '';
        foreach ($vars as $refProp) {
            $property = $refProp->getName();
            $i++;
            $refProp->setAccessible(true);
            $value = $refProp->getValue($obj);
            $ret .= '<li>';
            $ret .= '<span class="access">' . $type . '</span> <span class="property">' . $property . '</span> ';
            if ($value instanceof \Closure) {
                $ret .= ' : ' . static::dump_closure($value, $debug);
            } elseif (in_array($property, $debug->options['blacklist']['property'])) {
                $ret .= ' : <span class="empty"> -- Blacklisted Property Avoided -- </span>';
            } else {
                $ret .= ' : ' . $debug->dump_it($value);
            }
            $ret .= '</li>';
        }
        return $i ? $ret : '';
    }

    public static function dump_other($var): string
    {
        $length = 0;
        $type = gettype($var);
        switch ($type) {
            case 'boolean':
                $var = $var ? 'true' : 'false';
                break;
            case 'string' :
                $length = strlen($var);
                $var = '\'' . htmlentities($var) . '\'';
                break;
            case 'NULL' :
                return '<span class="empty">NULL</span>';
                break;
        }
        $ret = '<span class="value ' . $type . '">' . $var . '</span>';

        if ($type == 'string') {
            $ret .= '<span class="type">string[' . $length . ']</span>';
        } else {
            $ret .= '<span class="type">' . $type . '</span>';
        }
        return $ret;
    }

    public static function locationString(array $location)
    {
        extract($location);
        $ret = "line: <span>$line</span> &nbsp;" .
            "file: <span>$file</span> &nbsp;";
        $ret .= isset($class) ? "class: <span>$class</span> &nbsp;" : '';
        $ret .= isset($function) && $function != 'include' ? "function: <span>$function</span> &nbsp;" : '';
        return $ret;
    }

    public static function top(array $outputs, ?string $key = null): void
    {
        if (empty($outputs)) {
            return;
        }
        echo '<div id="debugger">';
        if ($key !== null) {
            if (!isset($outputs[$key])) {
                throw new \Exception('DEBUG: Not that many outputs in buffer');
            }
            echo $outputs[$key];
            return;
        }
        foreach ($outputs as $out) {
            echo $out;
        }
        echo '</div>';
    }
}
