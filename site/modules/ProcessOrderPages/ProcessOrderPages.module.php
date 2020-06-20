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

      if (strpos($return, 'active-form') !== false) {
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
    $new_quantity = $this->sanitizer->int($item->quantity);
    
    // Is there an existing order for this product?
    $f_customer = $this['f_customer'];
    $f_sku_ref = $this['f_sku_ref'];
    $user_id = $this->users->getCurrentUser()->id;
    $parent_selector = $this->config->url('admin') . 'orders/cart-items/';
    $child_selector = "$f_customer=$user_id,$f_sku_ref=$sku";
    $exists_in_cart = $this->pages->get($parent_selector)->child($child_selector);

    if($exists_in_cart->id) {
      
      // Add to existing item
      $sum = $new_quantity + $exists_in_cart[$this['f_quantity']];
      $exists_in_cart->of(false);
      $exists_in_cart->set($this['f_quantity'], $sum);
      $exists_in_cart->save();

    } else { 

      // Create a new item
      $item_title = $sku . ': ' . $this->users->get($user_id)[$this['f_display_name']];
      $item_data = array('title' => $item_title);
      $item_data[$this['f_customer']] = $user_id;
      $item_data[$this['f_sku_ref']] = $sku;
      $item_data[$this['f_quantity']] = $new_quantity;

      $cart_item = $this->wire('pages')->add($this['t_line-item'],  $this->config->url('admin') . 'orders/cart-items', $item_data);
    }
    return json_encode(array("success"=>true));
  }
  public function ___execute() {
    // Live version
    if($this->input->post->submit) {
      
      $form = $this->modules->get('InputfieldForm');
      $form->processInput($this->input->post);

      if($form->getErrors()) {
        $out .= $form->render();
      } else {
        $operation = $this->sanitizer->text($this->input->post->submit);
        
        if($operation === 'Processed') {
          $order_num = $this->sanitizer->text($this->input->post['pending-order']);
          $this->progressOrder($order_num, 'active');
        } else if ($operation === 'Completed') {
          $order_num = $this->sanitizer->text($this->input->post['active-order']);
          $this->progressOrder($order_num, 'completed');
        }
      }
    }

    // Operations are 'Processed' and 'Completed'!!!
    $pending_orders = $this->getOrdersPage('pending')->children();
    $active_orders = $this->getOrdersPage('active')->children();
    $num_orders = 0;
    // Array to hold arrays of table rows
    $table_rows = array();

    $table = $this->modules->get('MarkupAdminDataTable');
    $table->setEncodeEntities(false);
    $table->headerRow(['Order Number', 'Product', 'Packs', 'Total', 'Customer', 'Status']);

    foreach ($pending_orders as $user_orders) {
      foreach ($this->getTableRows($user_orders, 'pending') as $row_out) {
        $num_orders++;
        $table->row($row_out);
      }
    }
    foreach ($active_orders as $user_orders) {
      foreach ($this->getTableRows($user_orders, 'active') as $row_out) {
        $num_orders++;
        $table->row($row_out);
      }
    }
    $out = $table->render();
    
    if($num_orders === 0) {
      $out .= "<p>There are no orders currently in the system";
    }
    return $out;
  }
 /**
 * Iterate through order pages, adding children to table rows 
 *
 * @param PageArray $user_orders The parent pages
 * @param string $step The order status
 * @return array of table rows
 */ 
  protected function getTableRows($user_orders, $step) {

    $table_rows = array();

    foreach ($user_orders as $order) {
      $order_number = $order->name;
      $form = $this->modules->get('InputfieldForm');
      $form->action = './';
      $form->method = 'post';

      // This attribute sets state of button - value is either 'processed-form' or 'completed-form'
      $form->attr('id+name',"{$step}-form");

      $field = $this->modules->get('InputfieldHidden');
      $field->attr('id+name', "{$step}-order");
      $field->set('value', $order_number);
      $form->add($field);

      $button = $this->modules->get('InputfieldSubmit');
      $button->value = $step === 'pending' ? 'Processed' : 'Completed';
      $form->add($button);

      $product_detail_lis = '';
      $quantity_lis = '';
      $total = 0;

      foreach ($order->children() as $line_item) {
        $product_sku = $line_item[$this['f_sku_ref']];
        $product_page = $this->pages->findOne("sku={$product_sku}");
        $product_title = $product_page->title;
        $product_price = $product_page->price;
        $product_quantity = $line_item[$this['f_quantity']];
        $product_detail_lis .=  "<li><span class='order-details__sku'>{$product_sku}</span> {$product_title}</li>";
        $quantity_lis .= "<li class='order-details__qty'>{$product_quantity}</li>";
        $total += $product_price * $product_quantity;
      }
      $order_total = $this->renderPrice( $total);
      $curr_user = $this->users->getCurrentUser();
      $user_id = $line_item[$this['f_customer']];
      $order_customer = $this->users->get($user_id);
      $customer_name_set = $order_customer[$this['f_display_name']];
      $customer_display_name = $customer_name_set ? $customer_name_set : $order_customer->name;
      $debug_array = array(
        $order_number,  
        $order_total, 
        $customer_display_name
      );
      $table_rows[] = array(
        $order_number, 
        "<ul class='order-details'>{$product_detail_lis}</ul>", 
        "<ul class='order-details'>{$quantity_lis}</ul>", 
        $order_total, 
        $customer_display_name,
        $form->render()
      );
    }
    return $table_rows;
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
      't_userorders'        => array('t_parents' => array('t_order'), 't_children' => array('t_order')),
      't_step'              => array('t_parents' => array('admin'), 't_children' => array('t_order')),
    );
    $required_pages = array(
      'cart-items'        =>  array('template' => 't_cart-item', 'parent'=>$this->config->url('admin') . 'orders/', 'title'=>'Cart Items'),
      'pending-orders'    =>  array('template' => 't_step', 'parent'=>$this->config->url('admin') . 'orders/', 'title'=>'Pending Orders', ),
      'active-orders'     =>  array('template' => 't_step', 'parent'=>$this->config->url('admin') . 'orders/', 'title'=>'Active Orders', ),
      'completed-orders'  =>  array('template' => 't_step', 'parent'=>$this->config->url('admin') . 'orders/', 'title'=>'Completed Orders', )
    );

    $safeToInstall = $this->preflightInstall(array('fields' => $required_fields, 'templates' => $required_templates));
    
    // Handle errors or proceed
    if($safeToInstall !== true) {
      wire('log')->save('order-pages-debug', __LINE__);
       wire('log')->save('order-pages-debug', print_r($safeToInstall, true));

      // The addToCart() operation that called this method will fail. Send an email to notify superusers

      $recipients = array();
      $superusers = $this->users->get('roles=superuser');
      foreach ($superusers as $sprusr) {
        $recipients[] = $sprusr->email;
      }
      $pa_email = 'paul@primitive.co';
      $from = 'Paul Ashby <{$pa_email}>';
      $subject = 'Paperbird order cart issue';
      $user_id = $this->users->getCurrentUser()->id;
      $sku = $this->sanitizer->text($item->sku);
      $quantity = $this->sanitizer->int($item->quantity);
      $body_html = "<html><body><h1>Item could not be added to cart</h1><p>User '{$user_id}' attempted to add an item to their cart, but this operation could not be completed as the ProcessOrderPages module is misconfigured.</p><p>Please use the module's settings page to enter unique field and template names.</p><h2>Details of the cart item are as follows:</h2><dl><dt>SKU</dt><dd>{$sku}</dd><dt>Quantity</dt><dd>{$quantity}</dd></dl></body></html>";
      $options = array('bodyHTML'=>$body_html, 'replyTo'=>$pa_email);

      $this->mail->send($recipients, $from, $subject, $options);

      //TODO: Test email remotely to check that email notification gets sent - for both the cart details (above) and the WireException (below)

      // Process errors
      $errs = $safeToInstall;

      $err_mssg = "The ProcessOrderPages module has been configured with non-unique names. Please use its settings page to provide new names for the following: ";

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
      foreach ($required_templates as $key => $spec) {
        $this->setTemplateFamily($key, $spec);
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
      throw new WireException("Unable to uninstall module as there are orders in progress. You can permanently delete this data from the " . $this->config->url('admin') . "orders page, then try again");
    }
  }
