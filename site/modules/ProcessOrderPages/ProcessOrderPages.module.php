<?php namespace ProcessWire;

class ProcessOrderPages extends Process {

  public static function getModuleinfo() {
    return [
      'title' => 'Process Order Pages',
      'summary' => 'Allows order pages to be created on front end and managed via admin.',
      'author' => 'Paul Ashby, primitive.co',
      'version' => 1,
      'requires' => [
        'FieldtypeTextUnique>=1.0.0'
      ],

      // page that you want created to execute this module
      'page' => [
        // your page will be online at /processwire/yourname/
        'name' => 'orders',
        // page title for this admin-page
        'title' => 'Orders',
      ],
    ];
  }
  protected $settings = array(
    'pre' => 'pop_'
  );
  protected $line_item_fields = array(
    'customer'      =>  array('fieldtype'=>'FieldtypeText', 'label'=>'Customer'),
    'sku'           =>  array('fieldtype'=>'FieldtypeTextUnique', 'label'=>'SKU - Unique product identifier'),
    'order_status'  =>  array('fieldtype'=>'FieldtypeText', 'label'=>'Status'),
    'quantity'      =>  array('fieldtype'=>'FieldtypeInteger', 'label'=>'Number of packs'),
    'total'         =>  array('fieldtype'=>'FieldtypeInteger', 'label'=>'Line item total')
  );
  protected $step_pages = array(
    'cart-items'        =>  array('title'=>'Cart Items', 'template'=>'cart-item'),
    'active-orders'     =>  array('title'=>'Active Orders', 'template'=>'step'),
    'completed-orders'  =>  array('title'=>'Completed Orders', 'template'=>'step')
  );
  protected $step_templates_setup = array(
  );
  protected $step_templates = array(
  );

