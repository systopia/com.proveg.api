<?php
/**
 * ------------------------------------------------------------+
 * | ProVeg API extension                                        |
 * | Copyright (C) 2018 SYSTOPIA                                 |
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
class CRM_ProvegAPI_Configuration {

  protected static $config = NULL;

  /**
   * Get the given setting
   *
   * @param $name                string
   * @param null $default_value  mixed
   * @return mixed
   */
  public static function getSetting($name, $default_value = NULL) {
    // load settings
    if (self::$config === NULL) {
      self::$config = CRM_Core_BAO_Setting::getItem('com.proveg.api', 'pvapi_config');
      if (self::$config === NULL) {
        // avoid re-loading
        self::$config = [];
      }
    }

    // return requested value
    return CRM_Utils_Array::value($name, self::$config, $default_value);
  }

  /**
   * Get the source string with the given from the given parameters.
   *  If that field is empty, use the source from the settings
   *
   * @param $params array  data
   * @param $key    string key
   *
   * @return string the source to be used.
   */
  public static function getSource($params, $key) {
    if (empty($params[$key])) {
      return self::getSetting('contribution_source_default', 'ProVeg API');
    }
    else {
      return trim($params[$key]);
    }
  }

  /**
   * Should all API calls be logged (debugging)
   * @return boolean
   */
  public static function logAPICalls() {
    $log_calls = self::getSetting('log_api_calls', FALSE);
    return !empty($log_calls);
  }

}
