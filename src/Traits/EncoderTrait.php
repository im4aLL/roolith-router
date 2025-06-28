<?php
namespace Roolith\Route\Traits;

trait EncoderTrait
{
    /**
     * Convert an array or object or string to UTF8
     *
     * @param $var
     * @param bool $deep
     * @return mixed
     */
    public function anythingToUtf8($var, bool $deep = TRUE): mixed
    {
        if (is_array($var)) {
            foreach($var as $key => $value){
                if($deep) {
                    $var[$key] = $this->anythingToUtf8($value, $deep);
                } elseif(!is_array($value) && !is_object($value) && !mb_detect_encoding($value, 'utf-8', true)) {
                    $var[$key] = utf8_encode(strval($var));
                }
            }
            return $var;
        } elseif (is_object($var)) {
            foreach($var as $key => $value){
                if($deep) {
                    $var->$key = $this->anythingToUtf8($value,$deep);
                } elseif(!is_array($value) && !is_object($value) && !mb_detect_encoding($value,'utf-8',true)) {
                    $var->$key = utf8_encode($var);
                }
            }
            return $var;
        } else {
            return (!mb_detect_encoding($var,'utf-8',true)) ? utf8_encode($var) : $var;
        }
    }
}
