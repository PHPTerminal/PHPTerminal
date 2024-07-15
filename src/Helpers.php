<?php

if (!function_exists('base_path')) {
    function base_path($path = '') {//Change this for composer
        return __DIR__ . '/..' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('trace')) {
    function trace(array $varsToDump = [], $exit = true, $args = false, $object = false, $file = true, $line = true, $class = true, $function = true, $returnTraces = false) {
        $backtrace = debug_backtrace();

        $traces = [];

        foreach ($backtrace as $key => $trace) {
            if ($file && isset($trace['file'])) {
                $traces[$key]['file'] = $trace['file'];
            }
            if ($line && isset($trace['line'])) {
                $traces[$key]['line'] = $trace['line'];
            }
            if ($class && isset($trace['class'])) {
                $traces[$key]['class'] = $trace['class'];
            }
            if ($function && isset($trace['function'])) {
                $traces[$key]['function'] = $trace['function'];
            }
            if ($args && isset($trace['args'])) {
                $traces[$key]['args'] = $trace['args'];
            }
            if ($object && isset($trace['object'])) {
                $traces[$key]['object'] = $trace['object'];
            }
        }

        if ($returnTraces) {
            return $traces;
        }

        $reversedTraces = array_reverse($traces);
        $lastTrace = $reversedTraces[array_key_last($reversedTraces)];

        echo 'Trace called at line: <strong>' . $lastTrace['line'] . '</strong> on file: <strong>' . $lastTrace['file'] . '</strong>';

        if ($object) {
            if (class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
                if (count($varsToDump) > 0) {
                    foreach ($varsToDump as $var) {
                        dump($var);
                    }
                }
                dump($reversedTraces);
            } else {
                if (count($varsToDump) > 0) {
                    foreach ($varsToDump as $var) {
                        var_dump($var);
                    }
                }
                var_dump($reversedTraces);
            }
        } else {
            if (count($varsToDump) > 0) {
                foreach ($varsToDump as $var) {
                    var_dump($var);
                }
            }
            var_dump($reversedTraces);
        }

        if ($exit) {
            exit;
        }
    }
}

if (!function_exists('json_trace')) {
    function json_trace($e) {
        $json = [];
        $json['class']   = $e::class;
        $json['message'] = $e->getMessage();
        $json['code']    = $e->getCode();
        $json['file']    = $e->getFile();
        $json['line']    = $e->getLine();

        $json['originalTrace'] = [];
        foreach ($e->getTrace() as $item) {
            $item['args']            = [];
            $json['originalTrace'][] = $item;
        }

        return json_encode($json, 16);
    }
}

if (!function_exists('checkCtype')) {
    function checkCtype($str, $ctype = 'alnum', $ignoreChars = null) {
        if (!$ignoreChars) {
            $ignoreChars = [' ', '&amp;', '&', ',', ':', ';'];
        }

        if ($ctype === 'alnum') {
            if (ctype_alnum(trim(str_replace($ignoreChars, '' , $str)))) {
                return trim(str_replace($ignoreChars, '' , $str));
            }
        } else if ($ctype === 'alpha') {
            if (ctype_alpha(trim(str_replace($ignoreChars, '' , $str)))) {
                return trim(str_replace($ignoreChars, '' , $str));
            }
        } else if ($ctype === 'digits') {
            if (ctype_digit(trim(str_replace($ignoreChars, '' , $str)))) {
                return trim(str_replace($ignoreChars, '' , $str));
            }
        }

        return false;
    }
}

if (!function_exists('true_flatten')) {
    function true_flatten(array $array, array $parents = [])
    {
        $return = [];
        foreach ($array as $k => $value) {
            $p = empty($parents) ? [$k] : [...$parents, $k];
            if (is_array($value)) {
                $return = [...$return, ...true_flatten($value, $p)];
            } else {
                $return[implode(' > ', $p)] = $value;
            }
        }

        return $return;
    }
}

if (!function_exists('extractLineFromFile')) {
    function extractLineFromFile($file, $word)
    {
        $lineWithWord = NULL;

        $handle = fopen($file, "r");

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, $word) === 0) {
                    $parts = explode(' ', $line);

                    $lineWithWord = rtrim(trim($parts[1]), ';');

                    break;
                }
            }

            fclose($handle);
        }

        return $lineWithWord;
    }
}