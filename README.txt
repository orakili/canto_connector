# Canto Connector

This module provides Canto DAM integration for Media entity. When a Canto DAM asset is added to a piece of content, this module will create a media entity which provides a "local" copy of the asset to your site.
The module uses Canto Universal Connector (JS solution), which submits data to Drupal Entity Browser form.

## Module installation

Download and install the Canto Connector module [See here for help with installing modules](https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules).

### Step-by-step configuration guide

This guide provides an example for how to implement the Canto Connector module on your Drupal 9 site.
### Quick start configuration guide


Manual:

Add a new media bundle (admin/structure/media/add) which uses "Canto DAM" as the "Type Provider".

- NOTE: The media bundle must be saved before adding fields. More info on creating a media bundle can be found at: https://drupal-media.gitbooks.io/drupal8-guide/content/modules/media_entity/create_bundle.html
- NOTE: It may be desirable to create separate media bundles for different types of Canto DAM assets (i.e. "Canto DAM Images", "Canto DAM Documents", "Canto DAM videos", etc).

Add a field to your newly created media bundle for storing the Canto DAM Asset File. The Asset file field type should be "Reference -> File" and it should be limited to 1 value.

- NOTE: It is not recommended to "Enable display field" for the file field as this currently causes new entities to be "Not Published" by default regardless of the "Files displayed by default" setting.
- NOTE: Canto DAM asset files are downloaded locally when they are added to a piece of content. Therefore you may want to [configure private file storage](https://www.drupal.org/docs/8/core/modules/file/overview) for your site in order to prevent direct access.
- NOTE: You must configure the list of allowed file types for this field which will be specific to this media bundle. Therefore you can create separate media bundles for different types of Canto DAM assets.

Return to the bundle configuration and set "Field with source information" to use the assetID field and set the field map to the file field.

Optional:

Additional fields may be added to store the Canto DAM asset metadata. Here is a list of metadata fields which can be mapped by the "Canto DAM Asset" type provider and the recommended field type.

// @todo

Additional XMP metadata field mapping options, depending on the fields enabled in Canto DAM, will also be available (ex. city, state, customfield1 etc.)

Return to the media bundle configuration page and set the field mappings for the fields that you created. When a Canto DAM asset is added to a piece of content, this module will create a media entity which provides a "local" copy of the asset to your site. When the media entity is created the Canto DAM values will be mapped to the entity fields that you have configured.
// @todo : The mapped field values will be periodically synchronized with Canto DAM via cron.

- REQUIRED: You must create a field for the Canto DAM asset ID and set the "Type provider configuration" to use this field as the "Field with source information".
- REQUIRED: You must create a field for the Canto DAM asset file and map this field to "File" under field mappings.

#### Asset status

If you want your site to reflect the Canto DAM asset status you should map the "Status" field to "Publishing status" in the media bundle configuration. This will set the published value (i.e. status) on the media entity that gets created when a Canto DAM asset is added to a piece of content. This module uses cron to periodically synchronize the mapped media entity field values with Cantodam.

- NOTE: If you are using the asset expiration feature in Canto DAM, be aware that that the published status will not get updated in Drupal until the next time that cron runs (after the asset has expired in Canto DAM).
- (2017-09-26) When an inactive asset is synchronized the entity status will show blank because of [this issue](https://www.drupal.org/node/2855630)

#### Date created and date modified
// @todo.

### Configure Canto DAM API credentials and Cron settings
// @todo

#### Crop configuration
If you are using the [Crop](https://www.drupal.org/project/crop) module on your site, you should map the "Crop configuration -> Image field" to the field that you created to store the Canto DAM asset file.

### Configure an Entity Browser for Canto DAM
In order to use the Canto DAM asset browser you will need to create a new entity browser or add a Canto DAM widget to an existing entity browser (/admin/config/content/entity_browser).

- NOTE: For more information on entity browser configuration please see the [Entity Browser](https://www.drupal.org/project/entity_browser) module and the [documentation](https://github.com/drupal-media/d8-guide/blob/master/modules/entity_browser/inline_entity_form.md) page on github
- NOTE: When editing and/or creating an entity browser, be aware that the "Modal" Display plugin is not compatible with the WYSIWYG media embed button.
- NOTE: When using the "Modal" Display plugin you may want to disable the "Auto open entity browser" setting.

### Add a media field
In order to add a Canto DAM asset to a piece of content you will need to add a media field to one of your content types.

- NOTE: For more information on media fields please see the [Media Entity](https://www.drupal.org/project/media_entity) module and the [Drupal 8 Media Guide](https://drupal-media.gitbooks.io/drupal8-guide/content/modules/media_entity/intro.html)
- NOTE: The default display mode for media fields will only show a the media entity label. If you are using a media field for images you will likely want to change this under the display settings (Manage Display).

### WYSIWYG configuration
The media entity module provides a default embed button which can be configured at /admin/config/content/embed. It can be configured to use a specific entity browser and allow for different display modes.

- NOTE: When choosing an entity browser to use for the media embed button, be aware that the "Modal" Display plugin is not compatible with the WYSIWYG media embed button. You may want to use the "iFrame" display plugin or create a separate Entity Browser to use with the media embed button

Project page: http://drupal.org/project/canto_connector
