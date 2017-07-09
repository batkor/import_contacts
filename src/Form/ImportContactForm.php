<?php

namespace Drupal\module_import_contacts\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Json;
use Drupal\file\Entity\File;
use Drupal\module_import_contacts\BatchContactImport;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use \GuzzleHttp\Exception\RequestException;
use Drupal\Core\Entity;
/**
 * Class ImportContactForm.
 *
 * @package Drupal\module_import_contacts\Form
 */
class ImportContactForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['module_import_contacts.import'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'import_contact_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('module_import_contacts.import');

    $form['url_contacts'] = [
      '#title'         => 'URl для загрузки контактов',
      '#type'          => 'textfield',
      '#default_value' => $config->get('url_contacts') ? [$config->get('url_contacts')] : NULL,
      '#required'      => TRUE,
    ];

    $form['paramatr_marka'] = [
      '#title'         => 'Введите параметр',
      '#type'          => 'textfield',
      '#default_value' => $config->get('paramatr_marka') ? [$config->get('paramatr_marka')] : NULL,
      '#description'   => 'Введите необходимую модель для фильтрации',
      '#required'      => TRUE,
    ];

    $form['url_uslugi'] = [
      '#title'         => 'URl для загрузки списка услуг',
      '#type'          => 'textfield',
      '#default_value' => $config->get('url_uslugi') ? [$config->get('url_uslugi')] : NULL,
      '#required'      => TRUE,
    ];

    $form['url_udobstva'] = [
      '#title'         => 'URl для загрузки списка удобств',
      '#type'          => 'textfield',
      '#default_value' => $config->get('url_udobstva') ? [$config->get('url_udobstva')] : NULL,
      '#required'      => TRUE,
    ];

    $form['auth_setting'] = array(
      '#type' => 'fieldset',
      '#title' => 'Параметра аутентификации'
    );
    $form['auth_setting']['user_name'] = [
      '#title'         => 'Имя пользователя',
      '#type'          => 'textfield',
      '#default_value' => $config->get('user_name') ? [$config->get('user_name')] : NULL,
      '#required'      => TRUE,
    ];
    $form['auth_setting']['user_pass'] = [
      '#title'         => 'Пароль',
      '#type'          => 'password',
      '#default_value' => $config->get('user_pass') ? [$config->get('user_pass')] : NULL,
      '#required'      => TRUE,
    ];

    $form['import'] = [
      '#value'  => $this->t('Import'),
      '#type'   => 'submit',
      '#submit' => array('::import'),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function import(array &$form, FormStateInterface $form_state) {
    module_import_contacts_import_uslugi($form_state->getValue('url_uslugi'));
    module_import_contacts_import_udobstva($form_state->getValue('url_udobstva'));

    $url = $form_state->getValue('url_contacts') . '?marka=' . $form_state->getValue('paramatr_marka');
    try {
      $response = \Drupal::httpClient()
        ->get($url, [
          'headers' => ['Accept' => 'application/json'],
          'auth'    => [$form_state->getValue('user_name'), $form_state->getValue('user_pass')],
        ]);
      $data = (string) $response->getBody();
      $data = Json::decode($data);
      if (!empty($data)) {
        $this->remove_all_contacts();
        $import = new BatchContactImport($data);
        $import->setBatch();
      }
      else {
        drupal_set_message('Запрос вернул пустое значение', 'error');
      }
    } catch (RequestException   $e) {
      drupal_set_message(t('An error occurred while retrieving the data'), 'error');
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('module_import_contacts.import');
    $config->set('url_uslugi', $form_state->getValue('url_uslugi'));
    $config->set('url_udobstva', $form_state->getValue('url_udobstva'));
    $config->set('url_contacts', $form_state->getValue('url_contacts'));
    $config->set('paramatr_marka', $form_state->getValue('paramatr_marka'));
    $config->set('user_name', $form_state->getValue('user_name'));
    $config->set('user_pass', $form_state->getValue('user_pass'));
    $config->save();
  }

  private function remove_all_contacts(){

    $nids_query = \Drupal::database()->select('node', 'n')
      ->fields('n', array('nid'))
      ->condition('n.type', 'kontakty', 'IN')
      ->range(0, 500)
      ->execute();

    $nids = $nids_query->fetchCol();

    $controller = \Drupal::entityManager()->getStorage('node');
    $entities = $controller->loadMultiple($nids);
    $controller->delete($entities);
  }
}
