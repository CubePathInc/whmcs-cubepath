<?php

$_ADDONLANG['page_dashboard'] = 'Dashboard';
$_ADDONLANG['page_creator'] = 'Product Creator';
$_ADDONLANG['page_products'] = 'Products';
$_ADDONLANG['page_locations'] = 'Locations';
$_ADDONLANG['page_templates'] = 'Templates';

// Common
$_ADDONLANG['apiUnavailable'] = 'Could not reach the CubePath API.';
$_ADDONLANG['checkConfiguration'] = 'Check the addon configuration.';
$_ADDONLANG['tokenMissing'] = 'No API token configured.';
$_ADDONLANG['unknownAction'] = 'Unknown action.';
$_ADDONLANG['configured'] = 'Configured';
$_ADDONLANG['notConfigured'] = 'Not configured';
$_ADDONLANG['name'] = 'Name';
$_ADDONLANG['apiValue'] = 'API value';
$_ADDONLANG['enabled'] = 'Enabled';
$_ADDONLANG['save'] = 'Save';
$_ADDONLANG['edit'] = 'Edit';
$_ADDONLANG['hidden'] = 'Hidden';
$_ADDONLANG['retired'] = 'Retired';
$_ADDONLANG['application'] = 'application';
$_ADDONLANG['plan'] = 'Plan';
$_ADDONLANG['plans'] = 'plans';

// Dashboard
$_ADDONLANG['connection'] = 'Connection';
$_ADDONLANG['apiStatus'] = 'API status';
$_ADDONLANG['connected'] = 'Connected';
$_ADDONLANG['disconnected'] = 'Disconnected';
$_ADDONLANG['apiToken'] = 'API token';
$_ADDONLANG['defaultProjectId'] = 'Default project';
$_ADDONLANG['projectNotFound'] = 'Project %s (not found)';
$_ADDONLANG['catalog'] = 'Catalog';

// Product creator
$_ADDONLANG['creatorIntro'] = 'Each product is linked to a CubePath plan. Clients choose the location and operating system when ordering.';
$_ADDONLANG['projectIdMissing'] = 'Choose a Default Project in the addon configuration before creating products, or VPS cannot be provisioned.';
$_ADDONLANG['noProductGroups'] = 'There are no product groups yet.';
$_ADDONLANG['createGroup'] = 'Create one';
$_ADDONLANG['createSingle'] = 'Create a product';
$_ADDONLANG['productName'] = 'Product name';
$_ADDONLANG['productNamePlaceholder'] = 'Defaults to the plan name';
$_ADDONLANG['productGroup'] = 'Product group';
$_ADDONLANG['paymentType'] = 'Payment type';
$_ADDONLANG['paytype_recurring'] = 'Recurring';
$_ADDONLANG['paytype_onetime'] = 'One time';
$_ADDONLANG['paytype_free'] = 'Free';
$_ADDONLANG['monthlyPrice'] = 'Monthly price';
$_ADDONLANG['priceHelp'] = 'The USD price is prefilled with the CubePath cost of the plan. Other billing cycles can be enabled afterwards from the product page.';
$_ADDONLANG['createProduct'] = 'Create product';
$_ADDONLANG['outOfStock'] = 'out of stock';
$_ADDONLANG['alreadyExists'] = 'product exists';
$_ADDONLANG['createAll'] = 'Create all plans';
$_ADDONLANG['createAllIntro'] = 'Creates one recurring product per available plan that does not have a product yet. If USD is the default currency, prices are set to the CubePath cost plus the markup; otherwise they are left at 0.00 for you to fill in.';
$_ADDONLANG['createAllConfirm'] = 'Create a product for every available CubePath plan?';
$_ADDONLANG['createAllButton'] = 'Create products';
$_ADDONLANG['markup'] = 'Markup';
$_ADDONLANG['planNotFound'] = 'That plan is no longer offered by CubePath.';
$_ADDONLANG['planOutOfStock'] = 'That plan is out of stock in every location.';
$_ADDONLANG['invalidPrice'] = 'Prices must be numbers equal to or greater than zero.';
$_ADDONLANG['groupRequired'] = 'Choose a product group.';
$_ADDONLANG['productCreated'] = 'Product #%d created.';
$_ADDONLANG['productsCreated'] = '%d products created.';

// Products
$_ADDONLANG['productsIntro'] = 'Products using the CubePath server module. Edit or delete them from the WHMCS product configuration.';
$_ADDONLANG['services'] = 'Services';
$_ADDONLANG['noProducts'] = 'No CubePath products yet.';
$_ADDONLANG['syncProducts'] = 'Sync options';
$_ADDONLANG['syncProductsHelp'] = 'Adds new locations and templates from CubePath to every product. Existing options and prices are kept.';
$_ADDONLANG['productsSynced'] = 'Products are in sync with the CubePath catalog.';

// Locations and templates
$_ADDONLANG['locationsIntro'] = 'Disabled locations are hidden from the order form of every CubePath product.';
$_ADDONLANG['templatesIntro'] = 'Disabled operating systems and applications are hidden from the order form of every CubePath product.';
$_ADDONLANG['locationsSaved'] = 'Locations saved.';
$_ADDONLANG['templatesSaved'] = 'Templates saved.';
