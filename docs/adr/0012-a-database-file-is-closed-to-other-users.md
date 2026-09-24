# 12. A database file is closed to other users

## Status

Accepted

## Context

A file that PDO creates takes whatever permissions the process inherits. Under the usual umask that is `rw-r--r--` inside a directory anyone can enter: every local user on the host can read the database. For the relay that is public data. For the signer it is the pairing secret of every connected app, held beside the permissions each was granted, on a machine where the vault is unlocked and signing. Nothing in a deployment reads these files as another user — the reverse proxy talks to the service over a socket — and a unit file that sets no umask, or a README that says nothing about the data directory, leaves the default standing.

The kernel cannot know which of its services keep secrets, and a rule that depends on each service remembering to ask for privacy is the rule that gets forgotten for the one that needed it. No other user has a reason to read a service's database, so the safe default is simply the default.

## Decision

A database core opens is closed to other users, in two steps.

A new database is created owner-only. `connect()` narrows the process umask for the duration of the open, so the file is `rw-------` from the instant it exists rather than being created open and corrected afterwards, and restores the umask it found. SQLite gives the write-ahead log and the shared-memory file the permissions of the database they belong to, so they follow.

An existing database has access for *other* users removed each time it is connected to, along with its `-wal` and `-shm` files. This is what covers a database created before this rule, and one restored from a backup with looser permissions. Group access is left exactly as it is found: core never grants it, so a group bit is an operator's deliberate act — a backup user, a maintenance account — and revoking it on every restart would be core overruling the person running the service.

The change of mode is attempted and its failure is not a fault. It fails when the connecting process does not own the file, which is the case of an operator's own account running a maintenance tool against a group-shared database; the owning service corrects the mode the next time it connects, which is far more often.

## Consequences

A service's data is private without any service asking for it, and deployments that predate the rule are corrected the next time they start. Do not remove the umask handling from `connect()` as clutter around a one-line open, and do not replace it with a `chmod` after the fact: that leaves the file readable for the moment between creation and correction, and the moment a secret-bearing file is created is the wrong one to leave open.

The umask is process-wide state, changed and restored around a synchronous call. Nothing else runs on the event loop between the two, so no other file is created under the narrowed mask; the restore is in a `finally` so a failed open cannot leave it narrowed.

The directory is not restricted: `data/` may hold more than the database, a listing reveals only file names, and an operator who wants the directory private can make it so without core undoing it. An in-memory database has no file and is untouched.
