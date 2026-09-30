<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Behat PHP crash investigation

This README is the continuity document for the intermittent native PHP crash
investigation tracked in LibreSign issue #8422 and diagnostic pull request
#8913.

It is intentionally more detailed than a normal test README. The investigation
has required many controlled CI experiments, and the main purpose of this file
is to make the state reproducible and understandable without relying on chat
history, local memory, or knowledge held by one maintainer.

If a new maintainer, AI coding assistant, or a new chat continues this work,
**read this file before changing the diagnostic workflow**.

## Related work

- LibreSign issue: https://github.com/LibreSign/libresign/issues/8422
- Diagnostic PR: https://github.com/LibreSign/libresign/pull/8913
- Diagnostic workflow: `.github/workflows/behat-php-crash-diagnostics.yml`
- Reproducer: `tests/integration/scripts/stress-behat-crash.sh`
- PHP runtime control: `tests/integration/scripts/configure-php-runtime.sh`
- Watchdog: `tests/integration/scripts/run-behat-with-watchdog.sh`
- Diagnostic collector: `tests/integration/scripts/collect-php-server-diagnostics.sh`
- Sanitized PHP build: `tests/integration/scripts/build-sanitized-php.sh`

Snapshot when this README was created: PR #8913 head
`292bdfc1c1fb1eee6ee990a4c2b74cae44d3764c`.

Do not treat that SHA as current forever. Always inspect the latest PR head and
the latest diagnostic artifacts before continuing.

## Short version

The original visible Behat error is usually:

```
cURL error 52: Empty reply from server
```

That is **not the root cause**.

The investigation has directly reproduced a native PHP crash with:

- PHP 8.3.35;
- exit status 139;
- SIGSEGV;
- a direct core dump;
- the PHP built-in server process dying before completing the HTTP response;
- GDB stopping at `zend_objects_store_del+141`.

The inspected object pointer at the crash site did not look like a valid live
`zend_object`. The evidence is consistent with an object that had already
been freed, overwritten, or corrupted before `zend_objects_store_del()` became
the visible crash point.

The crash also reproduces when:

- `PHP_CLI_SERVER_WORKERS=0`;
- Imagick is not required;
- tracing JIT is not required;
- OPcache is not required by the already reproduced cases;
- `USE_ZEND_ALLOC=0` is used.

Therefore none of those variables is currently a necessary condition for the
crash.

The strongest application-level signal remains:

```
POST /ocs/v2.php/apps/libresign/api/v1/request-signature
```

especially the Behat scenario:

```
tests/integration/features/file/list.feature
Scenario: Return a list with 3 pages
```

The current task is not to hide the flake. The task is to find the first
invalid memory operation or the smallest deterministic reproducer.

---

## What is proven

The following items have direct evidence and should be treated as facts unless
new evidence contradicts them.

### 1. The HTTP error is a consequence of a PHP process failure

Multiple runs show the sequence:

1. request accepted by the PHP built-in server;
2. PHP terminates before a complete HTTP response;
3. the client reports `cURL error 52: Empty reply from server`;
4. subsequent calls may report `cURL error 7` because the server is gone;
5. the server health checker reports an unhealthy or missing PHP process.

Do not fix this by retrying HTTP requests.

### 2. A real SIGSEGV has been captured

One of the strongest captured reproductions used:

- PHP 8.3.35;
- MariaDB 10.6;
- GitHub Actions Ubuntu runner;
- the PHP built-in development server.

The diagnostic log recorded:

```
Server process exit status: 139 (possibly SIGSEGV)
Segmentation fault (core dumped)
```

A direct core dump was produced.

### 3. The visible native crash frame is in Zend object destruction

GDB stopped at:

```
zend_objects_store_del+141
```

Two independent cores reached the same instruction.

This is important, but it does **not** prove that the original bug starts in
`zend_objects_store_del()`.

The likely categories are still:

- use-after-free;
- double free / repeated destruction;
- an overwritten object pointer;
- memory corruption that happened earlier;
- a PHP core bug;
- a shared extension corrupting memory;
- application code triggering a PHP/runtime bug.

