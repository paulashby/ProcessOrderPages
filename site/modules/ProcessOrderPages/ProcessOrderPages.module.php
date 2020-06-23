<?php namespace ProcessWire;

class ProcessOrderPages extends Process {

  public static function getModuleinfo() {
    return [
      "title" => "Process Order Pages",
      "summary" => "Allows order pages to be created on front end and managed via admin.",
      "author" => "Paul Ashby, primitive.co",
      "version" => 1,
      "singular" => true,
      'autoload' => true,
      "requires" => [
        "FieldtypeTextUnique>=1.0.0", "PageMaker>=0.0.1"
      ],

      // page that you want created to execute this module
      "page" => [
        // your page will be online at /processwire/yourname/
        "name" => "orders",
        // page title for this admin-page
        "title" => "Orders",
      ],
    ];
  }

  //TODO: Shipping?

  public function ready() {
    $this->addHookBefore("Modules::saveConfig", $this, "customSaveConfig");
  }
  public function init() {
    
    // include supporting files (css, js)
    parent::init();

    $this->addHookBefore("Modules::uninstall", $this, "customUninstall");
    $this->addHookAfter("InputfieldForm::render", $this, "customInputfieldFormRender");

  }
/**
 * Store info for created elements and pass to completeInstall function
 *
 * @param  HookEvent $event
 */
  public function customSaveConfig($event) {
    
    $class = $event->arguments(0);
    if($class !== $this->className) return;
   
    // Config input
    $data = $event->arguments(1);
    $modules = $event->object;
    $page_maker = $modules->get("PageMaker");
    $configured = array_key_exists("configured", $data);

    if($configured){
      
      // We don't want to change anything once installed, so show warning and submit existing data if anything has been changed
      if($this->configDiff($data)) {
      
        $this->session->warning("Names cannot be changed after installation. If you really need to rename, you can reinstall the module, but be aware that this will mean losing the order data currently in the system");
        $curr_config = $this->modules->getConfig("PageMaker");
        $event->arguments(1, $curr_config);

      } else {
        
        // All good since we're changing nothing
        $event->arguments(1, $data);

      }
    } else {
     
      // Just installed module
      $data["configured"] = true; // Set flag
      $order_root_id = $data["order_root_location"];
      $order_root = $this->pages->get($order_root_id);

      if($order_root->id) {

        $order_root_path = $order_root->path();

      } else {
       
        $order_root_path = "/";
        $order_root = $this->pages->get("/");
      }
      
      $pgs = array(
        "fields" => array(
          "f_customer" => array("fieldtype"=>"FieldtypeText", "label"=>"Customer"),
          "f_sku_ref" => array("fieldtype"=>"FieldtypeText", "label"=>"Record of cart item sku"),
          "f_quantity" => array("fieldtype"=>"FieldtypeInteger", "label"=>"Number of packs")
        ),
        "templates" => array(
          "t_line-item" => array("t_parents" => array("t_cart-item", "t_order"), "t_fields"=>array("f_customer", "f_sku_ref", "f_quantity")),
          "t_cart-item" => array("t_parents" => array("t_section"), "t_children" => array("t_line-item")),
          "t_order" => array("t_parents" => array("t_user-orders"), "t_children" => array("t_line-item")),
          "t_user-orders" => array("t_parents" => array("t_step"), "t_children" => array("t_order")),
          "t_step" => array("t_parents" => array("t_section"), "t_children" => array("t_user-orders")),
          "t_section" => array("t_children" => array("t_section", "t_cart-item"))
        ),
        "pages" => array(
          "order-pages" => array("template" => "t_section", "parent"=>$order_root_path, "title"=>"Order Pages"),
          "cart-items" => array("template" => "t_cart-item", "parent"=>"{$order_root_path}order-pages/", "title"=>"Cart Items"),
          "pending-orders" => array("template" => "t_step", "parent"=>"{$order_root_path}order-pages/", "title"=>"Pending Orders", ),
          "active-orders" => array("template" => "t_step", "parent"=>"{$order_root_path}order-pages/", "title"=>"Active Orders", ),
          "completed-orders" => array("template" => "t_step", "parent"=>"{$order_root_path}order-pages/", "title"=>"Completed Orders", )
        )
      );

      $made_pages = $page_maker->makePages($pgs, $data);
      if($made_pages !== true){
        
        foreach ($made_pages as $e) {
          $this->error($e);
        }
        throw new WireException($e . ". Please uninstall the module then try again using unique names.");
      } else {

        $data["order_root"] = $order_root_path . "order-pages";
        
        // Add display_name field to user template
        $f = new Field();
        $f->type = $this->modules->get("FieldtypeText");
        $f_name = $data["f_display_name"];
        $f->name = $f_name;
        $f->label = "Name displayed on orders";
        $f->save();
        $usr_template = $this->templates->get("user");
        $ufg = $usr_template->fieldgroup;
        $ufg->add($f);
        $ufg->save();

        // Set initial value of this field to user name 
        foreach ($this->users->find("start=0") as $u) {
          $u->of(false);
          $u->set($f_name, $u->name);
          $u->save();
        }
      }
      $event->arguments(1, $data);
    }   
  }




/**
 * Custom uninstall 
 * 
 * @param HookEvent $event
 */
  public function customUninstall($event) {

    $class = $event->arguments(0);
    if($class !== $this->className) return;

    $page_maker = $this->modules->get("PageMaker");
    $pages_removed = $page_maker->removePages();

    if($pages_removed !== true) {
      if(gettype($pages_removed) === "string"){
        // PageMaker files still on system - abort uninstall
        $this->error($pages_removed);
        $event->replace = true; // prevent uninstall
        $this->session->redirect("./edit?name=$class"); 
      } else {
        // No pages on system, but some fields or templates remain - PageMaker has shown errors
      }
    } else {
      
      // Remove display_name field from user template
      $f_name = $this["f_display_name"];
      $rm_fld = wire("fields")->get($f_name);
      if($rm_fld !== null) {
        $ufg = wire("fieldgroups")->get("user");
        $ufg->remove($rm_fld);
        $ufg->save();
        wire("fields")->delete($rm_fld);
      }
    } 
  }
/**
 * Check for config naming collisions between common config elements
 *
 * @param Array $new_config New config array to check
 * @return Boolean - do common config entries differ?
 */
  public function configDiff($new_config) {

    $curr_config = $this->modules->getConfig($this->className);
     foreach ($new_config as $input_key => $input_val) {
      
      if(array_key_exists($input_key, $curr_config)){

          if($new_config[$config_key] !== $curr_config[$config_key]){
            return true;
          }
        }
    }
    return false;
  }  
/**
 * Adjust appearance of form field
 *
 * @param  HookEvent $event
 */
  public function customInputfieldFormRender($event) {

    //TODO: Make sure this is only affecting our Orders admin page
    // Get the object the event occurred on, if needed
    $InputfieldForm = $event->object;
    bd("Make sure this is only affecting the ProcessOrdersPages page");
    bd($InputfieldForm);

    // Add class suffix for css to remove top margin and set button colour according to status
    $return = $event->return;

    if (strpos($return, "active-form") !== false) {
      $class_suffix = "--pending";
    } else {
      $class_suffix = "--processed";
    }
    $event->return = str_replace(array("uk-margin-top", "ui-button"), array("", "ui-button ui-button" . $class_suffix), $return);
  }

