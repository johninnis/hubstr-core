# 0011. A service logs to standard output only, without blocking

## Status

Accepted

## Context

Every service runs on one amphp event loop: one process, one thread, many connections held open at once. Anything that blocks that thread blocks every connection. Writing a log line is the thing a service does far more often than it queries its database — at the default level the relay logs every connection, disconnection and authentication — so how it is written matters more than it looks.

Monolog's own file and stream handlers write synchronously. Wired the usual way, to a rotating file and to standard output, every record is two blocking writes on the loop, and a slow disk or a backed-up output pipe stalls the whole service. The record is also stored twice. The services ship systemd units and tell operators to read the log with `journalctl`, so a rotating file duplicates what the journal already holds, and brings with it a rotation policy, a directory to create, a path key in every config, and a write path to grant in every unit file.

## Decision

A service writes its log to one sink, its standard output, through amphp's stream handler, which writes without blocking the loop. There is no log file. The supervisor that started the process — journald under the shipped units, a container runtime, a terminal — keeps, rotates and ships what it receives, which is that supervisor's job and one it already does.

`LoggerFactory` is built with the sink and a `LogLevel` and creates a PSR-3 logger per channel. The sink is a constructor argument rather than a hard-wired standard output, so the composition root passes `getStdout()` and the behaviour — what is written, what is dropped, one line per record — is tested against a buffer with no real I/O. Records are formatted as plain single lines with no terminal colouring, because the usual reader is a journal, not a terminal. A line ends where its content ends: Monolog's line formatter leaves the spaces that surround an empty context in place, so a record without one would end in two spaces, and `TrimmedLineFormatter` removes them so that a line can be matched to its end.

There is no `log_path` key. A config that carries one is refused at start-up by name, like any other unknown key. `log_level` is the operator's control: how much a service says is theirs to choose, where it goes is not.

## Consequences

Logging cannot stall the loop, a record is stored once, and the kernel owns no rotation, no log directory and no second handler. A service needs no write path for logs.

An operator who wants a file gets one from the supervisor — a journald export, a `StandardOutput=` directive, a container log driver — not from the service. Do not add a file handler back beside the stream handler "as an option": it returns the blocking write this removes, and a second destination is a second thing to configure, test and keep consistent. Do not swap the handler for Monolog's own `StreamHandler` on `php://stdout` as a simplification either; it looks equivalent and is the blocking write again.

The cost falls on anyone running a service by hand with its output discarded: they see no log at all. That is the correct failure for a process whose output nobody is collecting.

An output that is closed is a different case from one that is discarded, and it ends the service. When whatever was reading the output exits — a pipe into a command that finishes, a supervisor that closes its end — the next record cannot be written, and that failure is a fault like any other: nothing catches it, and the process ends. A service in that state keeps serving for as long as it has nothing to say and stops at its next record; during start-up that is at once, and when the record is the one written on shutdown, the shutdown is abrupt rather than graceful (see [0007](0007-the-lifecycle-drains-through-the-server-stop-callback.md)). This is deliberate. A service that cannot log would otherwise run unobserved, and ending it lets the supervisor start it again with a working output. Do not wrap the handler so that a failed write is ignored, which Monolog makes easy: it keeps the service up by hiding the one condition an operator most needs to hear about.