/**
 * Process line items, creating new order in /processwire/orders/pending-orders/
 *
 * @return Json
 */
  public function placeOrder() {

    // Get the parent page for the new order
    $errors = array();

    $orders_parent = $this->getOrdersPage('pending', $this->users->getCurrentUser()->id);
    
    if($orders_parent) {

      $order_number = $this->getOrderNum();

      // Create the order
      $order_page = $this-> makePage($order_number, array('template' => 't_order', 'parent'=>$orders_parent->path(), 'title'=>$order_number));
      $cart_items = $this->getCartItems();

      foreach ($cart_items as $item) {
        $item->of(false);
        $item->parent = $order_page;
        $item->save();
      }
      return json_encode(array("success"=>true));
    }
    $errors[] = "The orders page could not be found";
    return json_encode(array("errors"=>$errors));
  }
/**
 * Move order to next step to reflect new status
 *
 * @param string $order_num
 * @param string $order_step
 * @return boolean
 */
  protected function progressOrder($order_num, $order_step) {
    $order_selector = "template=" . $this['t_order'] . ",name={$order_num}";
    $admin_url = $this->config->url('admin');
    $order_pg = $this->pages->findOne($order_selector);
    
    if($order_pg->id){
      // Get the customer
      $customer = $order_pg->children()->first()[$this['f_customer']];
      $next_step = $this->getOrdersPage($order_step, $customer);
      $order_pg->of(false);
      $order_pg->parent = $next_step;
      $order_pg->save();
      return true;
    }
    return false;
  }
