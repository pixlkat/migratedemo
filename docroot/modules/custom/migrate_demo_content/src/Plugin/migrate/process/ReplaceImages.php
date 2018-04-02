<?php

namespace Drupal\migrate_demo_content\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\media\Entity\Media;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a process plugin to return text with image tags replaced.
 *
 * @MigrateProcessPlugin(
 *   id = "replace_images"
 * )
 */
class ReplaceImages extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * An instance of the "asset_creator" process plugin.
   *
   * @var \Drupal\migrate\Plugin\MigrateProcessInterface
   */
  protected $assetPlugin;

  /**
   * The migrate executable passed to the transform method.
   *
   * @var \Drupal\migrate\MigrateExecutableInterface
   */
  protected $migrateExecutable;

  /**
   * The row passed to the transform method.
   *
   * @var \Drupal\migrate\Row
   */
  protected $row;

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!$value) {
      return NULL;
    }

    $value = stripslashes($value);
    $value = str_replace('\"', '"', $value);
    if (isset($this->configuration['base_uri']) && $this->configuration['base_uri']) {
      $uri_match = '(' . $this->configuration['base_uri'] . ')?';
    }

    // Replace image tags with media embed tokens.
    $this->migrateExecutable = $migrate_executable;
    $this->row = $row;
    $pattern = '|<img .*src="(' . $uri_match . '/.*)".*>|Uis';
    $value = preg_replace_callback($pattern, [$this, 'replaceImgs'], $value);
    return $value;
  }

  /**
   * Replacement callback for converting image references to Drupal 8 embeds.
   *
   * @param array $matches
   *   An <img> src paramater extracted from a text field.
   *
   * @return string
   *   A Drupal 8 <drupal-entity> element embedding a media item.
   */
  protected function replaceImgs(array $matches) {
    $result = $matches[0];
    $src = $matches[1];
    $style_pattern = '/.*style=".*float: (right|left)/Uis';

    $media_id = $this->assetPlugin->transform($src, $this->migrateExecutable, $this->row, 'body');
    if ($media_id) {
      $media = Media::load($media_id);
      $media_uuid = $media->uuid();
      $data_align = '';
      if (preg_match($style_pattern, $result, $style_matches)) {
        $align = $style_matches[1];
        $data_align = " data-align=\"$align\"";
      }
      $result = "<drupal-entity data-embed-button=\"media_browser\" data-entity-embed-display=\"view_mode:media.embedded\" data-entity-type=\"media\"$data_align data-entity-uuid=\"$media_uuid\"></drupal-entity>";
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $asset_config = ['plugin' => 'asset_creator', 'bundle' => 'image'] + $configuration;
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migrate.process')
        ->createInstance('asset_creator', $asset_config)
    );
  }

  /**
   * ReplaceImages constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\migrate\Plugin\MigrateProcessInterface $asset_plugin
   *   An instance of the asset_creator plugin to create the media entity.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrateProcessInterface $asset_plugin) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->assetPlugin = $asset_plugin;
  }

}
