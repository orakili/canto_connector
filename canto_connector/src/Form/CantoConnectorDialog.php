<?php

namespace Drupal\canto_connector\Form;

use Drupal\Core\Field\TypedData\FieldItemDataDefinition;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\editor\Ajax\EditorDialogSave;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\canto_connector\CantoConnectorRepository;
use Drupal\canto_connector\OAuthConnector;
use Drupal\media\MediaInterface;
use Drupal\media\MediaTypeInterface;


class CantoConnectorDialog extends FormBase {


  protected $fileStorage;
  protected $repository;
  public function __construct(EntityStorageInterface $file_storage,CantoConnectorRepository $repository) {
    $this->fileStorage = $file_storage;

    $this->repository = $repository;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('file'),
      $container->get('canto_connector.repository') ,
      $container->get('string_translation'));
  }

  public function getFormId() {
    return 'canto_connector_dialog';
  }

  public function buildForm(array $form, FormStateInterface $form_state, FilterFormat $filter_format = NULL) {
    if (isset($form_state->getUserInput()['editor_object'])) {
      $image_element = $form_state->getUserInput()['editor_object'];
      $form_state->set('image_element', $image_element);
      $form_state->setCached(TRUE);
    }
    else {
      $image_element = $form_state->get('image_element') ?: [];
    }
    
    $config = $this->config('canto_connector.settings');
    $image_styles = image_style_options(FALSE);
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#attached']['library'][] = 'canto_connector/canto_connector.inserter';
    $form['#attached']['library'][] = 'canto_connector/canto_connector.uc';
    $form['#attached']['drupalSettings']['canto_connector']['env'] = $config->get('env');
    $entry= $this->CheckAccessToken();
    if(count($entry) >0)
    {
        \Drupal::logger('canto_connector')->notice("check access -". $entry[0]['accessToken']);
        $form['#attached']['drupalSettings']['canto_connector']['accessToken'] =$entry[0]['accessToken'];
        $form['#attached']['drupalSettings']['canto_connector']['tenants'] =$entry[0]['subDomain'];
        $form['#attached']['drupalSettings']['canto_connector']['tokenType'] =$entry[0]['tokenType'];
        
        $image_factory = \Drupal::service('image.factory');
        $supported_extensions = $image_factory ->getSupportedExtensions();
        $form['#attached']['drupalSettings']['canto_connector']['allowExtensions'] = implode(';', $supported_extensions);
        
    }  
    
    $form['files'] = [
        '#type' => 'item',
        '#markup' => '<div id="cantoPickbox" class="canto-pick-box">
        <div class="img-box" id="cantoimage">
           + Insert Files from Canto
        </div>
		 <div class="info">The total selected files size is limited to 128 MB. 
        </div>
    </div>',
    ];

    unset($form['cantofid']);
    $form['cantofid'] = [
        '#type' => 'hidden',
    ];

    $form['actions'] = array(
      '#type' => 'actions',
    );

    $form['actions']['save_modal'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Close'),
      '#submit' => array(),
        '#class' => 'my_class',
      '#ajax' => array(
        'callback' => '::submitForm',
        'event' => 'click',
      ),
        
    );
    $form['actions']['save_modal']['#attributes'] = array('class' => array('canto-confirm-button'));
    
    return $form;
  }


 public function submitForm(array &$form, FormStateInterface $form_state) {
     $response = new AjaxResponse();
     
     $cantoFiles = $form_state->getValue('cantofid');
     $insertHTML= '' ;
     
     if(strlen($cantoFiles)>1)
     {
         $assets = explode(";", $cantoFiles);
         
         foreach ( $assets as $item)
         {
             if(strlen($item) > 1)
             {
                 
                 $array=explode(",", $item);
                 $url =  $array[0];
                 $fileName=$array[1];
                 \Drupal::logger('canto_connector')->notice('original_image-'.$url);
                 
                 $local = system_retrieve_file($url, 'public://'.$fileName, TRUE, FILE_EXISTS_REPLACE);
                 
                 $efid=$local->id();
                 $file=File::load($efid);
                 $drupal_file_uri = $file->getFileUri();
                 
                 $image_path = file_url_transform_relative(file_create_url($drupal_file_uri));
                 \Drupal::logger('canto_connector')->notice("image_path-". $image_path);
                 
                 $insertHTML .= "<img alt=".$fileName." src=". $image_path.">";
                 $this->createMedia($efid);
                 $form_state->setValue('cantofid','');
                 $form_state->setValue('insertHTML',$insertHTML);
                 
             }
         }
     }
     $response->addCommand(new EditorDialogSave($form_state->getValue('insertHTML')));
     $response->addCommand(new CloseModalDialogCommand());
     return $response;
   
  }
  
  public function CheckAccessToken()
  {
      $user =  \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      $userId= $user->get('uid')->value;
      $envSettings=$this->config('canto_connector.settings')->get('env');
      $env=($envSettings === NULL)?"canto.com":$envSettings;
      $entries=[];

      $entry = [
          'uid' => $userId,
          'env' => $env,
      ];
      
      $entries = $this->repository->getAccessToken($entry);
      if(count($entries) >0 )
      {
          
          $subDomain = $entries[0]['subDomain'];
          
          $accessToken = $entries[0]['accessToken'];
          
          $connector = new OAuthConnector();
          $isValid = $connector->checkAccessTokenValid($subDomain, $accessToken);
          \Drupal::logger('canto_connector')->notice("check access token valid");
          if (! $isValid) {
              $this->repository->delete($entry);
              \Drupal::logger('canto_connector')->notice("delete invalid token");
              $entries=[];
          }
      }
      return $entries;
  }
  
  public function createMedia(int $fid) {
      $info = system_get_info('module', 'media');
      if ($info) {
          $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
          
          /** @var \Drupal\file\FileInterface $file */
          $types = $this->filterTypesThatAcceptFile($file, $this->getTypes());
          if (!empty($types)) {
              //if (count($types) === 1) {
                  $this->createMediaEntity($file, reset($types))->save();
              //}
          }
      }
      
  }
  
  protected function createMediaEntity(FileInterface $file, MediaTypeInterface $type) {
      $source_field = $type->getSource()->getSourceFieldDefinition($type)->getName();
      $media = \Drupal::entityTypeManager()->getStorage('media')->create([
          'bundle' => $type->id(),
          'name' => $file->getFilename(),
          $source_field =>$file->id()
      ]);
      
      return $media;
  }
  
  
  protected function getUploadLocationForType(MediaTypeInterface $type) {
      return $this->getFileItemForType($type)->getUploadLocation();
  }
  protected function filterTypesThatAcceptFile(FileInterface $file, array $types) {
      $types = $this->filterTypesWithFileSource($types);
      return array_filter($types, function (MediaTypeInterface $type) use ($file) {
          $validators = $this->getUploadValidatorsForType($type);
          $errors = file_validate($file, $validators);
          return empty($errors);
      });
  }
  
  
  protected function getUploadValidatorsForType(MediaTypeInterface $type) {
      return $this->getFileItemForType($type)->getUploadValidators();
  }
  
  protected function getFileItemForType(MediaTypeInterface $type) {
      $source = $type->getSource();
      $source_data_definition = FieldItemDataDefinition::create($source->getSourceFieldDefinition($type));
      return new FileItem($source_data_definition);
  }
  
  protected function filterTypesWithFileSource(array $types) {
      return array_filter($types, function (MediaTypeInterface $type) {
          return is_a($type->getSource()->getSourceFieldDefinition($type)->getClass(), FileFieldItemList::class, TRUE);
      });
  }
  
  protected function filterTypesWithCreateAccess(array $types) {
      $access_handler =  \Drupal::entityTypeManager()->getAccessControlHandler('media');
      return array_filter($types, function (MediaTypeInterface $type) use ($access_handler) {
          return $access_handler->createAccess($type->id());
      });
  }
  
  protected function getTypes(array $allowed_types = NULL) {
      if (!isset($this->types)) {
          $media_type_storage = \Drupal::entityTypeManager()->getStorage('media_type');
          
          if (!$allowed_types) {
              $allowed_types = $this->media_library_get_allowed_types() ?: NULL;
          }
          $types = $media_type_storage->loadMultiple($allowed_types);
          
          $types = $this->filterTypesWithFileSource($types);
          
          $types = $this->filterTypesWithCreateAccess($types);
          $this->types = $types;
      }
      return $this->types;
  }
  
  protected function media_library_get_allowed_types() {
      $t = \Drupal::request()->query->get('media_library_allowed_types');
      \Drupal::logger('canto_connector')->notice('$media_library_get_allowed_types-'.json_encode($t));
      if ($types && is_array($t)) {
          return array_filter($t, 'is_string');
      }
      return [];
  }

}

