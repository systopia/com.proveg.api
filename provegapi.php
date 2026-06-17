<?php
/*------------------------------------------------------------+
| ProVeg API extension                                        |
| Copyright (C) 2017 SYSTOPIA                                 |
| Author: B. Endres (endres@systopia.de)                      |
|         J. Schuppe (schuppe@systopia.de)                    |
+-------------------------------------------------------------+
| This program is released as free software under the         |
| Affero GPL license. You can redistribute it and/or          |
| modify it under the terms of this license which you         |
| can read by viewing the included agpl.txt or online         |
| at www.gnu.org/licenses/agpl.html. Removal of this          |
| copyright header is strictly prohibited without             |
| written permission from the original author(s).             |
+-------------------------------------------------------------*/

require_once 'provegapi.civix.php';
use CRM_ProvegAPI_ExtensionUtil as E;

/**
 * Define custom (Drupal) permissions
 */
function provegapi_civicrm_permission(&$permissions) {
  //$permissions['access Donation API'] = 'API: access ProvegDonation API';
  $permissions['access ProVeg API'] = [
    'label' => E::ts('API: access Proveg API'),
    'description' => E::ts('Access the ProVeg API'),
  ];
}

/**
 * Set permissions for runner/engine API call
 */
function provegapi_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {
  // ProvegDonation
  //$permissions['proveg_donation']['submit']                = array('access Donation API');
  //$permissions['proveg_newsletter_subscription']['submit'] = array('access Donation API');

  // General ProVeg API
  $permissions['proveg_selfservice']['contactbyhash'] = ['access ProVeg API'];
  $permissions['proveg_selfservice']['contactdata']   = ['access ProVeg API'];
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function provegapi_civicrm_config(&$config) {
  _provegapi_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function provegapi_civicrm_install() {
  _provegapi_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function provegapi_civicrm_enable() {
  _provegapi_civix_civicrm_enable();

  // deprecated:
  //  require_once 'CRM/ProvegAPI/CustomData.php';
  //  $customData = new CRM_ProvegAPI_CustomData('com.proveg.api');
  //  $customData->syncOptionGroup(__DIR__ . '/resources/option_group_activity_type.json');
}

/**
 * We will provide our own Mailer (wrapping the original one).
 * so we can manipulate the content of outgoing emails
 */
function provegapi_civicrm_alterMailer(&$mailer, $driver, $params) {
  $mailer = new CRM_ProvegAPI_Mailer($mailer);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *

 // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function provegapi_civicrm_navigationMenu(&$menu) {
  _provegapi_civix_insert_navigation_menu($menu, NULL, array(
    'label' => E::ts('The Page'),
    'name' => 'the_page',
    'url' => 'civicrm/the-page',
    'permission' => 'access CiviReport,access CiviContribute',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _provegapi_civix_navigationMenu($menu);
} // */
