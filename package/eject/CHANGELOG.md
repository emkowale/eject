v1.0.1 — 2025-11-05

Changed files:
- README.md
- assets/css/admin.css
- assets/js/admin.js
- eject.php
- includes/ajax/class-eject-ajax-run.php
- includes/ajax/class-eject-ajax-scan.php
- includes/ajax/class-eject-ajax-settings.php
- includes/ajax/traits/trait-eject-ajax-run-add.php
- includes/ajax/traits/trait-eject-ajax-run-lines.php
- includes/ajax/traits/trait-eject-ajax-run-status.php
- includes/class-eject-admin.php
- includes/class-eject-ajax.php
- includes/class-eject-cpt.php
- includes/data/class-eject-data.php
- includes/eject-hooks.php
- includes/views/view-pos.php
- includes/views/view-queue.php
- includes/views/view-runs.php
- includes/views/view-settings.php
- release.sh

# 🧾 Eject Changelog

All notable changes to **Eject** will be documented in this file.  
This project follows [Semantic Versioning](https://semver.org/).

---

## [1.0.0] - 2025-11-03
### Added
- Initial plugin scaffold for Eject
- Custom Post Type `eject_run` for purchase orders and runs
- Admin screens: Queue, Runs, POs, Settings
- WooCommerce integration for Processing orders
- AJAX endpoints and Woo spinner interactions
- GitHub-compatible metadata for WordPress update functionality
- README.md and CHANGELOG.md added for release.sh automation

---

## [Unreleased]
### Planned
- Vendor email integration for automatic PO dispatch
- Integration with **Tracks** for work order generation
- Extended reporting and vendor cost tracking
