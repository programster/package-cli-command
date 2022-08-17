CLI Command PHP Package
=======================

A package to simplify the creation of CLI commands in PHP, with BASH autocompletion support.


### Execute Parameters
When writing your `execute` function, be aware that the keys for the options and switches will 
always be the longhand name, and without any hyphens. E.g. `shell` instead of `s` when the user 
enters `--shell` or `-s`.

### Option Value Validation
We don't validate any of the values that get passed into the options, so you will need to perform
any validation yourself.


## Example
Below is an example, in which I have written a command that wraps around docker-exec, to allow
me to tab-complete the container names, and then enter them by ID, defaulting to the BASH shell,
but allowing the user to provide the `--shell` or `-s` option to specify they want to use `sh` 
instead.

```php
class DockerEnter extends \Programster\Command\Command
{
    public function execute(array $options, array $switches, array $args): void
    {
        if (count($args) !== 1)
        {
            throw new Exception("You must pass the ID or name of the container you wish to enter.");
        }

        $nameOrId = $args[0];

        $info = shell_exec("docker ps --format '{{ json .}}'");
        $lines = array_filter(explode(PHP_EOL, $info));
        $handled = false;
        $shell = array_key_exists("shell", $options) ? "/bin/{$options['shell']}" : "/bin/bash";

        foreach ($lines as $line)
        {
            $containerArray = json_decode($line, true);

            if ($containerArray['ID'] === $nameOrId)
            {
                passthru("docker exec -it {$containerArray['ID']} {$shell}");
                $handled = true;
                break;
            }
            elseif ($containerArray['Names'] === $nameOrId)
            {
                passthru("docker exec -it {$containerArray['ID']} {$shell}");
                $handled = true;
                break;
            }
        }

        if (!$handled)
        {
            die("There is no container with that ID or name.");
        }
    }
    
    public function getPossibleArgs(): ?array
    {
        $hints = [];
        $info = shell_exec("docker ps --format '{{ json .}}'");
        $lines = array_filter(explode(PHP_EOL, $info));

        foreach ($lines as $line)
        {
            $containerArray = json_decode($line, true);
            $hints[] = $containerArray['ID'];
            $hints[] = $containerArray['Names'];
        }

        return $hints;
    }
    
    public function getOptions(): ?CommandOptionCollection
    {
        return new CommandOptionCollection(
            new BasicCommandOption("shell", "s", ["bash", "sh"])
        );
    }

    public function getSwitches(): ?CommandSwitchCollection
    {
        return null;
    }

    public function getSubCommands(): ?CommandCollection
    {
        return null;
    }

    public function getName(): string
    {
        return "enter";
    }
}
```

## Install Command
Once you have built a command using this framework, you will want to know how to "install" it,
so that you can execute it from anywhere, and the BASH autocomplete functionality works.

### Put It In Your $PATH
Place the command, or a symlink to it, in your $PATH at */usr/bin/{command name}*. Make sure the
executable flag is set.


### Creating The BASH Completion File
Unfortunately, one still needs to create a completion file for your program, in order to tell 
BASH to ask your program for the tab hints. One case easily create this using your program, using
the "hidden" `--generate-autocomplete-file` switch like so:

```bash
my-command --generate-autocomplete-file | sudo tee /etc/bash_completion.d/dothis-completion.bash > /dev/null
```

Alternatively, you can manually create your own completion script. Below is an example for a custom 
command you created called `my-command`.

```bash
#!/usr/bin/env bash
__my_command_completions()
{
    readarray -t COMPREPLY <<< $(my-command --autocomplete-help ${COMP_LINE})
}

complete -F __my_command_completions dothis
```

Now open a new BASH shell, and you should see it working!.