### 4. The object seen at the crash site looks invalid

The diagnostic collector inspects the object pointer passed in `$rdi`,
nearby memory, likely `zend_object` fields, registers, mappings, and the
instruction stream around the program counter.

The captured pointer did not look like a valid live Zend object.

Treat that as evidence of earlier lifetime or memory corruption, not as proof
of which component caused it.

### 5. Multi-worker mode is not required

The crash has reproduced with:

```
PHP_CLI_SERVER_WORKERS=0
```

Therefore the old hypothesis "the crash is caused by
`PHP_CLI_SERVER_WORKERS`" is too strong and should not be repeated.

Multi-worker mode can still affect timing, heap layout, frequency, or expose a
separate PHP CLI-server problem. It is simply not a necessary condition for
this SIGSEGV.

A previous controlled run also showed:

- workers=0/default extensions: SIGSEGV reproduced;
- workers=2/default extensions: one full suite passed;
- workers=2/"minimal": that run ended because the test harness reached its
  runtime limit, not because it proved the same crash.

A single pass is not evidence that workers=2 is safe.

### 6. Database backend is not a necessary condition

The same high-level server disappearance / `cURL error 52` pattern has
appeared in MariaDB, PostgreSQL and MySQL jobs.

This makes a database-specific root cause less likely.

Do not spend time changing database backends unless the experiment is testing
a specific, controlled database hypothesis.

### 7. Imagick is not required

Earlier runner environments included Imagick, which made it a plausible native
extension to investigate.

Controlled runtime work later showed that the crash is not dependent on
Imagick being present.

Do not blame Imagick merely because it is a native extension or because
LibreSign uses it in visible-signature code.

Imagick remains relevant only if a future experiment proves a reproducible
difference when it is added back to an otherwise identical runtime.

### 8. OPcache and tracing JIT are not required by the reproduced crash

