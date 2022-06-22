<?php

namespace Drupal\canto_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Returns responses for Canto Connector static files.
 */
class CantoConnectorStaticController extends ControllerBase {

  const CANTO_ASSETS_PATH = '/canto_assets/';

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The MIME type guesser.
   *
   * @var \Symfony\Component\Mime\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * Constructs a new S3fsImageStyleRoutes object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\Mime\MimeTypeGuesserInterface $mimeTypeGuesser
   *   The MIME type guesser.
   */
  public function __construct(ModuleHandlerInterface $module_handler, MimeTypeGuesserInterface $mimeTypeGuesser) {
    $this->moduleHandler = $module_handler;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('file.mime_type.guesser'),
    );
  }

  /**
   * Returns the requested static asset.
   *
   * @param string $file
   *   The name of the file.
   *
   * @return false|string
   *   The static file content.
   */
  public function build($file) {
    $moduleHandler = $this->moduleHandler;
    $modulePath = DRUPAL_ROOT . '/' . $moduleHandler->getModule('canto_connector')
      ->getPath();
    $path = $modulePath . $this::CANTO_ASSETS_PATH . $file;
    if (!file_exists($path)) {
      throw new NotFoundHttpException();
    }
    $type = $this->mimeTypeGuesser->guessMimeType($path);
    return new Response(file_get_contents($path), 200, ['Content-Type' => $type]);
  }

}
