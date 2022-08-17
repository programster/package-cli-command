<?php

namespace Programster\Command;

final class CommandSwitchCollection extends \ArrayObject
{
    public function __construct(CommandSwitch ...$switches)
    {
        parent::__construct($switches);
    }


    public function append(mixed $value) : void
    {
        if ($value instanceof CommandSwitch)
        {
            parent::append($value);
        }
        else
        {
            throw new Exception("Cannot append non CommandSwitch to a " . __CLASS__);
        }
    }


    public function offsetSet(mixed $index, mixed $newval) : void
    {
        if ($newval instanceof CommandSwitch)
        {
            parent::offsetSet($index, $newval);
        }
        else
        {
            throw new Exception("Cannot add a non CommandSwitch value to a " . __CLASS__);
        }
    }
}