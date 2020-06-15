<?php namespace ProcessWire;

/**
 * Optional config file for OrderPages.module
 *
 * When present, the module will be configurable and the configurable properties
 * described here will be automatically populated to the module at runtime.  
 * 
 */

$config = array(
	
	'prefixMessage' => array(
		'name'=> 'order_num',
		'type' => 'text', 
		'label' => 'Next order',
		'description' => 'Please Enter the norder number you would like to start from', 
		'value' => '1000', 
		'required' => true 
	),
	'skuField' => array(
		'name'=> 'f_sku',
		'type' => 'text', 
		'label' => 'Name for sku field',//This is the label of the field on the config page
		'description' => 'Please enter the name of your product number field - ideally this should be FieldtypeTextUnique', 
		'value' => '', 
		'required' => true 
	),
	'customerField' => array(
		'name'=> 'f_customer',
		'type' => 'text', 
		'label' => 'Name for customer field',//This is the label of the field on the config page
		'description' => 'Please use a custom value if this collides with an existing field name', 
		'value' => 'customer', 
		'required' => true 
	),
	'skuRefField' => array(
		'name'=> 'f_sku_ref',
		'type' => 'text', 
		'label' => 'Name for sku reference field',//This is the label of the field on the config page
		'description' => 'Please use a custom value if this collides with an existing field name',
		'value' => 'sku_ref', 
		'required' => true 
	),
	'quantityField' => array(
		'name'=> 'f_quantity',
		'type' => 'text', 
		'label' => 'Name for quantity field',//This is the label of the field on the config page
		'description' => 'Please use a custom value if this collides with an existing field name', 
		'value' => 'quantity', 
		'required' => true 
	),
	'totalField' => array(
		'name'=> 'f_total',
		'type' => 'text', 
		'label' => 'Name for quantity field',//This is the label of the field on the config page
		'description' => 'Please use a custom value if this collides with an existing field name', 
		'value' => 'total', 
		'required' => true 
	),
	'lineItemTemplate' => array(
		'name'=> 't_line-item',
		'type' => 'text', 
		'label' => 'Name for line item template',//This is the label of the field on the config page
		'description' => 'Please use a custom value if this collides with an existing template name', 
		'value' => 'line-item', 
		'required' => true 
	),
	'cartItemTemplate' => array(
		'name'=> 't_cart-item',
		'type' => 'text', 
		'label' => 'Name for cart item template',//This is the label of the field on the config page
		'description' => 'Please use a custom value if this collides with an existing template name',  
		'value' => 'cart-item', 
		'required' => true 
	),
	'orderTemplate' => array(
		'name'=> 't_order',
		'type' => 'text', 
		'label' => 'Name for order template',//This is the label of the field on the config page
		'description' => 'Please use a custom value if this collides with an existing template name', 
		'value' => 'order', 
		'required' => true 
	),
	'stepTemplate' => array(
		'name'=> 't_step',
		'type' => 'text', 
		'label' => 'Name for step template',//This is the label of the field on the config page
		'description' => 'Please use a custom value if this collides with an existing template name',  
		'value' => 'step', 
		'required' => true 
	)
	
);