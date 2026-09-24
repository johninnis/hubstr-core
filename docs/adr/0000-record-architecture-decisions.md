# 0. Record architecture decisions

## Status

Accepted

## Context

This library makes a number of design choices whose justification is not obvious from the code alone: where the fault hierarchy is rooted, how templates treat untrusted data, where the shared HTTP host kit draws its boundary, and similar. When the reasoning lives only in a reviewer's head or in a commit message that is hard to find later, future maintainers re-litigate settled questions, reverse decisions without knowing why they were made, or preserve constraints that no longer apply. The "why" needs to travel with the code.

## Decision

We record architecture decisions as Architecture Decision Records (ADRs) kept in `docs/adr/`.

- Each record is a Markdown file named `NNNN-kebab-title.md`, numbered sequentially with a zero-padded four-digit prefix.
- Each record uses the sections Status, Context, Decision, and Consequences.
- A record describes a single decision and stands alone: it explains its own context and reasoning without depending on external style guides or contributor documents.
- Until the first tagged release, every record is kept true to the code by editing it in place: a record that describes anything other than the library as it stands is wrong, and there is no published history for a supersession chain to protect.
- From the first tagged release onward a record is never edited to change its decision; a new record supersedes it, restating the complete current decision, and the old record's Status is set to `Superseded by ADR-NNNN`.

This record is the first in the series and establishes the practice itself. Substantive decisions begin at `0001`.

## Consequences

The rationale behind non-obvious choices is captured next to the code and survives staff changes. Reviewers can point to a record rather than repeat an argument. Before the first release the set always reads as one current account, with no amendments to reassemble; after it, reversing a decision becomes a deliberate, documented act. The cost is the small ongoing discipline of writing a record whenever a non-self-evident decision is made.
