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
4. CI (Dev) runs: PHP lint (blocking), PHPCS/PHPStan (non-blocking), build ZIP.
5. Merge when green; test on staging (Plesk tracks `develop`).

## Releases
1. Open a PR from `develop` → `main`.
2. Bump plugin version in header.
3. CI (Hardening) runs: all checks **blocking**.
4. Merge when green, tag a release (`vX.Y.Z`). Plesk production tracks `main`.

## Coding checks
- During heavy dev: PHPCS/PHPStan findings do **not** fail builds.
- Before release: we tighten rules and/or use a PHPStan baseline to catch regressions.

## Notes
- Keep branches focused; write clear commit messages.
- Resolve conflicts in your editor; delete conflict markers.
- Never commit build artifacts, vendor, `.github`, or `stubs/` to releases; CI ZIP excludes them automatically.