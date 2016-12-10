<?php

/**
 * @file
 * Contains \Drupal\workbench_access_path\Plugin\AccessControlHierarchy\MenuPath.
 */

namespace Drupal\workbench_access_path\Plugin\AccessControlHierarchy;

use Drupal\workbench_access\Plugin\AccessControlHierarchy\Menu;
use Drupal\workbench_access\WorkbenchAccessManagerInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu as MenuEntity;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;

/**
 * Defines a path-based hierarchy based on menus.
 *
 * @AccessControlHierarchy(
 *   id = "menu_path",
 *   label = @Translation("Menu Parent by Path"),
 *   module = "menu_ui",
 *   base_entity = "menu",
 *   entity = "menu_link_content",
 *   description = @Translation("Uses paths and menus as an access control hierarchy.")
 * )
 */
class MenuPath extends Menu {

  /**
   * {@inheritdoc}
   */
  public function getEntityValues(EntityInterface $entity, $field) {
    $config = $this->config('workbench_access.settings');
    $menus = $config->get('parents');
    $parent_ids = [];

    // loop through each menu and try to find a parent of the current page
    // currently the path -> id lookup only works for nodes... but that's OK
    // since workbench_access only filters those
    foreach ($menus as $menu) {
      $link = \Drupal::service('workbench_access_path.active_trail')->getActiveTrailLink($menu);
      if ($link) {
        $link_params = $link->getUrlObject()->getRouteParameters();
        if (isset($link_params['node'])) {
          // there's probably a better way to get the UUID
          $parent_ids[] = 'menu_link_content:' . $link->getDerivativeId();
        }
      }
    }

    if (count($parent_ids)) {
      return $parent_ids;
    }

    // this is a probably a hack to account for how the manager's checkAccess works
    // needs to return something and not be empty, but meaningless to deny access
    return [0];
  }

}
