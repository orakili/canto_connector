<?php

namespace Drupal\canto_connector\Plugin\media\Source;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;

/**
 * Provides media type plugin for DAM assets.
 *
 * @MediaSource(
 *   id = "cantodam_asset",
 *   label = @Translation("Canto DAM asset"),
 *   description = @Translation("Provides business logic and metadata for
 *   assets stored on Canto DAM."),
 *   allowed_field_types = {"string", "json_native"},
 *   default_thumbnail_filename = "no-thumbnail.png",
 * )
 */
class CantodamAsset extends MediaSourceBase {

  const METADATA_FIELD_NAME = 'field_cantodam_asset_metadata';

  /**
   * The media entity that is being wrapped.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected $mediaEntity;

  /**
   * Statically cached metadata information for the given assets.
   *
   * @var array
   */
  protected $metadata;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Fieldset with configuration options not needed.
    hide($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'source_field' => 'field_cantodam_asset_id',
      'metadata_field' => CantodamAsset::METADATA_FIELD_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $submitted_config = array_intersect_key(
      $form_state->getValues(),
      $this->configuration
    );
    foreach ($submitted_config as $config_key => $config_value) {
      $this->configuration[$config_key] = $config_value;
    }

    // For consistency, always use the default source_field field name.
    $default_field_name = $this->defaultConfiguration()['source_field'];
    // Check if it already exists so it can be used as a shared field.
    $storage = $this->entityTypeManager->getStorage('field_storage_config');
    $existing_source_field = $storage->load('media.' . $default_field_name);

    // Set or create the source field.
    if ($existing_source_field) {
      // If the default field already exists, return the default field name.
      $this->configuration['source_field'] = $default_field_name;
    }
    else {
      // Default source field name does not exist, so create a new one.
      $field_storage = $this->createSourceFieldStorage();
      $field_storage->save();
      $this->configuration['source_field'] = $field_storage->getName();
    }

    $metadata_field_name = $this->defaultConfiguration()['metadata_field'];
    // Check if it already exists so it can be used as a shared field.
    $existing_metadata_field = $storage->load('media.' . $metadata_field_name);

    // Set or create the source field.
    if ($existing_metadata_field) {
      // If the default field already exists, return the default field name.
      $this->configuration['metadata_field'] = $metadata_field_name;
    }
    else {
      // Default source field name does not exist, so create a new one.
      $metadata_field_storage = $this->createMetadataFieldStorage();
      $metadata_field_storage->save();
      $this->configuration['metadata_field'] = $metadata_field_storage->getName();
    }
  }

  /**
   * {@inheritdoc}
   *
   * @todo fix with appropriate canto fields.
   */
  public function getMetadataAttributes() {
    $fields = [
      'file' => $this->t('file'),
      'metadata' => $this->t('Metadata'),
      'uuid' => $this->t('ID'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'approval_status' => $this->t('Approval Status'),
      'tags' => $this->t('Tags'),
      'scheme' => $this->t('Type'),
      'smart_tags' => $this->t('Smart Tags'),
      'thumbnail_urls' => $this->t('Thumbnail urls'),
      'width' => $this->t('Width'),
      'height' => $this->t('Height'),
      'created' => $this->t('Date created'),
      'modified' => $this->t('Data modified'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function createMetaDataFieldStorage() {
    $default_field_name = $this->defaultConfiguration()['metadata_field'];
    // Create the field.
    return $this->entityTypeManager->getStorage('field_storage_config')->create(
      [
        'entity_type' => 'media',
        'field_name' => $default_field_name,
        'type' => next($this->pluginDefinition['allowed_field_types']),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function createSourceFieldStorage() {
    $default_field_name = $this->defaultConfiguration()['source_field'];
    // Create the field.
    return $this->entityTypeManager->getStorage('field_storage_config')->create(
      [
        'entity_type' => 'media',
        'field_name' => $default_field_name,
        'type' => reset($this->pluginDefinition['allowed_field_types']),
      ]
    );
  }

  /**
   * Ensures the given media entity has Canto metadata information in place.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return bool
   *   TRUE if the metadata is ensured. Otherwise, FALSE.
   *
   * @todo add the method.
   */
  public function ensureMetadata(MediaInterface $media) {
    if (!$media->hasField(CantodamAsset::METADATA_FIELD_NAME)) {
      \Drupal::logger('canto')
        ->error('The media type @type must have a canto metadata field named "canto_metadata".', [
          '@type' => $media->bundle(),
        ]);
      return FALSE;
    }
    $mediaId = $this->getSourceFieldValue($media);
    $metadata = Json::decode($media->get(CantodamAsset::METADATA_FIELD_NAME)->value);
    if (is_array($metadata)) {
      $this->metadata[$mediaId] = $metadata;
    }
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function getMetadata(MediaInterface $media, $name) {
    if (!$this->ensureMetadata($media)) {
      throw new \RuntimeException('Metadata field not found.');
    }
    $field = $this->getAssetFileField($media);
    $file = $media->get($field)->entity;
    $id = $this->getSourceFieldValue($media);
    // If the source field is not required, it may be empty.
    if (!$file) {
      return parent::getMetadata($media, $name);
    }
    switch ($name) {
      case 'default_name':
        return parent::getMetadata($media, 'default_name');

      case 'file':
        $is_file = !empty($file) && $file instanceof FileInterface;
        return $is_file ? $file->id() : NULL;

      case 'thumbnail_uri':
        return $file->getFileUri();

      default:
        $default = $this->metadata[$id]['detailData'][$name] ?? FALSE;
        return $default ?: parent::getMetadata($media, $name);

    }
  }

  /**
   * Gets the file field being used to store the asset.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return false|string
   *   The name of the file field on the media bundle or FALSE on failure.
   */
  protected function getAssetFileField(MediaInterface $media) {
    try {
      /** @var \Drupal\media\Entity\MediaType $bundle */
      $bundle = $this->entityTypeManager->getStorage('media_type')
        ->load($media->bundle());
      $field_map = !empty($bundle) ? $bundle->getFieldMap() : FALSE;
    }
    catch (\Exception $exception) {
      watchdog_exception('error', $exception->getMessage());
      return FALSE;
    }
    return empty($field_map['file']) ? FALSE : $field_map['file'];
  }

  /**
   * Gets the value of a field without knowing the key to use.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param string $fieldName
   *   The field name.
   *
   * @return null|mixed
   *   The field value or NULL.
   */
  protected function getFieldPropertyValue(MediaInterface $media, $fieldName) {
    if ($media->hasField($fieldName)) {
      /** @var \Drupal\Core\Field\FieldItemInterface $item */
      $item = $media->{$fieldName}->first();
      if (!empty($item)) {
        $property_name = $item->mainPropertyName();
        if (isset($media->{$fieldName}->{$property_name})) {
          return $media->{$fieldName}->{$property_name};
        }
      }
    }
    return NULL;
  }

}
