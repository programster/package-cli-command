<?php

namespace Programster\Command;

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
     * Validates this object has a correct structure. Normally this would be done in the constructor, but cannot
     * do that, because we need the user to be able to make their own constructors.
     * @return void
     */
    final public function validate()
    {
        if ($this->getSubCommands() !== null && count($this->getSubCommands()) > 0)
        {
            if (
                   ($this->getOptions() !== null && count($this->getOptions()) > 0)
                || ($this->getSwitches() !== null && count($this->getSwitches()) > 0)
            )
            {
                $msg =
                    "A command with subcommands cannot contain switches/options. " .
                    "Perhaps you meant to add them the subcommand instead?";

                throw new Exception($msg);
            }
        }
    }


    /**
     * This is the entrypoint to the program. The first thing we need to do is figure out if the program
     * was entered asking for tab-completion help, or if it is actually being called to run. Then handle
     * accordingly.
     * @return void
     */
    public function run()
    {
        $this->validate(); // normally this would go in the constructor, but can't do that as this is an abstract class.

        $argv = $GLOBALS['argv'];

        if (count($argv) >= 2)
        {
            if ($argv[1] === "--autocomplete-help")
            {
                $currentTypedWords = array_slice($argv, 2);
                $helpOptions = $this->handleAutocompleteRequest($currentTypedWords);
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
    protected function handleAutocompleteRequest(array $args) : array
    {
        $hints = [];

        if (count($args) === 0)
        {
            // output all possible subcommands/switches/options etc.
            $hints = array_merge($hints, $this->getSubCommandNames());
            $hints = array_merge($hints, $this->getOptionNames(true));
            $hints = array_merge($hints, $this->getSwitchNames(true));
            $hints = array_merge($hints, ($this->getPossibleArgs() ?? []));
        }
        else
        {
            foreach ($args as $index => $arg)
            {
                $isLastWord = ($index === (count($args) - 1));

                if (str_starts_with($arg,"-"))
                {
                    // should be a switch/option
                    if ($isLastWord)
                    {
                        if (str_contains($arg,"="))
                        {
                            // this is a completely typed option with either a partial or complete value.
                            $pos = strpos($arg, "=");
                            $optionName = substr($arg, 0, $pos);
                            $option = $this->getOptionByName($optionName);
                            $optionValue = substr($arg, $pos + 1);
                            $hints = array_merge($hints, $option->getPartialMatchingOptionValues($optionValue));
                        }
                        else
                        {
                            // check if partially completed switch/option in which case return those that it could be,
                            // if not, then return all other possible switches/options/subommands/args.
                            $hints = array_merge($hints, $this->getPartialMatchingSwitches($arg));
                            $hints = array_merge($hints, $this->getPartialMatchingOptions($arg));
                        }


                        if (count($hints) === 0)
                        {
                            // assume that the user has completed an argument, and that they are looking for all available
                            // remaining switches/options etc. If this is the first
                            // @TODO - it would be great to be able to tell if there was a space after the last word.
                            // Perhaps bash autocomplete caret position can assist with this.
                            $hints = array_merge($hints, $this->getSubCommandNames());
                            $hints = array_merge($hints, $this->getSwitchNames(true));
                            $hints = array_merge($hints, $this->getOptionNames(true));
                            $hints = array_merge($hints, ($this->getPossibleArgs()) ?? []);
                        }
                    }
                    else
                    {
                        // continue, as only really care about last word if this word is not a subcommand to hand off to.
                        continue;
                    }
                }
                else
                {
                    // either a command or an arg. Check if is a full command, if so, then hand off to that command to
                    // handle the remaining auto complete.
                    if (in_array($arg, $this->getSubCommandNames()))
                    {
                        $subCommand = $this->getSubCommandByName($arg);
                        $remainingArgs = array_slice($args, 1);
                        $hints = $subCommand->handleAutocompleteRequest($remainingArgs);
                        break;
                    }

                    if ($isLastWord)
                    {
                        $hints = array_merge($hints, $this->getPartialMatchingSubcommands($arg));
                        $hints = array_merge($hints, $this->getPartialMatchingArgs($arg));

                        if (count($hints) === 0)
                        {
                            // assume that the user has completed an argument, and that they are looking for all available
                            // remaining switches/options etc. If this is the first
                            // @TODO - it would be great to be able to tell if there was a space after the last word.
                            // Perhaps bash autocomplete caret position can assist with this.
                            $hints = array_merge($hints, $this->getSubCommandNames());
                            $hints = array_merge($hints, $this->getSwitchNames(true));
                            $hints = array_merge($hints, $this->getOptionNames(true));
                            $hints = array_merge($hints, ($this->getPossibleArgs() ?? []));
                        }
                    }
                    else
                    {
                        continue;
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
                    throw new Exception("Cannot have switches or options before a subcommand.");
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
                $optionNames[] = ($includeHyphens) ? "--{$option->getLonghandName()}" : $option->getLonghandName();

                if ($option->getShorthandName() !== null)
                {
                    $optionNames[] = ($includeHyphens) ? "--{$option->getShorthandName()}" : $option->getShorthandName();
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
                    $switchNames[] = ($includeHyphens) ? "--{$switch->getShorthandName()}" : $switch->getShorthandName();
                }
            }
        }

        return $switchNames;
    }


    /**
     * Get a list of possible arguments for tab completion. For example, if building a tool to help with docker,
     * this might look up the currently running containers, and return their ID's/names (if the tool
     * is expecting a container name/ID).
     * @return array|null
     */
    abstract public function getPossibleArgs() : ?array;


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
    readarray -t COMPREPLY <<< $(' . $commandName . ' --autocomplete-help ${COMP_LINE})
}

complete -F __' . $commandNameUnderscores . '_completions ' . $commandName . PHP_EOL;

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


    # Accessors
    abstract public function getName() : string;
    abstract public function getOptions() : ?CommandOptionCollection;
    abstract public function getSwitches() : ?CommandSwitchCollection;
    abstract public function getSubCommands() : ?CommandCollection;
}