This is a wrapper around DDEV so that I can add truly global commands.

See https://ddev.readthedocs.io for the official DDEV documentation.

Normally global commands in DDEV aren't actually global - they're only accessible when you're in the directory of a DDEV project.
In order to add commands that can be used from any directory, this repo can be used to wrap DDEV with a Symfony Console project.

This allows me to have a single binary to interact with DDEV both with its built-in commands and my custom ones.
