<?php

/*
 * A switch for a command. Could not use "Switch" as it is a reserved programming word.
 */

namespace Programster\Command;

class CommandSwitch
{
    public function __construct(private readonly string $longhand, private readonly ?string $shorthand=null)
    {
        if (str_starts_with("--", $longhand) === false)
        {
            throw new \Exception("Command switches must start with '--'");
        }

        if (str_starts_with("--", $shorthand))
        {
            throw new \Exception("Command switches must start with a single '-'");
        }

        if (str_starts_with("-", $shorthand) === false)
        {
            throw new \Exception("Command switches must start with a single '-'");
        }
    }


    public function getLonghandName() : string { return $this->longhand; }
    public function getShorthandName() : string { return $this->shorthand; }
}