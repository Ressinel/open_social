<?php

/**
 * @file
 * Preprocessors and helper functions to make theming easier.
 */

use Drupal\Core\Url;

/**
 * Prepares variables for followers templates.
 *
 * Default template: followers.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - flag: The flag object.
 *   - flaggable: The flaggable entity.
 *   - url: The url of the followers page.
 */
function template_preprocess_followers(array &$variables): void {
  $variables['flag'] = \Drupal::entityTypeManager()->getStorage('flag')
    ->load('follow_user');

  $variables['url'] = Url::fromRoute('view.followers_user.page_user_followers',
    ['user' => $variables['flaggable']->getOwner()->id()]);
  $variables['#cache']['tags'][] = 'followers_user';
}

/**
 * Prepares variables for following templates.
 *
 * Default template: following.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - count: The number of the following users.
 *   - flaggable: The flaggable entity.
 *   - url: The url of the following page.
 */
function template_preprocess_following(array &$variables): void {
  /** @var \Drupal\flag\FlagInterface $flag */
  $flag = \Drupal::entityTypeManager()->getStorage('flag')->load('follow_user');

  /** @var \Drupal\profile\Entity\Profile $profile */
  $profile = $variables['flaggable'];

  $variables['count'] = \Drupal::service('flag.count')
    ->getUserFlagFlaggingCount($flag, $profile->getOwner());
  $variables['url'] = Url::fromRoute('view.following_users.page_following_users',
    ['user' => $profile->getOwner()->id()]);
  $variables['#cache']['tags'][] = 'following_user';
}