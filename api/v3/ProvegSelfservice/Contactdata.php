<?php
/*------------------------------------------------------------+
| ProVeg API extension                                        |
| Copyright (C) 2017-2019 SYSTOPIA                            |
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

/**
 * Process ProvegSelfservice.contactdata
 *
 *  Update contact's data using XCM
 *
 * @return array API result array
 * @access public
 */
function civicrm_api3_proveg_selfservice_contactdata($params) {
  // preprocess incoming call
  CRM_ProvegAPI_Processor::preprocessCall($params, 'ProvegSelfservice.contactdata');

  if (!empty($params['hash'])) {
    try {
      // identify the contact
      $params['id'] = CRM_Selfservice_HashLinks::getContactIdFromHash($params['hash']);

      // add xcm profile
      $params['xcm_profile'] = CRM_ProvegAPI_Configuration::getSetting('selfservice_xcm_profile');

      // pass through XCM
      // TODO: filter parameters?
      civicrm_api3('Contact', 'getorcreate', $params);

      // make sure the new email is there and marked as bulk
      if (!empty($params['email'])) {
        CRM_ProvegAPI_Processor::setBulkMail($params['id'], $params['email']);
      }

      // update diet
      if (!empty($params['custom_13'])) {
        civicrm_api3('Contact', 'create', [
          'id'        => $params['id'],
          'custom_13' => [$params['custom_13']],
        ]);
      }

      // update interests
      if (!empty($params['custom_144'])) {
        civicrm_api3('Contact', 'create', [
          'id'         => $params['id'],
          'custom_144' => $params['custom_144'],
        ]);
      }

      return civicrm_api3_create_success('contact updated');
    }
    catch (Exception $ex) {
      // not found
      return civicrm_api3_create_error('Not found');
    }
  }
  else {
    return civicrm_api3_create_error("Missing only parameter 'hash'.");
  }
}

/**
 * Adjust Metadata for Payment action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_proveg_selfservice_contactdata_spec(&$params) {
  // CONTACT BASE
  $params['hash'] = [
    'name'         => 'hash',
    'api.required' => 0,
    'title'        => 'Contact Hash',
    'description'  => 'If given, triggers update',
  ];
  $params['email'] = [
    'name'           => 'email',
    'api.required'   => 0,
    'title'          => 'email address',
  ];
  $params['first_name'] = [
    'name'         => 'first_name',
    'api.required' => 0,
    'title'        => 'First Name',
  ];
  $params['last_name'] = [
    'name'         => 'last_name',
    'api.required' => 0,
    'title'        => 'Last Name',
  ];
  $params['birth_date'] = [
    'name'         => 'birth_date',
    'api.required' => 0,
    'title'        => 'Birth Date',
  ];
}
