<?php namespace ProcessWire;

class ProcessOrderPages extends Process {

  public static function getModuleinfo() {
    return [
      'title' => 'Process Order Pages',
      'summary' => 'Allows order pages to be created on front end and managed via admin.',
      'author' => 'Paul Ashby, primitive.co',
      'version' => 1,
      'singular' => true,
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
/**
 * Add product to cart (creates a line-item page as child of /processwire/orders/cart-items)
 *
 * @param string $item The submitted form
 * @return string The configured field name
 */
  public function addToCart($item) {
    if( ! $this->ready) {
      $this->completeInstallation($item);
    }

    $sku = $this->sanitizer->text($item->sku);
    $quantity = $this->sanitizer->int($item->quantity);
    
    // Is there an existing order for this product?
    $template = $this['t_line-item'];
    $customer_field = $this['f_customer'];
    $sku_field = $this['f_sku_ref'];
    $user_id = $this->users->getCurrentUser()->id;
    $exists_in_cart = $this->pages->findOne('template=' . $template . ', ' . $customer_field . '=' . $user_id . ', ' . $sku_field . '=' . $sku);

    if($exists_in_cart->id) {
      
      // Add to existing item
      $sum = $quantity + $exists_in_cart[$this['f_quantity']];
      $exists_in_cart->of(false);
      $exists_in_cart->set($this['f_quantity'], $sum);
      $exists_in_cart->save();

    } else { 

      // Create a new item
      $item_title = $sku . ': ' . $this->users->get($user_id)[$this['f_display_name']];
      $item_data = array('title' => $item_title);
      $item_data[$this['f_customer']] = $user_id;
      $item_data[$this['f_sku_ref']] = $sku;
      $item_data[$this['f_quantity']] = $quantity;

      $cart_item = $this->wire('pages')->add($this['t_line-item'],  '/processwire/orders/cart-items', $item_data);
    }
    return json_encode(Array("success"=>true));
  }
  public function ___executeOld() {
    
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

    // This attribute sets state of button - value is either 'processed-form' or 'completed-form'
    /*
      "id+name" attribute sets state of button

      Orders 
    */
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
  public function ___execute() {
    // Live version
  }
///TODO: Is there a better way to call this function? We really need it in place before an item is added to the cart
/**
 * Create all fields, templates and pages required by the module - 
 *   also emails superusers if installation has failed
 *
 * @param string $item The submitted form
 * @return object The new field
 */
  public function completeInstallation($item) {

    // Not including the sku field - it's up to the user to create and add to their products

    $required_fields = array(
      'f_customer'          =>  array('fieldtype'=>'FieldtypeText', 'label'=>'Customer'),
      'f_sku_ref'           =>  array('fieldtype'=>'FieldtypeText', 'label'=>'Record of cart item sku'),
      'f_quantity'          =>  array('fieldtype'=>'FieldtypeInteger', 'label'=>'Number of packs')
    );
    $required_templates = array(
      't_line-item'         => array('t_parents' => array('t_cart-item', 't_order'), 't_fields'=>array('f_customer', 'f_sku_ref', 'f_quantity')),
      't_cart-item'         => array('t_parents' => array('admin'), 't_children' => array('t_line-item')),
      't_order'             => array('t_parents' => array('t_step'), 't_children' => array('t_line-item')),
      't_step'              => array('t_parents' => array('admin'), 't_children' => array('t_order')),
    );
    $required_pages = array(
      'p_cart-items'        =>  array('template' => 't_cart-item', 'parent'=>'/processwire/orders/', 'title'=>'Cart Items'),
      'p_pending-orders'    =>  array('template' => 't_step', 'parent'=>'/processwire/orders/', 'title'=>'Pending Orders', ),
      'p_active-orders'     =>  array('template' => 't_step', 'parent'=>'/processwire/orders/', 'title'=>'Active Orders', ),
      'p_completed-orders'  =>  array('template' => 't_step', 'parent'=>'/processwire/orders/', 'title'=>'Completed Orders', )
    );

    $safeToInstall = $this->preflightInstall(array('fields' => $required_fields, 'templates' => $required_templates));
    
    // Handle errors or proceed
    if($safeToInstall !== true) {
      wire('log')->save('order-pages-debug', __LINE__);
       wire('log')->save('order-pages-debug', print_r($safeToInstall, true));

      // The addToCart() operation that called this method will fail. Send an email to notify superusers

      $recipients = array();
      $superusers = $this->users->get("roles=superuser");
      foreach ($superusers as $sprusr) {
        $recipients[] = $sprusr->email;
      }
      $pa_email = "paul@primitive.co";
      $from = "Paul Ashby <{$pa_email}>";
      $subject = "Paperbird order cart issue";
      $user_id = $this->users->getCurrentUser()->id;
      $sku = $this->sanitizer->text($item->sku);
      $quantity = $this->sanitizer->int($item->quantity);
      $body_html = "<html><body><h1>Item could not be added to cart</h1><p>User '{$user_id}' attempted to add an item to their cart, but this operation could not be completed as the ProcessOrderPages module is misconfigured.</p><p>Please use the module's settings page to enter unique field and template names.</p><h2>Details of the cart item are as follows:</h2><dl><dt>SKU</dt><dd>{$sku}</dd><dt>Quantity</dt><dd>{$quantity}</dd></dl></body></html>";
      $options = array("bodyHTML"=>$body_html, 'replyTo'=>$pa_email);

      $this->mail->send($recipients, $from, $subject, $options);

      //TODO: Test email remotely to check that email notification gets sent - for both the cart details (above) and the WireException (below)

      // Process errors
      $errs = $safeToInstall;

      $err_mssg = 'The ProcessOrderPages module has been configured with non-unique names. Please use its settings page to provide new names for the following: ';

      if(count($errs['fields'])) {
        $err_mssg .= implode(' field, ', $errs['fields']);
        $err_mssg .= ' field. ';
      }
      if(count($errs['templates'])) {
        $err_mssg .= implode(' template, ', $errs['templates']);
        $err_mssg .= ' template. ';
      }
      throw new WireException($err_mssg);

    } else {

      foreach ($required_fields as $key => $spec) {
        $this->makeField($key, $spec);
      }
      foreach ($required_templates as $key => $spec) {
        $this->makeTemplate($key, $spec);
      }
      foreach ($required_pages as $key => $spec) {
        $this->makePage($key, $spec);
      }

      // Add display_name field to user template
      $f = new Field();
      $f->type = $this->modules->get('FieldtypeText');
      $f->name = $this['f_display_name'];
      $f->label = 'Name displayed on orders';
      $f->save();
      $usr_template = $this->templates->get('user');
      $ufg = $usr_template->fieldgroup;
      $ufg->add($f);
      $ufg->save();

      // Set initial value of this field to user name 
      foreach ($this->users->find("start=0") as $u) {
          $u->of(false);
          $u->set($this['f_display_name'], $u->name);
          $u->save();
      }

      $data = $this->modules->getConfig('ProcessOrderPages');
      $data['ready'] = 'true';
      $this->modules->saveConfig('ProcessOrderPages', $data);

    }
  }  
  public function ___uninstall() {

    $module_elmts = array(
      'pages' => array('cart-items', 'pending-orders', 'active-orders', 'completed-orders'),
      'templates' => array('t_line-item', 't_cart-item', 't_order', 't_step'),
      'fields' => array('f_display_name', 'f_customer', 'f_sku_ref', 'f_quantity')
    );

    if($this->preflightUninstall($module_elmts['pages'])) {

      // Remove display_name field from user template
      $rm_fld = wire('fields')->get($this['f_display_name']);
      if($rm_fld !== null) {
        $ufg = wire('fieldgroups')->get('user');
        $ufg->remove($rm_fld);
        $ufg->save();
        wire('fields')->delete($rm_fld);
      }

      foreach ($module_elmts['pages'] as $pg) {
        $selector = 'name=' . $pg;
        $curr_p = $this->pages->findOne($selector);
        if($curr_p->id) {
          $curr_p->delete();
        }
      }
      foreach ($module_elmts['templates'] as $t) {
        $curr_t = $this->templates->get($this[$t]);
        if( $curr_t !== null) {
          $rm_fldgrp = $curr_t->fieldgroup;
          wire('templates')->delete($curr_t);
          wire('fieldgroups')->delete($rm_fldgrp);  
        }
      }
      foreach($module_elmts['fields'] as $f) {
        $curr_f = wire('fields')->get($this[$f]);
        if($curr_f !== null) {
          wire('fields')->delete($curr_f);
        }
      }

      // Remove admin Order page - now that its child pages have been removed
      parent::___uninstall();

    } else {
      throw new WireException('Unable to uninstall module as there are orders in progress. You can permanently delete this data from the /processwire/orders page, then try again');
    }
  }
/**
 * Process line items, creating new order in /processwire/orders/pending-orders/
 *
 * @param string  $array The item to remove
 * @return Json
 */
  public function placeOrder() {
    
    foreach ($cart_items as $item) {

          // Make a new order page first, then add these to that
          // order template may need more info actually - 
          // don't we need to add the user so we can easily get orders for current customer?
      
          // $item->of(false);
          // $item->parent = $pages->get('name=pending-orders');
          // $item->save();
    }
  }
/**
 * Remove line item from cart
 *
 * @param string  $sku The item to remove
 * @return Json Updated cart markup if successful
 */
  public function removeCartItem($sku) {
    $cart_item = $this->getCartItem($sku);

    if($cart_item->id) {
        $cart_item->delete();
        return json_encode(array('success'=>true, 'cart'=>$this->renderCart()));  
    }
    return json_encode(array('error'=>'The item could not be found'));
  }
/**
 * Change quantity of cart item
 *
 * @param string  $sku The item to update
 * @param string  $qty The new value
 * @return Json
 */
  public function changeQuantity($sku, $qty) {

    $cart_item = $this->getCartItem($sku);
    $qtys = $this->sanitizer->text($qty);

    if($cart_item->id) {
        $cart_item->of(false);
        $cart_item->set($this['f_quantity'], (int)$qtys);
        $cart_item->save();
        return json_encode(array('success'=>true));  
    }
    return json_encode(array('error'=>'The item could not be found'));
  }
/**
 * Convert an integer representing GB pence to a GBP string 
 *
 * @param int $pence
 * @return string GBP value as a string with decimal point and prepended £
 */
  public function renderPrice($pence) {

    return '£' . number_format($pence/100, 2);
  }
/**
 * Generate HTML markup for current user's cart
 *
 * @return string HTML markup
 */
  public function renderCart() {

    // Store field and template names in variables for markup
    $f_sku = $this['f_sku'];
    $f_sku_ref = $this['f_sku_ref'];
    $f_quantity = $this['f_quantity'];

    $cart_items = $this->getCartItems();

    $render = "<div class='cart-items'>
    <form class='.cart-items__form' action='' method='post'>";
    // cart_items are line_items NOT product pages
    foreach ($cart_items as $item => $data) {

      $sku_ref = $data[$f_sku_ref];
      $product_selector = "template=product, {$f_sku}={$sku_ref}";
      $product = $this->pages->findOne($product_selector);
      $price = $this->renderPrice($product->price);
      $quantity = $data[$f_quantity];
      $subtotal = $this->renderPrice($product->price * $quantity);

      $render .= "<fieldset class='.form__fieldset'>
      <legend>" . $product->title . "</legend>";
      
      // Added <p> text to debug submitted form - as we're submitting inputs as arrays - name='quantity[]' etc - NOTE this <p> will only update when the page is reloaded as the quantity change is an ajax call and I'm not going to bother updating a value I'm only going to remove later
      $render .= "<p>SKU: {$sku_ref}. Quantity: {$quantity}. Price: {$price}</p>
        <label class='.form__label' for='quantity'>Quantity (Packs of 6):</label>
        <input class='.form__quantity' type='number' data-action='qtychange' data-sku='{$sku_ref}' name='quantity[]' min='1' step='1' value='{$quantity}'>
        <p class='.form__price'>{$price}</p>
        <input type='hidden' name='sku[]' value='{$sku_ref}'>
        <a class='form__button form__button--cancel' role='button' data-action='remove' data-sku='{$sku_ref}'>Remove</a>
      </fieldset>";
    }
    $render .= "<input class='form__button form__button--submit' type='submit' name='submit' value='submit'>
      </form>
    </div>";

    return $render;
  }
/**
 * Get item from cart
 *
 * @param string  $sku The item to get
 * @return object Line item page or boolean false
 */
  protected function getCartItem($sku) {

    $skus = $this->sanitizer->text($sku);
    $user_id = $this->users->getCurrentUser()->id;
    $template_name = $this['t_line-item'];
    $customer_field_name = $this['f_customer'];
    $sku_field_name = $this['f_sku_ref'];

    $selector = 'template=' . $template_name . ', ' . $customer_field_name . '=' .  $user_id . ', ' . $sku_field_name . '=' . $skus;
    $cart_item = $this->pages->findOne($selector);

    if($cart_item->id) {
      return $cart_item;
    }
    return false;
  }
/**
 * Get all cart items for the current user
 *
 * @return wireArray The cart items
 */
  protected function getCartItems() {
    
    $user_id = $this->users->getCurrentUser()->id;
    $admin_url = $this->config->url('admin');
    $t_line_item = $this['t_line-item'];
    $f_customer = $this['f_customer'];
    return $this->pages->find("has_parent={$admin_url}orders/cart-items/, template={$t_line_item}, {$f_customer}={$user_id}");
  }
/**
 * Get order number
 *
 * @return  string The next free order number
 */
  protected function getOrderNum() {

    $data = $this->modules->getConfig('ProcessOrderPages');
    return $data['order_num'];
  }

/**
 * Set order number
 *
 * @param string  $val The number to base new orders on
 * @return boolean
 */
  protected function setOrderNum($val) {

    $data = $this->modules->getConfig('ProcessOrderPages');
    $data['order_num'] = $val;
    return $this->modules->saveConfig('ProcessOrderPages', $data);
  }

/**
 * Increment order number
 *
 * @return string The new order number
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
 * Check there are no naming collisions before completing installation
 *
 * @param array $module_elmts Array of elements to check ['fields' Array of strings , 'templates' Array of strings]
 * @return array of errors or boolean true
 */
  protected function preflightInstall($module_elmts) {

    $errors = array(
      'fields' => array(),
      'templates' => array()
    );

    // Check if fields exist
    foreach($module_elmts['fields'] as $f => $spec) {
      $curr_f = wire('fields')->get($this[$f]);
      if($curr_f !== null) {
        $errors['fields'][] = $this[$f];
      }
    }

    foreach ($module_elmts['templates'] as $t => $spec) {
      $curr_t = $this->templates->get($this[$t]);
      if( $curr_t !== null) {
        $errors['templates'][] = $this[$t];
      }
    }
    if(count($errors['fields']) || count($errors['templates'])) {
      return $errors;
    }
    return true;
  }
/**
 * Check it's safe to delete provided items
 *
 * @param array $ps Names of pages to check
 * @return boolean
 */
  protected function preflightUninstall($ps) {

    // Check for ongoing orders
    foreach ($ps as $pg) {
      $selector = 'name=' . $pg;
      $curr_p = $this->pages->findOne($selector);
      if($curr_p->id){
        if($curr_p->numChildren()) {
          return false;
        }
      }
    }
    return true;
  }
/**
 * Make a field
 *
 * @param string $key Name of field
 * @param array $spec [string 'fieldtype', string 'label']
 * @return object The new field
 */
  protected function makeField($key, $spec) {
    $f = new Field();
    $f->type = $this->modules->get($spec['fieldtype']);
    $f->name = $this[$key]; // From config
    $f->label = $spec['label'];
    $f->save();
    return $f;
  }
/**
 * Make a template
 *
 * @param string $key Name of template with 'p_' prepended
 * @param array $spec [array $t_parents [string Template name], array $t_children [string Template name], $array T_field $array [string Field name]]
 * @return object The new template
 */
  protected function makeTemplate($key, $spec) {

    $fg_name = $this[$key]; // From config
    if(! $fg_name) {
      throw new WireException(__LINE__ . ': Unable to create fieldgroup as name was not provided');
    }
    $fg = new Fieldgroup();
    $fg->name = str_replace('t_', 'fg_', $fg_name);
    $fg->add($this->fields->get('title'));
    if($key === 't_line-item') {
      foreach ($spec['t_fields'] as $fkey) {
        $f_name = $this[$fkey]; // From config
        if(! $f_name) {
          throw new WireException(__LINE__ . ': Unable to add field as name was not provided');
        }
        $fg->add($f_name);
      }
    }

    $fg->save();

    $t_name = $this[$key];
    if(! $t_name) {
      throw new WireException(__LINE__ . ': Unable to create fieldgroup as name was not provided');
    }
    $t = new Template();
    $t->name = $t_name;
    $t->fieldgroup = $fg;
    $t->save();

    if(array_key_exists('t_parents', $spec)) {
      // Set permitted parent templates
      $p_selector = $this->getFamilySelector($spec['t_parents']);
      // $t->parentTemplates = $this->templates->find($f_selector);
      $parent_templates = $this->templates->find($p_selector);
      $t->parentTemplates($parent_templates);
    }

    if(array_key_exists('t_children', $spec)) {
      // Set permitted child templates
      $c_selector = $this->getFamilySelector($spec['t_children']);
      $child_templates = $this->templates->find($c_selector);
      $t->childTemplates($child_templates);
    }

    $t->save();
    return $t;
  }

/**
 * Create a new page
 *
 * @param string $key Name of page
 * @param array $spec [string 'template' - name of template, string 'parent' - path of parent page, string 'title']
 * @return Object The new page
 */
  protected function makePage($key, $spec) {
    $p = $this->wire(new Page());
    $p->template = $this[$spec['template']];
    $p->parent = wire('pages')->get($spec['parent']);
    $p->name = str_replace('p_', '', $key); // Name used in url - we're not allowing custom page names in config
    $p->title = $spec['title'];
    $p->save();

    return $p;
  }
/**
 * Make a template selector string
 *
 * @param string $relation to current template
 * @return string The selector string
 */
  protected function getFamilySelector($relation) {
    $t_selector = 'name=';
    foreach ($relation as $searchkey) {
      $t_selector .= $searchkey . '|';
    }
    return $t_selector;
  }

}