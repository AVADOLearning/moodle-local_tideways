# Tideways integration

[Tideways](https://tideways.io/) is an application performance management tool that enables profiling, alerting and error reporting.

---

## Brief

This plugin aims to:

1. Set meaningful names, based on Moodle page types, against transactions for easier identification of interesting transactions.
2. Install instrumentation for SQL Server with the `sqlsrv` extension.

## Installation

1. Install this repository to `/local/tideways`.
2. Run the Moodle upgrade process, either via the UI or CLI.
3. Add the following to your `/config.php` above the `/lib/setup.php` include:

   ```php
   require_once __DIR__ . '/local/tideways/bootstrap.php';
   local_tideways_pre_setup();
   ```
4. Add the following below the `/lib/setup.php` include:

   ```php
   local_tideways_post_setup();
   ```

## Configuration

`$CFG->local_tideways` can be set (prior to calling the `local_tideways_*` functions) to an array containing the following keys to alter the plugin's behaviour:

* `development` causes the profiler to be started in development mode, where it always collects a complete trace and profile. This is useful for debugging this plugin but should not be enabled in production environments.
* `profiler_options` allows you to override options passed to the profiler at start-time.
