=== UPC/EAN/GTIN Code Generator ===
Contributors: UkrSolution <https:
Tags: UPC, EAN, GTIN, GS1, Barcode
Requires at least: 4.0.1 
Tested up to: 6.4
Stable tag: trunk
Requires PHP: 5.8.1
License: GPLv2 or later 
License URI: http:

Generate or import UPC/EAN/GTIN codes for WooCommerce products automatically

== Description ==

### Generate UPC/EAN/GTIN codes for WooCommerce products.

https://youtu.be/i0C0e0lRRtQ

This plugin was designed to achieve 2 major purposes:

1. Assign codes for all your existing products automatically.
So, you don't have to open each WooCommerce product and add the UPC/EAN code manually.

2. Assign codes for newly created products.
So, when you create a new product - the code will be added to the product automatically.

In plugin settings you will need to specify barcode type (EAN or UPC) and the product field where to store the code.

UPC is used in USA and contains 12 digits.
EAN is used wordlwide and contains 13 digits.

You can generate codes into product SKU field or into any other product field as plugin integrated with serveral barcode plugins.
Also you can specify custom field name to use any other product field for UPC/EAN generation.

After you selected required code type, save the settings and then press "Assign codes" button. 
In opened popup you will see progress how much products were processed. 
As soon as codes are generated you can close the window and check your products for generated UPC/EAN codes.

For the new products UPC/EAN barcode will be generated automatically as soon as you save/publish the product.

Generated codes are valid (have correct the last checkum digit) and ready for using in barcode generation tools.

However, from legal point of view free UPC/EAN codes should be used only internaly (inside your website/company).

[Read more](https://www.ukrsolution.com/WordPress/UPC-EAN-code-importer-and-generator) about diferences between free and paid UPC/EAN codes.

PRO Version: Allows to import UPC/EAN codes from the purchased Excel or CSV file.

Contact [UKR Solution](https://www.ukrsolution.com/) team if you have any questions.


== Frequently Asked Questions == 

= Can codes be used for barcodes ? =

Yes, you can use generated UPC/EAN codes to create barcodes. However you will need to find a separate plugin for barcode image generation. Try to search something like "Barcode Generator" or "Print Barcode Labels" plugins. 

= Plugins creates codes for variations too ? =

Yes, each variation will have its own UPC or EAN field. Field will be created as soon as plugin is installed.
You can find this field empty if you didn't start "code generation" process yet.

= Are generated codes real/valid ? =

Generated codes are valid (have correct checksum digit), however some warehouses and shipping centers may request non-free UPC/EAN codes, for more information please [contact us](https://www.ukrsolution.com/ContactUs)

== Screenshots ==

1. Newly created EAN/UPC field for products
2. Plugin settings
3. EAN-13 code generation process

== Changelog =

= 2.0.0 - November 15rd, 2021 =
* Feature: Added integration with the third party plugins.
* Feature: Added default "Barcode" field for the products.
* Feature: Added possibility to assign codes for new products.
* Feature: Implemented import codes from the Excel/CSV files (pro version)
* Improvement: Minor UI/UX improvement

= 1.0.0 - October 13rd, 2021 =
* First version.

== Upgrade Notice ==
* First version.
