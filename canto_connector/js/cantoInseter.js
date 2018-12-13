/**
 * @file
 * Contains js for the accordion example.
 */

(function ($,Drupal) {

  'use strict';
  
  
  var cantoFilePopupCallback = function(id,assetArray) {
	    var imageHtml = "";
	    
	    for(var i = 0; i < assetArray.length; i++){
	        imageHtml +=  assetArray[i].originalUrl + ';';
	    }
	    document.getElementsByName("cantofid")[0].value=imageHtml;
	    
	    document.getElementsByClassName('button js-form-submit form-submit ui-button ui-corner-all ui-widget')[0].click();
	  };
	  
  $(function () {
	  console.log(drupalSettings.canto_connector.env);
	  $('#cantoUC').cantoUC({
		  env: drupalSettings.canto_connector.env?drupalSettings.canto_connector.env:'canto.com',
		  accessToken: drupalSettings.canto_connector.accessToken,
		  tenants:drupalSettings.canto_connector.tenants,
		  tokenType:drupalSettings.canto_connector.tokenType
	    },  cantoFilePopupCallback);
  })
})(jQuery,Drupal,drupalSettings);
