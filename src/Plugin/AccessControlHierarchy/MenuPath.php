<?php

/**
 * @file
 * Contains \Drupal\workbench_access_path\Plugin\AccessControlHierarchy\MenuPath.
 */

namespace Drupal\workbench_access_path\Plugin\AccessControlHierarchy;

use Drupal\workbench_access\AccessControlHierarchyBase;
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
class MenuPath extends AccessControlHierarchyBase {

  /**
   * The access tree array.
   *
   * @var array
   */
  public $tree;

  /**
   * @inheritdoc
   */
  public function getTree() {
    if (!isset($this->tree)) {
      $config = $this->config('workbench_access.settings');
      $parents = $config->get('parents');
      $tree = array();
      $this->menuTree = \Drupal::getContainer()->get('menu.link_tree');
      foreach ($parents as $id => $label) {
        if ($menu = MenuEntity::load($id)) {
          $tree[$id][$id] = array(
            'label' => $menu->label(),
            'depth' => 0,
            'parents' => [],
            'weight' => 0,
            'description' => $menu->label(),
          );
          $params = new MenuTreeParameters();
          $data = $this->menuTree->load($id, $params);
          $this->tree = $this->buildTree($id, $data, $tree);
        }
      }
    }
    return $this->tree;
  }

  /**
   * Traverses the menu link tree and builds parentage arrays.
   *
   * Note: this method is necessary because Menu does not auto-load parents.
   *
   * @param $id
   *   The root id of the section tree.
   * @param array $data
   *   An array of menu tree or subtree data.
   * @param array &$tree
   *   The computed tree array to return.
   *
   * @return array $tree
   *   The compiled tree data.
   */
  public function buildTree($id, $data, &$tree) {
    foreach ($data as $link_id => $link) {
      $tree[$id][$link_id] = array(
        'id' => $link_id,
        'label' => $link->link->getTitle(),
        'depth' => $link->depth,
        'parents' => [],
        'weight' => $link->link->getWeight(),
        'description' => $link->link->getDescription(),
      );
      // Get the parents.
      if ($parent = $link->link->getParent()) {
        $tree[$id][$link_id]['parents'] = array_unique(array_merge($tree[$id][$link_id]['parents'], [$parent]));
        $tree[$id][$link_id]['parents'] = array_unique(array_merge($tree[$id][$link_id]['parents'], $tree[$id][$parent]['parents']));
      }
      else {
        $tree[$id][$link_id]['parents'] = [$id];
      }
      if (isset($link->subtree)) {
        $this->buildTree($id, $link->subtree, $tree);
      }
    }
    return $tree;
  }

  /**
   * @inheritdoc
   */
  public function getFields($entity_type, $bundle, $parents) {
    return ['menu' => 'Menu field'];
  }

  /**
   * {@inheritdoc}
   */
  public function alterOptions($field, WorkbenchAccessManagerInterface $manager) {
    $element = $field;
    $user_sections = $manager->getUserSections();
    $menu_check = [];
    foreach ($element['link']['menu_parent']['#options'] as $id => $data) {
      // The menu value here prepends the menu name. Remove that.
      $parts = explode(':', $id);
      $menu = array_shift($parts);
      $sections = [implode(':', $parts)];
      // Remove unusable elements, except the existing parent.
      if ((!empty($element['link']['menu_parent']['#default_value']) && $id != $element['link']['menu_parent']['#default_value']) && empty($manager->checkTree($sections, $user_sections))) {
        unset($element['link']['menu_parent']['#options'][$id]);
      }
      // Check for the root menu item.
      if (!isset($menu_check[$menu]) && isset($element['link']['menu_parent']['#options'][$menu . ':'])) {
        if (empty($manager->checkTree([$menu], $user_sections))) {
          unset($element['link']['menu_parent']['#options'][$menu . ':']);
        }
        $menu_check[$menu] = TRUE;
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityValues(EntityInterface $entity, $field) {
    $trail_service = \Drupal::service('menu.active_trail');
    $config = $this->config('workbench_access.settings');
    $menus = $config->get('parents');

    // loop through each menu and try to find a parent of the current page
    // currently the path -> id lookup only works for nodes... but that's OK
    // since workbench_access only filters those
    foreach ($menus as $menu) {
      $link = $trail_service->getActiveTrailLink($menu);
      if ($link) {
        $link_params = $link->getUrlObject()->getRouteParameters();
        if (isset($link_params['node'])) {
          if ($link_params['node'] == $entity->id()) {
            // there's probably a better way to get the UUID
            $id = 'menu_link_content:' . $link->getDerivativeId();
            return [$id];
          }
        }
      }
    }
    return [];
  }

  /**
   * {inheritdoc}
   */
  public function disallowedOptions($field) {
    // On the menu form, we never remove an existing parent item, so there is
    // no concept of a disallowed option.
    return array();
  }

  /**
   * {inheritdoc}
   */
  public function getViewsJoin($table, $key, $alias = NULL) {
    if ($table == 'users') {
      $configuration['menu'] = [
       'table' => 'user__' . WORKBENCH_ACCESS_FIELD,
       'field' => 'entity_id',
       'left_table' => $table,
       'left_field' => $key,
       'operator' => '=',
       'table_alias' => WORKBENCH_ACCESS_FIELD,
       'real_field' => WORKBENCH_ACCESS_FIELD . '_value',
      ];
    }
    else {
      $configuration['menu'] = [
       'table' => 'menu_tree',
       'field' => 'route_param_key',
       'left_table' => $table,
       'left_field' => $key,
       'left_query' => "CONCAT('{$table}=', {$alias}.{$key})",
       'operator' => '=',
       'table_alias' => 'menu_tree',
       'real_field' => 'id',
      ];
    }
    return $configuration;
  }

}
