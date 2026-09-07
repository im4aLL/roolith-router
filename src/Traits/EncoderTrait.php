<?php
namespace Roolith\Route\Traits;

trait EncoderTrait
{
    /**
     * Convert an array or object or string to UTF8
     *
     * Non-UTF-8 strings are assumed ISO-8859-1 (matching the legacy
     * utf8_encode behavior) and converted with mb_convert_encoding, since
     * utf8_encode is deprecated since PHP 8.2. Only public object
     * properties are traversed; non-string scalars pass through as-is.
     *
     * @param $var
     * @param bool $deep
     * @return mixed
     */
    public function anythingToUtf8(mixed $var, bool $deep = true): mixed
    {
        if (is_array($var)) {
            foreach($var as $key => $value){
                if($deep) {
                    $var[$key] = $this->anythingToUtf8($value, $deep);
                } elseif(is_string($value) && !mb_detect_encoding($value, 'utf-8', true)) {
                    $var[$key] = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }
            }
            return $var;
        } elseif (is_object($var)) {
            foreach($var as $key => $value){
                if($deep) {
                    $var->$key = $this->anythingToUtf8($value,$deep);
                } elseif(is_string($value) && !mb_detect_encoding($value,'utf-8',true)) {
                    $var->$key = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }
            }
            return $var;
        } else {
            if (!is_string($var)) {
                return $var;
            }

            return mb_detect_encoding($var,'utf-8',true) ? $var : mb_convert_encoding($var, 'UTF-8', 'ISO-8859-1');
        }
    }
}
