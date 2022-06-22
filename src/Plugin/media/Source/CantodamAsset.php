<?php

namespace Drupal\canto_connector\Plugin\media\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\Image;

/**
 * Provides media type plugin for DAM assets.
 *
 * @MediaSource(
 *   id = "cantodam_asset",
 *   label = @Translation("Canto DAM asset"),
 *   description = @Translation("Provides business logic and metadata for
 *   assets stored on Canto DAM."),
 *   allowed_field_types = {"string"},
 *   default_thumbnail_filename = "no-thumbnail.png",
 * )
 */
class CantodamAsset extends Image {

  /**
   * The media entity that is being wrapped.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected $mediaEntity;

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
   * {@inheritDoc}
   */
  public function getMetadata(MediaInterface $media, $name) {
    $field = $this->getAssetFileField($media);
    $file = $media->get($field)->entity;
    // If the source field is not required, it may be empty.
    if (!$file) {
      return parent::getMetadata($media, $name);
    }
    $uri = $file->getFileUri();
    switch ($name) {
      case 'default_name':
        return parent::getMetadata($media, 'default_name');

      case static::METADATA_ATTRIBUTE_WIDTH:
        $image = $this->imageFactory->get($uri);
        return $image->getWidth() ?: NULL;

      case static::METADATA_ATTRIBUTE_HEIGHT:
        $image = $this->imageFactory->get($uri);
        return $image->getHeight() ?: NULL;

      case 'file':
        $is_file = !empty($file) && $file instanceof FileInterface;
        return $is_file ? $file->id() : NULL;

      case 'thumbnail_uri':
        return $uri;

    }
    // @todo Change the autogenerated stub.
    // return parent::getMetadata($media, $name);
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

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    $attributes = parent::getMetadataAttributes();
    $attributes += [
      'file' => $this->t('File'),
    ];
    return $attributes;
  }

}
