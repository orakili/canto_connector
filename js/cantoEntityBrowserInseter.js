/**
 * @file
 * Contains js for the canto connector.
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.cantoConnectorEntityBrowser = {
    attach: function (context, settings) {
      $(function () {
        console.log("allowExtension:" + drupalSettings.canto_connector.allowExtensions);
        $('#cantoUC').cantoUC({
          env: drupalSettings.canto_connector.env ? drupalSettings.canto_connector.env : 'canto.com',
          accessToken: drupalSettings.canto_connector.accessToken,
          tenants: drupalSettings.canto_connector.tenants,
          tokenType: drupalSettings.canto_connector.tokenType,
          extensions: drupalSettings.canto_connector.allowExtensions
        }, Drupal.behaviors.cantoConnectorEntityBrowser.cantoFilePopupCallback);
      });
    },
    cantoFilePopupCallback:  (id, assetArray) => {
      const val = encodeURIComponent(JSON.stringify(assetArray))
      console.log(val);
      // @todo fix hard class.
      $('.entity-browser-form').append('<div class="ajax-progress-fullscreen"></div>');
      document.getElementById("canto-assets-data").value = val;
      // Trigger form submission
      document.getElementsByClassName('is-entity-browser-submit button button--primary js-form-submit form-submit')[0].click();
    }
  };
})(jQuery, Drupal, drupalSettings);
