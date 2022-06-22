/*!
    Canto Universal Connector 1.0.0
    Dependencies on jQuery.

*/

/**
 * This file is downloaded from canto and modifies slightly for drupal use.
 * @see https://support.canto.com/helpdesk/attachments/9125659569.
 */

(function ($, Drupal, once) {
  Drupal.behaviors.cantoConnector = {
    attach: function (context, settings) {
      once('cantoUniversalConnector', 'html', context).forEach( function (element) {
        // Below this is mostly canto universal connector code downloaded from canto.
        //  @todo clean up.
        var cantoUC,
          pluginName = "cantoUC",
          redirectUri = "",
          tokenInfo = {},
          env = "canto.com",
          appId = "a9dc81b1bf9d492f8ee3838302d266b2",
          _workerID = "",
          callback,
          currentCantoTagID,
          formatDistrict;
        var secretObj = {
          "canto.com": "585070cc17ea463390e9224717dbdd85f2a2ff165ebb4083bbc7ff60f4f2e873",
          "canto.global": "1d902fb7bf9843d99a7280fb660f35257ff75ffd41ed44bf8f759021bad24c44",
          "canto.de": "3a093e70635c4a418f9e7e13d936d28f8250f2ca3a6d45c989b010c197bb27cf",
          "ca.canto.com": "3a093e70635c4a418f9e7e13d936d28f8250f2ca3a6d45c989b010c197bb27cf",
        };

        cantoUC = $.fn[pluginName] = $[pluginName] = function (options, callback) {
          settings(options);
          callback = callback;
          loadCantoUCResource();
          createIframe();
          addEventListener();

          window.onmessage = function (e) {
            var data = event.data;
            if (data && data.type == "getTokenInfo") {
              var receiver = document.getElementById('cantoUCFrame').contentWindow;
              tokenInfo.formatDistrict = formatDistrict;
              receiver.postMessage(tokenInfo, '*');
            } else if (data && data.type == "cantoLogout") {
              if (tokenInfo.accessToken) {
                $.ajax({
                  url: Drupal.url('canto_connector/delete_access_token'),
                  type: 'POST',
                  data: {
                    'accessToken': tokenInfo.accessToken, 'env': env

                  },
                  dataType: 'json',
                });
              }
              //clear token and close the frame.
              tokenInfo = {};
              $(".canto-uc-iframe-close-btn").trigger("click");

            } else if (data && data.type == "cantoInsertImage") {
              $(".canto-uc-iframe-close-btn").trigger("click");
              // insertImageToCantoTag(cantoURL);
              callback(currentCantoTagID, data.assetList);

            } else {
              if (!data.accessToken) {
                return;
              }
              $.ajax({
                url: Drupal.url('canto_connector/save_access_token'),
                type: 'POST',
                data: {'accessToken': data.accessToken, 'tokenType': data.tokenType, 'subdomain': data.refreshToken},
                dataType: 'json',
              });
              tokenInfo = data;
              // @todo replace with drupal settings.
              var cantoContentPage = "/canto-assets/static/universal/cantoContent.html";
              $("#cantoUCFrame").attr("src", cantoContentPage);
            }

          };
        };

        function settings(options) {
          var envObj = {
            "canto.com": "a9dc81b1bf9d492f8ee3838302d266b2",
            "canto.global": "f87b44d366464dfdb4867ab361683c96",
            "canto.de": "e7135823e3d046468287e835008da493",
            "ca.canto.com": "e7135823e3d046468287e835008da493",
          };
          env = options.env;
          appId = envObj[env];
          if (options.tenants && options.tenants.length > 1 && options.accessToken && options.accessToken.length > 1) {
            console.log("get token info from Drupal DB");
            tokenInfo = {accessToken: options.accessToken, tokenType: options.tokenType, refreshToken: options.tenants};
          }
          formatDistrict = options.extensions;
        }

        function loadCantoUCResource() {
          // @todo what's this for?
          // dynamicLoadJs("./cantoAssets/main.js");
          //dynamicLoadCss("./cantoAssets/base.css");
        }

        function addEventListener() {

          $(document).on('click', ".canto-uc-iframe-close-btn", function (e) {
            $("#cantoUCPanel").addClass("hidden");
            $("#cantoUCFrame").attr("src", "");
          })
            .on('click', ".img-box", function (e) {
              //currentCantoTagID = $(e.target).closest("canto").attr("id");
              $("#cantoUCPanel").removeClass("hidden");
              loadIframeContent();
            })
            .on('click', ".js-canto-oauth-btn", function (e) {
              var getSSOcodeURL = "https://oauth." + env + "/oauth/api/oauth2/ssocode?worker_id=" + _workerID;
              $.ajax({
                type: "GET",
                url: getSSOcodeURL,
                async: false,
                error: function (request) {
                  alert("Get login url failed, please retry");
                  $("#cantoUCPanel").addClass("hidden");
                },
                success: function (data) {
                  getTokenByOauthCode(data);
                }
              });
            });
        }

        /*--------------------------get token by code---------------------------------------*/
        function getTokenByOauthCode(authorizationCode) {
          var url = "https://oauth." + env + ":443/oauth/api/oauth2/token";
          var redirectUri = "https://oauth." + env + "/oauth/loading.html";

          var data = {
            app_id: appId,
            app_secret: secretObj[env],
            grant_type: "authorization_code",
            redirect_uri: redirectUri,
            code: authorizationCode,
            url: url,
            timeout: 8000
          };
          $.ajax({
            type: "POST",
            url: url,
            async: false,
            data: data,
            error: function (request) {
              alert("Get login url failed, please retry");
              $("#cantoUCPanel").addClass("hidden");
            },
            success: function (data) {
              // console.log(data);
              getTenant(data)
            }
          });
        }

        function getTenant(tokens) {
          var url = "https://oauth." + env + ":443/oauth/api/oauth2/tenant/" + tokens.refreshToken;
          $.ajax({
            type: "GET",
            url: url,
            async: false,
            error: function (request) {
              alert("Get login url failed, please retry");
              $("#cantoUCPanel").addClass("hidden");
            },
            success: function (data) {
              // console.log(data);
              tokens.refreshToken = data;
              saveTokenToDrupal(tokens);
            }
          });
        }

        function saveTokenToDrupal(tokens) {
          var result = {'accessToken': tokens.accessToken, 'tokenType': tokens.tokenType, 'subdomain': tokens.refreshToken};
          $.ajax({
            url: Drupal.url('canto_connector/save_access_token'),
            type: 'POST',
            data: result,
            dataType: 'json',
          });
          tokenInfo = tokens;
          loadIframeContent();
        }

        /*--------------------------load iframe content---------------------------------------*/
        function initCantoTag() {
          var body = $("body");
          var cantoTag = body.find("canto");
          var imageHtml = '<button class="canto-pickup-img-btn">+ Insert Files from Canto</button>';

          cantoTag.append(imageHtml);
        }

        /*--------------------------load iframe content---------------------------------------*/
        function loadIframeContent() {
          var cantoLoginPage = "https://oauth." + env + "/oauth/api/oauth2/compatible/authorize?response_type=code&app_id=" + appId + "&redirect_uri=http://loacalhost:3000&state=abcd";

          var cantoContentPage = "/canto-assets/static/universal/cantoContent.html";
          if (tokenInfo.accessToken) {
            $("#cantoUCFrame").attr("src", cantoContentPage);
            $(".canto-sso-section").addClass("hidden");
            $("#cantoUCFrame").removeClass("hidden");
          } else {
            var getSSOwidURL = "https://oauth." + env + "/oauth/api/oauth2/ssowid";
            $.ajax({
              type: "GET",
              url: getSSOwidURL,
              data: $('#frmGrant').serialize(),
              async: false,
              error: function (request) {
                alert("Get login url failed, please retry");
                $("#cantoUCPanel").addClass("hidden");
              },
              success: function (data) {
                _workerID = data;
                cantoLoginPage += "&worker_id=" + data;
                $("#cantoUCFrame").attr("src", cantoLoginPage);
                window.open(cantoLoginPage, '_blank');
                $(".canto-sso-section").removeClass("hidden");
                $("#cantoUCFrame").addClass("hidden");
              }
            });

          }
        }

        /*--------------------------add iframe---------------------------------------*/
        function createIframe() {
          var body = $("body");
          var iframeHtml = '<div class="canto-uc-frame hidden" id="cantoUCPanel">';
          iframeHtml += '<div class="header">';
          iframeHtml += '<div class="title">Canto Content</div>';
          iframeHtml += '<div class="close-btn icon-s-closeicon-16px canto-uc-iframe-close-btn"></div>';
          iframeHtml += '</div>';
          iframeHtml += '<div class="canto-sso-section">';
          iframeHtml += '<div class="sso-description">Please finish authorization in the new browser tab</div>'
          iframeHtml += '<div class="sso-description"><b>Note:</b> You may need to configure your browser to allow pop-up windows.</div>';
          iframeHtml += '<div class="sso-description">If you have finished authorization, please click <b>"Continue"</b> to proceed using Canto. </div>';
          iframeHtml += '<div class="submit-button js-canto-oauth-btn">Continue</div>';
          iframeHtml += '</div>';
          iframeHtml += '<iframe id="cantoUCFrame" class="canto-uc-subiframe hidden" src=""></iframe>';
          iframeHtml += '</div>';

          body.append(iframeHtml);
        }

        /*--------------------------load file---------------------------------------*/
        function dynamicLoadJs(url, callback) {
          var head = document.getElementsByTagName('head')[0];
          var script = document.createElement('script');
          script.type = 'text/javascript';
          script.src = url;
          if (typeof (callback) == 'function') {
            script.onload = script.onreadystatechange = function () {
              if (!this.readyState || this.readyState === "loaded" || this.readyState === "complete") {
                callback();
                script.onload = script.onreadystatechange = null;
              }
            };
          }
          head.appendChild(script);
        }

        function dynamicLoadCss(url) {
          var head = document.getElementsByTagName('head')[0];
          var link = document.createElement('link');
          link.type = 'text/css';
          link.rel = 'stylesheet';
          link.href = url;
          head.appendChild(link);
        }

      })
    }
  };
})(jQuery, Drupal, once);

