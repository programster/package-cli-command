<?php

namespace Programster\Command;

use Programster\Command\exceptions\ExceptionInvalidCommandDefinition;
use Programster\Command\exceptions\ExceptionInvalidSwitch;

abstract class Command
{
    /**
     * @param array $options - an associative array of option-name/value pairs provided by the user.
     * E.g. a user passing --encoding=hevc would turn into ["encoding" => "hevc"]
     * @param array $switches - an associative array of switch-name/value pairs provided by the user.
     * E.g. a user passing --recursive would turn into ["recursive" => true]
     * @param array $args - a collection of arguments passed by the user.
     * @return void
     */
    abstract public function execute(array $options, array $switches, array $args) : void;


    /**
     * Get the name of this command. This will match against arguments the user types.
     * This only really applies if this command is acting as a subcommand to another command.
     * @return string
     */
    abstract public function getName() : string;


    /**
     * Get all of the options that are applicable to this command. An option is something like
     * --encoding=hevc (the user provides a value).
     * @return CommandOptionCollection|null - a collectino of options, or possibly null if there are none
     */
    abstract public function getOptions() : ?CommandOptionCollection;


    /**
     * Get a list of possible arguments for tab completion. For example, if building a tool to help with Docker,
     * this might look up the currently running containers, and return their ID's/names (if the tool
     * is expecting a container name/ID).
     *
     * @return array|null - an array of string arguments, such as ["bob", "dick", "harry"], or possibly null if there
     * are none.
     */
    abstract public function getPossibleArgs() : ?array;


    /**
     * Get all of the subcommands to this command, or null if there are no subcommands. A subcommand would be
     * something like "remote" when you enter the command "git remote".
     * @return CommandCollection|null
     */
    abstract public function getSubCommands() : ?CommandCollection;


    /**
     * Get all of the switcehs that are applicable to this command. An option is something like
     * --recursive (the user doesn't provide a value, it just turns a setting "on"). Settings are
     * assumed to be false/off if not provided.
     * @return CommandOptionCollection|null - a collectino of options, or possibly null if there are none
     */
    abstract public function getSwitches() : ?CommandSwitchCollection;


    /**
     * Validates this object has a correct structure. Normally this would be done in the constructor, but cannot
     * do that, because we need the user to be able to make their own constructors.
     * @return void
     */
    final protected function validate()
    {
        // check all subcommand names are unique.
        $subCommandNames = [];
        $subCommands = $this->getSubCommands() ?? [];

        foreach ($subCommands as $subCommand)
        {
            /* @var $subCommand Command */
            if (array_key_exists($subCommand->getName(), $subCommandNames))
            {
                throw new \ExceptionInvalidCommandDefinition("Sub command name: {$subCommand->getName()} is not unique.");
            }

            $subCommandNames[$subCommand->getName()] = 1;
        }

        // check all option names are unique.
        $optionNames = [];
        $options = $this->getOptions() ?? [];

        foreach ($options as $option)
        {
            /* @var $option CommandOption */
            if (array_key_exists($option->getLonghandName(), $optionNames))
            {
                throw new ExceptionInvalidCommandDefinition("Option name: {$option->getLonghandName()} is not unique.");
            }
            else
            {
                $optionNames[$option->getLonghandName()] = 1;
            }

            if (array_key_exists($option->getShorthandName(), $optionNames))
            {
                throw new ExceptionInvalidCommandDefinition("Option name: {$option->getShorthandName()} is not unique.");
            }
            else
            {
                $optionNames[$option->getShorthandName()] = 1;
            }
        }

        // check all switch names are unique.
        $switchNames = [];
        $switches = $this->getSwitches() ?? [];

        foreach ($switches as $switch)
        {
            /* @var $switch CommandSwitch */
            if (array_key_exists($switch->getLonghandName(), $switchNames))
            {
                throw new ExceptionInvalidCommandDefinition("Switch name: {$switch->getLonghandName()} is not unique.");
            }
            else
            {
                $switchNames[$switch->getLonghandName()] = 1;
            }

            if (array_key_exists($switch->getShorthandName(), $switchNames))
            {
                throw new ExceptionInvalidCommandDefinition("Switch shorthand: {$switch->getShorthandName()} is not unique.");
            }
            else
            {
                $switchNames[$switch->getShorthandName()] = 1;
            }
        }
    }


