<?php

namespace Drupal\canto_connector;

/**
 * Oauth helper class.
 */
class OAuthConnector {

  /**
   * Returns user info.
   *
   * @todo Use dependency injection.
   */
  private static function obtainUserInfo(string $subDomain, string $accessToken): string {
    /** @var \GuzzleHttp\Client $client */
    $client = \Drupal::service('http_client');
    // https://yourdomain.cantoflight.com/api/v1/user
    $url = 'https://' . $subDomain . '/api/v1/user';
    $headers = [
      'Referer' => 'Universal Connector',
      'User-Agent' => 'Universal Connector',
      'Authorization' => 'Bearer ' . $accessToken,
    ];
    try {
      $response = $client->get($url, ['headers' => $headers]);
      return $response->getStatusCode() == 200 ? $response->getBody()
        ->getContents() : FALSE;
    } catch (\Exception $e) {
      \Drupal::logger('canto_connector')->error("Couldn't retrieve user info",
        ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Check if token is valid.
   */
  public static function checkAccessTokenValid(string $subDomain, string $accessToken): bool {
    return (bool) self::obtainUserInfo($subDomain, $accessToken);
  }

}
