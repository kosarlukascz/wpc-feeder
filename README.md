# WP Google Merchant Center Feeder

**WP Google Merchant Center Feeder**  is a WordPress plugin that generates product feeds for Google Merchant Center. It allows you to easily sync your WooCommerce products with Google Merchant Center to promote your products through Google Shopping Ads. Plugin use data from your WooCommerce 3.1+ store.
## Installation 
1. Download the plugin.
2. Log in to your WordPress admin dashboard.
3. Navigate to "Plugins" → "Add New".
4. Click on the "Upload Plugin" button at the top of the page.
5. Select the downloaded zip file and click "Install Now".
6. After installation, click "Activate" to activate the plugin.
## XML Directory
Files that the plugin generates can be found in the 
```
wp-content/uploads/wpc-feeder/
```
## Generating Feeds
To generate feeds using WP CLI, you can use the following commands:
### Generate Feeds for All Products

```bash

wp wpc-feeder --generate=all
```



This command will generate feeds for all products in your WooCommerce store.
### Generate Feeds for Products with Zero Sales

```bash

wp wpc-feeder --generate=zero
```



This command will generate feeds for products that have zero sales.
### Generate Feeds for Products with Sales

```bash

wp wpc-feeder --generate=one
```

This command will generate product sales for custom labels
### Generate meta data for custom labels used in feed

```bash

wp wpc-feeder-meta
```



This command will generate feeds for products that have more than zero sales.
## Contributing

Contributions are welcome! If you encounter any issues or have suggestions for improvements, please open an issue on the [plugin's GitHub repository](https://github.com/kosarlukascz/wpc-feeder) .
## License

This plugin is released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html) .
