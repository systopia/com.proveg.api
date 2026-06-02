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
class CRM_ProvegAPI_UrlReplace {

  private static $unsubscribe_url = NULL;
  private static $confirm_url = NULL;
  private static $baseurl = NULL;

  public function __construct() {
    if ($this::$unsubscribe_url === NULL || $this::$confirm_url === NULL) {
      $this::$unsubscribe_url = CRM_ProvegAPI_Configuration::getSetting('mailing_unsubscription_endpoint');
      $this::$confirm_url     = CRM_ProvegAPI_Configuration::getSetting('mailing_confirmation_endpoint');
    }
    if ($this::$baseurl === NULL) {
      $this::$baseurl = CIVICRM_UF_BASEURL;
    }
  }

  /**
   * Parse mail content and replace URLs
   * @param $content
   */
  public function parse(&$content) {
    $this->parse_confirmation_endpoint($content);
    $this->parse_confirmation_parameters($content);
    $this->parse_unsubscription_endpoint($content);
  }

  /**
   * Replace confirmation endpoint URL to configured URL
   * @param $content
   */
  private function parse_confirmation_endpoint(&$content) {
    $pattern = '/https:\/\/(?P<url>[a-zA-Z0-9\/_.-]+)\/civicrm\/mailing\/confirm/';
    $content = preg_replace($pattern, $this::$confirm_url, $content);
  }

  /**
   * replaces parameters in URLs to fitting parameters in civicrm (contact_id, subscribe_id, hash)
   * and removes parameter 'reset=1'
   * Example String could be
   *  Pre: https://proveg.com/confirm?reset=1&cid=42565&sid=5&h=627ab2542bc7fd81
   * Post: https://proveg.com/confirm?contact_id=42565&subscribe_id=5&hash=627ab2542bc7fd81
   * @param $content
   */
  private function parse_confirmation_parameters(&$content) {
    $pattern = '/(?P<reset>reset=1&(amp;)?)'
      . '(?P<contact_id>cid)(?P<contact_id_val>=[0-9]+&(amp;)?)'
      . '(?P<subscribe_id>sid)(?P<subscribe_id_val>=[0-9]+&(amp;)?)'
      . '(?P<hash>h)(?P<hash_val>=[a-z0-9]+)/';
    $content = preg_replace_callback($pattern, 'self::replace_callback', $content);
  }

  /**
   * replace callback function, serving preg_replace_callback
   * https://www.php.net/manual/en/function.preg-replace-callback.php
   *
   * replaces URL paramters
   *    cid --> contact_id
   *    sid --> subscribe_id
   *    h   --> hash
   * @param $matches
   *
   * @return string
   */
  private function replace_callback($matches) {
    return "contact_id{$matches['contact_id_val']}subscribe_id{$matches['subscribe_id_val']}hash{$matches['hash_val']}";
  }

  /**
   * Replace unsubscribe URL to configured URL
   * @param $content
   */
  private function parse_unsubscription_endpoint(&$content) {
    $pattern = '/https:\/\/(?P<url>[a-zA-Z0-9\/_.-]+)\/civicrm\/mailing\/unsubscribe/';
    $content = preg_replace($pattern, $this::$unsubscribe_url, $content);
  }

}
