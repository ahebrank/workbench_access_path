<?php

namespace Drupal\workbench_access_path\Path;

use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\menu_trail_by_path\Path\CurrentPathHelper;
use Drupal\menu_trail_by_path\Path\PathHelperInterface;

class CurrentFrontendPathHelper extends CurrentPathHelper implements PathHelperInterface {

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @var \Drupal\Core\Routing\RequestContext
   */
  private $context;

  public function __construct(RouteMatchInterface $route_match, RequestContext $context) {
    $this->routeMatch = $route_match;
    $this->context    = $context;
  }


  /**
   * Returns the frontend version of the current request Url
   *
   * NOTE: There is a difference between $this->routeMatch->getRouteName and $this->context->getPathInfo()
   * for now it seems more logical to prefer the latter, because that's the "real" url that visitors enter in their browser..
   *
   * @return \Drupal\Core\Url|null
   */
  protected function getCurrentRequestUrl() {
    $current_pathinfo_url = Url::fromUserInput($this->getContextPath());
    if ($current_pathinfo_url->isRouted()) {
      return $current_pathinfo_url;
    }
    elseif ($route_name = $this->routeMatch->getRouteName()) {
      $route_parameters = $this->routeMatch->getRawParameters()->all();
      return new Url($route_name, $route_parameters);
    }

    return NULL;
  }

  /**
   * @return \Drupal\Core\Url[]
   */
  protected function getCurrentPathUrls() {
    $urls = [];

    $path = trim($this->getContextPath(), '/');
    $path_elements = explode('/', $path);

    while (count($path_elements) > 1) {
      array_pop($path_elements);
      $url = Url::fromUserInput('/' . implode('/', $path_elements));
      if ($url->isRouted()) {
        $urls[] = $url;
      }
    }

    return array_reverse($urls);
  }

  /**
   * Return the frontend path info
   * even if this is the backend version of the page
   * this is only gonna work for nodes
  */
  protected function getContextPath() {
    $pathinfo = $this->context->getPathInfo();

    $router = \Drupal::service('router.no_access_checks');
    $router_info = $router->match($pathinfo);

    if (isset($router_info['_route'])) {
      $route = $router_info['_route'];

      // node edit form
      if ($route == 'entity.node.edit_form') {
        $nid = $router_info['node']->id();
        $pathinfo = Url::fromRoute('entity.node.canonical', ['node' => $nid])->toString();
      }
    }

    return $pathinfo;
  }
}
