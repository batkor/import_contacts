<?php

namespace Drupal\module_import_contacts;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Component\Serialization\Json;

/**
 * Class BatchContactImport.
 *
 * @package Drupal\module_import_contacts
 */
class BatchContactImport {

  # Здесь мы будем хранить всю информацию о нашей Batch операции.
  private $batch;

  private $data;

  /**
   * {@inheritdoc}
   */
  public function __construct($data, $batch_name = 'Import Contacts') {
    $this->data = $data;
    $this->batch = [
      'title'    => $batch_name,
      'finished' => [$this, 'finished'],
      'file'     => drupal_get_path('module', 'module_import_contacts') . '/src/BatchContactImport.php',
    ];
    $this->parse();
  }

  /**
   * {@inheritdoc}
   *
   * В данном методе мы обрабатываем наш CSV строка за строкой, а не грузим
   * весь файл в память, так что данный способ значительно менее затратный
   * и более шустрый.
   *
   * Каждую строку мы получаем в виде массива, а массив передаем в операцию на
   * выполнение.
   */
  public function parse() {
    foreach ($this->data as $item) {
      $this->setOperation($item);
    }
  }

  /**
   * {@inheritdoc}
   *
   * В данном методе мы подготавливаем операции для импорта. В него мы будем
   * передавать данные в виде массива. Каждая строка
   * будет добавлена в операцию данным методом.
   */
  public function setOperation($data) {
    $this->batch['operations'][] = array(
      array($this, 'processItem'),
      array($data),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Обработка элемента (строки из файла). В соответствии со столбцами и их
   * порядком мы получаем их данные в переменные. И не забываем про $context.
   */
  public function processItem($item, &$context) {

      $config = \Drupal::getContainer()->get('config.factory')
        ->getEditable('module_import_contacts.import');
      $id_mass = $config->get('ids_importing', []);

      if (empty($id_mass)) {
        $node = Node::create(array(
          'type'     => 'kontakty',
          'langcode' => 'ru',
          'uid'      => 1,
          'status'   => 1,
        ));
      }
      else {
        $node = Node::load($id_mass[$item['nid']]);
        if (is_null($node)) {
          $node = Node::create(array(
            'type'     => 'kontakty',
            'langcode' => 'ru',
            'uid'      => 1,
            'status'   => 1,
          ));
        }
      }
      $node->title = $item['title'];
      $node->field_url = $item['field_url'];
      $node->field_adres = $item['field_adres'];
      $node->field_kontaktnoe_lico = $item['field_kontaktnoe_lico'];
      $node->field_koordinaty = $item['field_koordinaty'];
      $node->field_dolznost = $item['field_dolznost'];
      $node->field_koordinaty_geometki = $item['field_koordinaty_geometki'];
      $node->field_yandex = $item['field_yandex'];
      $node->field_pokazat_na_karte = $item['field_pokazat_na_karte'];
      $node->body = [
        'value'  => $item['body'],
        'format' => 'full_html',
      ];
      $node->field_telefon = $item['field_telefon'];
      $node->field_casy_raboty = $item['field_casy_raboty'];

      //Set value usluga field
      $udobstva_s = explode("|", $item['field_udobstva']);
      $term_ids = [];
      foreach ($udobstva_s as $udobstva) {
        $terms_gets = taxonomy_term_load_multiple_by_name($udobstva);
        foreach ($terms_gets as $term_get) {
          if ($term_get->getVocabularyId() == 'udobstva') {
            $term_ids[] = $term_get->get('tid')->value;
          }
        }
      }
      $node->field_udobstva = $term_ids;

      $uslugi_s = explode("|", $item['field_uslugi']);
      $term_ids = [];
      foreach ($uslugi_s as $uslugi) {
        $terms_gets = taxonomy_term_load_multiple_by_name($uslugi);
        foreach ($terms_gets as $term_get) {
          if ($term_get->getVocabularyId() == 'uslugi') {
            $term_ids[] = $term_get->get('tid')->value;
          }
        }
      }
      $node->field_uslugi = $term_ids;

      //Загружаем изображения и привязываем к ноде
      $productImg = $item['field_izobrazenia'];
      $files_imgs = array();
      if (!empty($productImg)) {
        $images = explode("|", $productImg);
        foreach ($images as $image) {
          $img_path = 'http://autoservice-alfaromeo.ru' . $image;
          $data_file = system_retrieve_file($img_path, NULL, TRUE, FILE_EXISTS_REPLACE);
          if ($data_file) {
            $files_imgs[] = array('target_id' => $data_file->id());
          }
        }
        $node->field_izobrazenia = $files_imgs;
      }

      //Загружаем изображения и привязываем к ноде
      $productImg = $item['field_foto'];
      $files_imgs = array();
      if (!empty($productImg)) {
        $images = explode("|", $productImg);
        foreach ($images as $image) {
          $img_path = 'http://autoservice-alfaromeo.ru' . $image;
          $data_file = system_retrieve_file($img_path, NULL, TRUE, FILE_EXISTS_REPLACE);
          if ($data_file) {
            $files_imgs[] = array('target_id' => $data_file->id());

          }
        }
        $node->field_foto = $files_imgs;
      }

      $productImg = $item['field_geometka'];
      $files_imgs = array();
      if (!empty($productImg)) {
        $images = explode("|", $productImg);
        foreach ($images as $image) {
          $img_path = 'http://autoservice-alfaromeo.ru' . $image;
          $data_file = system_retrieve_file($img_path, NULL, TRUE, FILE_EXISTS_REPLACE);
          if ($data_file) {
            $files_imgs[] = array('target_id' => $data_file->id());

          }
        }
        $node->field_geometka = $files_imgs;
      }

      $node->save();
      $id_mass[$item['nid']] = $node->id();
      $config->set('ids_importing', $id_mass);
      $config->save();

      # Записываем результат в общий массив результатов batch операции. По этим
      # данным мы будем выводить кол-во импортированных данных.
      $context['results'][] = $node->id() . ' : ' . $node->label();
      $context['message'] = $node->label();

  }

  /**
   * {@inheritdoc}
   */
  public function setBatch() {
    batch_set($this->batch);
  }

  /**
   * {@inheritdoc}
   *
   * Данный метод на случай, если вызываться будет не из субмита формы.
   */
  public function processBatch() {
    batch_process();
  }

  /**
   * {@inheritdoc}
   *
   * Информация по завершнеию выполнения операций.
   */
  public function finished($success, $results, $operations) {
    if ($success) {
      $message = \Drupal::translation()
        ->formatPlural(count($results), 'One post processed.', '@count posts processed.');
    }
    else {
      $message = t('Finished with an error.');
    }

    drupal_set_message($message);

  }

}
