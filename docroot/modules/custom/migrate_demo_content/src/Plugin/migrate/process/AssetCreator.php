<?php

namespace Drupal\migrate_demo_content\Plugin\migrate\process;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a process plugin to retrieve and create a media asset.
 *
 * The plugin expects either a single URI string or an array of two values,
 * the URI and the media name. If you are passing both, the URI must be in the
 * first position.
 *
 * Examples:
 *
 * @code
 * process:
 *   unnamed_file:
 *     plugin: asset_creator
 *     source: filepath
 *   named_file:
 *     plugin: asset_creator
 *     source:
 *       - filepath
 *       - name
 * @endcode
 *
 * The first map will copy the file from filepath and create a media entity
 * with an empty media name.
 *
 * The second map will copy the file from filepath and create a media entity
 * with the media name set to the value passed in "name".
 *
 * @MigrateProcessPlugin(
 *   id = "asset_creator"
 * )
 */
class AssetCreator extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * An instance of the file_copy process plugin.
   *
   * @var \Drupal\migrate\Plugin\MigrateProcessInterface
   */
  protected $fileCopyPlugin;

  /**
   * An instance of the entity storage interface.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * AssetCreator constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\migrate\Plugin\MigrateProcessInterface $file_copy_plugin
   *   An instance of the download plugin for downloading the file.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   An instance of the entity storage interface.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrateProcessInterface $file_copy_plugin, EntityStorageInterface $storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileCopyPlugin = $file_copy_plugin;
    $this->storage = $storage;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migrate.process')
        ->createInstance('download'),
      $container->get('entity.manager')->getStorage('file')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!$value || !$this->configuration['bundle']) {
      return NULL;
    }
    if (is_string($value)) {
      $uri = $value;
      $media_name = '';
    }
    else {
      list($uri, $media_name) = $value;
    }
    if (!$uri) {
      $migrate_executable->saveMessage('No file URI provided');
      return NULL;
    }
    $language = $row->getDestinationProperty('langcode') ?: 'en';
    $source_dir = $this->configuration['source_dir'] ?: '';
    $destination_schema = $this->configuration['schema'] ?: 'public://';

    $uri = ltrim($uri, '/');
    $asset_source = $source_dir . $uri;
    $asset_destination = $destination_schema . $uri;

    try {
      $asset_file_uri = $this->fileCopyPlugin->transform([
        $asset_source,
        $asset_destination,
      ], $migrate_executable, $row, $destination_property);
    }
    catch (MigrateException $e) {
      $migrate_executable->saveMessage($e->getMessage());
      \Drupal::logger('asset_creator')
        ->warning('Unable to retrieve file @asset: @message', [
          '@asset' => $asset_source,
          '@message' => $e->getMessage(),
        ]);
      return NULL;
    }

    $file = $this->storage->loadByProperties(['uri' => $asset_file_uri]);
    if ($file) {
      $file = reset($file);
    }
    else {
      $file = File::create();
      $file->setFileUri($asset_file_uri);
      $file->setOwnerId(1);
      $file->setMimeType(mime_content_type($asset_file_uri));
      $file->setFileName(basename($asset_file_uri));
    }

    $file->setPermanent();
    $file->save();

    $query = \Drupal::entityQuery('media');
    $query->condition('bundle', $this->configuration['bundle']);
    $query->condition('langcode', $language);
    $query->condition('image.target_id', $file->id());
    $query->sort('mid', 'DESC');

    $ids = $query->execute();
    if ($ids) {
      $image_media = Media::load(reset($ids));
      $image_media->setPublished();
    }
    else {
      $image_media = Media::create([
        'bundle' => $this->configuration['bundle'],
        'uid' => '1',
        'langcode' => $language,
        'status' => 1,
        'name' => $media_name,
        'image' => [
          'target_id' => $file->id(),
          'alt' => '',
          'title' => '',
        ],
      ]);
    }
    $image_media->save();
    return $image_media->id();
  }

}
