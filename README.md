# Attribute Set Cleaner extension for Magento 2.

This module was designed to clean up eav attribute sets by removing unused attributes from the attribute group sets using CLI.

Removing unused attributes from attribute sets could be a daunting task as it requires much effort to find and identify the attributes
which are not used in the given attribute set entity tables. The Attribute Set Cleaner was developed to solve this problem.  

The module will search and remove the attributes, which are not used in the given entity tables [catalog_product_entity_int, catalog_product_entity_varchar etc.]. 
As an example, if an attribute with the code "color" is assigned to "Default" attribute set, and the attribute has no value in the given entity table [catalog_product_entity_int], the attribute will be removed from the attribute group set.

## Supported Entities
- Catalog product.

_We'll be adding support for other entities in the future._

## Compatibility
- Magento >= 2.4 CE || EE || ECE
- PHP ~7.4 || ~8.0 || ~8.1

## Installation
Using composer

```
composer require softcommerce/module-attribute-set-cleaner
```

## Post Installation

```sh
# Enable the module
bin/magento module:enable SoftCommerce_AttributeSetCleaner
```

In production mode:
```sh
# compile & generate static files
bin/magento deploy:mode:set production
```

In development mode:
```
bin/magento setup:di:compile
```

## Usage

### Remove unused attributes

Command options:

```
bin/magento eavattributeset:clean [id|-i]
```

Example:

```sh
# Remove ALL unused attributes:
bin/magento eavattributeset:clean

# Remove attributes for particular attribute set, where -i stands for attribute set IDs:
bin/magento eavattributeset:clean -i 4,10
```

## Support
Soft Commerce Ltd <br />
support@softcommerce.io

## License
Each source file included in this package is licensed under OSL 3.0.

[Open Software License (OSL 3.0)](https://opensource.org/licenses/osl-3.0.php).
Please see `LICENSE.txt` for full details of the OSL 3.0 license.

## Thanks for dropping by

<p align="center">
    <a href="https://softcommerce.co.uk" target="_blank">
        <img src="https://softcommerce.co.uk/pub/media/banner/logo.svg" width="200" alt="Soft Commerce Ltd" />
    </a>
    <br />
    <a href="https://softcommerce.co.uk/" target="_blank">https://softcommerce.io/</a>
</p>
