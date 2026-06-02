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

use CRM_ProvegAPI_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_ProvegAPI_Form_Settings extends CRM_Core_Form {

  public function buildQuickForm() {

    // Generic settings
    $this->add(
        'checkbox',
        'log_api_calls',
        E::ts('Log all API calls')
    );

    // Configuration for Mailing Self-Service
    $this->add(
        'select',
        'selfservice_xcm_profile',
        E::ts('XCM Profile'),
        CRM_Xcm_Configuration::getProfileList(),
        TRUE
    );

    //    Configuration ProvegMailing APi
    $this->add(
      'select',
      'mailing_xcm_profile',
      E::ts('Mailing XCM Profile'),
      CRM_Xcm_Configuration::getProfileList(),
      TRUE
    );

    $this->add(
      'text',
      'mailing_confirmation_endpoint',
      E::ts('Mailing Confirmation Endpoint'),
      ['class' => 'huge'],
      FALSE
    );

    $this->add(
      'text',
      'mailing_unsubscription_endpoint',
      E::ts('Mailing Unsubscription Endpoint'),
      ['class' => 'huge'],
      FALSE
    );

    $this->add(
      'text',
      'mailing_default_group_id',
      E::ts('Mailing default group'),
      ['class' => 'huge'],
      FALSE
    );

    /** Configuration for Donation API (discontinued)
    // add form elements
    $this->add(
      'select',
      'financial_type_id',
      E::ts('Financial Type for Donations'),
      $this->getList('FinancialType', 'id', 'name'),
      TRUE
    );

    $this->add(
        'select',
        'paypal_instrument_id',
        E::ts('Paypal Instrument'),
        $this->getList('OptionValue', 'value', 'name', ['option_group_id' => 'payment_instrument']),
        TRUE
    );

    $this->add(
        'text',
        'contribution_source_default',
        E::ts('Default Contribution Source'),
        [],
        TRUE
    );

    $this->add(
        'select',
        'sepa_creditor_id',
        E::ts('SEPA Creditor'),
        $this->getList('SepaCreditor', 'id', 'name'),
        TRUE
    );

    $this->add(
        'select',
        'buffer_days',
        E::ts('SEPA Buffer Days'),
        range(0,15),
        TRUE
    );

    $this->add(
        'select',
        'newsletter_group',
        E::ts('Newsletter Group'),
        $this->getList('Group', 'id', 'name'),
        TRUE
    );

    $this->add(
        'select',
        'work_location_type_id',
        E::ts('Location Type for WORK Address'),
        $this->getList('Location Type', 'id', 'name'),
        TRUE
    );

    */

    $this->addButtons([
        [
          'type'      => 'submit',
          'name'      => E::ts('Save'),
          'isDefault' => TRUE,
        ],
    ]);

    $current_values = CRM_Core_BAO_Setting::getItem('com.proveg.api', 'pvapi_config');
    $this->setDefaults($current_values);

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues(NULL, TRUE);
    $settings_in_form = $this->getSettingsInForm();
    foreach ($settings_in_form as $name) {
      $settings[$name] = CRM_Utils_Array::value($name, $values, NULL);
    }
    CRM_Core_BAO_Setting::setItem($settings, 'com.proveg.api', 'pvapi_config');
    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  /**
   * Generate a dropdown list of arbitrary entites
   *
   * @param $entity       string entity name
   * @param $id_field     string name of the field to be used as ID
   * @param $label_field  string name of the field to be used as label
   * @param $params       array  additional parameters for the query
   *
   * @return array list
   * @throws CRM_Core_Exception
   */
  protected function getList($entity, $id_field = 'id', $label_field = 'name', $params = []) {
    if (empty($params['option.limit'])) {
      $params['option.limit'] = 0;
    }

    $list = [];
    $params['return'] = "{$id_field},{$label_field}";
    $results = civicrm_api3($entity, 'get', $params);
    foreach ($results['values'] as $key => $entry) {
      $list[$entry[$id_field]] = $entry[$label_field];
    }
    return $list;
  }

  /**
   * get the elements of the form
   * used as a filter for the values array from post Process
   * @return array
   */
  protected function getSettingsInForm() {
    return [
      'log_api_calls',
      'selfservice_xcm_profile',
      'mailing_xcm_profile',
      'mailing_confirmation_endpoint',
      'mailing_unsubscription_endpoint',
      'mailing_default_group_id',
    ];
  }

}
