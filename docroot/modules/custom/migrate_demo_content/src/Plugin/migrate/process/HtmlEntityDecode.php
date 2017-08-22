<?php

namespace Drupal\migrate_demo_content\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Decode HTML entities for use in unformatted text fields.
 *
 * @MigrateProcessPlugin(
 *   id = "html_entity_decode"
 * )
 */
class HtmlEntityDecode extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return html_entity_decode($value, ENT_QUOTES);
  }

}
