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
      "installs" => [
        "PageMaker>=0.0.1",
        "OrderCart>=0.0.1"
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
    $page_path = $this->page->path();
    if($class !== $this->className || $page_path !== "/processwire/module/") return;
    
    // Config input
    $data = $event->arguments(1);
    $modules = $event->object;
    $page_maker = $modules->get("PageMaker");
    $configured = array_key_exists("configured", $data);

    if( ! $configured) {
     
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
    } else{

      // false or the current config returned
      $revertConfig = $this->configDiffers($data);

      if($revertConfig !== false) {
            
        // We don't want to change anything once installed, so show warning and submit existing data if anything has been changed
        $this->session->error("Names cannot be changed after installation. If you really need to rename, you can reinstall the module, but be aware that this will mean losing the order data currently in the system");

        // Revert to previous values
        $event->arguments(1, $revertConfig);
      }
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
      parent::uninstall();
    } 
  }
/**
 * Check for config naming collisions between common config elements
 *
 * @param Array $new_config New config array to check
 * @return Boolean false or the current config
 */
  public function configDiffers($new_config) {

    // Get the whole config as we've added more settings than just the user-submitted names
    $curr_config = $this->modules->getConfig($this->className);

    foreach ($new_config as $key => $val) {

      if(array_key_exists($key, $curr_config)){

          if($new_config[$key] !== $curr_config[$key]){
            return $curr_config;
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

    if($this->page->path === '/processwire/orders/'){
      
      // Add class suffix for css to remove top margin and set button colour according to status
      $return = $event->return;

      if (strpos($return, "active-form") !== false) {
        $class_suffix = "--pending";
      } else {
        $class_suffix = "--processed";
      }
      $event->return = str_replace(array("uk-margin-top", "ui-button"), array("", "ui-button ui-button" . $class_suffix), $return);
      }

  }

  // Orders page
  public function ___execute() {

    $cart = $this->modules->get("OrderCart");

    if($this->input->post->submit) {
      
      $form = $this->modules->get("InputfieldForm");
      $form->processInput($this->input->post);

      if($form->getErrors()) {
        $out .= $form->render();
      } else {
        $operation = $this->sanitizer->text($this->input->post->submit);
        
        if($operation === "Processed") {
          $order_num = $this->sanitizer->text($this->input->post["pending-order"]);
          $cart->progressOrder($order_num, "active");
        } else if ($operation === "Completed") {
          $order_num = $this->sanitizer->text($this->input->post["active-order"]);
          $cart->progressOrder($order_num, "completed");
        }
      }
    }

    // Operations are "Processed" and "Completed"!!!
    $pending_orders = $cart->getOrdersPage("pending")->children();
    $active_orders = $cart->getOrdersPage("active")->children();
    $num_orders = 0;
    // Array to hold arrays of table rows
    $table_rows = array();

    $table = $this->modules->get("MarkupAdminDataTable");
    $table->setEncodeEntities(false);
    $table->headerRow(["Order Number", "Product", "Packs", "Total", "Customer", "Status"]);

    foreach ($pending_orders as $user_orders) {
      foreach ($this->getTableRows($user_orders, "pending", $cart) as $row_out) {
        $num_orders++;
        $table->row($row_out);
      }
    }
    foreach ($active_orders as $user_orders) {
      foreach ($this->getTableRows($user_orders, "active", $cart) as $row_out) {
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
  protected function getTableRows($user_orders, $step, $cart) {

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
      $order_total = $cart->renderPrice( $total);
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
}