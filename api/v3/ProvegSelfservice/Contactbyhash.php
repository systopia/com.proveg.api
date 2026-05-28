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
 * Process ProvegSelfservice.contactbyhash
 *
 *  Frontend for Contact.getsingle query by hash value
 *
 * @param array $params see specs below (_civicrm_api3_engage_signpetition_spec)
 * @return array API result array
 * @access public
 */
function civicrm_api3_proveg_selfservice_contactbyhash($params) {
  // preprocess incoming call
  CRM_ProvegAPI_Processor::preprocessCall($params, 'ProvegSelfservice.contactbyhash');

  if (!empty($params['hash'])) {
    try {
      // first, load the contact base data
      $contact_id = CRM_Selfservice_HashLinks::getContactIdFromHash($params['hash']);
      $data = civicrm_api3('Contact', 'getsingle', [
        'id'                => $contact_id,
        'check_permissions' => 0,
      // TODO: extend here
        'return'            => 'first_name,last_name,birth_date,prefix_id,custom_13,'
        . 'street_address,postal_code,city,custom_144',
      ]);

      // add hash
      $data['hash'] = $params['hash'];

      // add the bulk (not primary) email
      $data['email'] = CRM_ProvegAPI_Processor::getBulkmail($data['id']);

      return $data;
    }
    catch (CRM_Core_Exception $ex) {
      // not found
      return civicrm_api3_create_error('Not found');
    }
  }
  else {
    return civicrm_api3_create_error("Missing only parameter 'hash'.");
  }
}

/**
 * Adjust Metadata for ProvegSelfservice.contactbyhash
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_proveg_selfservice_contactbyhash_spec(&$params) {
  // CONTACT BASE
  $params['hash'] = [
    'name'         => 'hash',
    'api.required' => 1,
    'title'        => 'Contact Hash',
    'description'  => 'Needs to be valid',
  ];
}
