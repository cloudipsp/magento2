# Magento 2
Fondy payment module for magento 2.X

Manual install
=======

1. Download the Payment Module archive, unpack it and upload its contents to a new folder <root>/app/code/Fondy/Fondy/ of your Magento 2 installation

2. Enable Payment Module

	```bash
	$ php bin/magento module:enable Fondy_Fondy --clear-static-content
	$ php bin/magento setup:upgrade
	 ```
3. Deploy Magento Static Content (Execute If needed)

	```bash
	$ php bin/magento setup:static-content:deploy
	```
Installation via Composer
=======

1. Go to Magento2 root folder

2. Enter following commands to install module:

    ```bash
    composer config repositories.cloudipsp git https://github.com/cloudipsp/magento2.git
    composer require cloudipsp/magento2:dev-master
    ```
   Wait while dependencies are updated.

3. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable Fondy_Fondy --clear-static-content
    php bin/magento setup:upgrade
    ```
4. Enable and configure Fondy in Magento Admin under Stores/Configuration/Payment Methods/Fondy

!Note: If it needed 
	```bash
    php bin/magento setup:static-content:deploy
    ```

## Installation via Magento Marketplace
=======

https://marketplace.magento.com/cloudipsp-fondy.html

## Plugin Configuration

Enable and configure Fondy plugin in Magento Admin under `Stores / Configuration / Sales / Payment Methods / Fondy`.

## Updates

v2.0 - Added direct method


![Скриншот][1]
----

[1]: https://raw.githubusercontent.com/cloudipsp/magento2/2.0/s.png