There is a verified php-src bug (#20818) where tracing JIT causes a
`zend_objects_store_del()` crash. That mechanism is interesting because the
visible frame is similar.

However, LibreSign has reproduced its crash without requiring tracing JIT, and
OPcache is not required by the already reproduced cases.

Therefore php-src #20818 is not an explanation for the current LibreSign crash.

Keep JIT tests isolated. A JIT-only crash must not be used to explain a
JIT-off reproduction.

### 9. The Zend allocator is not required

The failure survives:

```
USE_ZEND_ALLOC=0
```

Therefore a bug that exists only in Zend MM is not sufficient to explain the
current reproducer.

The allocator is still useful as a diagnostic variable because changing the
allocator changes memory layout and can expose an earlier invalid access more
clearly.

---

## What is not proven

Do not turn any of the following into conclusions without a controlled
reproduction.

We have **not** proven that:

- Zend Engine itself is the original source of the corruption;
- LibreSign application code is doing an invalid memory operation directly;
- any specific PHP extension is responsible;
- `request-signature` alone is sufficient in every run;
- `file/list.feature` is the only trigger;
- mail or notification dispatch is the trigger;
- PHP built-in server workers are the root cause;
- PHP 8.4 or PHP 8.5 are affected in exactly the same way;
- a successful single CI run means a test case is safe;
- a timeout means a native crash happened.

The problem is intermittent and sensitive to process history and memory
layout. Interpret each experiment accordingly.

---

## Strongest reproducer and application path

The most repeated application-level signal is:

```
POST /ocs/v2.php/apps/libresign/api/v1/request-signature
```

The original issue frequently appeared in:

```
tests/integration/features/file/list.feature
Scenario: Return a list with 3 pages
```

This scenario performs several signature-request creations in the same PHP
server lifetime.

Failures have also happened around validation endpoints and other scenarios,
so the current interpretation is:

- the request-signature path is a strong reproducer;
- the crash is not proven to belong to one HTTP route;
- previous requests may influence object lifetime or heap layout;
- the visible crash may occur later than the operation that corrupted memory.

That is why the current diagnostic workflow includes both focused request
generation and feature-history experiments.

---

## Diagnostic experiment families

The matrix in `.github/workflows/behat-php-crash-diagnostics.yml` is the
canonical definition of the active experiments.

Do not infer a case from its name only. Check the effective runtime artifact.

### PHP runtime / extension-layout isolation

Current or recent cases compare:

- PHP 8.3, 8.4 and 8.5;
- OPcache unloaded;
- CLI OPcache loaded with JIT disabled;
- tracing JIT as a separate case;
- default runner extension layout;
- controlled extension layout;
- Imagick added back;
- groups of additional native extensions.

The rule is:

> Interpret the effective `php -m` and `php --ini` output, not only what
> `setup-php` was asked to install.

GitHub runners may already contain extensions or configuration that would make
an experiment misleading if only the requested YAML is inspected.

### Allocator / heap-layout isolation

Cases compare:

- Zend allocator;
- glibc allocator;
- `MALLOC_CHECK_`;
- `MALLOC_PERTURB_`;
- glibc tcache disabled.

These experiments are not "fixes". They are intended to make invalid memory
behavior easier to expose or classify.

### Application-path isolation

Focused flows include:

1. email signer with normal notification;
2. email signer with `notify=0`;
3. account signer with `notify=0`;
4. draft request with no signer;
5. repeated validation calls;
6. exact `Return a list with 3 pages` scenario.

Useful interpretation examples:

- email notify fails but email no-notify repeatedly passes:
  investigate event/mail/notification paths;
- account and email both fail:
  email delivery is less likely to be necessary;
- draft with no signers fails:
  signer and identify-method handling are not necessary;
- validation-only fails:
  the issue is broader than request creation.

Do not draw these conclusions from one run. Repeat a discriminator.

### Test-history / state-contamination isolation

The same PHP process can run progressively larger preludes before
`file/list.feature`:

- list only;
- envelope + list;
- build/sign + envelope + list;
- account + admin + build/sign + envelope + list.

Purpose:

- find whether previous tests are needed to create the bad heap/object state;
- reduce a long full-suite history into the smallest necessary prelude.

If a larger prelude fails while the shorter one repeatedly passes, bisect the
difference.

### Process-lifetime isolation

The same request workload can be run as:

- many requests through one long-lived PHP server;
- one request per fresh PHP/Behat server process.

If the long-lived case repeatedly fails while fresh processes repeatedly pass,
that is strong evidence for accumulated process state, delayed destruction, or
memory corruption surviving across requests.

### ASan / UBSan

The most important root-cause path is a PHP 8.3.35 debug build with:

- `--enable-debug`;
- debug symbols;
- frame pointers;
- AddressSanitizer;
- UndefinedBehaviorSanitizer;
- OPcache disabled;
- controlled extensions;
- `USE_ZEND_ALLOC=0`.

The purpose is to catch the **first invalid read/write/free** rather than the
later crash in `zend_objects_store_del()`.

If ASan reports an earlier invalid access, investigate that event first and
treat the later Zend crash as secondary.

### Valgrind

A smaller focused workload runs under Valgrind Memcheck.

Valgrind:

- changes timing substantially;
- is much slower;
- may suppress an intermittent race/layout-sensitive failure.

Use it as supporting evidence, not as the primary reproducer.

### Xdebug trace

Xdebug has its own isolated case.

It is used to obtain a PHP-level breadcrumb trail close to the crash.

Do not use a crash that appears only with Xdebug as proof of the original root
cause because Xdebug changes:

- executor hooks;
- observer behavior;
- timing;
- memory layout.

---

## Watchdog behavior

`run-behat-with-watchdog.sh` exists to distinguish a normal Behat assertion
failure from test-infrastructure failure.

It tracks:

- Behat log progress;
- PHP server log progress;
- master process liveness;
- expected worker count when workers are enabled;
- total runtime.

It classifies failures such as:

- PHP master exited;
- worker count dropped;
- no progress;
- total runtime exceeded;
- normal command failure.

Important:

- a watchdog timeout is **not** a SIGSEGV;
- a worker-count failure is **not** automatically the same bug;
- the failure classification in the artifact should be read before looking at
  the rest of the logs.

---

## Crash artifact checklist

When a diagnostic job fails, inspect the artifact in this order.

### 1. `failure-summary.txt`

This is the first file to read.

It records, when available:

- diagnostic reason;
- failure classification;
- master PID;
- configured worker count;
- server exit status;
- signal;
- whether a core file belongs to the master process;
- recent worker timeline;
- final PHP server log lines;
- final master-process log lines.

### 2. Effective PHP runtime

Inspect:

- PHP version;
- `php --ini`;
- `php -m`;
- OPcache state;
- allocator environment;
- loaded libraries.

Do not compare two cases until their effective runtimes are known.

### 3. Direct core / GDB output

For a core dump, inspect:

- full backtrace;
- `info registers`;
- `info sharedlibrary`;
- process mappings;
- `info symbol $pc`;
- instructions around `$pc`;
- `$rdi`;
- memory around `$rdi`.

On x86_64, `$rdi` is the first argument to
`zend_objects_store_del(zend_object *object)`.

### 4. Server timeline

Look at:

- the last request accepted;
- whether a response was logged;
- which PID handled it;
- master and worker states;
- whether the crash happened during request execution or after response
  shutdown/destruction.

### 5. Sanitizer or Valgrind reports

An earlier ASan/UBSan/Valgrind invalid access is more useful than the later
`zend_objects_store_del()` SIGSEGV.

---

## Relevant php-src reports

These are mechanism references, not conclusions.

### php-src #10593

https://github.com/php/php-src/issues/10593

An invalid object-store state reaches `zend_objects_store_del()`.

A PHP maintainer explicitly suggested possibilities such as an object already
freed and then freed again during later cleanup.

This is currently one of the closest mechanism-level comparisons.

### php-src #20818

https://github.com/php/php-src/issues/20818

Verified tracing-JIT bug where generated code passes the wrong value type into
object destruction and crashes in `zend_objects_store_del()`.

LibreSign reproduces without requiring tracing JIT, so this specific bug does
not explain the current failure.

### php-src #9400

https://github.com/php/php-src/issues/9400

Known built-in server problem with `PHP_CLI_SERVER_WORKERS`, primarily hanging
requests.

Our crash also reproduces with workers disabled and has a real SIGSEGV, so
#9400 is not sufficient to explain it.

### Other useful mechanism comparisons

The PR investigation has also reviewed reports involving:

- shutdown / use-after-free behavior sensitive to memory layout;
- OPcache map-pointer corruption;
- DOM use-after-free reports;
- re-entrant destruction / double-free behavior.

These are useful for reasoning about possible mechanisms. None has yet been
shown to be the same LibreSign bug.

---

## Rules for continuing the investigation

These rules are important because several early experiments could easily have
produced misleading conclusions.

### Do not change production code yet

A crash frame in Zend is not enough evidence to change LibreSign business
logic.

Only change production code when a controlled experiment identifies a
LibreSign operation that is both:

- necessary for the crash; and
- wrong independently of the crash.

### Do not add retries

Retries can hide the process crash and make the suite green without fixing the
problem.

The investigation is explicitly intended to expose infrastructure failure.

### Change one variable at a time

Once a discriminator is found, avoid a broad matrix expansion.

Prefer:

1. reproduce;
2. change one variable;
3. repeat;
4. compare effective runtime and artifacts.

### Repeat discriminators

Because the crash is intermittent:

- one pass is evidence only;
- one failure is useful but should be repeated when testing causality;
- frequency differences matter only after enough repetitions.

### Preserve exact commits

When comparing environments, pin:

- Nextcloud;
- LibreSign;
- notifications;
- activity;
- guests;
- PHP version.

Do not mix moving `master` branches into a causal comparison.

### Keep normal CI separate

The normal Behat workflows are not the experimental laboratory.

Crash-specific instrumentation should remain in the dedicated diagnostic
workflow unless there is a separate decision to make it permanent.

---

## Current decision tree

Use this order when choosing the next experiment.

### If ASan/UBSan reports an earlier invalid access

Stop reducing the later Zend crash.

Investigate the earliest sanitizer report and reproduce that operation with the
smallest workload possible.

### If the exact list scenario fails without any prelude

Reduce inside `Return a list with 3 pages`:

- number of request-signature calls;
- signer type;
- status;
- notification behavior;
- list calls.

### If the exact list scenario passes but a prelude sequence fails

Bisect the prelude until one feature or scenario is necessary.

Then reduce that feature to the smallest state-changing operation.

### If long-lived fails but fresh-process passes repeatedly

Focus on:

- request shutdown;
- object destruction;
- static/singleton state;
- persistent resources;
- shared extension state;
- object or resource lifetime across requests.

### If one controlled extension group restores the failure

Bisect only that group.

Do not reintroduce all runner extensions.

### If controlled PHP 8.3 fails but 8.4/8.5 repeatedly pass

Compare php-src changes around the first invalid operation.

Do not assume a version fix until the same reduced reproducer has enough runs.

### If all controlled runtimes still fail

The evidence moves toward:

- PHP core;
- a required extension;
- or an application path that triggers a generic engine bug.

At that point prepare a php-src-quality minimal reproducer.

---

## What a php-src reproducer should contain

Do not open an upstream PHP issue with the full LibreSign stack if the problem
can be reduced further.

A useful upstream reproducer should include:

- exact PHP version and build flags;
- CLI-server command;
- whether workers are enabled;
- minimal required extensions;
- OPcache/JIT state;
- allocator state;
- smallest HTTP request sequence;
- deterministic or measured reproduction rate;
- SIGSEGV/core evidence;
- native backtrace with symbols if possible;
- sanitizer output if available.

The target is a reproducer independent from LibreSign and Nextcloud.

---

## Instructions for a new chat or new maintainer

Use this checklist verbatim.

1. Read this README completely.
2. Read issue #8422.
3. Read the current description of PR #8913.
4. Fetch the current PR head; do not assume the snapshot SHA above is current.
5. Inspect `.github/workflows/behat-php-crash-diagnostics.yml`.
6. Inspect the latest runs of **Behat PHP crash diagnostics**.
7. For every failed case, read the artifact `failure-summary.txt` first.
8. Confirm the effective PHP runtime with `php -m`, `php --ini`, OPcache and
   allocator data.
9. Separate:
   - SIGSEGV;
   - sanitizer error;
   - Valgrind error;
   - worker exit;
   - hang;
   - timeout;
   - ordinary Behat failure.
10. Do not infer causality from a single passing case.
11. Update this README whenever a hypothesis is proven, disproven, or a new
    controlled experiment changes the investigation state.
12. Update the PR description with the shorter reviewer-facing summary, while
    keeping this README as the detailed continuity record.

A useful first message for a new AI/chat session is:

> Continue the LibreSign Behat native PHP crash investigation in PR #8913.
> Read tests/integration/scripts/README.md first, then inspect issue #8422,
> the current PR description, the current diagnostic workflow, and the latest
> workflow artifacts. Treat the README as the investigation handoff, but verify
> every current CI result before making changes. Do not change production code
> or add retries unless the controlled evidence justifies it.

---

## Updating this README

This file is part of the debugging process.

Update it when:

- a hypothesis becomes proven or disproven;
- a controlled case gives a meaningful repeated result;
- a new core gives a different crash site;
- ASan/UBSan/Valgrind identifies an earlier invalid operation;
- the smallest reproducer changes;
- a relevant upstream PHP issue is found;
- the diagnostic workflow changes enough that the continuation instructions
  are no longer accurate.

Do not fill it with raw CI logs. Preserve conclusions, experiment definitions,
important exact evidence, and links to the artifacts/PR where raw data can be
retrieved.