/**
 * Get parent page for order - for current user only if id supplied
 *
 * @param string $order_step
 * @param integer $user_id
 * @return PageArray or Page
 */
  protected function getOrdersPage($order_step, $user_id = null) {
    
    $admin_url = $this->config->url('admin');
    $parent_path = "{$admin_url}orders/{$order_step}-orders/";
    $parent_selector = "$parent_path,include=all"; 
    $order_parent_name =  "{$user_id}_orders";
    if($user_id) {
      // User provided, so get the orders page just for this customer
      $child_selector = "name=$order_parent_name,include=all";
      $user_order_page = $this->pages->get($parent_selector)->child($child_selector);
      if($user_order_page->id) {
        return $user_order_page;
      }
      // No orders for this user - make a new page within pending orders
      return $this->makePage($order_parent_name, array('template' => 't_userorders', 'parent'=>$parent_path, 'title'=>$order_parent_name));
    }
    // All orders for given step
    return $this->pages->get($parent_path)->children();
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
      
      $cart_item->delete(true);
      return json_encode(array('success'=>true, 'cart'=>$this->renderCart(true)));  
    }
    return json_encode(array('error'=>"The item could not be found"));
  }
/**
 * Change quantity of cart item
 *
 * @param string  $sku The item to update
 * @param string  $qty The new value
 * @return Json Updated cart markup if successful
 */
  public function changeQuantity($sku, $qty) {

    $cart_item = $this->getCartItem($sku);
    $qtys = $this->sanitizer->text($qty);

    if($cart_item->id) {
        $cart_item->of(false);
        $cart_item->set($this['f_quantity'], (int)$qtys);
        $cart_item->save();
        return json_encode(array('success'=>true, 'cart'=>$this->renderCart(true)));  
    }
    return json_encode(array('error'=>"The item could not be found"));
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
 * @param boolean $omitContainer - true if outer div not required (useful to avoid losing click handler)
 * @return string HTML markup
 */
  public function renderCart($omitContainer = false) {

    // Store field and template names in variables for markup
    $f_sku = $this['f_sku'];
    $f_sku_ref = $this['f_sku_ref'];
    $f_quantity = $this['f_quantity'];
    $open = $omitContainer ? "" : "<div class='cart-items'>";
    $close = $omitContainer ? "" : "</div>";

    $cart_items = $this->getCartItems();

    $render = $open;
    $render .= "<form class='cart-items__form' action='' method='post'>";
    // cart_items are line_items NOT product pages
    foreach ($cart_items as $item => $data) {
      $sku_ref = $data[$f_sku_ref];
      $sku_uc = strtoupper($sku_ref);
      $product_selector = "template=product, {$f_sku}={$sku_ref}";
      $product = $this->pages->findOne($product_selector);
      $price = $this->renderPrice($product->price);
      $quantity = $data[$f_quantity];
      $subtotal = $this->renderPrice($product->price * $quantity);

      $render .= "<fieldset class='form__fieldset'>
      <legend>" . $product->title . "</legend>";
      
      $render .= "<p>SKU: {$sku_uc}</p>
        <label class='form__label' for='quantity'>Quantity (Packs of 6):</label>
        <input class='form__quantity' type='number' data-action='qtychange' data-sku='{$sku_ref}' name='quantity[]' min='1' step='1' value='{$quantity}'>
        <p class='form__price'>Pack price: $price</p>
        <p class='form__price--subtotal'>Subtotal: $subtotal</p>
        <input type='hidden' name='sku[]' value='{$sku_ref}'>
        <input type='button' class='form__button form__button--remove' value='Remove' data-action='remove' data-sku='{$sku_ref}'>
        </fieldset>";
    }
    $render .= "<input class='form__button form__button--submit' type='submit' name='submit' value='submit'>
      </form>";
    $render .= $close;

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
    $f_customer = $this['f_customer'];
    $f_sku_ref = $this['f_sku_ref'];
    $admin_url = $this->config->url('admin');
    $parent_selector = "{$admin_url}orders/cart-items/, include=all";
    $child_selector = "{$f_customer}={$user_id}, {$f_sku_ref}={$skus}, include=all";
    $cart_item = $this->pages->findOne($parent_selector)->child($child_selector);

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
    return $this->pages->findOne("{$admin_url}orders/cart-items/, include=all")->children("template={$t_line_item}, {$f_customer}={$user_id}, include=all");
  }
/**
 * Get order number then increment in db
 *
 * @return  string The unincremented order number
 */
  protected function getOrderNum() {

    $data = $this->modules->getConfig('ProcessOrderPages');
    $order_num = $this->sanitizer->text($data['order_num']);
    $this_order_num = $order_num;
    $order_num++;
    $data['order_num'] = $this->sanitizer->text($order_num);
    $this->modules->saveConfig('ProcessOrderPages', $data);
    return $this_order_num;
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
 * @param string $key name of config input field
 * @param array $spec [array $t_parents [string Template name], array $t_children [string Template name], $array T_field $array [string Field name]]
 * @return object The new template
 */
  protected function makeTemplate($key, $spec) {

    $fg_name = $this[$key]; // From config
    if(! $fg_name) {
      throw new WireException(__LINE__ . ": Unable to create fieldgroup as name was not provided");
    }
    $fg = new Fieldgroup();
    $fg->name = str_replace('t_', 'fg_', $fg_name);
    $fg->add($this->fields->get('title'));
    if($key === 't_line-item') {
      foreach ($spec['t_fields'] as $fkey) {
        $f_name = $this[$fkey]; // From config
        if(! $f_name) {
          throw new WireException(__LINE__ . ": Unable to add field as name was not provided");
        }
        $fg->add($f_name);
      }
    }

    $fg->save();

    $t_name = $this[$key];
    if(! $t_name) {
      throw new WireException(__LINE__ . ": Unable to create template as name was not provided");
    }
    $t = new Template();
    $t->name = $t_name;
    $t->fieldgroup = $fg;
    $t->save();
    return $t;
  }
/**
 * Apply family settings to template to restrict permitted parent and child templates
 *
 * @param string $key name of config input field
 * @param array $spec [array $t_parents [string Template name], array $t_children [string Template name], $array T_field $array [string Field name]]
 * @return boolean
 */
  protected function setTemplateFamily($key, $spec) {

    $t_name = $this[$key];
    if(! $t_name) {
      $this->error("Unable to set template family", Notice::logOnly);
      return false;
    }
    $t = $this->templates->get($t_name);
    if(! $t->id) {
      $this->error("Unable to set family for template {$t_name}", Notice::logOnly);
      return false;
    }
    
    if(array_key_exists('t_parents', $spec)) {
      $parent_template_names = array();
      foreach ($spec['t_parents'] as $key => $input_name) {
        $parent_template_names[] = $this[$input_name];
      }
      // Set permitted parent templates
      $t->parentTemplates($parent_template_names);
    }

    if(array_key_exists('t_children', $spec)) {
      $child_template_names = array();
      foreach ($spec['t_children'] as $key => $input_name) {
        $child_template_names[] = $this[$input_name];
      }
      // Set permitted parent templates
      $t->childTemplates($child_template_names);
    }
    $t->save();
    return true;
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
    $p->name = $key; // Name used in url
    $p->title = $spec['title'];
    $p->save();

    return $p;
  }

}