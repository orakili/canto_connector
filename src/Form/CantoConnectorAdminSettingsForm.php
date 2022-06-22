<?php

namespace Drupal\canto_connector\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Admin form for canto connector module.
 */
class CantoConnectorAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'canto_connector_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'canto_connector.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('canto_connector.settings');

    $form['env'] = [
      '#type' => 'select',
      '#title' => $this->t('Canto Environment selection'),
      '#options' => [
        'canto.com' => $this->t('canto.com'),
        'canto.global' => $this->t('canto.global'),
        'canto.de' => $this->t('canto.de'),
        'ca.canto.com' => $this->t('ca.canto.com'),
      ],
      '#default_value' => $config->get('env') ?? 'canto.com',
      '#attributes' => [
        'data-editor-canto_connector-canto' => 'env',
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->configFactory->getEditable('canto_connector.settings')
      ->set('env', $form_state->getValue('env'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
