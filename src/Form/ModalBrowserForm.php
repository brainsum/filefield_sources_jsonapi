<?php

namespace Drupal\filefield_sources_jsonapi\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use GuzzleHttp\Client;
use Drupal\Component\Utility\UrlHelper;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Implements the ModalBrowserForm form controller.
 */
class ModalBrowserForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'filefield_sources_jsonapi_browser_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $bundle = NULL, $form_mode = NULL, $field_name = NULL) {
    $form['#attached']['library'][] = 'filefield_sources_jsonapi/modal';
    $field_widget_settings = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load($entity_type . '.' . $bundle . '.' . $form_mode)->getComponent($field_name);
    $settings = $field_widget_settings['third_party_settings']['filefield_sources']['filefield_sources']['source_remote_jsonapi'];
    if (!empty($settings['sort_option_list'])) {
      foreach (explode("\n", $settings['sort_option_list']) as $sort_option) {
        list($key, $label) = explode('|', $sort_option);
        $settings['sort_options'][$key] = $label;
      }
    }
    $settings['cardinality'] = FieldStorageConfig::loadByName($entity_type, $field_name)->getCardinality();

    $form_state->set('jsonapi_settings', $settings);

    $rest_api_url = $settings['api_url'];

    $page = $form_state->get('page');
    if ($page === NULL) {
      $form_state->set('page', 0);
      $page = 0;
    }

    $query = [];
    $query['format'] = 'api_json';

    $query['page[limit]'] = $settings['items_per_page'];
    $query['page[offset]'] = $page * $query['page[limit]'];

    foreach (explode("\n", $settings['params']) as $param) {
      list($key, $value) = explode('|', $param);
      $query[$key] = $value;
    }

    // Add browser form data to JSON API query.
    $user_input = $form_state->getUserInput();
    if (!empty($settings['name_filter']) && isset($user_input['name']) && !empty($user_input['name'])) {
      $query['filter[nameFilter][condition][path]'] = $settings['name_filter'];
      $query['filter[nameFilter][condition][operator]'] = 'CONTAINS';
      $query['filter[nameFilter][condition][value]'] = $user_input['name'];
    }
    if (isset($user_input['sort']) && !empty($user_input['sort'])) {
      $query['sort'] = $user_input['sort'];
    }
    else {
      if (isset($settings['sort_options'])) {
        $sort = array_keys($settings['sort_options']);
        $query['sort'] = reset($sort);
      }
    }

    $query_str = UrlHelper::buildQuery($query);
    $rest_api_url = $rest_api_url . '?' . $query_str;

    $response = $this->getJsonApiCall($rest_api_url);
    if (200 === $response->getStatusCode()) {
      $response = json_decode($response->getBody());
      $form['filefield_filesources_jsonapi_form'] = $this->renderFormElements($response, $form_state);
    }

    $form['#prefix'] = '<div id="filefield-sources-jsonapi-browser-form">';
    $form['#suffix'] = '</div>';

    // If cardinality is 1, don't display submit button - autosubmit on slelect.
    if ($settings['cardinality'] != 1) {
      $form['actions'] = [
        '#type' => 'actions',
      ];

      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit selected'),
        '#ajax' => [
          'callback' => '::ajaxSubmitForm',
          'event' => 'click',
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $selected_media = array_values(array_filter($form_state->getUserInput()['media_id_select']));
    /*if (count($selected_media) > 1) {
      $form_state->setErrorByName('tml_media_image_url', $this->t('You can select only one media.'));
      return;
    }*/
    if ($media_id = $selected_media[0]) {
      $settings = $form_state->get('jsonapi_settings');

      $api_url_base = $this->getApiBaseUrl($settings['api_url']);
      $rest_api_url = $api_url_base . '/jsonapi/file/file/' . $media_id;
      $query['fields[file--file]'] = 'url';
      $query_str = UrlHelper::buildQuery($query);
      $rest_api_url = $rest_api_url . '?' . $query_str;

      $response = $this->getJsonApiCall($rest_api_url);
      if (200 === $response->getStatusCode()) {
        $response = json_decode($response->getBody());
        $image_url = $api_url_base . $response->data->attributes->url;
      }
      if (!$image_url && curl_init($image_url)) {
        $form_state->setErrorByName('tml_media_image_url', $this->t("Can't fetch image from remote server."));
      }
      $form_state->set('fetched_image_url', $image_url);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Implements the filter submit handler for the ajax call.
   */
  public function ajaxSubmitFilterForm(array &$form, FormStateInterface $form_state) {
    $form_state->set('page', 0);
    $form_state->setRebuild();
  }

  /**
   * Implements the pager submit handler for the ajax call.
   */
  public function ajaxSubmitPagerNext(array &$form, FormStateInterface $form_state) {
    $page = $form_state->get('page');
    $form_state->set('page', ($page + 1));
    $form_state->setRebuild();
  }

  /**
   * Implements the pager submit handler for the ajax call.
   */
  public function ajaxSubmitPagerPrev(array &$form, FormStateInterface $form_state) {
    $page = $form_state->get('page');
    $form_state->set('page', ($page - 1));
    $form_state->setRebuild();
  }

  /**
   * Implements the pager submit handler for the ajax call.
   */
  public function ajaxPagerCallback(array &$form, FormStateInterface $form_state) {
    return $form['filefield_filesources_jsonapi_form']['lister'];
  }

  /**
   * Implements the submit handler for the ajax call.
   *
   * @param array $form
   *   Render array representing from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Array of ajax commands to execute on submit of the modal form.
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    // Clear the message set by the submit handler.
//    drupal_get_messages();

    // We begin building a new ajax reponse.
    $response = new AjaxResponse();
    if ($form_state->getErrors()) {
      unset($form['#prefix']);
      unset($form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#filefield-sources-jsonapi-browser-form', $form));
    }
    else {
      $image_url = $form_state->get('fetched_image_url');
      $response->addCommand(new InvokeCommand('.filefield-source-remote_jsonapi input[type=text]', 'val', [$image_url]));
      $response->addCommand(new InvokeCommand('.filefield-source-remote_jsonapi input[type=submit]', 'mousedown'));
      $response->addCommand(new CloseModalDialogCommand());
    }
    return $response;
  }

  /**
   * Render form elements.
   */
  private function renderFormElements($response, FormStateInterface $form_state) {
    $settings = $form_state->get('jsonapi_settings');
    $api_url_base = $this->getApiBaseUrl($settings['api_url']);

    $render = [];
    if (!empty($settings['sort_options'] || !empty($settings['name_filter']))) {
      $render['filter'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'filefield_filesources_jsonapi_filter',
          'class' => ['browser-filter', 'inline'],
        ],
      ];
      if (!empty($settings['sort_options'])) {
        $render['filter']['sort'] = [
          '#title' => $this->t('Sort'),
          '#type' => 'select',
          '#options' => $settings['sort_options'],
          '#attributes' => ['class' => ['sort-by', 'inline']],
          '#submit' => ['::ajaxSubmitFilterForm'],
          '#ajax' => [
            'callback' => '::ajaxPagerCallback',
            'wrapper' => 'filefield_filesources_jsonapi_lister',
          ],
        ];
      }
      if (!empty($settings['name_filter'])) {
        $render['filter']['name'] = [
          '#type' => 'textfield',
          '#attributes' => [
            'class' => ['file-name'],
            'placeholder' => $this->t('Search'),
          ],
        ];
        $render['filter']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Apply'),
          '#limit_validation_errors' => [],
          '#submit' => ['::ajaxSubmitFilterForm'],
          '#ajax' => [
            'callback' => '::ajaxPagerCallback',
            'wrapper' => 'filefield_filesources_jsonapi_lister',
          ],
        ];
      }
    }

    $render['lister'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['browser-lister']],
      '#prefix' => '<div id="filefield_filesources_jsonapi_lister">',
      '#suffix' => '</div>',
    ];

    $render['lister']['media'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['media-lister']],
    ];
    foreach ($response->data as $data) {
      $media_id = $data->relationships->field_image->data->id;
      $thumbnail_url = NULL;
      foreach ($response->included as $included) {
        if ($data->relationships->thumbnail->data->type === $included->type && $data->relationships->thumbnail->data->id === $included->id) {
          $thumbnail_url = $included->attributes->url;
          break;
        }
      }
      if ($media_id && $thumbnail_url) {
        $render['lister']['media'][$media_id] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['media-row']],
        ];
        $render['lister']['media'][$media_id]['media_id'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Select this item'),
          '#title_display' => 'invisible',
          '#return_value' => $media_id,
          '#attributes' => ['name' => "media_id_select[$media_id]"],
          '#default_value' => NULL,
        ];
        if ($settings['cardinality'] === 1) {
          $render['lister']['media'][$media_id]['media_id']['#ajax'] = [
            'callback' => '::ajaxSubmitForm',
            'event' => 'click',
          ];
        }
