# TransclusionBot #

TransclusionBot is a simple MediaWiki bot that replaces transclusions of
templates with substitutions. Please note, the bot is only tested on Ubuntu
14.04, and may not work on other distros/Operating Systems.

## Install ##

You will need [Composer](https://getcomposer.org/) installed on your system to
install the dependencies.

```bash
> git clone https://github.com/underscorre/transclusion-bot
> cd transclusion-bot
> composer install
```

## Usage ##

Once you've got a copy of the code, `cd` into the TransclusionBot folder and
run:

```bash
> php bot.php -w http://example.com/api.php -t Example
```

The script takes two required arguments:

### -w, --wiki ###

Specifies the location of the 'api.php' on the wiki the bot should edit on. So,
if you wanted to edit on the English Wikipedia, you would give
`https://en.wikipedia.org/w/api.php` as the `--wiki` argument.

### -t, --template ###

Specifies the template that should be replaced. Do not include the "Template:"
portion of the template's name, just what you'd normally type in when
transcluding the template. So, if you wanted to replace instances of a template
called Template:Delete, you would give `Delete` as the `--template` argument.

### Specifying Username/Password ###

When the script is run with the correct usage, you will be asked to enter a
username/password. To edit anonymously, just press `enter` when asked for a
username.

### Forcing Bot Rights ###

If you want to require that the account editing has bot rights, you can pass the
`-b` or `--forcebot` flag, which will cause the script to exit if the account
does not have bot rights.
