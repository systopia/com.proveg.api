<?php
use CRM_ProvegAPI_ExtensionUtil as E;

/**
 * ProvegMailing.Subscribe API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_proveg_mailing_Subscribe_spec(&$spec) {
  $spec['email']['api.required'] = 1;
  $spec['first_name']['api.required'] = 0;
  $spec['last_name']['api.required'] = 0;
  $spec['prefix_id']['api.required'] = 0;
  $spec['group_id']['api.required'] = 0;
}

/**
 * ProvegMailing.Subscribe API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_proveg_mailing_Subscribe($params) {

  try {
    $subscribeHandler = new CRM_ProvegAPI_MailingSubscribe();
    $subscribeHandler->handle_request($params);

    // If CRM_Mods_SubscriptionLogger exists, use it to log this subscription.
    // Logger is included in com.proveg.mods
    if (class_exists('CRM_Mods_SubscriptionLogger')) {
      $log_params = $subscribeHandler->get_log_parameters();
      $logger = new CRM_Mods_SubscriptionLogger(
        $log_params['contact_id'],
        $log_params['hash'],
        $log_params['group_id'],
        $log_params['email']
      );
      $logger->log_subscription('ProVegApi');
    }
    return civicrm_api3_create_success("Created Subscription for {$subscribeHandler->get_contact_id()}");
  }
  catch (Exception $e) {
    throw new API_Exception("Error parsing Request. Message: '{$e->getMessage()}'");
  }
}