  public function init() {

     parent::init();

    // include css

    $this->addHookAfter('InputfieldForm::render', function(HookEvent $event) {

      // Add class suffix for css to remove top margin and set button colour according to status
      $return = $event->return;

      if (strpos($return, 'processed-form') !== false) {
        $class_suffix = '--pending';
      } else {
        $class_suffix = '--processed';
      }
      $event->return = str_replace(array('uk-margin-top', 'ui-button'), array('', 'ui-button ui-button' . $class_suffix), $return);
    });
  }
  public function ___install() {

    parent::___install();

    // Initalised here as it must be possible to evaluate class member properties at compile time
    $this->settings['line_item_template_name'] = $this->validateName($this->settings['pre'] . 'line-item', 'templates');
    $this->settings['order_template_name'] = $this->validateName($this->settings['pre'] . 'order', 'templates'); 
    
    $this->step_templates_setup['cart-item'] = $this->settings['line_item_template_name'];
    $this->step_templates_setup['order'] = $this->settings['line_item_template_name'];
    $this->step_templates_setup['step'] = $this->settings['order_template_name'];

    // Make template for line item pages
    $fg = new Fieldgroup();
    
    $fgname = $this->validateName($this->settings['pre'] . 'line-item', 'fieldgroups');
    $fg->name = $fgname;
    $fg->add($this->fields->get('title'));

    foreach ($this->line_item_fields as $fieldname => $options) {
      $f = new Field();
      $f->type = $this->modules->get($options['fieldtype']);
      $f->name = $this->validateName($this->settings['pre'] . $fieldname, 'fields');
      $f->label = $options['label'];
      $f->save();
      $fg->add($f);
    }

    $fg->save();

    $line_item_template = new Template();
    $line_item_template->name = $this->settings['line_item_template_name'];
    $line_item_template->fieldgroup = $fg;
    $line_item_template->save();

    // Make template for cart items page, order pages, order step pages (Orders/Cart Items, Orders/Active, Orders/Complete) - set permitted child pages
    foreach ($this->step_templates_setup as $name => $chld_pg_tmplt_name) {
      $this->step_templates[$name] = $this->makeSimpleTemplate($name, array($chld_pg_tmplt_name));
    }

    // Make pages for order steps - these are parent pages for cart items, active orders and completed orders
    foreach ($this->step_pages as $raw_name => $setup) { 
      $pg_name = $this->validateName($this->settings['pre'] . $raw_name, 'pages');
      $this->makePage($this->step_templates[$setup['template']], '/processwire/orders/', $pg_name, $setup['title']);
    } 
  }
  public function ___uninstall() {

    $this->removeTemplate($this->settings['pre'] . 'line-item', $this->line_item_fields);

    // Safe to remove the following as all line-items will have been removed

    // Remove order step pages (Cart Items, Active Orders, Completed Orders)
    foreach ($this->step_pages as $pg => $value) {
      $pg_selector = 'name='. $this->settings['pre'] . $pg; 
      
      if(wire('pages')->count($pg_selector)){
        wire('pages')->get($pg_selector)->delete(true); 
      }
    }

    // Remove templates
    $this->removeTemplate($this->settings['pre'] . 'cart-item');
    $this->removeTemplate($this->settings['pre'] . 'step');
    $this->removeTemplate($this->settings['pre'] . 'order');

    // Remove admin Order page - now that its child pages have been removed
    parent::___uninstall();

  }
  public function ___execute() {
    
    $feedback = '';

    if($this->input->post->submit) {
      
      $form = $this->modules->get('InputfieldForm');
      $form->processInput($this->input->post);

      if($form->getErrors()) {
        $out .= $form->render();
      } else {
        $operation = $this->sanitizer->text($this->input->post->submit);

        // For order pages,move the Cart Items pages to become children of the order
        /*
          $page = $pages->get('name=page-to-move');
          $page->of(false);
          $page->parent = $pages->get('name=new-parent');
          $page->save();

        */
        
        if($operation === 'Processed') {
          $order_num = $this->sanitizer->text($this->input->post['processed-order']);
          $feedback = '<h1>Processed - should change '. $order_num . ' to completed</h1>';
        } else if ($operation === 'Completed') {
          $order_num = $this->sanitizer->text($this->input->post['completed-order']);
          $feedback = '<h1>Completed - should move ' . $order_num . ' to completed orders</h1>';
        }
      }
    }

    // This was a test to add the customer to an order - now doing this by saving user->id into the 'customer' text field
    // $this->pages->get(1021)->customer_test = $this->user;
    // $p = $this->pages->get(1021);
    // $p->of(false);
    // $p->customer_test = $this->user;
    // $p->save();
    //////
    $out = "";

    $out .= $feedback;

    $order_number = 100234;

    $form = $this->modules->get('InputfieldForm');
    $form->action = "./";
    $form->method = "post";
    $form->attr("id+name",'processed-form');

    $field = $this->modules->get("InputfieldHidden");
    $field->attr('id+name','processed-order');
    $field->set('value', $order_number);
    $form->add($field);

    $button = $this->modules->get('InputfieldSubmit');
    $button->value = 'Processed';
    $form->add($button);

    $table = $this->modules->get('MarkupAdminDataTable');
    $table->setEncodeEntities(false);
    $table->headerRow(['Order Number', 'Product', "Packs", "Total", 'Customer', 'Status']);

    $table->row([$order_number, '<ul class="order-details"><li><span class="order-details__sku">FL180</span> Another Fine Nest You&apos;ve Got Me Into</li><li><span class="order-details__sku">699</span> A Flower for You</li></ul>', '<ul class="order-details"><li>2</li><li>4</li></ul>', '£16.00','Medi Gifts & Homestyle',  $form->render()]);

    $order_number = 100235;

    $form = $this->modules->get('InputfieldForm');
    $form->action = "./";
    $form->method = "post";
    $form->attr("id+name",'completed-form');

    $field = $this->modules->get("InputfieldHidden");
    $field->attr('id+name','completed-order');
    $field->set('value', $order_number);
    $form->add($field);

    $button = $this->modules->get('InputfieldSubmit');
    $button->value = 'Completed';
    $form->add($button);

    $table->row([$order_number, '<ul class="order-details"><li><span class="order-details__sku">NC706</span> Chrysanthemum</li></ul>', '<ul class="order-details"><li>2</li></ul>', '£3.50', 'Athena',  $form->render()]);

    $out .= $table->render();

    $out .= '<p><a href="./mysecondpage" class="ui-button ui-state-default">Go to Page 2</a></p>';
    
    return $out;
  }

  public function ___executeMysecondpage() {

    $out = '<p>Hello Page2 :)</p>';
    $out .= '<p><a href="./" class="ui-button ui-state-default">Go to Page 1</a></p>';

    return $out;
  }

