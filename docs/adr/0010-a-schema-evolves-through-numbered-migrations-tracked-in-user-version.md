# 10. A schema evolves through numbered migrations tracked in user_version

## Status

Accepted

## Context

The smallest way to give a service a schema is one file of `CREATE TABLE IF NOT EXISTS` statements, executed on every start. It works for exactly one version of a schema. `IF NOT EXISTS` makes a table that already exists a no-op, so a column added to the file never reaches a database created before it, and a column no longer wanted cannot be removed at all. While a database can simply be deleted that costs nothing; once a service holds data its operator cares about, the first schema change has nowhere to go.

The substrate is the kernel's (see [0004](0004-a-database-is-named-by-a-value-not-opened-by-a-static-factory.md)), every service needs the same answer, and the answer has to exist from the first release: a database created with no version record cannot later tell a migrator what it contains.

## Decision

A schema is a directory of migrations, `resources/migrations/NNNN-description.sql`, numbered contiguously from `0001`. `SchemaMigrator::migrate(string $directory)` applies, in order, every migration whose number is greater than the database's `PRAGMA user_version`. There is one way to bring a database up to date: no single re-executed schema file exists beside the migrations.

Each migration runs in its own `BEGIN IMMEDIATE` transaction together with the statement that sets `user_version` to its number. SQLite's DDL is transactional and `user_version` lives in the database header, so a migration either happens completely and is recorded, or fails, rolls back, and is not. The version is read again once the write lock is held: a service and its command-line tools each migrate on start, and the one that waited for the lock must not re-run what the other just applied.

A migration file therefore contains no `BEGIN`, `COMMIT` or `ROLLBACK` of its own, and the migrator checks rather than trusts that. A migration that commits for itself ends the transaction it was given: what ran before its `COMMIT` is permanent, nothing after it can be rolled back, and the version has not been recorded, so the next start would run the file again against tables it already made. The migrator asks the connection whether it is still in a transaction once the file has run, and again before rolling back a failure, and reports that the migration ended its transaction — with the statement that failed, if one did — instead of attempting a rollback that cannot happen and whose own error would bury the real one. Any failure while a migration runs, not only one the database raises, releases the write lock before it is reported. It cannot undo what such a file committed; it can make sure the first run of a bad migration, which is a service's own test suite migrating a fresh database, says exactly what is wrong. The check reads the connection's state and not the file's text, because `BEGIN … END` is also how a trigger body is written.

Two smaller guarantees sit beside it. A write lock the migrator cannot take is reported against the migration that was waiting, not as a bare driver error. And the migrator refuses, at construction, a connection that does not throw on error: in PDO's silent mode a failing statement returns `false`, and a migration that failed would be recorded as applied.

The sequence is validated, and every migration file read, before anything is applied, so a fault in a later migration never leaves a database upgraded part of the way. Loading is the sequence's job and applying is the migrator's: `MigrationSequence::fromDirectory()` is the one place that touches the filesystem, each `Migration` it yields carries its own SQL, and `SchemaMigrator` holds nothing but the connection. A migration already applied is read too, so a shipped file that has gone missing or empty stops the service at start-up rather than waiting to be noticed. A file that is not named `NNNN-description.sql`, a gap, two files claiming one number, a sequence that does not start at `0001`, an empty directory, a file that cannot be read, and an empty file — which would record a version for no change, and is almost always a migration someone forgot to write — are all refused, because each means the shipped migrations are not what their author believes. A database whose version is *ahead* of the newest migration is refused too: older code must not run against a schema it has never seen.

`user_version` is used rather than a bookkeeping table because it is the mechanism SQLite provides for exactly this, needs no schema of its own, and is a single integer that rolls back with the migration. A migrations table records *which* migrations ran, which matters when branches apply them out of order; a contiguous, validated sequence makes that impossible by construction, so the count is the whole truth.

A database that predates its version record is adopted rather than rebuilt. When a service's first migration is written with `IF NOT EXISTS`, running it against a database that already holds those tables changes nothing and records version 1. Only that first migration needs `IF NOT EXISTS`. Later ones run exactly once and should fail loudly if the schema is not what they expect.

## Consequences

A schema change is a new numbered file. It reaches every existing database on the next start, in order, atomically, and a failed change leaves the database as it was with the fault naming the file. Do not edit a migration that has shipped — a database that already ran it will never run it again — and do not reintroduce a single re-executed `schema.sql` beside the migrations as a "simpler" path for fresh databases: two descriptions of one schema are what drift.

Adoption trusts that an unversioned database matches the first migration. One that lacks some column of `0001` is not repaired by it.

There are no down-migrations. Rolling back a release means restoring a backup, and the refusal to run against a newer database is what stops an old binary from quietly misreading a new schema.

SQLite's limits apply inside a migration: `PRAGMA foreign_keys` cannot be changed within a transaction, so a table rebuild uses `PRAGMA defer_foreign_keys = ON`, and statements that cannot run in a transaction at all, such as `VACUUM`, do not belong in one.
