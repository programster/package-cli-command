<?php

namespace Programster\Command;

final class CommandOptionCollection extends \ArrayObject
{
    public function __construct(CommandOption ...$switches)
    {
        parent::__construct($switches);
    }


    public function append(mixed $value) : void
    {
        if ($value instanceof CommandOption)
        {
            parent::append($value);
        }
        else
        {
            throw new Exception("Cannot append non CommandOption to a " . __CLASS__);
        }
    }


    public function offsetSet(mixed $index, mixed $newval) : void
    {
        if ($newval instanceof CommandOption)
        {
            parent::offsetSet($index, $newval);
        }
        else
        {
            throw new Exception("Cannot add a non CommandOption value to a " . __CLASS__);
        }
    }
}