//        $img = [
//          '#theme' => 'image_style',
//          '#style_name' => $settings['image_style'] ?: 'original',
//          '#uri' => 'http://tml_tmp.dd:8083' . $thumbnail_url,
//        ];
        $img = [
          '#theme' => 'image',
          '#uri' => $api_url_base . $thumbnail_url,
          '#width' => '100',
        ];
        $render['lister']['media'][$media_id]['media_id']['#field_suffix'] = drupal_render($img);
      }
    }
    if (empty($response->data)) {
      $render['lister']['media']['empty'] = [
        '#markup' => $this->t('No results.'),
        '#attributes' => ['class' => ['no-result']],
      ];
    }

    // Add navigation buttons.
    if (isset($response->links->prev)) {
      $render['lister']['prev'] = [
        '#type' => 'submit',
        '#value' => $this->t('« Prev'),
        '#limit_validation_errors' => [],
        '#submit' => ['::ajaxSubmitPagerPrev'],
        '#ajax' => [
          'callback' => '::ajaxPagerCallback',
          'wrapper' => 'filefield_filesources_jsonapi_lister',
        ],
      ];
    }
    if (isset($response->links->next)) {
      $render['lister']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next »'),
        '#limit_validation_errors' => [],
        '#submit' => ['::ajaxSubmitPagerNext'],
        '#ajax' => [
          'callback' => '::ajaxPagerCallback',
          'wrapper' => 'filefield_filesources_jsonapi_lister',
        ],
      ];
    }

    return $render;
  }

  /**
   *
   */
  private function getApiBaseUrl($url) {
    $api_url_parsed = parse_url($url);
    $api_url_base = $api_url_parsed['scheme'] . '://' . $api_url_parsed['host'] . ($api_url_parsed['port'] ? ':' . $api_url_parsed['port'] : '');

    return $api_url_base;
  }

  /**
   * @param $rest_api_url
   */
  private function getJsonApiCall($rest_api_url) {
    $client = new Client();
    $myConfig = \Drupal::config('filefield_sources_jsonapi');
    $username = $myConfig->get('username');
    $password = $myConfig->get('password');

    $response = $client->get($rest_api_url, [
      'headers' => ['Authorization' => 'Basic ' . base64_encode("$username:$password")],
    ]);

    return $response;
  }

}
