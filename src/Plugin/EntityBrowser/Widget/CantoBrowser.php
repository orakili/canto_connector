<?php

namespace Drupal\canto_connector\Plugin\EntityBrowser\Widget;

use Drupal\canto_connector\OAuthConnector;
use Drupal\canto_connector\CantoConnectorRepository;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\WidgetBase;
use Drupal\entity_browser\WidgetValidationManager;
use Drupal\file\FileInterface;
use Drupal\Component\Serialization\Json;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaSourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * An Entity Browser widget for creating media entities using Canto.
 *
 * @EntityBrowserWidget(
 *   id = "canto_browser",
 *   label = @Translation("Canto DAM Browser"),
 *   description = @Translation("Canto Dam Browser."),
 *   auto_select = FALSE
 * )
 */
class CantoBrowser extends WidgetBase {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * A media source manager.
   *
   * @var \Drupal\media\MediaSourceManager
   */
  protected $sourceManager;

  /**
   * An entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;


  /**
   * Drupal RequestStack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * CantoConnector repository.
   *
   * @var \Drupal\canto_connector\CantoConnectorRepository
   */
  protected $repository;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Canto browser constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    EventDispatcherInterface $event_dispatcher,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    WidgetValidationManager $validation_manager,
    AccountInterface $account,
    MediaSourceManager $sourceManager,
    RequestStack $requestStack,
    CantoConnectorRepository $repository,
    ConfigFactoryInterface $config,
    ImageFactory $imageFactory) {

    parent::__construct($configuration, $plugin_id, $plugin_definition, $event_dispatcher, $entity_type_manager, $validation_manager);
    $this->user = $account;
    $this->sourceManager = $sourceManager;
    $this->entityFieldManager = $entity_field_manager;
    $this->requestStack = $requestStack;
    $this->repository = $repository;
    $this->config = $config;
    $this->imageFactory = $imageFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.entity_browser.widget_validation'),
      $container->get('current_user'),
      $container->get('plugin.manager.media.source'),
      $container->get('request_stack'),
      $container->get('canto_connector.repository'),
      $container->get('config.factory'),
      $container->get('image.factory'));
  }

  /**
   * {@inheritdoc}
   *
   * @todo Add more settings for configuring this widget.
   * such as media type ...etc
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $mediaTypeOptions = [];
    $mediaTypes = $this->entityTypeManager->getStorage('media_type')
      ->loadByProperties(['source' => 'cantodam_asset']);

    foreach ($mediaTypes as $mediaType) {
      $mediaTypeOptions[$mediaType->id()] = $mediaType->label();
    }

    if (empty($mediaTypeOptions)) {
      $url = Url::fromRoute('entity.media_type.add_form')->toString();
      $form['media_type'] = [
        '#markup' => $this->t("You don't have media type of the Canto DAM asset type. You should <a href=':link'>create one</a>", [':link' => $url]),
      ];
    }
    else {
      $form['media_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Media type'),
        '#default_value' => $this->configuration['media_type'],
        '#options' => $mediaTypeOptions,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'media_type' => NULL,
      'submit_text' => $this->t('Select assets'),
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    // Start by inheriting parent form.
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);
    // Add container for assets (and folder buttons)
    $form['assets_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['assets-browser'],
      ],
    ];

    $config = $this->config->get('canto_connector.settings');
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#attached']['library'][] = 'canto_connector/canto_connector.entity_browser';
    $form['#attached']['library'][] = 'canto_connector/canto_connector.uc';
    $form['#attached']['drupalSettings']['canto_connector']['env'] = $config->get('env');
    $form['#attached']['drupalSettings']['canto_connector']['context'] = 'entity-browser';
    // @todo fix entry format.
    $entry = $this->checkAccessToken();
    if (count($entry) > 0) {
      $form['#attached']['drupalSettings']['canto_connector']['accessToken'] = $entry[0]['accessToken'];
      $form['#attached']['drupalSettings']['canto_connector']['tenants'] = $entry[0]['subDomain'];
      $form['#attached']['drupalSettings']['canto_connector']['tokenType'] = $entry[0]['tokenType'];
      $supported_extensions = $this->imageFactory->getSupportedExtensions();
      $form['#attached']['drupalSettings']['canto_connector']['allowExtensions'] = implode(';', $supported_extensions);
    }

    $form['assets_container']['canto_container'] = [
      '#type' => 'container',
      '#id' => 'cantoPickbox',
      '#attributes' => [
        'class' => ['canto-pick-box'],
      ],
    ];
    $form['assets_container']['canto_container']['trigger'] = [
      '#prefix' => '<a>',
      '#markup' => '<div class="img-box" id="cantoimage"> + Insert Files from Canto</div>',
      '#suffix' => '</a>',
    ];
    $form['assets_container']['cantofid'] = [
      '#type' => 'hidden',
    ];
    $form['assets_container']['assets_data'] = [
      '#type' => 'hidden',
      '#attributes' => ['data-assets' => '', 'id' => 'canto-assets-data'],
    ];
    $form['assets_container']['canto_asset_id'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'canto-asset-id'],
    ];
    $form['assets_container']['actions'] = $form['actions'];
    // Hide the submit button and allow JS to trigger upon selection.
    $form['assets_container']['actions']['#attributes']['class'][] = 'visually-hidden';
    unset($form['actions']);
    return $form;
  }

  /**
   * Check the access token for the current user.
   *
   * @todo this is orginial form the contrib but need refactoring
   * possible use userData for storing token.
   */
  public function checkAccessToken() {
    $user = $this->user;
    $userId = $user->id();
    $envSettings = $this->config->get('canto_connector.settings')->get('env');
    $env = ($envSettings === NULL) ? "canto.com" : $envSettings;
    $entries = [];

    $entry = [
      'uid' => $userId,
      'env' => $env,
    ];

    $entries = $this->repository->getAccessToken($entry);
    if (count($entries) > 0) {
      $subDomain = $entries[0]['subDomain'];
      $accessToken = $entries[0]['accessToken'];
      $isValid = OAuthConnector::checkAccessTokenValid($subDomain, $accessToken);
      if (!$isValid) {
        $this->repository->delete($entry);
        $entries = [];
      }
    }
    return $entries;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array &$form, FormStateInterface $formState) {
    if (!empty($formState->getTriggeringElement()['#eb_widget_main_submit'])) {
      // The media bundle.
      $mediaBundleConfig = $this->entityTypeManager->getStorage('media_type');
      $mediaBundle = $mediaBundleConfig->load($this->configuration['media_type']);
      // Load the field definitions for this bundle.
      $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('media', $mediaBundle->id());
      // Load the file settings to validate against.
      $fieldMap = $mediaBundle->getFieldMap();
      if (!isset($fieldMap['file'])) {
        $message = $this->t('Missing file mapping. Check your media configuration.');
        $formState->setError($form['widget'], $message);
        return;
      }
      $fileExtensions = $fieldDefinitions[$fieldMap['file']]->getItemDefinition()
        ->getSetting('file_extensions');
      $supportedExtensions = explode(',', preg_replace('/,?\s/', ',', $fileExtensions));
      // The form input uses checkboxes which returns zero for unchecked assets.
      // Remove these unchecked assets.
      $assetsJson = urldecode($formState->getValue('assets_data'));
      $assets = JSON::decode($assetsJson);

      // Get the cardinality for the media field that is being populated.
      $fieldCardinality = $formState->get([
        'entity_browser',
        'validators',
        'cardinality',
        'cardinality',
      ]);
      // Getting all ids, validating field cardinatlity.
      if (is_array($assets) && !count($assets)) {
        $formState->setError($form['widget']['assets_container'], $this->t('Please select an asset.'));
      }
      // If the field cardinality is limited and the number of assets selected
      // is greater than the field cardinality.
      // @todo Support a rich text field where cardinality isn't an issue.
      if ($fieldCardinality > 0 && count($assets) > $fieldCardinality) {
        $message = $this->formatPlural($fieldCardinality, 'You can not select more than 1 entity.', 'You can not select more than @count entities.');
        // Set the error message on the form.
        $formState->setError($form['widget']['assets_container']['assets_data'], $message);
      }
      // Transform the assets data:
      $allAssetsData = $this->processAssetsData($assets);
      // If the asset's file type does not match allowed file types.
      foreach ($allAssetsData as $assetData) {
        $info = pathinfo($assetData['displayName']);
        $typSupported = in_array($info['extension'], $supportedExtensions);

        if (!$typSupported) {
          $message = $this->t('Please make another selection. The "@filetype" file type is not one of the supported file types (@supported_types).', [
            '@filetype' => $info['extension'],
            '@supported_types' => implode(', ', $supportedExtensions),
          ]);
          // Set the error message on the form.
          $formState->setError($form, $message);
        }
      }
      // Hold on the transformed data.
      $formState->setValue('assets', $allAssetsData);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $assets = [];
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $assets = $this->prepareEntities($form, $form_state);
    }
    $this->selectEntities($assets, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $formState) {
    $assets = $formState->getValue('assets');
    // Get canto assets ids;.
    $assetIds = array_keys($assets);
    // Load type information.
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')
      ->load($this->configuration['media_type']);
    // Get the source field for this type which stores the asset id.
    $source_field = $media_type->getSource()
      ->getSourceFieldDefinition($media_type)
      ->getName();
    // Query for existing entities.
    $existing_ids = $this->getExistingEntities($media_type, $source_field, $assetIds);
    // Load the entities found.
    $entities = $this->entityTypeManager->getStorage('media')
      ->loadMultiple($existing_ids);
    // Loop through the existing entities.
    foreach ($entities as $entity) {
      // Set the asset id of the current entity.
      $assetId = $entity->get($source_field)->value;
      // If the asset id of the entity is in the list of asset id's selected.
      if (in_array($assetId, $assetIds)) {
        // Remove the asset id from the input so it does not get fetched
        // and does not get created as a duplicate.
        unset($assets[$assetId]);
      }
    }
    // @todo Create canto asset utility class.
    // Loop through the returned assets.
    foreach ($assets as $id => $info) {
      // Get the file data from the directUri:
      $fileData = file_get_contents($info['directUri']);
      if (!$fileData) {
        return $formState->setError($form['widget']['assets_container'], $this->t('An error occurred during file retrieval.'));
      }
      // @todo if the file exists should we use the original or the new one?
      $file = file_save_data($fileData, 'public://' . $info['displayName'], FileSystemInterface::EXISTS_REPLACE);
      $entity = $this->createMediaEntity($media_type, $file, $id);
      // Add the new entity to the array of returned entities.
      $entities[] = $entity;
    }
    return $entities;
  }

  /**
   * Create media entities from canto assets array.
   *
   * @param \Drupal\media\Entity\MediaType $mediaType
   *   The media type object.
   * @param \Drupal\file\FileInterface $file
   *   The file to attach.
   * @param string $assetId
   *   The canto asset id.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The media entity created.
   */
  public function createMediaEntity(MediaType $mediaType, FileInterface $file, $assetId): EntityInterface {
    // Get the source field for this type which stores the asset id.
    $source_field = $mediaType->getSource()
      ->getSourceFieldDefinition($mediaType)
      ->getName();
    // Initialize entity values.
    $entity_values = [
      'bundle' => $mediaType->id(),
      // This should be the current user id.
      'uid' => $this->user->id(),
      // @todo This should be the current language code.
      'langcode' => LanguageInterface::LANGCODE_DEFAULT,
      // $this->languageManager->getCurrentLanguage()->getId(),
      // This should map the asset status to the drupal entity status.
      // @todo ($asset->status === 'active'),
      'status' => TRUE,
      // Set the entity name to the asset name.
      'name' => $file->label(),
      'field_file' => ['target_id' => $file->id()],
      // Set the chosen source field for this entity to the asset id.
      $source_field => $assetId,
    ];
    // Create a new entity to represent the asset.
    $entity = $this->entityTypeManager->getStorage('media')
      ->create($entity_values);
    // Save the entity.
    $entity->save();
    // Reload the entity to make sure we have everything populated properly.
    $entity = $this->entityTypeManager->getStorage('media')
      ->load($entity->id());
    return $entity;
  }

  /**
   * Get existing entities with asset ID.
   */
  public function getExistingEntities($mediaType, $sourceField, $assetIds) {
    return $this->entityTypeManager->getStorage('media')
      ->getQuery()
      ->addTag('canto_existing_entities')
      ->condition('bundle', $mediaType->id())
      ->condition($sourceField, $assetIds, 'IN')
      ->execute();
  }

  /**
   * Transform the data.
   */
  public function processAssetsData(array $assets) {
    $results = [];
    foreach ($assets as $asset) {
      $results[$asset['id']] = $asset;
    }
    return $results;
  }

}
