<?php namespace ProcessWire;

/**
 * Optional config file for OrderPages.module
 *
 * When present, the module will be configurable and the configurable properties
 * described here will be automatically populated to the module at runtime.  
 * 
 */

$config = array(
	
	"adminEmail" => array(
		"name"=> "order_admin_email",
		"type" => "email", 
		"label" => "Order admin email",
		"description" => "Used to send order confirmations and receive new order notifications.", 
		"value" => "", 
		"required" => true 
	),
	"orderNumber" => array(
		"name"=> "order_num",
		"type" => "text", 
		"label" => "Next order",
		"description" => "Please Enter the order number you would like to start from", 
		"value" => "", 
		"required" => true 
	),
	"orderRootlocation" => array(
	    "name" => "order_root_location",
	    "type" => "PageListSelect",
	    "label" => "Where to install the order system (can't be reset after installation)",
	    "description" => "Please select a page for order storage - if no page is selected, the orders will be stored at '/order-pages/'"
	),
	"priceField" => array(
		"name"=> "f_price",
		"type" => "text", 
		"label" => "Name of price page reference field",
		"description" => "Useful if you're using tiered pricing. Just leave this blank if you're using a simple price field on your product page", 
		"value" => "", 
		"required" => false 
	),
	"skuField" => array(
		"name"=> "f_sku",
		"type" => "text", 
		"label" => "Name of sku field",
		"description" => "Please enter the name of your existing product number field - ideally this should be FieldtypeTextUnique", 
		"value" => "", 
		"required" => true 
	),
	"popPrefix" => array(
		"name"=> "prfx",
		"type" => "text", 
		"label" => "Prefix for fields and templates (can't be reset after installation)",
		"description" => "Please enter a string to prepend to generated field and template names to avoid naming collisions", 
		"value" => "pop", 
		"required" => true 
	),
	"orderPageAccess" => array(
		"name"=> "t_access",
		"type" => "text", 
		"label" => "Roles with view access to order pages (can't be reset after installation)",
		"description" => "Please provide a comma-separated list of role names or IDs.",  
		"value" => "", 
		"required" => false 
	)
);