    /**
     * This is the entrypoint to the program. The first thing we need to do is figure out if the program
     * was entered asking for tab-completion help, or if it is actually being called to run. Then handle
     * accordingly.
     * @return void
     */
    final public function run()
    {
        $this->validate(); // normally this would go in the constructor, but can't do that as this is an abstract class.

        $argv = $GLOBALS['argv'];

        if (count($argv) >= 2)
        {
            if ($argv[1] === "--autocomplete-help")
            {
                $cursorHasSpaceAfterLastWord = (intval($argv[2]) === 1);
                $currentTypedWords = array_slice($argv, 3);
                $helpOptions = $this->handleAutocompleteRequest($currentTypedWords, $cursorHasSpaceAfterLastWord);
                print(implode(PHP_EOL, $helpOptions));
            }
            elseif ($argv[1] === "--generate-autocomplete-file")
            {
                $this->outputBashAutocompletionFileContent();
            }
            else
            {
                // This program is being executed, rather than asking for tab completion help.
                $programArgs = array_slice($argv, 1);
                $name = $argv[1];
                $this->handleExecutionRequest($programArgs);
            }
        }
        else
        {
            // program was called directly (not asking for tab completion) and with no arguments
            $this->handleExecutionRequest([]);
        }
    }


    /**
     * Handle a request to get autocomplete hints.
     * @param array $args
     * @return array
     * @throws \Exception
     */
    protected function handleAutocompleteRequest(array $args, bool $cursorHasSpaceAfterLastWord) : array
    {
        $hints = [];

        if (count($args) === 0)
        {
            // output all possible subcommands/switches/options etc.
            $hints = array_merge($hints, $this->appendSpacesToArrayElements($this->getSubCommandNames()));
            $hints = array_merge($hints, $this->getOptionNames(true));
            $hints = array_merge($hints, $this->appendSpacesToArrayElements($this->getSwitchNames(true)));
            $hints = array_merge($hints, ($this->appendSpacesToArrayElements($this->getPossibleArgs()) ?? []));
        }
        else
        {
            foreach ($args as $index => $arg)
            {
                $isLastWord = ($index === (count($args) - 1));

                // Handle autocompleting last word switch. If switch/option and not last word dont care.
                if (str_starts_with($arg,"-"))
                {
                    if ($isLastWord)
                    {
                        if ($cursorHasSpaceAfterLastWord === false)
                        {
                            if (str_contains($arg,"="))
                            {
                                // this is a completely typed option name with either a partial or complete value.
                                $pos = strpos($arg, "=");
                                $optionName = substr($arg, 0, $pos);
                                $option = $this->getOptionByName($optionName);
                                $optionValue = substr($arg, $pos + 1);
                                $hints = array_merge($hints, $this->appendSpacesToArrayElements($option->getPartialMatchingOptionValues($optionValue)));
                            }
                            else
                            {
                                // check if partially completed switch/option in which case return those that it could be,
                                // if not, then return all other possible switches/options/subommands/args.
                                $hints = array_merge($hints, $this->appendSpacesToArrayElements($this->getPartialMatchingSwitches($arg)));
                                $hints = array_merge($hints, $this->getPartialMatchingOptions($arg));
                            }
                        }
                        else
                        {
                            // output all switches/options etc. Not outputting subcommands because subcommands should
                            // never come after a switch/option.
                            $hints = array_merge($hints, $this->appendSpacesToArrayElements($this->getSwitchNames(true)));
                            $hints = array_merge($hints, $this->getOptionNames(true));
                            $hints = array_merge($hints, ($this->appendSpacesToArrayElements($this->getPossibleArgs()) ?? []));
                            break;
                        }
                    }
                    else
                    {
                        // don't care about switches/options that aren't the last item. Nothing to autocomplete.
                    }
                }
                else
                {
                    // This is either a command or an arg (doesn't start with -)
                    if ($isLastWord === false)
                    {
                        // Check if is a full command, if so, then hand off to that
                        // command to handle the remaining auto complete.
                        if (in_array($arg, $this->getSubCommandNames()))
                        {
                            $subCommand = $this->getSubCommandByName($arg);
                            $remainingArgs = array_slice($args, 1);
                            $hints = $subCommand->handleAutocompleteRequest($remainingArgs, $cursorHasSpaceAfterLastWord);
                            break;
                        }
                        else
                        {
                            // this is just an arg in the middle of the entire string. Ignore it
                        }
                    }
                    else
                    {
                        // this is the last word in the string and it is either an arg or a subcommand
                        if ($cursorHasSpaceAfterLastWord)
                        {
                            if (in_array($arg, $this->getSubCommandNames()))
                            {
                                $subCommand = $this->getSubCommandByName($arg);
                                $remainingArgs = array_slice($args, 1);
                                $hints = $subCommand->handleAutocompleteRequest($remainingArgs, $cursorHasSpaceAfterLastWord);
                                break;
                            }
                            else
                            {
                                // this is an ending arg with a space after it, ignore the arg, and just output all
                                // options for this command:
                                $hints = array_merge($hints, $this->appendSpacesToArrayElements($this->getSubCommandNames()));
                                $hints = array_merge($hints, $this->getOptionNames(true));
                                $hints = array_merge($hints, $this->appendSpacesToArrayElements($this->getSwitchNames(true)));
                                $hints = array_merge($hints, $this->appendSpacesToArrayElements(($this->getPossibleArgs() ?? [])));
                            }
                        }
                        else
                        {
                            // this is potentially a partially completed argument or command. Output matches.
                            $hints = array_merge($hints, $this->appendSpacesToArrayElements($this->getPartialMatchingSubcommands($arg)));
                            $hints = array_merge($hints, $this->appendSpacesToArrayElements($this->getPartialMatchingArgs($arg)));
                            break;
                        }
                    }
                }
            }
        }

        return $hints;
    }


