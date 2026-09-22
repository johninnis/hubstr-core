# 0008. The config loader returns the values, not the runtime config

## Status

Accepted

## Context

Every service is configured by one PHP file returning an array. Five of its keys are the same in every service — `host`, `port`, `trusted_proxies`, `database_path`, `log_level` — and the kernel parses those into `ServiceRuntimeConfig`. The rest are the service's own: the relay's `admin_pubkey` and `relay_url`, blossom's `tenant_pubkeys` and `max_upload_bytes`, the signer's `ncryptsec` and `relays`.

A loader that hands back the typed `ServiceRuntimeConfig` reads as the natural, finished shape, and it is uncallable. A service needs the same array for its own keys, and such a loader has discarded it. Every service is then pushed into skipping the loader and reimplementing its body — the environment-variable override, the missing-file fault, the `require` — and copies of that kind drift: one keeps the check that the file returned an array, another casts the result and loses it.

## Decision

`ConfigLoader` is built with the name of the environment variable that may point it at another file, and `load(string $defaultPath)` returns a `ConfigValues`. The kernel turns a file into values; a service turns values into its typed config.

Every config class, `ServiceRuntimeConfig` included, is built by `fromValues(ConfigValues)`, and a service's own `load()` is that applied to the loader's result: one line. `ServiceRuntimeConfig` reads the five shared keys; the service reads its own from the same values.

What `ConfigValues` is and how strictly it reads is recorded in [0017](0017-config-reads-are-strict-and-a-bad-value-stops-the-service.md), its refusal of unknown keys in [0018](0018-an-unknown-config-key-is-an-error-and-the-known-keys-are-listed.md), and the scope the file runs in in [0019](0019-a-config-file-is-required-in-a-scope-of-its-own.md).

## Consequences

The file-loading path exists once. The array check and the missing-file fault cannot drift between services, because no service has a copy.

Do not give `ConfigLoader` a typed return, however much tidier `load(): ServiceRuntimeConfig` looks: that is the shape no service can call.

The cost is that the config classes and their tests name a kernel type rather than taking a bare array; a test builds `ConfigValues::fromArray([...])`. That is the intended coupling.
