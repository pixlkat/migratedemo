<?php

namespace Drupal\migrate_demo_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate_plus\Entity\Migration;

/**
 * Configuration form for the Demo Content migration.
 */
class MigrationConfigurationForm extends FormBase {
  const DESTINATION_FOLDER = 'private://uploaded_data';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_demo_content_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['demo_content_article_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Blog Article data export file (CSV)'),
      '#description' => $this->t('Select a CSV export of blog articles. Maximum file size is @size.',
        ['@size' => format_size(file_upload_max_size())]),
    ];

    $form['demo_content_category_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Article Category export file (CSV)'),
      '#description' => $this->t('Select a CSV export of article categories. Maximum file size is @size.',
        ['@size' => format_size(file_upload_max_size())]),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $directory = self::DESTINATION_FOLDER;
    file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

    $all_files = $this->getRequest()->files->get('files', []);
    if (!empty($all_files['demo_content_article_file'])) {
      $validators = ['file_validate_extensions' => ['csv']];
      if ($file = file_save_upload('demo_content_article_file', $validators, self::DESTINATION_FOLDER, 0, FILE_EXISTS_REPLACE)) {
        $demo_articles_migration = Migration::load('demo_articles');
        $source = $demo_articles_migration->get('source');
        $source['path'] = $file->getFileUri();
        $demo_articles_migration->set('source', $source);
        $demo_articles_migration->save();
        drupal_set_message($this->t('Demo article content data uploaded as @uri.', ['@uri' => $file->getFileUri()]));
      }
    }

    if (!empty($all_files['demo_content_category_file'])) {
      $validators = ['file_validate_extensions' => ['csv']];
      if ($file = file_save_upload('demo_content_category_file', $validators, self::DESTINATION_FOLDER, 0, FILE_EXISTS_REPLACE)) {
        $demo_category_migration = Migration::load('demo_categories');
        $source = $demo_category_migration->get('source');
        $source['path'] = $file->getFileUri();
        $demo_category_migration->set('source', $source);
        $demo_category_migration->save();
        drupal_set_message($this->t('Demo category data uploaded as @uri.', ['@uri' => $file->getFileUri()]));
      }
    }

  }

}