    /**
     * Parse the inputted command for execution. This is NOT tab completion.
     * @param array $args
     * @return void
     * @throws Exception
     */
    public function handleCommandArgs(array $args)
    {
        $hasOptionsOrSwitches = false;

        foreach ($args as $index => $arg)
        {
            $isCommand = false;

            if (str_starts_with($arg, "--"))
            {
                // this is an option or a longhand switch
                if (str_contains($arg, "="))
                {
                    // this is an option
                    $parts = explode("=", $arg);
                    $optionName = $parts[0];
                    $optionValue = $parts[1];

                }
                else
                {
                    // this is a switch.
                }
            }
            else
            {
                // is subcommand, find out which one and hand off to them.
                if ($hasOptionsOrSwitches)
                {
                    // @todo - derive a better error message than this.
                    throw new ExceptionInvalidCommandDefinition("Cannot have switches or options before a subcommand.");
                }
            }
        }
    }


    /**
     * Get a list of all the subcommand names.
     * @return array
     */
    protected function getSubCommandNames()
    {
        $subCommandNames = [];
        $subCommands = $this->getSubCommands() ?? [];

        if (count($subCommands) > 0)
        {
            foreach ($subCommands as $subCommand)
            {
                /* @var $subCommand Subcommand */
                $subCommandNames[] = $subCommand->getName();
            }
        }

        return $subCommandNames;
    }


    /**
     * Get one of this object's subcommands by name.
     * @param string $name
     * @return Subcommand
     * @throws \Exception
     */
    protected function getSubCommandByName(string $name) : Command
    {
        $subcommand = null;
        $subCommands = $this->getSubCommands() ?? [];

        if (count($subCommands) > 0)
        {
            foreach ($subCommands as $candidate)
            {
                /* @var $candidate Subcommand */
                if ($candidate->getName() === $name)
                {
                    $subcommand = $candidate;
                    break;
                }
            }
        }

        if ($subcommand === null)
        {
            throw new \Exception("There is no subcommand with name: {$name}");
        }

        return $subcommand;
    }





