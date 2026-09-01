# Contributing – Branch & PR Flow

## Branching model
- `main` = release-only, protected
- `develop` = integration/staging
- `feature/<topic>` = short-lived branches for each change

## Daily flow
1. Create a feature branch from `develop`:
   - Name: `feature/<short-topic>`
2. Commit small changes; push regularly.
3. Open a Pull Request into `develop`.
4. CI (Dev) runs: PHP 8.2/8.4 lint and regression tests (blocking), PHP 7.4–8.4 compatibility (blocking), PHPCS/PHPStan, and a clean ZIP build.
5. Merge when green; test on staging (Plesk tracks `develop`).

## Releases
1. Open a PR from `develop` → `main`.
2. Bump plugin version in header.
3. CI (Hardening) runs: regression tests, PHP compatibility, and new PHPStan findings **blocking**.
4. Merge when green, tag a release (`vX.Y.Z`). Plesk production tracks `main`.

## Coding checks
- During heavy development, legacy WordPress style findings remain non-blocking.
- The PHPStan baseline records existing findings; newly introduced findings fail builds.
- PHP compatibility is checked for the supported PHP 7.4–8.4 range.

## Notes
- Keep branches focused; write clear commit messages.
- Resolve conflicts in your editor; delete conflict markers.
- Never commit build artifacts, vendor, `.github`, or `stubs/` to releases; CI ZIP excludes them automatically.
