<?php

namespace Programster\Command;

final class CommandCollection extends \ArrayObject
{
    public function __construct(Command ...$subcommands)
    {
        parent::__construct($subcommands);
    }


    public function append(mixed $value) : void
    {
        if ($value instanceof Command)
        {
            parent::append($value);
        }
        else
        {
            throw new Exception("Cannot append non Command to a " . __CLASS__);
        }
    }


    public function offsetSet(mixed $index, mixed $newval) : void
    {
        if ($newval instanceof Command)
        {
            parent::offsetSet($index, $newval);
        }
        else
        {
            throw new Exception("Cannot add a non Command value to a " . __CLASS__);
        }
    }
}