    /**
     * Whether to include the -- or - charagers before the option names.
     * @param bool $includeHyphens
     * @return array - a list of option longhand/shorthand names.
     */
    protected function getOptionNames(bool $includeHyphens) : array
    {
        $optionNames = [];
        $options = $this->getOptions() ?? [];

        if (count($options) > 0)
        {
            foreach ($options as $option)
            {
                /* @var $option CommandOption */
                $optionNames[] = ($includeHyphens) ? "--{$option->getLonghandName()}=" : $option->getLonghandName();

                if ($option->getShorthandName() !== null)
                {
                    $optionNames[] = ($includeHyphens) ? "-{$option->getShorthandName()}=" : $option->getShorthandName();
                }
            }
        }

        return $optionNames;
    }


    /**
     * Whether to include the -- or - charagers before the option names.
     * @param bool $includeHyphens
     * @return array - a list of option longhand/shorthand names.
     */
    protected function getSwitchNames(bool $includeHyphens) : array
    {
        $switchNames = [];
        $switches = $this->getSwitches() ?? [];

        if (count($switches) > 0)
        {
            foreach ($switches as $switch)
            {
                /* @var $switch CommandOption */
                $switchNames[] = ($includeHyphens) ? "--{$switch->getLonghandName()}" : $switch->getLonghandName();

                if ($switch->getShorthandName() !== null)
                {
                    $switchNames[] = ($includeHyphens) ? "-{$switch->getShorthandName()}" : $switch->getShorthandName();
                }
            }
        }

        return $switchNames;
    }


    /**
     * Return a list of arguments that might match up to what the user was beginning to type.
     * @param $word - a partially completed word that the user was typing.
     * @return array
     */
    protected function getPartialMatchingArgs($word) : array
    {
        $matches = [];
        $args = $this->getPossibleArgs() ?? [];

        if (count($args) > 0)
        {
            foreach ($args as $candidate)
            {
                if (str_starts_with($candidate, $word))
                {
                    $matches[] = $candidate;
                }
            }
        }

        return $matches;
    }


    /**
     * Return a list of arguments that might match up to what the user was beginning to type.
     * @param $word - a partially completed word that the user was typing.
     * @return array
     */
    protected function getPartialMatchingSubcommands($word) : array
    {
        $matches = [];
        $subcommands = $this->getSubCommandNames();

        if (count($subcommands) > 0)
        {
            foreach ($subcommands as $candidate)
            {
                if (str_starts_with($candidate, $word))
                {
                    $matches[] = $candidate;
                }
            }
        }

        return $matches;
    }


    protected function getPartialMatchingSwitches($word) : array
    {
        $matches = [];
        $switchNames = $this->getSwitchNames(true);

        if (count($switchNames) > 0)
        {
            foreach ($switchNames as $candidate)
            {
                if (str_starts_with($candidate, $word))
                {
                    $matches[] = $candidate;
                }
            }
        }

        return $matches;
    }


    protected function getPartialMatchingOptions($word) : array
    {
        $matches = [];
        $optionNames = $this->getOptionNames(true);

        if (count($optionNames) > 0)
        {
            foreach ($optionNames as $candidate)
            {
                if (str_starts_with($candidate, $word))
                {
                    $matches[] = $candidate;
                }
            }
        }

        return $matches;
    }


