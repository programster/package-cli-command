<?php



namespace Programster\Command;

final class BasicCommandOption extends CommandOption
{
    /**
     * Create a basic command option. This is useful most of the time. However, if you need to dynamically calculate
     * the possible values for tab completion, then one will need to create a class that extends CommandOption instead
     * and fill in the getPossibleValuesForTabCompletion() manually.
     * @param string $longhandName
     * @param string|null $shorthandName
     * @param array $possibleValuesForTabCompletion
     * @throws \Exception
     */
    public function __construct(
        private readonly string $longhandName,
        private readonly ?string $shorthandName,
        private readonly array $possibleValuesForTabCompletion
    )
    {
        if (str_starts_with("-", $this->longhandName))
        {
            throw new \Exception("Do not pass the hyphens to the beginning of option longhand names. These get added for you.");
        }

        if ($this->shorthandName !== null && str_starts_with("-", $this->shorthandName))
        {
            throw new \Exception("Do not pass the hyphens to the beginning of option shorthand names. These get added for you.");
        }
    }


    public function getLonghandName(): string
    {
        return $this->longhandName;
    }


    public function getShorthandName(): ?string
    {
        return $this->shorthandName;
    }


    public function getPossibleValuesForTabCompletion(): array
    {
        return $this->possibleValuesForTabCompletion;
    }
}