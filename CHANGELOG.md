# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Immutable `Definition` with `DefinitionBuilder` and `DefinitionValidator` reporting unreachable
  states, dead ends, duplicate transitions, unknown states, missing or multiple initial states,
  forks in state machines and final states with exits.
- `#[StateMachine]`, `#[State]` and `#[Transition]` attributes for enums and classes, loaded by `AttributeLoader`.
- `Machine` runtime with `apply()`, `can()`, `blockers()`, `enabledTransitions()`, `initialize()`,
  `isFinished()`, and before/after hooks with a wildcard.
- State machine and workflow (parallel places, forks and joins) modes.
- `PropertyMarkingStore` and `MethodMarkingStore`, enum aware.
- `Guard` interface, closure guards and `GuardResolver` backed by an optional PSR-11 container.
- PSR-14 events: `TransitionStarted`, `TransitionCompleted`, `TransitionBlocked`, `GuardFailed`.
- Audit trail port with `InMemoryAuditTrail` and `PdoAuditTrail`, plus migrations for MySQL,
  PostgreSQL and SQLite.
- `MermaidDumper` and `GraphvizDumper`.
- `bin/statecraft` with `validate` and `render` commands.
- Examples: order lifecycle and document approval workflow.

[Unreleased]: https://github.com/nikrybak/statecraft/commits/main
