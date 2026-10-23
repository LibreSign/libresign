<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Twig sandbox compatibility profile

`TwigSandboxSafeFeatures.php` is generated, not a manually maintained allowlist.
Twig 3.30 classifies its safe tests natively, but does not yet label common
safe tags, filters and functions. For those, this generator reads the
`isAlwaysAllowedInSandbox()` metadata **directly from a pinned official Twig
4.x checkout**, intersects it with the installed Twig 3.x capabilities, and
writes a reviewed snapshot for the LibreSign runtime.

For an intentional update (outside production):

```sh
git clone https://github.com/twigphp/Twig.git /tmp/libresign-twig4-reference
git -C /tmp/libresign-twig4-reference checkout 92fc7b14903529462615b23166c02862d2dcff52
(cd /tmp/libresign-twig4-reference && composer install --no-dev --no-scripts --no-plugins)
php tools/generate-twig-sandbox-profile.php /tmp/libresign-twig4-reference --check
# After reviewing/advancing the pinned commit in the generator:
php tools/generate-twig-sandbox-profile.php /tmp/libresign-twig4-reference --write
```

The source must match the SHA pinned in the generator. `--check` exits nonzero
if the committed snapshot differs. Upgrading upstream must not silently expand
production permissions: review the generated diff, the runtime exceptions in
`SandboxedTemplateRenderer.php`, and the security regression tests first.

The runtime never contacts Twig's repository and never loads Twig 4.x. The
`macro` tag is deliberately denied due to unbounded recursion potential.
The `raw` and `date` filters remain explicitly allowed for existing LibreSign
footer and signature-stamp templates. Twig sandbox permission is not HTML
sanitization or a CPU/memory quota. Reassess the macro constraint when moving
to Twig 4, where upstream may treat it as always allowed.
