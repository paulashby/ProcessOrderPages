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
	"skuField" => array(
		"name"=> "f_sku",
		"type" => "text", 
		"label" => "Name of sku field",
		"description" => "Please enter the name of your product number field - ideally this should be FieldtypeTextUnique", 
		"value" => "", 
		"required" => true 
	),
	"priceField" => array(
		"name"=> "f_price",
		"type" => "text", 
		"label" => "Name of price page reference field",
		"description" => "Useful if you're using tiered pricing. Just leave this blank if you're using a simple price field on your product page", 
		"value" => "", 
		"required" => false 
	),
	"userField" => array(
		"name"=> "f_display_name",
		"type" => "text", 
		"label" => "Name of short customer name field (used on orders, should be included on user profile page. Can't be reset after installation)",
		"description" => "Please use a unique name if this collides with an existing field name. Use only ASCII letters (a-z A-Z), numbers (0-9) or underscores.", 
		"value" => "display_name", 
		"required" => true 
	),
	"customerField" => array(
		"name"=> "f_customer",
		"type" => "text", 
		"label" => "Name of customer field (can't be reset after installation)",
		"description" => "Please use a unique name if this collides with an existing field name. Use only ASCII letters (a-z A-Z), numbers (0-9) or underscores.",  
		"value" => "customer", 
		"required" => true 
	),
	"skuRefField" => array(
		"name"=> "f_sku_ref",
		"type" => "text", 
		"label" => "Name of sku reference field (can't be reset after installation)",
		"description" => "Please use a unique name if this collides with an existing field name. Use only ASCII letters (a-z A-Z), numbers (0-9) or underscores.", 
		"value" => "sku_ref", 
		"required" => true 
	),
	"quantityField" => array(
		"name"=> "f_quantity",
		"type" => "text", 
		"label" => "Name of quantity field (can't be reset after installation)",
		"description" => "Please use a unique name if this collides with an existing field name. Use only ASCII letters (a-z A-Z), numbers (0-9) or underscores.",  
		"value" => "quantity", 
		"required" => true 
	),
	"sectionTemplate" => array(
		"name"=> "t_section",
		"type" => "text", 
		"label" => "Name of section template (can't be reset after installation)",
		"description" => "Please use a unique name if this collides with an existing template name. Template names may use letters (a-z A-Z), numbers (0-9), hyphens and underscores. Lowercase is optional but recommended. Do not include a file extension.",  
		"value" => "section", 
		"required" => true 
	),
	"lineItemTemplate" => array(
		"name"=> "t_line_item",
		"type" => "text", 
		"label" => "Name of line item template (can't be reset after installation)",
		"description" => "Please use a unique name if this collides with an existing template name. Template names may use letters (a-z A-Z), numbers (0-9), hyphens and underscores. Lowercase is optional but recommended. Do not include a file extension.",  
		"value" => "line-item", 
		"required" => true 
	),
	"cartItemTemplate" => array(
		"name"=> "t_cart_item",
		"type" => "text", 
		"label" => "Name of cart item template (can't be reset after installation)",
		"description" => "Please use a unique name if this collides with an existing template name. Template names may use letters (a-z A-Z), numbers (0-9), hyphens and underscores. Lowercase is optional but recommended. Do not include a file extension.",  
		"value" => "cart-item", 
		"required" => true 
	),
	"orderTemplate" => array(
		"name"=> "t_order",
		"type" => "text", 
		"label" => "Name of order template (can't be reset after installation)",
		"description" => "Please use a unique name if this collides with an existing template name. Template names may use letters (a-z A-Z), numbers (0-9), hyphens and underscores. Lowercase is optional but recommended. Do not include a file extension.",
		"value" => "order", 
		"required" => true 
	),
	"userOrdersTemplate" => array(
		"name"=> "t_user_orders",
		"type" => "text", 
		"label" => "Name of user orders template (can't be reset after installation)",
		"description" => "Please use a unique name if this collides with an existing template name. Template names may use letters (a-z A-Z), numbers (0-9), hyphens and underscores. Lowercase is optional but recommended. Do not include a file extension.",
		"value" => "user-orders", 
		"required" => true 
	),
	"stepTemplate" => array(
		"name"=> "t_step",
		"type" => "text", 
		"label" => "Name of step template (can't be reset after installation)",
		"description" => "Please use a unique name if this collides with an existing template name. Template names may use letters (a-z A-Z), numbers (0-9), hyphens and underscores. Lowercase is optional but recommended. Do not include a file extension.",  
		"value" => "step", 
		"required" => true 
	)
);