  /**
 * Check whether field name exists
 *
 * @param    string  $key The field name to check
 * @param    string  $existing The data type to check - page, field etc
 * @return   string
 *
 * @throws   WireException if $existing already has element named with the provided key.
  *
 */

  protected function validateName($key, $existing) {

    $selector = 'name=' . $key;

    if($existing === 'pages') {
      $exists = $this[$existing]->count($selector); 
    } else {
      $exists = $this[$existing]->get($selector);
    }
    
    if($exists) {
      $existing_type = substr($existing, 0, -1); // Remove last character - 's'
      throw new WireException($existing_type . ' with name "' . $key . '" already exists.');
    }
    return $key;
  }

  /**
 * Get order number
 *
 * @return   string The next free order number
 *
  *
 */
  protected function getOrderNum() {

    $data = $this->modules->getConfig('ProcessOrderPages');
    return $data['order_num'];
  }

/**
 * Set order number
 *
 * @param    string  $val The number to base new orders on
 * @return   boolean
 *
  *
 */
  protected function setOrderNum($val) {

    $data = $this->modules->getConfig('ProcessOrderPages');
    $data['order_num'] = $val;
    return $this->modules->saveConfig('ProcessOrderPages', $data);
  }

/**
 * Increment order number
 *
 * @return   string The new order number
 *
  *
 */
  protected function incrementOrderNum() {

    $data = $this->modules->getConfig('ProcessOrderPages');
    $order_num = $this->sanitizer->text($data['order_num']);
    $order_num++;
    $data['order_num'] = $this->sanitizer->text($order_num);
    $this->modules->saveConfig('ProcessOrderPages', $data);
    return $data['order_num'];
  }

/**
 * Create a template with single field for title
 *
 * @param    string $key Name for the new template
 * @param    array $child_tmpl Array of permitted child template names
 * @return   Object The new template
 *
  *
 */
  protected function makeSimpleTemplate($key, $child_tmpl, $full_name = null) {

    $fg = new Fieldgroup();
    $fg->name = $this->validateName($this->settings['pre'] . $key, 'fieldgroups');
    $fg->add($this->fields->get('title'));
    $fg->save();
    $t = new Template();
    if(is_null($full_name)) {
      $t->name = $this->validateName($this->settings['pre'] . $key, 'templates'); 
    } else {
      $t->name = $full_name;
    }
    
    $t->fieldgroup = $fg;
    $t->save();

    $t->childTemplates = $child_tmpl;
    // $t->childTemplates($child_tmpl);
    $t->save();
    return $t;
  }

  /**
   * Remove a template
   *
   * @param    string $key Name of the template to delete
   * @param    associative array List of fields
   * @return   Boolean
   *
    *
   */
  protected function removeTemplate($key, $fields=null) {
    
    $rm_tmplt = $this->templates->get($key);
    // $rm_fldgrp = $this->fieldgroups->get($key);
    $rm_fldgrp = $rm_tmplt->fieldgroup;

    if ($rm_tmplt->getNumPages() > 0) {
        throw new WireException("Can't uninstall because template is in use by some pages.");
    } else {
        wire('templates')->delete($rm_tmplt);
        wire('fieldgroups')->delete($rm_fldgrp);

        if( ! is_null($fields)) {
          foreach($fields as $fieldname => $options) {
            $rm_fld = wire('fields')->get($this->settings['pre'] . $fieldname);
            wire('fields')->delete($rm_fld);
          }
        }
      }
      return true;
  }

  /**
   * Create a new page
   *
   * @param    string $template Name of template to use for the new page
   * @param    string $parent Path to parent of the new page eg ('/about/')
   * @param    string $name Name used in the page url
   * @param    string $title Set page title
   * @return   Object The new page
   *
    *
   */
  protected function makePage($template, $parent, $name, $title) {

    $p = $this->wire(new Page()); // create new page object
    $p->template = $template;
    $p->parent = wire('pages')->get($parent); // set the parent
    $p->name = $name; // give it a name used in the url for the page
    if( ! is_null($title)) {
      $p->title = $title; // set page title (not neccessary but recommended)
    }
    $p->save();
    return $p;
  }
}