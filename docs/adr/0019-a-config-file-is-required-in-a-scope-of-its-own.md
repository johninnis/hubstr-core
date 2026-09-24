# 19. A config file is required in a scope of its own

## Status

Accepted

## Context

A PHP file that is `require`d runs in the scope of whatever required it. Required from inside a method, a config file can see that method's object as `$this`, and — less exotically — read and overwrite the method's local variables. A config file that assigns `$path`, an ordinary name for a config file to use, changes the loader's own `$path`, and the loader then reports the wrong file in its error message.

## Decision

`ConfigLoader` requires the file inside a static closure, whose only variable is the path it was given. The file sees no object and none of the loader's variables, and cannot alter what the loader goes on to do.

## Consequences

The call reads as needless indirection — `(static fn (string $configFile): mixed => require $configFile)($path)` where `require $path` would appear to do. It carries a comment pointing here, and tests pin both halves: the file cannot see the loader, and cannot change which file the loader names. Do not simplify it back.