  // Orders page
  public function ___execute() {
    // Live version
    if($this->input->post->submit) {
      
      $form = $this->modules->get("InputfieldForm");
      $form->processInput($this->input->post);

      if($form->getErrors()) {
        $out .= $form->render();
      } else {
        $operation = $this->sanitizer->text($this->input->post->submit);
        
        if($operation === "Processed") {
          $order_num = $this->sanitizer->text($this->input->post["pending-order"]);
          $this->progressOrder($order_num, "active");
        } else if ($operation === "Completed") {
          $order_num = $this->sanitizer->text($this->input->post["active-order"]);
          $this->progressOrder($order_num, "completed");
        }
      }
    }

    // Operations are "Processed" and "Completed"!!!
    $pending_orders = $this->getOrdersPage("pending")->children();
    $active_orders = $this->getOrdersPage("active")->children();
    $num_orders = 0;
    // Array to hold arrays of table rows
    $table_rows = array();

    $table = $this->modules->get("MarkupAdminDataTable");
    $table->setEncodeEntities(false);
    $table->headerRow(["Order Number", "Product", "Packs", "Total", "Customer", "Status"]);

    foreach ($pending_orders as $user_orders) {
      foreach ($this->getTableRows($user_orders, "pending") as $row_out) {
        $num_orders++;
        $table->row($row_out);
      }
    }
    foreach ($active_orders as $user_orders) {
      foreach ($this->getTableRows($user_orders, "active") as $row_out) {
        $num_orders++;
        $table->row($row_out);
      }
    }
    $out = $table->render();
    
    if($num_orders === 0) {
      $out .= "<p>There are no orders currently in the system</p>";
    }
    $out .= "<h3>Danger - do not click this button unless you are sure you want to delete all your orders</h3>
        <a href='./confirm' class='ui-button ui-state-default'>Remove data</a>";
    return $out;
  }
  public function ___executeConfirm() {
  return "<h3>Are you absolutely sure you want to delete your order data?</h3>
    <a href='./deleteorders' class='ui-button ui-state-default'>Yes, get on with it!</a>";
  }
  public function ___executeDeleteOrders() {

    // $order_root_id = $this["order_root_location"];
    // $order_root = $this->pages->get($order_root_id)->child("name=order-pages");

    $order_root = $this->pages->get($this["order_root"]);

    if($order_root->id) {

      $order_root->delete(true);
      return "<h3>Orders successfully removed</h3>
      <p>You can now uninstall the " . $this->className . "module</p>";

    } else {

      return "<h3>Something went wrong</h3>
      <p>The orders page could not be found</p>";

    }
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
      $form = $this->modules->get("InputfieldForm");
      $form->action = "./";
      $form->method = "post";

      // This attribute sets state of button - value is either "processed-form" or "completed-form"
      $form->attr("id+name","{$step}-form");

      $field = $this->modules->get("InputfieldHidden");
      $field->attr("id+name", "{$step}-order");
      $field->set("value", $order_number);
      $form->add($field);

      $button = $this->modules->get("InputfieldSubmit");
      $button->value = $step === "pending" ? "Processed" : "Completed";
      $form->add($button);

      $product_detail_lis = "";
      $quantity_lis = "";
      $total = 0;

      foreach ($order->children() as $line_item) {
        $product_sku = $line_item[$this["f_sku_ref"]];
        $sku_uc = strtoupper($product_sku);
        $product_page = $this->pages->findOne("sku={$product_sku}");
        $product_title = $product_page->title;
        $product_price = $product_page->price;
        $product_quantity = $line_item[$this["f_quantity"]];
        $product_detail_lis .=  "<li><span class='order-details__sku'>{$sku_uc}</span> {$product_title}</li>";
        $quantity_lis .= "<li class='order-details__qty'>{$product_quantity}</li>";
        $total += $product_price * $product_quantity;
      }
      $order_total = $this->renderPrice( $total);
      $curr_user = $this->users->getCurrentUser();
      $user_id = $line_item[$this["f_customer"]];
      $order_customer = $this->users->get($user_id);
      $customer_name_set = $order_customer[$this["f_display_name"]];
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
// ----- Cart --------------------------------------------------------------------------
/**
 * Add product to cart (creates a line-item page as child of /processwire/orders/cart-items)
 *
 * @param string $item The submitted form
 * @return string The configured field name
 */
  public function addToCart($item) {
    
    $sku = $this->sanitizer->text($item->sku);
    $new_quantity = $this->sanitizer->int($item->quantity);
    
    // Is there an existing order for this product?
    $f_customer = $this["f_customer"];
    $f_sku_ref = $this["f_sku_ref"];
    $user_id = $this->users->getCurrentUser()->id;
    $parent_selector = $this->getCartURL();
    $child_selector = "$f_customer=$user_id,$f_sku_ref=$sku";
    $exists_in_cart = $this->pages->get($parent_selector)->child($child_selector);

    if($exists_in_cart->id) {
      
      // Add to existing item
      $sum = $new_quantity + $exists_in_cart[$this["f_quantity"]];
      $exists_in_cart->of(false);
      $exists_in_cart->set($this["f_quantity"], $sum);
      $exists_in_cart->save();

    } else { 

      // Create a new item
      $item_title = $sku . ": " . $this->users->get($user_id)[$this["f_display_name"]];
      $item_data = array("title" => $item_title);
      $item_data[$this["f_customer"]] = $user_id;
      $item_data[$this["f_sku_ref"]] = $sku;
      $item_data[$this["f_quantity"]] = $new_quantity;

      $cart_item = $this->wire("pages")->add($this["t_line-item"],  $this->getCartURL(), $item_data);
    }
    return json_encode(array("success"=>true));
  }
/**
 * Process line items, creating new order in /processwire/orders/pending-orders/
 *
 * @return Json
 */
  public function placeOrder() {

    // Get the parent page for the new order
    $errors = array();

    $orders_parent = $this->getOrdersPage("pending", $this->users->getCurrentUser()->id);
    
    if($orders_parent) {

      $order_number = $this->getOrderNum();

      // Create the order
      $order_page = $this-> makePage($order_number, array("template" => "t_order", "parent"=>$orders_parent->path(), "title"=>$order_number));
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
    $order_selector = "template=" . $this["t_order"] . ",name={$order_num}";
    $admin_url = $this->config->url("admin");
    $order_pg = $this->pages->findOne($order_selector);
    
    if($order_pg->id){
      // Get the customer
      $customer = $order_pg->children()->first()[$this["f_customer"]];
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
    
    $admin_url = $this->config->url("admin");
    $parent_path = $this["order_root"] . "/{$order_step}-orders/";
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
      return $this->makePage($order_parent_name, array("template" => "t_user-orders", "parent"=>$parent_path, "title"=>$order_parent_name));
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
      return json_encode(array("success"=>true, "cart"=>$this->renderCart(true)));  
    }
    return json_encode(array("error"=>"The item could not be found"));
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
        $cart_item->set($this["f_quantity"], (int)$qtys);
        $cart_item->save();
        return json_encode(array("success"=>true, "cart"=>$this->renderCart(true)));  
    }
    return json_encode(array("error"=>"The item could not be found"));
  }
/**
 * Convert an integer representing GB pence to a GBP string 
 *
 * @param int $pence
 * @return string GBP value as a string with decimal point and prepended £
 */
  public function renderPrice($pence) {

    return "£" . number_format($pence/100, 2);
  }
/**
 * Generate HTML markup for current user's cart
 *
 * @param boolean $omitContainer - true if outer div not required (useful to avoid losing click handler)
 * @return string HTML markup
 */
  public function renderCart($omitContainer = false) {

    // Store field and template names in variables for markup
    $f_sku = $this["f_sku"];
    $f_sku_ref = $this["f_sku_ref"];
    $f_quantity = $this["f_quantity"];
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
    $f_customer = $this["f_customer"];
    $f_sku_ref = $this["f_sku_ref"];
    $admin_url = $this->config->url("admin");
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
    $admin_url = $this->config->url("admin");
    $t_line_item = $this["t_line-item"];
    $f_customer = $this["f_customer"];
    return $this->pages->findOne($this["order_root"] . ", include=all")->children("template={$t_line_item}, {$f_customer}={$user_id}, include=all");
  }
/**
 * Get the path of the cart
 *
 * @return string
 */
  public function getCartURL() {
    return $this["order_root"] . "/cart-items/";
  }
/**
 * Get order number then increment in db
 *
 * @return  string The unincremented order number
 */
  protected function getOrderNum() {

    $data = $this->modules->getConfig("ProcessOrderPages");
    $order_num = $this->sanitizer->text($data["order_num"]);
    $this_order_num = $order_num;
    $order_num++;
    $data["order_num"] = $this->sanitizer->text($order_num);
    $this->modules->saveConfig("ProcessOrderPages", $data);
    return $this_order_num;
  }
/**
 * Set order number
 *
 * @param string  $val The number to base new orders on
 * @return boolean
 */
  protected function setOrderNum($val) {

    $data = $this->modules->getConfig("ProcessOrderPages");
    $data["order_num"] = $val;
    return $this->modules->saveConfig("ProcessOrderPages", $data);
  }
/**
 * Increment order number
 *
 * @return string The new order number
 */
  protected function incrementOrderNum() {

    $data = $this->modules->getConfig("ProcessOrderPages");
    $order_num = $this->sanitizer->text($data["order_num"]);
    $order_num++;
    $data["order_num"] = $this->sanitizer->text($order_num);
    $this->modules->saveConfig("ProcessOrderPages", $data);
    return $data["order_num"];
  }
}