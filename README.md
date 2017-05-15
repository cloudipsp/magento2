# magento2
magento2 payment Fondy module

Install
=======

1. Go to Magento2 root folder

2. Enter following commands to install module:

    ```bash
    composer config repositories.cloudipsp git https://github.com/cloudipsp/magento2.git
    composer require cloudipsp/magento:dev-master
    ```
   Wait while dependencies are updated.

3. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable Fondy_Fondy --clear-static-content
    php bin/magento setup:upgrade
    ```
4. Enable and configure Fondy in Magento Admin under Stores/Configuration/Payment Methods/Fondy