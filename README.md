# skye-online-magento-1.9
Magento 1.9 plugin for Skye Mastercard® 

## Installation

To deploy the plugin, clone this repo, and copy the following plugin files and folders into the corresponding folder under the Magento root directory.

```bash
/app/code/community/Skye/
/app/design/frontend/base/default/template/skyepayments/
/app/design/adminhtml/base/default/template/skyepayments/
/app/etc/modules/Skye_Skyepayments.xml
/app/etc/modules/Skye_Skyewidgetss.xml

/skin/frontend/base/default/images/Skye/
/skin/adminhtml/base/default/images/Skye/
```

Once copied - you should be able to see the Skye Mastercard plugin loaded in magento (note this may require a cache flush/site reload)

## Varnish cache exclusions

A rule must be added to varnish configuration for any magento installation running behind a varnish backend. (Or any other proxy cache) to invalidate any payment controller action.

Must exclude: `.*skyepayments.`* from all caching.
