<?php
namespace Demo;

class Controller
{
    protected AnotherClass $anotherClass;

    public function __construct(AnotherClass $anotherClass)
    {
        $this->anotherClass = $anotherClass;
    }

    public function index(): array
    {
         return $this->anotherClass->test();
    }

    public function simpleIndex(string $id): string
    {
        return 'Simple Index ' . $id;
    }

    public function simpleIndex2(): string
    {
        return 'Simple Index 2';
    }
}