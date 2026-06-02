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

/**
 * Submit a donation.
 *
 * @param array $params
 *   Associative array of property name/value pairs.
 *
 * @return array api result array
 *
 * @access public
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_proveg_donation_submit($params) {
  // Log the API call to the CiviCRM debug log.
  if (defined('PROVEG_API_LOGGING') && PROVEG_API_LOGGING) {
    Civi::log()->debug('ProvegDonation.submit: ' . json_encode($params));
  }

  // extract campaign_id (see PV-8280)
  CRM_ProvegAPI_Submission::extractCampaign($params);

  $extra_return_values = [];
  $recurring_contribution_id = NULL;
  $annual_amount = NULL;

  try {
    if ($params['frequency'] && $params['payment_instrument_id'] != 'sepa') {
      throw new CRM_Core_Exception(
        'Recurring donations can only be submitted with SEPA.',
        'invalid_format'
      );
    }

    // Get the ID of the contact matching the given contact data, or create a
    // new contact if none exists for the given contact data.
    $contact_data = [
      'first_name'     => $params['first_name'],
      'last_name'      => $params['last_name'],
      'email'          => $params['email'],
      'street_address' => $params['street_address'],
      'city'           => $params['city'],
      'postal_code'    => $params['postal_code'],
      'country'        => $params['country'],
    ];
    // Determine gender ID from the given gender.
    if (!empty($params['gender'])) {
      $gender_options = civicrm_api3('OptionValue', 'get', [
        'check_permissions' => 0,
        'option_group_id'   => 'gender',
      ]);
      $genders = [];
      foreach ($gender_options['values'] as $gender_option) {
        $genders[$gender_option['value']] = $gender_option['name'];
      }
      switch ($params['gender']) {
        case 'm':
          $contact_data['gender_id'] = array_search('Male', $genders);
          break;

        case 'f':
          $contact_data['gender_id'] = array_search('Female', $genders);
          break;

        default:
          throw new CRM_Core_Exception('Could not determine option value from given gender.', 0);
        break;
      }
    }

    if (!$contact_id = CRM_ProvegAPI_Submission::getContact('Individual', $contact_data)) {
      throw new CRM_Core_Exception(
        'Individual contact could not be found or created.',
        'invalid_format'
      );
    }

    // Prepare contribution data.
    $contribution_data = [
      'financial_type_id' => CRM_ProvegAPI_Configuration::getSetting('financial_type_id', 1),
      'campaign_id'       => $params['campaign_id'],
      'contact_id'        => $contact_id,
      'total_amount'      => $params['amount'] / 100,
      'source'            => CRM_ProvegAPI_Configuration::getSource($params, 'contribution_source'),
      'receive_date'      => date('YmdHis', (!empty($params['receive_date']) ? $params['receive_date'] : REQUEST_TIME)),
    ];

    // Handle recurring donations.
    if ($params['frequency']) {
      $contribution_data['frequency_unit'] = 'month';
      $contribution_data['frequency_interval'] = 12 / $params['frequency'];
      $contribution_data['amount'] = $contribution_data['total_amount'] / 100;
      unset($contribution_data['total_amount']);
      $annual_amount = ((float) $params['amount']) * (float) $params['frequency'] / 100;
    }

    // Handle payment instruments.
    switch ($params['payment_instrument_id']) {
      // SEPA.
      case 'sepa':
        // Require IBAN.
        if (empty($params['iban'])) {
          throw new CRM_Core_Exception(
            'For donations via SEPA, the IBAN must be provided.',
            'invalid_format'
          );
        }
        elseif ($error = CRM_Sepa_Logic_Verification::verifyIBAN($params['iban'])) {
          throw new CRM_Core_Exception(
            $error,
            'invalid_format'
          );
        }
        // Require BIC.
        if (empty($params['bic'])) {
          throw new CRM_Core_Exception(
            'For donations via SEPA, the SWIFT code (BIC) must be provided.',
            'invalid_format'
          );
        }
        elseif ($error = CRM_Sepa_Logic_Verification::verifyBIC($params['bic'])) {
          throw new CRM_Core_Exception(
            $error,
            'invalid_format'
          );
        }

        // Create SEPA mandate and contribution.
        $contribution_data['type']        = ($params['frequency'] ? 'RCUR' : 'OOFF');
        $contribution_data['iban']        = $params['iban'];
        $contribution_data['bic']         = $params['bic'];
        $contribution_data['creditor_id'] = CRM_ProvegAPI_Configuration::getSetting('sepa_creditor_id', 1);
        $contribution_data['amount']      = $params['amount'] / 100;
        $contribution_data['start_date']  = CRM_ProvegAPI_Submission::getStartDate();
        $contribution_data['check_permissions'] = 0;
        $sepa_mandate = civicrm_api3(
          'SepaMandate',
          'createfull',
          $contribution_data
        );
        $sepa_mandate = reset($sepa_mandate['values']);
        // $extra_return_values['SepaMandate'] = $sepa_mandate;

        // Load contribution.
        if (!empty($sepa_mandate['entity_id'])) {
          if ($sepa_mandate['entity_table'] == 'civicrm_contribution') {
            $contribution = civicrm_api3('Contribution', 'getsingle', [
              'check_permissions' => 0,
              'id'                => $sepa_mandate['entity_id'],
            ]);
          }
          elseif ($sepa_mandate['entity_table'] == 'civicrm_contribution_recur') {
            $recurring_contribution_id = (int) $sepa_mandate['entity_id'];

            $contribution = civicrm_api3('ContributionRecur', 'getsingle', [
              'check_permissions' => 0,
              'id' => $sepa_mandate['entity_id'],
            ]);
          }
          if (!isset($contribution)) {
            throw new CRM_Core_Exception(
              'Could not load contribution for SEPA mandate.',
              'invalid_format'
            );
          }
        }
        break;

      // PayPal.
      case 'paypal':
        $contribution_data['payment_instrument_id']  = CRM_ProvegAPI_Configuration::getSetting('paypal_instrument_id', 12);
        $contribution_data['contribution_status_id'] = 'Completed';
        $contribution_data['check_permissions'] = 0;
        $contribution = civicrm_api3(
          'Contribution',
          'create',
          $contribution_data
        );
        break;

      // Invalid payment method.
      default:
        throw new CRM_Core_Exception(
          'Invalid payment instrument.',
          'invalid_format'
        );
      break;
    }

    // If requested, create membership for the contact.
    if (!empty($params['membership_type_id'])) {
      $membership_data = [
        'check_permissions'  => 0,
        'membership_type_id' => $params['membership_type_id'],
        'campaign_id'        => $params['campaign_id'],
        'contact_id'         => $contact_id,
        'source'             => CRM_ProvegAPI_Configuration::getSource($params, 'contribution_source'),
      ];

      // add subtype if given
      if (!empty($params['membership_subtype_id'])) {
        $membership_data['membership_type.membership_subtype'] = $params['membership_subtype_id'];
      }

      // add join/start/end date
      $start_date = CRM_ProvegAPI_Submission::getStartDate();
      $membership_data['start_date'] = $start_date;
      $membership_data['end_date']   = date('Y-m-d', strtotime("{$start_date} +1 year -1 day"));
      $membership_data['join_date']  = date('Y-m-d');

      // add annual amount
      if ($annual_amount) {
        $membership_data['membership_info.membership_annual'] = $annual_amount;
      }

      // create membership
      CRM_ProvegAPI_CustomData::resolveCustomFields($membership_data);
      Civi::log()->debug('Membership create: ' . json_encode($membership_data));
      $membership = civicrm_api3('Membership', 'create', $membership_data);

      // reload to get all data
      $membership = civicrm_api3('Membership', 'getsingle', ['id' => $membership['id']]);

      // finally: set the payment contract
      if ($recurring_contribution_id) {
        $membership_update = [
          'id'                                      => $membership['id'],
          'contact_id'                              => $membership['contact_id'],
          'membership_info.membership_paid_through' => $recurring_contribution_id,
        ];
        CRM_ProvegAPI_CustomData::resolveCustomFields($membership_update);
        Civi::log()->debug('Membership update: ' . json_encode($membership_update));
        civicrm_api3('Membership', 'create', $membership_update);
      }

      // DISABLED: Include membership in extraReturnValues parameter.
      // I think this reveals a lot while being unnecessary
      // $extra_return_values['Membership'] = $membership;
    }

    // If requested, perform a newsletter subscription for the contact.
    if (!empty($params['newsletter'])) {
      $newsletter_subscription = civicrm_api3('ProvegNewsletterSubscription', 'submit', [
        'check_permissions'  => 0,
        'contact_id'         => $contact_id,
        'newsletter'         => 1,
      ]);
      $extra_return_values['ProvegNewsletterSubscription'] = $newsletter_subscription;
    }

    return civicrm_api3_create_success($contribution, $params, NULL, NULL, $dao = NULL, ['extra' => $extra_return_values]);
  }
  catch (CRM_Core_Exception $exception) {
    if (defined('PROVEG_API_LOGGING') && PROVEG_API_LOGGING) {
      Civi::log()->debug('ProvegDonation:submit:Exception caught: ' . $exception->getMessage());
    }

    $extraParams = $exception->getExtraParams();

    // Rollback current base transaction in order to not rollback the creation
    // of the activity.
    if (($frame = \Civi\Core\Transaction\Manager::singleton()->getFrame()) !== NULL) {
      $frame->forceRollback();
    }
    try {
      // Create an activity of type "Failed contribution processing" and assign
      // it to the contact defined in configuration.
      $assignee_id = CRM_Core_BAO_Setting::getItem(
        'com.proveg.api',
        'provegapi_contact_failed_contribution_processing'
      );
      $activity_data = [
        'check_permissions'  => 0,
        'assignee_id'        => $assignee_id,
        'activity_type_id'   => CRM_Core_OptionGroup::getValue('activity_type', 'provegapi_failed_contribution_processing', 'name'),
        'subject'            => 'Failed ProVeg API contribution processing',
        'activity_date_time' => date('YmdHis', REQUEST_TIME),
        'source_contact_id'  => CRM_Core_Session::singleton()->getLoggedInContactID(),
        'status_id'          => CRM_Core_OptionGroup::getValue('activity_status', 'Scheduled', 'name'),
        'target_id'          => $contact_id,
        'campaign_id'        => $params['campaign_id'],
        'details'            => json_encode($params),
      ];
      $activity = civicrm_api3('Activity', 'create', $activity_data);
      $extraParams['additional_notices']['activity']['result'] = $activity;
      if (!isset($assignee_id)) {
        $extraParams['additional_notices']['activity']['messages'][] = 'No contact ID is configured for assigning an activity of the type "Failed contribution processing". The activity has not been assigned to a contact.';
      }
    }
    catch (CRM_Core_Exception $activity_exception) {
      $extraParams['additional_notices']['activity']['messages'][] = 'Failed creating an activity of the type "Failed contribution processing".';
      $extraParams['additional_notices']['activity']['result'] = civicrm_api3_create_error($activity_exception->getMessage(), $activity_exception->getExtraParams());
    }

    return civicrm_api3_create_error($exception->getMessage(), $extraParams);
  }
}

/**
 * Parameter specification for the "Submit" action on "ProvegDonation" entities.
 *
 * @param $params
 */
