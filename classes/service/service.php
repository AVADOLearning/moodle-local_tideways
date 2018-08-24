<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

namespace local_tideways\service;

/**
 * Service interface.
 *
 * Tideways services group related transactions into sets that are aggregated
 * together. In this component they're used to group web service, web, AJAX,
 * cron and general CLI transactions.
 *
 * @see https://support.tideways.com/article/41-services
 */
interface service {
    /**
     * Perform pre-setup actions.
     *
     * @return void
     */
    public function pre_setup();

    /**
     * Perform post-setup actions.
     *
     * @return void
     */
    public function post_setup();
}
