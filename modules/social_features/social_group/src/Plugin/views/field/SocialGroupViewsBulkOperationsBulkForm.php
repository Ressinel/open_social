<?php

namespace Drupal\social_group\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\gvbo\Plugin\views\field\GroupViewsBulkOperationsBulkForm;

/**
 * Defines the Groups Views Bulk Operations field plugin.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("social_views_bulk_operations_bulk_form_group")
 */
class SocialGroupViewsBulkOperationsBulkForm extends GroupViewsBulkOperationsBulkForm {

  /**
   * {@inheritdoc}
   */
  public function getBulkOptions() {
    $bulk_options = parent::getBulkOptions();

    if ($this->view->id() !== 'group_manage_members') {
      return $bulk_options;
    }

    foreach ($bulk_options as $id => &$label) {
      if (!empty($this->options['preconfiguration'][$id]['label_override'])) {
        $real_label = $this->options['preconfiguration'][$id]['label_override'];
      }
      else {
        $real_label = $this->actions[$id]['label'];
      }

      switch ($id) {
        case 'social_group_send_email_action':
        case 'social_group_members_export_member_action':
        case 'social_group_delete_group_content_action':
          $label = $this->t('<b>@action</b> selected members', [
            '@action' => $real_label,
          ]);

          break;

        case 'social_group_change_member_role_action':
          $label = $this->t('<b>@action</b> of selected members', [
            '@action' => $real_label,
          ]);

          break;
      }
    }

    return $bulk_options;
  }

  /**
   * {@inheritdoc}
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    $this->view->setExposedInput(['status' => TRUE]);

    parent::viewsForm($form, $form_state);

    if ($this->view->id() !== 'group_manage_members') {
      return;
    }

    // Get pager data if available.
    if (!empty($this->view->pager) && method_exists($this->view->pager, 'hasMoreRecords')) {
      $pagerData = [
        'current' => $this->view->pager->getCurrentPage(),
        'more' => $this->view->pager->hasMoreRecords(),
      ];
    }

    // Render select all results checkbox when there is a pager and more data.
    $display_select_all = isset($pagerData) && ($pagerData['more'] || $pagerData['current'] > 0);
    if ($display_select_all) {
      $form['header'][$this->options['id']]['select_all'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Select all @count results in this view', [
          '@count' => $this->tempStoreData['total_results'] ? ' ' . $this->tempStoreData['total_results'] : '',
        ]),
        '#attributes' => [
          'class' => [
            'vbo-select-all',
            'form-no-label',
            'checkbox',
          ],
        ],
      ];
    }

    // Render proper classes for the header in VBO form.
    $wrapper = &$form['header'][$this->options['id']];
    $wrapper['#attributes']['class'][] = 'card';
    $wrapper['#attributes']['class'][] = 'card__block';

    $form['#attached']['library'][] = 'social_group/views_bulk_operations.frontUi';

    // Render page title.
    $count = isset($this->tempStoreData['list']) ? count($this->tempStoreData['list']) : 0;
    $title = $this->formatPlural($count, '<b>@count Member</b> is selected', '<b>@count Members</b> are selected');

    $wrapper['multipage']['#title'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['placeholder'],
      ],
      '#value' => $title,
    ];

    $wrapper['multipage']['list']['#title'] = $this->t('See selected members on other pages');

    // We don't show the multipage list if there are no items selected.
    if (isset($wrapper['multipage']['list']['#items']) && count($wrapper['multipage']['list']['#items']) < 1) {
      unset($wrapper['multipage']['list']);
    }

    $actions = &$wrapper['actions'];
    $actions['#theme'] = 'links__dropbutton__operations__actions';
    $actions['#label'] = $this->t('Actions');

    unset($actions['#type']);

    unset($wrapper['multipage']['clear']);
    $items = [];

    foreach ($wrapper['action']['#options'] as $key => $value) {
      if (!empty($key)) {
        $items[] = [
          '#type' => 'submit',
          '#value' => $value,
        ];
      }
    }
    // Add our links to the dropdown buttondrop type.
    $actions['#links'] = $items;
    // Remove the Views select list and submit button.
    $form['actions']['#access'] = FALSE;
    $form['header']['social_views_bulk_operations_bulk_form_group']['action']['#access'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormValidate(&$form, FormStateInterface $form_state) {
    if ($this->view->id() === 'group_manage_members') {
      $user_input = $form_state->getUserInput();
      $available_options = $this->getBulkOptions();
      // Grab all the actions that are available.
      foreach (Element::children($this->actions) as $action) {
        // If the option is not in our selected options, next.
        if (empty($available_options[$action])) {
          continue;
        }

        /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $label */
        $label = $available_options[$action];

        // Match the Users action from our custom dropdown.
        // Find the action from the VBO selection.
        // And set that as the chosen action in the form_state.
        if (strip_tags($label->render()) === $user_input['op']) {
          $user_input['action'] = $action;
          $form_state->setUserInput($user_input);
          $form_state->setValue('action', $action);
          $form_state->setTriggeringElement($this->actions[$action]);
          break;
        }
      }
    }

    parent::viewsFormValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormSubmit(array &$form, FormStateInterface $form_state) {
    parent::viewsFormSubmit($form, $form_state);

    if ($form_state->get('step') === 'views_form_views_form' && $this->view->id() === 'group_manage_members') {
      /** @var \Drupal\Core\Url $url */
      $url = $form_state->getRedirect();

      if ($url->getRouteName() === 'views_bulk_operations.execute_configurable') {
        $parameters = $url->getRouteParameters();

        if (empty($parameters['group'])) {
          $group = _social_group_get_current_group();
          $parameters['group'] = $group->id();
        }

        $url = Url::fromRoute('social_group_gvbo.views_bulk_operations.execute_configurable', [
          'group' => $parameters['group'],
        ]);

        $form_state->setRedirectUrl($url);
      }
    }
  }

}
