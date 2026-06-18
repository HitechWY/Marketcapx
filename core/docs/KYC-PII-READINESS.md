# KYC / PII Exposure Readiness (B5)

Audit of how KYC / verification documents and other user uploads are named,
stored and served — plus the one backward-compatible hardening applied. No file
migration or bulk filesystem move is performed (out of scope / requires ops
approval).

## Current storage model

- Upload paths come from `App\Constants\FileInfo` (e.g. `verify => assets/verify`,
  plus `assets/images/{user,agent,merchant}`). These live under the **project web
  root** and are served as static files (the app's split layout serves the repo
  root; `core/` is the framework). They are gitignored but publicly reachable in
  production.
- The stored filename is whatever `App\Lib\FileManager::getFileName()` returns;
  callers persist that exact string in the owning row (e.g. KYC data, profile
  image columns) and later build the URL from `path + filename`.
- There is no per-request authorization gate in front of `assets/verify` — access
  control is **filename obscurity**, so the filename must be unguessable.

## Risk found

`getFileName()` previously returned `uniqid() . time() . .<ext>`. Both parts are
time-derived:

- `uniqid()` is the current microtime in hex (not random, not a CSPRNG);
- `time()` is the upload second.

An attacker who knows roughly when a victim submitted KYC can brute-force a small
time window and recover the document URL — a realistic PII/KYC exposure given the
directory is web-served with no auth gate.

## Fix applied (future-safe, backward-compatible)

`getFileName()` now appends 128 bits of CSPRNG entropy:

```php
return uniqid() . time() . bin2hex(random_bytes(16)) . '.' . $ext;
```

- **New uploads** get an unguessable 32-hex-char token before the extension.
- **Existing files are untouched** — their names are already stored in the DB and
  still resolve. No migration, no rename, no bulk move.
- The return shape is still a flat `<token>.<ext>` string, so every caller/reader
  (which stores and re-reads the name verbatim) is unaffected.

Covered by `tests/Unit/FileNameEntropyTest.php`: new names carry a 128-bit token,
keep the original extension, and never collide across 200 generations.

## Deliberately NOT done (needs separate, approved work)

1. **Migrating/renaming existing documents** to high-entropy names — requires a
   filesystem batch move + DB update on production data; ops decision.
2. **Moving `assets/verify` out of the web root** and serving KYC docs through an
   authenticated controller (`Storage::download` behind an admin/owner gate) —
   the correct long-term fix, but it changes the storage location and every read
   path, so it is a separate project, not a future-safe drop-in.
3. Any exposure of sample KYC files for testing — none were read or added.
