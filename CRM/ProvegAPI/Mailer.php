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
class CRM_ProvegAPI_Mailer {

  /**
   * this is the original, wrapped mailer
   */
  protected $mailer = NULL;

  /**
   * CRM_ProvegAPI_Mailer constructor.
   */
  public function __construct($mailer) {
    $this->mailer = $mailer;
  }

  /**
   * Send an email via the wrapped mailer,
   *  mending the URLs contained
   */
  public function send($recipients, $headers, $body) {
    $urlReplacer = new CRM_ProvegAPI_UrlReplace();
    $urlReplacer->parse($body);
    $this->mailer->send($recipients, $headers, $body);
  }

}
