<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

defined('MOODLE_INTERNAL') || die;
/** @var \stdClass $plugin */

$plugin->component = 'local_tideways';
$plugin->maturity = MATURITY_ALPHA;

$plugin->version = 2018082100;
$plugin->release = '0.1.0';

$plugin->requires = 2016052300; // 3.1
