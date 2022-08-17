<?php

/*
 * A switch for a command. Could not use "Switch" as it is a reserved programming word.
 */

namespace Programster\Command;

use Programster\Command\exceptions\ExceptionInvalidSwitch;

class CommandSwitch
{
    public function __construct(private readonly string $longhand, private readonly ?string $shorthand=null)
    {
        if (str_starts_with("--", $longhand) === false)
        {
            throw new ExceptionInvalidSwitch("Command switches must start with '--'");
        }

        if (str_starts_with("--", $shorthand))
        {
            throw new ExceptionInvalidSwitch("Command switches must start with a single '-'");
        }

        if (str_starts_with("-", $shorthand) === false)
        {
            throw new ExceptionInvalidSwitch("Command switches must start with a single '-'");
        }

        if (strlen($shorthand) > 2)
        {
            throw new ExceptionInvalidSwitch("Invalid shorthand switch '{$shorthand}'. Shorthand switches must consist of a single character. E.g. '-r'.");
        }
    }


    public function getLonghandName() : string { return $this->longhand; }
    public function getShorthandName() : string { return $this->shorthand; }
}