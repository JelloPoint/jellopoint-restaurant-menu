# Fase 1X-R — WordPress.org submission preparation

Version **2.0.41**. Branch
`feature/phase-1x-r-wordpress-org-submission`.

This phase prepares the independent Free edition for its initial manual review
by the WordPress.org Plugin Team. It does not publish the plugin.

## Fixed submission identity

- Display name: `JelloPoint – Restaurant Menu`
- Requested slug: `jellopoint-restaurant-menu`
- WordPress.org account: `jellopoint` (confirmed by the account owner)
- Tested up to: WordPress 7.1
- Minimum WordPress: 6.5
- Minimum PHP: 7.4
- Text domain: `jellopoint-restaurant-menu`
- License of the combined distribution: GPLv3

## Submission artifact

Only the independently generated Free ZIP may be submitted. It must:

1. contain one root folder named `jellopoint-restaurant-menu`;
2. identify itself as the Free edition in its plugin header and readme;
3. physically exclude Daily/Weekly Menus, Print/PDF and Import/Export;
4. retain Multiple Prices and the approved core functionality;
5. contain the WordPress.org-compliant Freemius configuration without a secret
   key, development constant, premium gatekeeper or third-party Update URI;
6. include the external-service, source and license disclosures;
7. contain no root `uninstall.php`, development files or repository metadata.

The Pro ZIP and Freemius-generated Paid ZIP are not WordPress.org submission
artifacts.

## Directory metadata and assets

The readme contributor is `jellopoint`. The final short description and Free
feature description must match the contents of the submitted Free package.

WordPress.org icons, banners and screenshots are stored outside the plugin
installation package in the separate top-level SVN `assets` directory after
the plugin is approved. They are therefore not added to the submission ZIP.
Screenshot captions should only be added to `readme.txt` when the matching,
reviewed screenshot files are ready.

## Safety boundary

- Do not submit the ZIP until the user explicitly approves the final artifact.
- Do not enable Freemius `Release Plans`.
- Do not change a Freemius deployment to a normal public release.
- Keep the existing 2.0.40 website installation in the Beta Program while the
  isolated 2.0.41 Free artifact is verified.
- Do not overwrite the Freemius-managed test website through Plesk.
- Do not put an application password, secret key or WordPress.org password in
  Git, a package, a command or this document.

## Planned acceptance

1. Validate the generated Free readme and plugin headers.
2. Run Plugin Check on the actual generated Free artifact.
3. Run the full regression, Builder, standards, compatibility and static checks.
4. Install the actual Free ZIP in the isolated WordPress/Elementor environment
   and confirm anonymous opt-out behavior and core functionality.
5. Record the final ZIP file count and SHA-256 checksum.
6. Present the exact artifact for manual approval before any submission.
7. After explicit approval, upload the ZIP for review while leaving all public
   release switches disabled.

The later WordPress.org review can request changes. Approval by the Plugin Team
creates the SVN repository; it does not by itself authorize the first public
SVN release.

## Automated and isolated acceptance

Verified on 8 September 2026:

| Check | Result |
| --- | --- |
| 31 standalone regression test files | PASS |
| Builder loading lifecycle | PASS |
| WordPress coding standards | PASS |
| PHP 7.4–8.4 compatibility | PASS |
| PHPStan | PASS against the accepted baseline; the same 21 recorded findings and no new finding |
| Generated Free package metadata and root-folder structure | PASS |
| Official interactive WordPress.org readme validator | PASS; no errors or warnings, only optional-section notes |
| Pro-module exclusion and Multiple Prices retention | PASS |
| Plugin Check 2.1.0 on the actual Free package | PASS; 0 errors and the same 58 reviewed warnings |
| WordPress 7.1 / Elementor 4.2.4 actual Free runtime | PASS |
| SDK, Builder permissions, three layouts, badges, Multiple Prices and Info Blocks | PASS |
| Anonymous Free use | PASS; tracking prohibited and tracking not allowed |

The official interactive validator accepted the generated Free readme. It only
reported informational notes for the absent Upgrade Notice, Screenshots and
donation link sections. These optional sections are intentionally omitted until
there is matching release information or reviewed directory artwork.

## Candidate Free artifact

- File: `jellopoint-restaurant-menu.zip`
- Size: 4,216,510 bytes
- Files: 264
- Root folder: `jellopoint-restaurant-menu`
- SHA-256: `75FBCBF52A46E05720B1CF5D57EF21B247DD0A4FD9B5EC7BB2BE1D86CA3510A4`

Local ignored path:
`package/phase-1x-r-wordpress-org-submission-final/jellopoint-restaurant-menu.zip`

The artifact must be rebuilt and its checksum re-recorded if any packaged source
file changes after this point.

## Proposed submission overview

JelloPoint Restaurant Menu lets restaurants manage menu items, sections,
prices, labels, dietary badges and reusable information centrally, then display
the selected menu with Elementor. The submitted Free edition includes Multiple
Prices and the Menu Builder. It works without a Freemius account or license;
the administrator may optionally connect for Pro checkout and licensing.

## Manual submission gate

Before uploading, the account owner must:

1. log in to WordPress.org as `jellopoint` and confirm the account email is
   current and monitored;
2. allow email from `plugins@wordpress.org`;
3. compare the selected ZIP checksum with the value above;
4. explicitly approve submitting that exact ZIP for review.

## WordPress.org submission acceptance

Submitted on 8 September 2026 after explicit approval by the account owner:

- Account: `jellopoint` (`info@jellopoint.com`)
- Submitted file: `jellopoint-restaurant-menu.zip`
- Submitted version: 2.0.41
- Initial assigned slug: `jellopoint-restaurant-menu`
- Automated Plugin Scanning: PASS
- Review status: **Awaiting Review**

WordPress.org sent a verification email to the account address. The initial
slug remains subject to the manual review, and no second submission should be
made while this one is pending. Reviewer questions or requested corrections
must be handled by replying to the WordPress.org review email and, when needed,
uploading a revised candidate through the existing submission.

This submission does not enable Freemius `Release Plans`, change the Beta
deployment, publish a normal Freemius release or create a public WordPress.org
SVN release. Those actions remain separately gated.
