<?php
/**
 * ------------------------------------------------------------+
 * | ProVeg API extension                                        |
 * | Copyright (C) 2018 SYSTOPIA                                 |
 * | Author: P. Batroff (batroff@systopia.de)                    |
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
class CRM_ProvegAPI_MailingSubscribe {

  private $group_id = NULL;
  private $xcm_params = [];
  private $contact_id = NULL;
  private $hash = NULL;

  /**
   * CRM_ProvegAPI_MailingSubscribe constructor.
   */
  public function __construct() {
  }

  /**
   * @param $parameters
   *
   * @throws \API_Exception
   */
  public function handle_request($parameters) {
    $this->handle_parameters($parameters);
    $this->contact_id = $this->get_or_create_contact();
    $this->mailing_event_subscribe();
  }

  /**
   * @throws \API_Exception
   * @throws \CiviCRM_API3_Exception
   */
  private function mailing_event_subscribe() {
    $params = [
      'email' => $this->xcm_params['email'],
      'contact_id' => $this->contact_id,
      'group_id' => $this->xcm_params['group_id'],
    ];

    $result = civicrm_api3('MailingEventSubscribe', 'create', $params);
    if ($result['is_error'] != '0') {
      throw new API_Exception(
        "Error Subscribing Contact {$this->contact_id} with Email {$this->email} "
        . "to group {$this->group_id}. Error Message: {$result['error_message']}"
      );
    }
    $this->hash = $result['values'][$result['id']]['hash'];
  }

  /**
   * @return null
   */
  public function get_contact_id() {
    return $this->contact_id;
  }

  /**
   * @return array
   */
  public function get_log_parameters() {
    return [
      'contact_id' => $this->contact_id,
      'group_id' => $this->group_id,
      'hash' => $this->hash,
      'email' => $this->xcm_params['email'],
    ];
  }

  /**
   * @param $parameters
   */
  private function handle_parameters($parameters) {
    if (isset($parameters['first_name'])) {
      $this->xcm_params['first_name'] = $parameters['first_name'];
    }
    if (isset($parameters['last_name'])) {
      $this->xcm_params['last_name'] = $parameters['last_name'];
    }
    if (isset($parameters['email'])) {
      $this->xcm_params['email'] = $parameters['email'];
    }
    if (isset($parameters['prefix_id'])) {
      $this->xcm_params['prefix_id'] = $parameters['prefix_id'];
    }
    if (isset($parameters['group_id'])) {
      $this->xcm_params['group_id'] = $parameters['group_id'];
      $this->group_id = $parameters['group_id'];
    }
    else {
      // get from Config
      $this->xcm_params['group_id'] = CRM_ProvegAPI_Configuration::getSetting('mailing_default_group_id');
    }
  }

  /**
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  private function get_or_create_contact() {
    $this->xcm_params['contact_type'] = 'Individual';
    $this->xcm_params['xcm_profile'] = CRM_ProvegAPI_Configuration::getSetting('mailing_xcm_profile');
    $result = civicrm_api3('Contact', 'getorcreate', $this->xcm_params);
    return $result['id'];
  }

}
