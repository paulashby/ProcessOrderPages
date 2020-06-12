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
		'description' => 'Order numbers will start from this value.', 
		'value' => '1000', 
		'required' => true 
	)
	
);