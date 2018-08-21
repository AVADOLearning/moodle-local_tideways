# Tideways integration

[Tideways](https://tideways.io/) is an application performance management tool that enables profiling, alerting and error reporting.

---

## Brief

This plugin aims to:

1. Set meaningful names, based on Moodle page types, against transactions for easier identification of interesting transactions.

## Installation

1. Install this repository to `/local/tideways`.
2. Run the Moodle upgrade process, either via the UI or CLI.
3. Add the following to your `/config.php` above the `/lib/setup.php` include to enable the component:

   ```php
   require_once __DIR__ . '/local/tideways/bootstrap.php';
   ```
