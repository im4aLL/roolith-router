<?php
namespace Roolith;

use Roolith\HttpConstants\HttpResponseCode;

class Response
{
    protected $statusCode;
    protected $hasHeaderContentType;

    public function __construct() {
        $this->hasHeaderContentType = false;
    }

    public function setStatusCode($code = HttpResponseCode::OK)
    {
        $this->statusCode = $code;
        http_response_code($code);

        return $this;
    }

    public function body($content)
    {
        if (!$this->statusCode) {
            $this->setStatusCode(HttpResponseCode::OK);
        }

        if (is_array($content) || is_object($content)) {
            echo $this->setHeaderJson()->outputJson($content);
        } else {
            echo $this->setHeaderHtml()->outputHtml($content);
        }
    }

    public function setHeaderJson()
    {
        if (!$this->hasHeaderContentType) {
            header('Content-Type: application/json; charset=UTF-8');
            $this->hasHeaderContentType = true;
        }

        return $this;
    }

    public function setHeaderHtml()
    {
        if (!$this->hasHeaderContentType) {
            header('Content-Type: text/html; charset=UTF-8');
            $this->hasHeaderContentType = true;
        }

        return $this;
    }

    public function setHeaderPlain()
    {
        if (!$this->hasHeaderContentType) {
            header('Content-Type: text/plain; charset=UTF-8');
            $this->hasHeaderContentType = true;
        }

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