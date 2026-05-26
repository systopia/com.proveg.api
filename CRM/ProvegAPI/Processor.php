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
 * Offers generic API processing functions
 */
class CRM_ProvegAPI_Processor {

  private static $technical_fields = ['sequential', 'prettyprint', 'json', 'check_permissions', 'version'];

  /**
   * generic preprocessor for every call
   */
  public static function preprocessCall(&$params, $log_id = 'n/a') {
    self::fixAPIUser();
    if (CRM_ProvegAPI_Configuration::logAPICalls()) {
      Civi::log()->debug("{$log_id}: " . json_encode($params));
    }

    // undo REST related changes
    CRM_ProvegAPI_CustomData::unREST($params);

    // resolve any custom fields
    CRM_ProvegAPI_CustomData::resolveCustomFields($params);
  }

  /**
   * strip the technical API fields from the params
   */
  public static function stripTechnicalFields(&$params) {
    foreach (self::$technical_fields as $field_name) {
      if (isset($params[$field_name])) {
        unset($params[$field_name]);
      }
    }
  }

  /**
   * Get the contact ID:
   *  if contact_id is given, great!
   *  if not, use XCM/resolveContact to find/create it
   *
   * @param $params array parameters
   * @return int contact ID
   */
  public static function getContactID(&$params) {
    // if the contact_id is given, we will take that
    if (!empty($params['contact_id'])) {
      return $params['contact_id'];
    }

    // otherwise, use XCM
    self::resolveContact($params);
    return $params['contact_id'];
  }

  /**
   * Get the bulk email address for the contact
   *
   * @param $contact_id
   * @return string
   */
  public static function getBulkmail($contact_id) {
    $emails = civicrm_api3('Email', 'get', [
      'contact_id' => $contact_id,
      'option.limit' => 0,
    ]);
    // try to find the bulk mail
    foreach ($emails['values'] as $email) {
      if (!empty($email['is_bulkmail'])) {
        return $email['email'];
      }
    }

    // no? then try to find the primary
    foreach ($emails['values'] as $email) {
      if (!empty($email['is_primary'])) {
        return $email['email'];
      }
    }

    // then rather not:
    return '';
  }

  /**
   * Set the bulk email address for the given contact
   *
   * @param $contact_id integer contact ID
   * @param $bulk_email string  email address
   */
  public static function setBulkMail($contact_id, $bulk_email) {
    $current_bulk_mail = self::getBulkmail($contact_id);
    if ($current_bulk_mail != $bulk_email) {
      // first: unset all current bulk mails
      $current_bulks = civicrm_api3('Email', 'get', [
        'contact_id'   => $contact_id,
        'option.limit' => 0,
        'is_bulkmail'  => 1,
      ]);
      foreach ($current_bulks['values'] as $email) {
        civicrm_api3('Email', 'create', [
          'id'          => $email['id'],
          'is_bulkmail' => 0,
        ]);
      }

      // then: find our email
      $existing_email = NULL;
      $existing_emails = civicrm_api3('Email', 'get', [
        'contact_id'   => $contact_id,
        'email'        => $bulk_email,
        'option.limit' => 0,
        'option.sort'  => 'is_primary asc',
      ]);
      foreach ($existing_emails['values'] as $email) {
        $existing_email = $email;
      }

      if ($existing_email) {
        // exists => simply make bulk
        civicrm_api3('Email', 'create', [
          'id'          => $existing_email['id'],
          'is_bulkmail' => 1,
        ]);

      }
      else {
        // doesn't exist => create
        civicrm_api3('Email', 'create', [
          'contact_id'  => $contact_id,
          'email'       => $bulk_email,
          'is_bulkmail' => 1,
        ]);
      }
    }
  }

  /**
   * will use XCM to resolve the contact and add it as
   *  'contact_id' parameter in the params array
   */
  public static function resolveContact(&$params) {
    $params['check_permissions'] = 0;
    if (empty($params['contact_type'])) {
      $params['contact_type'] = 'Individual';
    }
    $contact_match = civicrm_api3('Contact', 'getorcreate', $params);
    $params['contact_id'] = $contact_match['id'];
  }

  /**
   * Extract (and remove) all the data with a certain prefix.
   * The prefix is stripped
   *
   * @param $prefix
   * @param $data
   *
   * @return array the extracted data
   */
  public static function extractSubdata($prefix, &$data) {
    $subdata = [];
    $prefix_length = strlen($prefix);
    $keys = array_keys($data);
    foreach ($keys as $key) {
      if (substr($key, 0, $prefix_length) == $prefix) {
        // prefix matches! add to subdata
        $subdata[substr($key, $prefix_length)] = $data[$key];

        // ...and remove from data
        unset($data[$key]);
      }
    }

    // undo REST related changes
    CRM_ProvegAPI_CustomData::unREST($subdata);

    // resolve any custom fields
    CRM_ProvegAPI_CustomData::resolveCustomFields($subdata);

    return $subdata;
  }

  /**
   * Make sure the current user exists
   */
  public static function fixAPIUser() {
    // see https://github.com/CiviCooP/org.civicoop.apiuidfix
    $session = CRM_Core_Session::singleton();
    $userId = $session->get('userID');
    if (empty($userId)) {
      $valid_user = FALSE;

      // Check and see if a valid secret API key is provided.
      $api_key = CRM_Utils_Request::retrieve('api_key', 'String', NULL, FALSE, NULL, 'REQUEST');
      if (!$api_key || strtolower($api_key) == 'null') {
        // fallback user needs configuration, and might probably be a security risk. Logging error for now
        // initial function not implemented
        Civi::log()->debug("[com.proveg.api] No API key provided for Uswr {$$userId}");
        //        $session->set('userID', CRM_ProvegAPI_Configuration::getFallbackUserID());
      }

      $valid_user = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $api_key, 'id', 'api_key');

      // If we didn't find a valid user, die
      if (!empty($valid_user)) {
        //now set the UID into the session
        $session->set('userID', $valid_user);
      }
    }
  }

  /**
   * Render the given template with the given data
   */
  public static function renderTemplate($template_path, $data) {
    $smarty = CRM_Core_Smarty::singleton();

    // first backup original variables, since smarty instance is a singleton
    $oldVars = $smarty->get_template_vars();
    $backupFrame = [];
    foreach ($data as $key => $value) {
      $key = str_replace(' ', '_', $key);
      $backupFrame[$key] = isset($oldVars[$key]) ? $oldVars[$key] : NULL;
    }

    // then assign new variables
    foreach ($data as $key => $value) {
      $key = str_replace(' ', '_', $key);
      $smarty->assign($key, $value);
    }

    // create result
    $rendered_text = $smarty->fetch($template_path);

    // reset smarty variables
    foreach ($backupFrame as $key => $value) {
      $key = str_replace(' ', '_', $key);
      $smarty->assign($key, $value);
    }

    return $rendered_text;
  }

}
