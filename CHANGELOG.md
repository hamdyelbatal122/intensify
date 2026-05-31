# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.7.0] — 2026-05-31

### Features

- **WebhookDriver**: Outbound webhook serial driver for pushing serial frames to external HTTP endpoints.
- **DiagnoseCommand**: Beautiful CLI diagnostic tool (`portflow:diagnose`) checking cache health, driver configurations, and serial mapping validations.
- **HasBufferPersistence**: Extracted common persistence logic for `BarcodeLineDriver`, `RfidAsciiDriver`, and `FingerprintPacketDriver` into a reusable Trait.
- **Rs232Driver Buffer Persistence**: Integrated the buffer persistence trait into the RS-232 driver to prevent data loss.
- **DriverRegistry Caching**: Added instance-level caching in the driver registry to optimize performance.
- **EscPosBuilder Reset**: Added `reset()` to easily reuse a builder instance.
- **SerialFrame Interface Implementation**: Implemented `Arrayable` and `JsonSerializable` on `SerialFrame` DTO.
- **PortFlow Facade annotations**: Added `registerDriver` to PHPDocs.

### Bug Fixes

- **MessageRouter**: Event mismatch logging level changed from `error` to `warning` to match documentation.
- **BladeEscPosRenderer Trimming**: Changed line trimming from `trim()` to `rtrim()` to preserve formatting and indentation space in Blade ESC/POS print templates.

### Tests

- Added robust test suite with 96 total passing tests, including new test cases for `WebhookDriver` and `DiagnoseCommand`.

### Code Quality

- PHPStan level 8: **0 errors** compliance.

---

## [0.6.0] — 2026-05-05

### Security

- **IngestController**: Whitelist allowed `context` keys (`session_id`, `source`, `device`, `baud_rate`, `device_name`) — reject arbitrary keys that could be used for privilege escalation ([#security])
- **ListenSerialCommand**: Tighten device path regex to only allow real TTY nodes (`/dev/ttyXxx`, `/dev/serial/by-id/*`, `/dev/serial/by-path/*`) — prevent path traversal via `/dev/shm/` or similar writable paths
- **PortFlowServiceProvider**: Rate limiter now keys by `IP + user ID` — prevents single-IP bypass from shared/proxied networks

### Features

- **PortFlowServiceProvider**: Config validation at boot — invalid driver classes, missing `SerialDriver` implementation, or non-existent event/model classes in `portflow.mappings` now throw `PortFlowException` immediately at boot rather than failing silently at runtime
- **Facades/PortFlow**: Added full `@method` PHPDoc annotations (`ingest`, `encode`, `print`, `health`) for IDE completion and PHPStan level 8 compatibility
- **PortFlowException**: New factory methods `invalidDriver()` and `invalidConfiguration()` for typed configuration errors

### Bug Fixes

- **MessageRouter**: `SerialEvent` interface is now **enforced** — events not implementing `SerialEvent` are rejected with `Log::error` and routing is skipped (previously only logged a warning and continued)
- **FingerprintPacketDriver**: Added `Log::warning` on checksum mismatch to surface corrupted packets; clarified that base64 cache storage is intentional for binary-safe serialisation; fixed `unpack()` return type handling to prevent silent offset errors
- **ListenSerialCommand**: Added type-safe `optionString()` / `argumentString()` helpers — eliminates unsafe `(string)` casts on `mixed` option/argument values (PHPStan level 8 compliance)

### Tests

- Added **`BarcodeLineDriverTest`** (11 test cases): single/multi barcode, custom delimiter, prefix stripping, empty line filtering, encode round-trip, field assertions
- Added **`RfidAsciiDriverTest`** (11 test cases): STX/ETX parsing, uppercase toggle, custom delimiters, empty tag filtering, partial frame buffering, encode round-trip
- Added **`FingerprintPacketDriverTest`** (13 test cases): command/ack/data/end-data packets, checksum validation, garbage discarding, custom start code, encode round-trip, field assertions
- Added **`FrameRoutingIntegrationTest`** (8 test cases): full ingest → event dispatch pipeline for raw-json/barcode-line/rfid-ascii/escpos drivers, SerialEvent enforcement, buffer persistence across split requests, context key whitelisting, queue routing, health endpoint, encode helper

**Test count: 19 → 86 tests, 172 assertions**

### Code Quality

- PHPStan level 8: **0 errors** (was 19 errors pre-release)
- All `unpack()` results guarded against `false` return
- `chr()` arguments explicitly narrowed to `int<0, 255>` with `& 0xFF`

---

## [0.5.2](https://github.com/hamdyelbatal122/PortFlow/compare/v0.5.1...v0.5.2) (2026-05-04)


### Bug Fixes

* remove duplicate file content + add browser compatibility support ([9447cf7](https://github.com/hamdyelbatal122/PortFlow/commit/9447cf79de47e35b74b5364eacb9b7466aae1b7c))

## [0.5.1](https://github.com/hamdyelbatal122/PortFlow/compare/v0.5.0...v0.5.1) (2026-05-04)


### Bug Fixes

* resolve all Pint code-style violations (CI Quality job) ([dece7be](https://github.com/hamdyelbatal122/PortFlow/commit/dece7be66f1c60f0a89d8cf8ec68fb8bc4df4d95))

## [0.5.0](https://github.com/hamdyelbatal122/PortFlow/compare/v0.4.0...v0.5.0) (2026-05-04)


### Features

* professional hardening — rate limiting, queue routing, buffer persistence, reconnect, make:driver command, feature tests ([53abaad](https://github.com/hamdyelbatal122/PortFlow/commit/53abaadbbf6bc08c5b39a07b110f31692cf21c6c))


### Bug Fixes

* resolve PHPStan errors in MakeDriverCommand + comprehensive README rewrite ([68f359f](https://github.com/hamdyelbatal122/PortFlow/commit/68f359f60024469ed568b630200c144a2f2aaa87))

## [0.4.0](https://github.com/hamdyelbatal122/PortFlow/compare/v0.3.0...v0.4.0) (2026-05-04)


### Features

* rename package Synapse to PortFlow ([f299eec](https://github.com/hamdyelbatal122/PortFlow/commit/f299eec9bd40459876f62a858f270de6d0728419))

## [0.3.0](https://github.com/hamdyelbatal122/PortFlow/compare/v0.2.0...v0.3.0) (2026-05-03)

### Features

* Laravel 13 support, PHP matrix CI, README corrections, PHPStan level 8

### Bug Fixes

* Add typed array PHPDoc annotations — resolves all 24 PHPStan errors
* **phpstan:** remove redundant ?? null on non-empty-list offset

## [0.2.0](https://github.com/hamdyelbatal122/PortFlow/compare/v0.1.0...v0.2.0) (2026-05-03)

### Features

* Professional review — EscPosBuilder commands (`bold`, `underline`, `align`, `divider`), `PortFlowException`, clean unit tests, README overhaul
* Add typed PHPDoc array annotations across all drivers and services — resolves 24 PHPStan errors

## [0.1.0](https://github.com/hamdyelbatal122/PortFlow/releases/tag/v0.1.0) (2026-05-03)

### Features

* Initial release: hardware bridge package with `RawJsonDriver`, `EscPosDriver`, `Rs232Driver`
* Web Serial JS bridge (`PortFlowBridge`), Livewire components, ESC/POS printing engine
* Clean Architecture (Domain / Application / Infrastructure), CI/CD, release-please automation