function _civicrm_api3_proveg_donation_submit_spec(&$params) {
  $params['membership_type_id'] = [
    'name' => 'membership_type_id',
    'title' => 'Membership type',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description' => 'The ID of the membership type to assign to the contact.',
  ];
  $params['membership_subtype_id'] = [
    'name' => 'membership_subtype_id',
    'title' => 'Membership sub type',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description' => 'The ID of the membership sub type to assign to the contact.',
  ];
  $params['amount'] = [
    'name'         => 'amount',
    'title'        => 'Amount (in Euro cents)',
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 1,
    'description'  => 'The donation amount in Euro cents.',
  ];
  $params['frequency'] = [
    'name'         => 'frequency',
    'title'        => 'Frequency',
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 1,
    'description'  => 'The number of installments per year, or 0 for one-off.',
  ];
  $params['gender'] = [
    'name'         => 'gender',
    'title'        => 'Gender',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The contact\'s gender.',
  ];
  $params['first_name'] = [
    'name'         => 'first_name',
    'title'        => 'First Name',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => 'The contact\'s first name.',
  ];
  $params['last_name'] = [
    'name'         => 'last_name',
    'title'        => 'Last Name',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => 'The contact\'s last name.',
  ];
  $params['email'] = [
    'name'         => 'email',
    'title'        => 'Email',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => 'The contact\'s email.',
  ];
  $params['street_address'] = [
    'name'         => 'street_address',
    'title'        => 'Street address',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => 'The contact\'s street address.',
  ];
  $params['postal_code'] = [
    'name'         => 'postal_code',
    'title'        => 'Postal / ZIP code',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => 'The contact\'s postal code.',
  ];
  $params['city'] = [
    'name'         => 'city',
    'title'        => 'City',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => 'The contact\'s city.',
  ];
  $params['country'] = [
    'name'         => 'country',
    'title'        => 'Country',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => 'The contact\'s country.',
  ];
  $params['payment_instrument_id'] = [
    'name'         => 'payment_instrument_id',
    'title'        => 'Payment instrument',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => 'The payment method used for the donation: sepa or paypal',
  ];
  $params['iban'] = [
    'name'         => 'iban',
    'title'        => 'IBAN',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The IBAN to register the SEPA mandate for.',
  ];
  $params['bic'] = [
    'name'         => 'bic',
    'title'        => 'BIC',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The SWIFT code (BIC) to register the SEPA mandate for.',
  ];
  $params['account_holder'] = [
    'name'         => 'account_holder',
    'title'        => 'Account holder',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The bank account holder\'s full name (when different from contact).',
  ];
  $params['newsletter'] = [
    'name'         => 'newsletter',
    'title'        => 'Newsletter',
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => 'Whether to subscribe the contact to the configured newsletter group.',
  ];
  $params['campaign_code'] = [
    'name'         => 'campaign_code',
    'title'        => 'Campaign Code',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'External identifier for a campaign',
  ];
  $params['receive_date'] = [
    'name'         => 'receive_date',
    'title'        => 'Receive date',
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => 'A timestamp when the donation was issued.',
  ];
  $params['contribution_source'] = [
    'name' => 'contribution_source',
    'title' => 'Contribution source',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description' => 'Text to identify the origin of the contribution.',
  ];
}