    /**
     * Handle a request to execute the program.
     * @return void
     */
    protected function handleExecutionRequest($passedArgs)
    {
        $handled = false;
        $executionSwitches = [];
        $executionOptions = [];
        $executionArgs = [];

        if (count($passedArgs) > 0)
        {
            foreach ($passedArgs as $index => $passedArg)
            {
                if (str_starts_with($passedArg,"-"))
                {
                    // either a switch or option. We can tell based on if there is a = in it.
                    if (str_contains($passedArg, "="))
                    {
                        // using strpos/substr instead of explode, in case value has a = in it. E.g. --something="a=b"
                        $pos = strpos($passedArg, "=");
                        $nameWithHyphens = substr($passedArg, 0, $pos);
                        $switchName = ltrim($nameWithHyphens, "-");
                        $option = $this->getOptionByName($switchName, false);
                        $value = substr($passedArg, $pos+1);
                        $executionOptions[$option->getLonghandName()] = $value;
                    }
                    else
                    {
                        //switch
                        // using strpos/substr instead of explode, in case value has a = in it. E.g. --something="a=b"
                        $switchName = ltrim($passedArg,  "-");
                        $executionSwitches[$switchName] = true;
                    }
                }
                else
                {
                    // either a command or an arg. Check if is a command, if so, then hand off to that command to handle
                    if (in_array($passedArg, $this->getSubCommandNames()))
                    {
                        $subCommand = $this->getSubCommandByName($passedArg);
                        $remainingArgs = array_slice($passedArgs, 1);
                        $subCommand->handleExecutionRequest($remainingArgs);
                        $handled = true;
                        break;
                    }
                    else
                    {
                        $executionArgs[] = $passedArg;
                    }
                }
            }
        }

        if ($handled === false)
        {
            $this->execute($executionOptions, $executionSwitches, $executionArgs);
        }
    }


    public function outputBashAutocompletionFileContent()
    {
        $args = $GLOBALS['argv'];
        $commandName = basename($args[0]);
        $commandNameUnderscores = str_replace("-", "_", $this->getName());

        $content =
'#!/usr/bin/env bash
__' . $commandNameUnderscores . '_completions()
{
    REGEXP="*[[:space:]]"

    if [[ ${COMP_LINE} == ${REGEXP} ]]; then
        ENDS_IN_SPACE=1
    else
        ENDS_IN_SPACE=0
    fi
    
    readarray -t COMPREPLY <<< $(' . $commandName . ' --autocomplete-help ${ENDS_IN_SPACE} ${COMP_LINE})
}

complete -o nospace -F __' . $commandNameUnderscores . '_completions ' . $commandName . PHP_EOL;
        // -o nospace makes sure tab wont append a space after filling in a suggestion.
        // this is required for us to keep the cursor beside the end of a "--my-option=" so that we then suggest values
        // for the option:
        // https://stackoverflow.com/questions/2339246/add-spaces-to-the-end-of-some-bash-autocomplete-options-but-not-to-others

        echo $content;
    }


    /**
     * Get a switch by name. The name could be longhand/shorthand (without any hyphens).
     * @param string $name
     * @return CommandSwitch
     */
    protected function getSwitchByName(string $name)
    {
        $matchedSwitch = null;
        $switches = $this->getSwitches() ?? [];

        if (count($switches) > 0)
        {
            foreach ($switches as $switch)
            {
                /* @var $switch \Programster\Command\CommandSwitch */
                if ($switch->getLonghandName() === $name || $switch->getShorthandName() === $name)
                {
                    $matchedSwitch = $switch;
                    break;
                }
            }
        }

        if ($matchedSwitch === null)
        {
            throw new \Exception("There is no switch with name '{$name}'.");
        }

        return $matchedSwitch;
    }


    /**
     * Get an option by name. The name could be longhand/shorthand and with/without the starting hyphens.
     * @param string $name
     * @return CommandSwitch
     */
    protected function getOptionByName(string $name)
    {
        if (str_starts_with($name, "-"))
        {
            $name = ltrim($name, "-");
        }

        $matchedOption = null;
        $options = $this->getOptions() ?? [];

        if (count($options) > 0)
        {
            foreach ($options as $candidate)
            {
                /* @var $candidate \Programster\Command\CommandOption */
                if ($candidate->getLonghandName() === $name || $candidate->getShorthandName() === $name)
                {
                    $matchedOption = $candidate;
                    break;
                }
            }
        }

        if ($matchedOption === null)
        {
            throw new \Exception("There is no option with name '{$name}'.");
        }

        return $matchedOption;
    }


    /**
     * Helper method that simply appends a space to all of the values in an array.
     * @param array $inputArray
     * @return array
     */
    private function appendSpacesToArrayElements(array $inputArray)
    {
        foreach ($inputArray as $index => $value)
        {
            $inputArray[$index] = "{$value} ";
        }

        return $inputArray;
    }

}