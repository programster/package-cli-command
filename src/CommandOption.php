<?php

namespace Programster\Command;

abstract class CommandOption
{
    /**
     * Get the name of this option. E.g. --encoding=hevc would be "encoding"
     * @return string
     */
    abstract public function getLonghandName() : string;


    /**
     * Get the name of this option. E.g. the Docker port option of: -p=80:80 would be "p"
     * @return ?string - must be a one letter character (case matters). Return null if there is no shorthand for this
     * option.
     */
    abstract public function getShorthandName() : ?string;


    /**
     * Get the possible values for tab completion. This is not used for validating an execution request
     * an execution request. That is still up to the user as all possible values may not be returnable. E.g.
     * if the value just needs to be an integer. These values will only show up if the user has fully entered the
     * option name and not provided a value yet. E.g. "--container-name=", in which case we may want to return a
     * list of all the currently running Docker containers.
     * @return array
     */
    abstract public function getPossibleValuesForTabCompletion() : array;


    public function getPartialMatchingOptionValues(string $value) : array
    {
        $hints = [];
        $hintValues = $this->getPossibleValuesForTabCompletion();

        foreach ($hintValues as $hintValue)
        {
            if (str_starts_with($hintValue, $value))
            {
                $hints[] = $hintValue;
            }
        }

        return $hints;
    }
}