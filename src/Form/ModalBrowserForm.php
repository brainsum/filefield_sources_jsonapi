<?php

namespace Drupal\filefield_sources_jsonapi\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;
use GuzzleHttp\Client;

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
    $form['#prefix'] = '<div id="filefield-sources-jsonapi-browser-form">';
    $form['#suffix'] = '</div>';

    if ($image = $form_state->get('fetched_image') && $form_state->get('form_type') === 'insert') {
      return self::buildInsertForm($form, $form_state);
    }

    $field_widget_settings = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load($entity_type . '.' . $bundle . '.' . $form_mode)
      ->getComponent($field_name);
    $settings = $field_widget_settings['third_party_settings']['filefield_sources']['filefield_sources']['source_remote_jsonapi'];
    if (!empty($settings['sort_option_list'])) {
      foreach (explode("\n", $settings['sort_option_list']) as $sort_option) {
        list($key, $label) = explode('|', $sort_option);
        $settings['sort_options'][$key] = $label;
      }
    }
    $settings['cardinality'] = FieldStorageConfig::loadByName($entity_type, $field_name)
      ->getCardinality();

    $form_state->set('jsonapi_settings', $settings);

    $rest_api_url = $settings['api_url'];
    $query = $this->bulidJsonApiQuery($settings);

    $page = $form_state->get('page');
    if ($page === NULL) {
      $form_state->set('page', 0);
      $page = 0;
    }
    $query['page[limit]'] = $settings['items_per_page'];
    $query['page[offset]'] = $page * $query['page[limit]'];

    // Add browser form data to JSON API query.
    $user_input = $form_state->getUserInput();
    if (!empty($settings['search_filter']) && isset($user_input['name']) && !empty($user_input['name'])) {
      $query['filter[nameFilter][condition][path]'] = $settings['search_filter'];
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

    return $form;
  }

  /**
   * Builds the insert form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildInsertForm(array &$form, FormStateInterface $form_state) {
    $image = $form_state->get('fetched_image');
    $form['title'] = [
      '#type' => 'item',
      '#title' => $this->t('Insert selected'),
    ];

    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['insert-wrapper']],
    ];
    $form['wrapper']['image'] = [
      '#theme' => 'image',
      '#uri' => $image['url'],
      '#width' => '400',
    ];
    $form['wrapper']['detail'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['details-wrapper']],
    ];
    $form['wrapper']['detail']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $image['title'],
    ];
    $form['wrapper']['detail']['alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alt'),
      '#default_value' => $image['alt'],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancelSelectedSubmit'],
      '#ajax' => [
        'callback' => '::ajaxInsertCallback',
        'wrapper' => 'filefield-sources-jsonapi-browser-form',
      ],
      '#attributes' => ['class' => ['cancel-button']],
      '#weight' => 1,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#name' => 'insert',
      '#value' => $this->t('Insert'),
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
        'event' => 'click',
      ],
      '#attributes' => ['class' => ['insert-button']],
      '#weight' => 2,
    ];

    return $form;
  }

  /**
   * Provides custom submission handler for change form to insert.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function insertSelectedSubmit(array &$form, FormStateInterface $form_state) {
    $form_state
      ->set('form_type', 'insert')
      ->setRebuild(TRUE);
  }

  /**
   * Provides custom submission handler for change form to basic.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function cancelSelectedSubmit(array &$form, FormStateInterface $form_state) {
    $form_state
      ->set('form_type', 'form')
      ->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] === 'insert_selected') {
      $selected_media = array_values(array_filter($form_state->getUserInput()['media_id_select']));
      /*if (count($selected_media) > 1) {
        $form_state->setErrorByName('', $this->t('You can select only one media.'));
        return;
      }*/
      $image_url = NULL;
      if ($media_id = $selected_media[0]) {
        $settings = $form_state->get('jsonapi_settings');

        $rest_api_url = $settings['api_url'] . '/' . $media_id;
        $query = $this->bulidJsonApiQuery($settings);
        $query_str = UrlHelper::buildQuery($query);
        $rest_api_url = $rest_api_url . '?' . $query_str;

        $response = $this->getJsonApiCall($rest_api_url);
        if (200 === $response->getStatusCode()) {
          $response = json_decode($response->getBody());
          $api_url_base = $this->getApiBaseUrl($settings['api_url']);
          $image['url'] = $this->getJsonApiDatabyPath($response, $settings['url_attribute_path']);
          $image['url'] = $api_url_base . $image['url'];
          if (!empty($settings['alt_attribute_path'])) {
            $image['alt'] = $this->getJsonApiDatabyPath($response, $settings['alt_attribute_path']);
          }
          if (!empty($settings['title_attribute_path'])) {
            $image['title'] = $this->getJsonApiDatabyPath($response, $settings['title_attribute_path']);
          }
        }

        if (!isset($image['url']) || !curl_init($image['url'])) {
          $form_state->setErrorByName('', $this->t("Can't fetch image from remote server."));
          $this->getLogger('filefield_sources_jsonapi')->warning("Can't fetch image (@url) from remote server.", ['@url' => $image['url']]);
        }
        $form_state->set('fetched_image', $image);
      }
      else {
        $form_state->setErrorByName('', $this->t("No image was selected."));
      }
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
   * Implements the insert submit handler for the ajax call.
   */
  public function ajaxInsertCallback(array &$form, FormStateInterface $form_state) {
    return $form;
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
      $image = $form_state->get('fetched_image');
      $response->addCommand(new InvokeCommand(".filefield-source-remote_jsonapi input[name$='[filefield_remote_jsonapi][url]']", 'val', [$image['url']]));
      $response->addCommand(new InvokeCommand(".filefield-source-remote_jsonapi input[name$='[filefield_remote_jsonapi][alt]']", 'val', [$form_state->getUserInput()['alt']]));
      $response->addCommand(new InvokeCommand(".filefield-source-remote_jsonapi input[name$='[filefield_remote_jsonapi][title]']", 'val', [$form_state->getUserInput()['title']]));
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
    $render['top'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'filefield_filesources_jsonapi_top',
        'class' => ['browser-top'],
      ],
    ];
    if (!empty($settings['sort_options'] || !empty($settings['search_filter']))) {
      $render['top']['filter'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'filefield_filesources_jsonapi_filter',
          'class' => ['browser-filter', 'inline'],
        ],
      ];
      if (!empty($settings['sort_options'])) {
        $render['top']['filter']['sort'] = [
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
      if (!empty($settings['search_filter'])) {
        $render['top']['filter']['name'] = [
          '#type' => 'textfield',
          '#attributes' => [
            'class' => ['file-name'],
            'placeholder' => $this->t('Search'),
          ],
        ];
        $render['top']['filter']['submit'] = [
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

    // If cardinality is 1, don't render submit button - autosubmit on slelect.
    $render['top']['action'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'filefield_filesources_jsonapi_action',
        'class' => ['browser-action'],
      ],
//      '#printed' => $settings['cardinality'] === 1 ? TRUE : FALSE,
    ];
    $render['top']['action']['submit'] = [
      '#type' => 'submit',
      '#name' => 'insert_selected',
      '#value' => $this->t('Insert selected'),
      '#submit' => ['::insertSelectedSubmit'],
      '#ajax' => [
        'callback' => '::ajaxInsertCallback',
        'wrapper' => 'filefield-sources-jsonapi-browser-form',
      ],
      '#attributes' => ['class' => ['insert-button', 'visually-hidden']],
    ];

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
      $media_id = $data->id;
      $thumbnail_url = $this->getJsonApiDatabyPath($response, $settings['url_attribute_path'], $data);
      if ($media_id && $thumbnail_url) {
        $render['lister']['media'][$media_id] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['media-row']],
        ];
        $checkbox = [
          '#type' => 'checkbox',
          '#title' => $this->t('Select this item'),
          '#title_display' => 'invisible',
          '#return_value' => $media_id,
          '#id' => $media_id,
          '#attributes' => ['name' => "media_id_select[$media_id]"],
          '#default_value' => NULL,
        ];
        // Auto trigger on cardinality 1 - doesn't work.
        if ($settings['cardinality'] === 1) {
          $checkbox['#ajax'] = [
            'trigger_as' => ['name' => 'insert_selected'],
            'callback' => '::ajaxInsertCallback',
            'wrapper' => 'filefield-sources-jsonapi-browser-form',
            'event' => 'click',
          ];
        }
        $img = [
          '#theme' => 'image',
          '#uri' => $api_url_base . $thumbnail_url,
          '#width' => '100',
        ];
        $render['lister']['media'][$media_id]['media_id'] = [
          '#theme' => 'browser_media_box',
          '#checkbox' => $checkbox,
          '#checkbox_id' => $media_id,
          '#img' => $img,
          '#title' => $data->attributes->name,
        ];
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
   * Helper function to get base url of api uri.
   */
  private function getApiBaseUrl($url) {
    $api_url_parsed = parse_url($url);
    $api_url_base = $api_url_parsed['scheme'] . '://' . $api_url_parsed['host'] . (isset($api_url_parsed['port']) ? ':' . $api_url_parsed['port'] : '');

    return $api_url_base;
  }

  /**
   * Build JSON API query based on settings.
   */
  private function bulidJsonApiQuery($settings) {
    $query['format'] = 'api_json';

    foreach (explode("\n", $settings['params']) as $param) {
      list($key, $value) = explode('|', $param);
      $query[$key] = $value;
    }
    return $query;
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

  /**
   * Get data from JSON API response by path.
   *
   * @param object $response
   *   Full JSON API response with data, included.
   * @param string $pathString
   *   Attribute's path string, e.g.:
   *   data->attributes->title
   *   data->attributes->field_image->attributes->data->url.
   * @param object $data
   *   Actual response data - optional.
   *
   * @return mixed
   *   Data from JSON API response.
   */
  public function getJsonApiDatabyPath($response, $pathString, $data = NULL) {
    if (!empty($data)) {
      $attribute_data = $data;
      $pathString = preg_replace('/^data->/', '', $pathString);
    }
    else {
      $attribute_data = $response;
    }
    $value = NULL;
    list($data_path, $included_path) = explode('->included->', $pathString);
    foreach (explode('->', $data_path) as $property) {
      $attribute_data = $attribute_data->{$property};
    }
    if (!empty($included_path)) {
      foreach ($response->included as $included) {
        $included_data = $included;
        foreach (explode('->', $included_path) as $property) {
          $included_data = $included_data->{$property};
        }
        if ($attribute_data->data->type === $included->type && $attribute_data->data->id === $included->id) {
          $value = $included_data;
          break;
        }
      }
    }
    else {
      $value = $attribute_data;
    }

    return $value;
  }

}
