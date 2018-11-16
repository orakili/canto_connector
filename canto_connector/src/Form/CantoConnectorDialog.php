<?php

namespace Drupal\canto_connector\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;


class CantoConnectorDialog extends FormBase {


  protected $fileStorage;

  public function __construct(EntityStorageInterface $file_storage) {
    $this->fileStorage = $file_storage;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('file')
    );
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

    $form['files'] = [
        '#type' => 'item',
        '#markup' => '<div id="cantoPickbox" >
        <div class="img-box" id="cantoimage">
           + Insert Files from Canto
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
      '#value' => $this->t('confirm'),
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
    
    $insertHTML="";
    $title = $form_state->getValue('cantofid');
   
    $assets = explode(";", $title);
    foreach ( $assets as $item)
    { 
        if(strlen($item) > 1)
        {
        \Drupal::logger('canto_connector')->notice('original_image-'.$item); 
        $local = system_retrieve_file($item, NULL, TRUE, FILE_EXISTS_REPLACE);
        $filename=$local->getFilename();
        $efid=$local->id();
        $drupal_file_uri = File::load($efid)->getFileUri();

        $image_path = file_url_transform_relative(file_create_url($drupal_file_uri));
        \Drupal::logger('canto_connector')->notice("filename-". $filename); 
        \Drupal::logger('canto_connector')->notice("image_path-". $image_path); 
        
        $insertHTML .= "<img alt=".$filename." src=". $image_path.">";
        }
    } 
    
    $response->addCommand(new EditorDialogSave($insertHTML));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
   
  }

}

