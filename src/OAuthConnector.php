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
    $response = $client->get($url, ['headers' => $headers]);

    return $response->getStatusCode() == 200 ? $response->getBody()
      ->getContents() : FALSE;
    // Get header and body:
//    if ($response->getStatusCode() == 200) {
//      return json_encode([
//        "error" => 0,
//        "user" => $response->getBody()->getContents(),
//      ]);
//    }
//    else {
//      $error = [
//        "error" => 1,
//        "error_code" => $response->getStatusCode(),
//      ];
//      return json_encode($error);
//    }
  }

  /**
   * Check if token is valid.
   */
  public static function checkAccessTokenValid(string $subDomain, string $accessToken): bool {
    return (bool) self::obtainUserInfo($subDomain, $accessToken);
//    // Convert to array.
//    $userInfoArray = json_decode($userInfoStr, TRUE);
//    if ($userInfoArray['error'] == 0) {
//      return TRUE;
//    }
//    else {
//      return FALSE;
//    }
  }

}
