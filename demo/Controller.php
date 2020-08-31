<?php
namespace Demo;

class Controller
{
    public function index()
    {
        return $this->paramText();
    }

    public function paramText()
    {
        return 'paramText: ';
    }
}