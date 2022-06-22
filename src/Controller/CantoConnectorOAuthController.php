<?php

namespace Drupal\canto_connector\Controller;

use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Controller\ControllerBase;
use Drupal\canto_connector\CantoConnectorRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller class.
 */
class CantoConnectorOAuthController extends ControllerBase {

  /**
   * The user data service.
   *
   * @var \Drupal\canto_connector\CantoConnectorRepository
   */
  protected $repository;

  /**
   * The user data service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * Account proxy.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $accountProxy;

  /**
   * {@inheritdoc}
   */
  public function __construct(CantoConnectorRepository $repository, LoggerChannelFactory $loggerFactory, AccountProxy $accountProxy) {
    $this->repository = $repository;
    $this->loggerFactory = $loggerFactory;
    $this->accountProxy = $accountProxy;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('canto_connector.repository'),
      $container->get('logger.factory'),
      $container->get('current_user'),
    );
  }

  /**
   * Save access token to databse.
   *
   * @todo use route  parameters for user
   */
  public function saveAccessToken(Request $request) {
    $logger = $this->loggerFactory->get('canto_connector');
    $logger->notice('saveAccessToken');
    $userId = $this->accountProxy->id();
    $env = $this->config('canto_connector.settings')->get('env');

    $entry = [
      'accessToken' => $request->request->get('accessToken'),
      'tokenType' => $request->request->get('tokenType'),
      'subdomain' => $request->request->get('subdomain'),
      'uid' => $userId,
      'env' => is_null($env) ? "canto.com" : $env,
    ];

    $return_value = $this->repository->insert($entry);

    return new JsonResponse($return_value);
  }

  /**
   * Delete access token.
   *
   * @todo use route  parameters for user
   */
  public function deleteAccessToken(Request $request) {
    $logger = $this->loggerFactory->get('canto_connector');
    $logger->notice('deleteAccessToken');
    $userId = $this->accountProxy->id();
    $entry = [
      'accessToken' => $request->request->get('accessToken'),
      'uid' => $userId,
      'env' => $request->request->get('env'),
    ];
    $logger->notice('delete AccessToken' . $request->request->get('accessToken'));
    $logger->notice('env' . $request->request->get('env'));

    $return_value = $this->repository->delete($entry);
    return new JsonResponse($return_value);
  }

}
