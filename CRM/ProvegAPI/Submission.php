<?php
/**
 * ------------------------------------------------------------+
 * | ProVeg API extension                                        |
 * | Copyright (C) 2017 SYSTOPIA                                 |
 * | Author: B. Endres (endres@systopia.de)                      |
 * |         J. Schuppe (schuppe@systopia.de)                    |
 * +-------------------------------------------------------------+
 * | This program is released as free software under the         |
 * | Affero GPL license. You can redistribute it and/or          |
 * | modify it under the terms of this license which you         |
 * | can read by viewing the included agpl.txt or online         |
 * | at www.gnu.org/licenses/agpl.html. Removal of this          |
 * | copyright header is strictly prohibited without             |
 * | written permission from the original author(s).             |
 * +-------------------------------------------------------------
 */
class CRM_ProvegAPI_Submission {

  /**
   * Retrieves the contact matching the given contact data or creates a new
   * contact.
   *
   * @param string $contact_type
   *   The contact type to look for/to create.
   * @param array $contact_data
   *   Data to use for contact lookup/to create a contact with.
   *
   * @return int | NULL
   *   The ID of the matching/created contact, or NULL if no matching contact
   *   was found and no new contact could be created.
   * @throws \CRM_Core_Exception | CRM_Core_Exception
   *   When invalid data was given.
   *
   * @deprecated ProvegDonation.submit was discontinued
   */
  public static function getContact($contact_type, $contact_data) {
    // If no parameters are given, do nothing.
    if (empty($contact_data)) {
      return NULL;
    }

    // Prepare values: country.
    if (!empty($contact_data['country'])) {
      if (is_numeric($contact_data['country'])) {
        // If a country ID is given, update the parameters.
        $contact_data['country_id'] = $contact_data['country'];
        unset($contact_data['country']);
      }
      else {
        // Look up the country depending on the given ISO code.
        $country = civicrm_api3('Country', 'get', [
          'check_permissions' => 0,
          'iso_code' => $contact_data['country'],
        ]);
        if (!empty($country['id'])) {
          $contact_data['country_id'] = $country['id'];
          unset($contact_data['country']);
        }
        else {
          throw new CRM_Core_Exception("Unknown country '{$contact_data['country']}'", 1);
        }
      }
    }

    // Pass to XCM.
    $contact_data['contact_type'] = $contact_type;
    $contact_data['check_permissions'] = 0;
    $contact = civicrm_api3('Contact', 'getorcreate', $contact_data);
    if (empty($contact['id'])) {
      return NULL;
    }

    return $contact['id'];
  }

  /**
   * Will scan the parameters for campaign information
   *  and set campaign_id in the parameters.
   *
   * If no campaign information is found, it will still set the campaign_id
   *  to an empty string
   *
   * @param $params array call data
   */
  public static function extractCampaign(&$params) {
    $campaign_id = CRM_Utils_Array::value('campaign_id', $params, '');
    if (is_numeric($campaign_id)) {
      $campaign_id = (int) $campaign_id;
    }
    else {
      $campaign_id = '';
      if (!empty($params['campaign_code'])) {
        $campaign_code = strtoupper(trim($params['campaign_code']));
        $campaign_query = civicrm_api3('Campaign', 'get', [
          'external_identifier' => $campaign_code,
          'is_active'           => 1,
        ]);
        if (empty($campaign_query['id'])) {
          Civi::log()->debug("PVAPI: Campaign code '{$campaign_code}' not (uniquely) identified!");
        }
        else {
          $campaign_id = (int) $campaign_query['id'];
        }
      }
    }
    $params['campaign_id'] = $campaign_id;
  }

  /**
   * Get the next possible start date,
   * which is the next 1st of the month
   * respecting the buffer days
   */
  public static function getStartDate() {
    $buffer = CRM_ProvegAPI_Configuration::getSetting('buffer_days', 5);
    $earliest = strtotime("now + {$buffer} days");
    while (date('j', $earliest) > 1) {
      // get to the next day
      $earliest = strtotime('+1 day', $earliest);
    }
    return date('Y-m-d', $earliest);
  }

  /**
   * Share an organisation's work address, unless the contact already has one
   *
   * @param $contact_id
   *   The ID of the contact to share the organisation address with.
   * @param $organisation_id
   *   The ID of the organisation whose address to share with the contact.
   * @param $location_type_id
   *   The ID of the location type to use for address lookup.
   *
   * @return boolean
   *   Whether the organisation address has been shared with the contact.
   *
   * @throws \CRM_Core_Exception
   */
  public static function shareWorkAddress($contact_id, $organisation_id, $location_type_id = NULL) {
    if ($location_type_id === NULL) {
      $location_type_id = CRM_ProvegAPI_Configuration::getSetting('work_location_type_id', 2);
    }
    if (empty($organisation_id)) {
      // Only if organisation exists.
      return FALSE;
    }

    // Check whether organisation has a WORK address.
    $existing_org_addresses = civicrm_api3('Address', 'get', [
      'check_permissions' => 0,
      'contact_id'        => $organisation_id,
      'location_type_id'  => $location_type_id,
    ]);
    if ($existing_org_addresses['count'] <= 0) {
      // Organisation does not have a WORK address.
      return FALSE;
    }

    // Check whether contact already has a WORK address.
    $existing_contact_addresses = civicrm_api3('Address', 'get', [
      'check_permissions' => 0,
      'contact_id'        => $contact_id,
      'location_type_id'    => $location_type_id,
    ]);
    if ($existing_contact_addresses['count'] > 0) {
      // Contact already has a WORK address.
      return FALSE;
    }

    // Create a shared address.
    $address = reset($existing_org_addresses['values']);
    $address['contact_id']         = $contact_id;
    $address['master_id']          = $address['id'];
    $address['check_permissions']  = 0;
    unset($address['id']);
    civicrm_api3('Address', 'create', $address);
    return TRUE;
  }

}
