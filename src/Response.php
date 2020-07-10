<?php
namespace Roolith;

use Roolith\HttpConstants\HttpResponseCode;

class Response
{
    protected $statusCode;

    public function __construct()
    {
        $this->statusCode = HttpResponseCode::OK;
    }

    public function setStatusCode($code = HttpResponseCode::OK)
    {
        $this->statusCode = $code;
    }

    public function body($content)
    {
        if (is_array($content) || is_object($content)) {
            echo $this->setHeaderJson()->outputJson($content);
        } else {
            echo $this->setHeaderHtml()->outputHtml($content);
        }
    }

    protected function setHeaderJson()
    {
        header('Content-Type: application/json; charset=UTF-8');

        return $this;
    }

    protected function setHeaderHtml()
    {
        header('Content-Type: text/html; charset=UTF-8');

        return $this;
    }

    protected function outputJson($content)
    {
        return json_encode($this->anythingToUtf8($content));
    }

    protected function outputHtml($content)
    {
        return $this->anythingToUtf8($content);
    }

    protected function anythingToUtf8($var, $deep = TRUE) {
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