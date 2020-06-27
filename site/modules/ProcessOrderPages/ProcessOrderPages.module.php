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
      "installs" => ["OrderCart", "PageMaker"],
      "page" => [
        "name" => "orders",
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
    $page_maker = $this->modules->get("PageMaker");
    $configured = array_key_exists("configured", $data);

    if( ! $configured) {
     
      // Installing - fine to update config
      $data["configured"] = true; // Set flag
      $order_root_id = $data["order_root_location"];
      $order_root = $this->pages->get($order_root_id);

      if($order_root->id) {

        $order_root_path = $order_root->path();

      } else {
       
        $order_root_path = "/";
        $order_root = $this->pages->get("/");
      }
      
      // Assign field, template and family settings from config
      $pgs = array(
        "fields" => array(
          $data["f_customer"] => array("fieldtype"=>"FieldtypeText", "label"=>"Customer"),
          $data["f_sku_ref"] => array("fieldtype"=>"FieldtypeText", "label"=>"Record of cart item sku"),
          $data["f_quantity"] => array("fieldtype"=>"FieldtypeInteger", "label"=>"Number of packs")
        ),
        "templates" => array(
          $data["t_line_item"] => array("t_parents" => array($data["t_cart_item"], $data["t_order"]), "t_fields"=>array($data["f_customer"], $data["f_sku_ref"], $data["f_quantity"])),
          $data["t_cart_item"] => array("t_parents" => array($data["t_section"]), "t_children" => array($data["t_line_item"])),
          $data["t_order"] => array("t_parents" => array($data["t_user_orders"]), "t_children" => array($data["t_line_item"])),
          $data["t_user_orders"] => array("t_parents" => array($data["t_step"]), "t_children" => array($data["t_order"])),
          $data["t_step"] => array("t_parents" => array($data["t_section"]), "t_children" => array($data["t_user_orders"])),
          $data["t_section"] => array("t_children" => array($data["t_section"], $data["t_cart_item"]))
        ),
        "pages" => array(
          "order-pages" => array("template" => $data["t_section"], "parent"=>$order_root_path, "title"=>"Order Pages"),
          "cart-items" => array("template" => $data["t_cart_item"], "parent"=>"{$order_root_path}order-pages/", "title"=>"Cart Items"),
          "pending-orders" => array("template" => $data["t_step"], "parent"=>"{$order_root_path}order-pages/", "title"=>"Pending Orders", ),
          "active-orders" => array("template" => $data["t_step"], "parent"=>"{$order_root_path}order-pages/", "title"=>"Active Orders", ),
          "completed-orders" => array("template" => $data["t_step"], "parent"=>"{$order_root_path}order-pages/", "title"=>"Completed Orders", )
        )
      );

      $made_pages = $page_maker->makePages($pgs);
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
    $page_maker_config = $this->modules->getConfig("PageMaker"); 
    $order_system_pages = $page_maker_config["setup"]["pages"];

    // Check for live orders before uninstalling
    if($this->inUse($order_system_pages)) { 
      
      // There are active orders - abort uninstall
      $this->error("The module could not be uninstalled as live data exists. If you want to proceed, you can remove all order data from the Admin/Orders page and try again.");
      $event->replace = true; // prevent uninstall
      $this->session->redirect("./edit?name=$class"); 

    } else {

      // Safe to proceed - remove display_name field from user template
      $f_name = $this["f_display_name"];
      $rm_fld = wire("fields")->get($f_name);
      if($rm_fld !== null) {
        $ufg = wire("fieldgroups")->get("user");
        $ufg->remove($rm_fld);
        $ufg->save();
        wire("fields")->delete($rm_fld);
      }

      /*
      Remove the fields and templates of the five order system pages - the parent, "Order Pages", 
      and "Cart Items", "Pending Orders", "Active Orders" and "Completed Orders".
      Args are $recursive (remove children), $report_pg_errs false as pages as will already have been removed
      */
      $page_maker->removeOrderElements(true, false);

      parent::uninstall();
    } 
  }
/**
 * Check it's safe to delete provided pages
 *
 * @param array $ps Names of pages to check
 * @return boolean true if pages are safe to delete
 */
  protected function inUse($ps) {

    // Check for ongoing orders
    foreach ($ps as $pg => $spec) {
      $selector = 'name=' . $pg;
      $curr_p = $this->pages->findOne($selector);

      if($curr_p->id === 0){

        // Removed already - presumably via button on /admin/orders/
        return false; 
      }
      $curr_p->numChildren();
      // Exclude Order Pages as it's the parent page of the system and will always have children
      if($pg !== "order-pages" && $curr_p->numChildren()) {

        return true;
      }
    }
    return false;
  }
/**
 * Check for config naming collisions between common config elements
 *
 * @param Array $new_config New config array to check
 * @return Boolean false or the current config
 */
  protected function configDiffers($new_config) {

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

      $return = $event->return;

      if (strpos($return, "active-form") !== false) {
        $class_suffix = "--pending";
      } else {
        $class_suffix = "--processed";
      }
      
      // Add class suffix for css to remove top margin and set button colour according to status
      $event->return = str_replace(
        array("uk-margin-top", "ui-button ui-widget ui-state-default ui-corner-all"), 
        array("", "ui-button ui-button$class_suffix ui-widget ui-state-default ui-corner-all"), $return);
    }

  }

  // Orders page
  public function ___execute() {

    $cart = $this->modules->get("OrderCart");

    if($this->input->post->submit) {
      
      // Update order status
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
   
    $table_settings = array("pending", "active");
    return $this->getTable($table_settings);
  }
  public function ___executeCompleted() {

    $table_settings = array("completed");
    return $this->getTable($table_settings);
  }
  public function ___executeConfirm() {

    // Double check it's OK to delete order data
    return "<h4>Are you absolutely sure you want to delete your order data?</h4>
      <a href='./' class='ui-button ui-button--pop ui-button--cancel ui-state-default'>Cancel</a>
      <a href='./deleteorders' class='ui-button ui-state-default'>Yes, get on with it!</a>";
  }
  public function ___executeDeleteOrders() {

    // Delete order data
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
 * Make a table showing orders for the provided steps
 *
 * @param Array $steps Array of step names - "pending", "active" or "completed"
 * @return Table markup
 */ 
  protected function getTable($steps) {

    $cart = $this->modules->get("OrderCart");
    $num_orders = 0;

    $table = $this->modules->get("MarkupAdminDataTable");
    $table->setEncodeEntities(false); // Parse form HTML
    $table_rows = array();

    $orders = array();
    foreach ($steps as $key) {
      $orders[$key] = $cart->getOrdersPage($key)->children();
    }

    $live_orders = ! array_key_exists("completed", $orders);
    $header_row_settings = ["Order Number", "Product", "Packs", "Total", "Customer"];

    if($live_orders){
      $header_row_settings[] = "Status";
    }

    $table->headerRow($header_row_settings);

    foreach ($orders as $step => $page_array) {
      foreach ($page_array as $user_orders) {
        foreach ($this->getTableRows($user_orders, $step) as $row_out) {
          $num_orders++;
          $table->row($row_out);
        }
      }
    }
    $out = $table->render();

    if($num_orders === 0) {
      $out .= "<p>There are no orders currently in the system</p>";
    } else {
      $bttn_settings = $live_orders ? array("path" => "./completed", "text" => "Completed Orders") : array("path" => "./", "text" => "Live Orders"); 
      $out .= "<small class='buttons completed-bttn'><a href='" . $bttn_settings["path"] . "' class='ui-button ui-button--pop ui-state-default '>" . $bttn_settings["text"] . "</a></small><small class='buttons remove-bttn'><a href='./confirm' class='ui-button ui-button--pop ui-button--remove ui-state-default '>Remove all order data</a></small>";
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

    $cart = $this->modules->get("OrderCart");
    $table_rows = array();

    foreach ($user_orders as $order) {
      $order_number = $order->name;
      $form = $this->modules->get("InputfieldForm");
      $form->action = "./";
      $form->method = "post";

      // Form/button only on live orders
      if($step !== 'completed') {

        $form->attr("id+name","{$step}-form");

        $field = $this->modules->get("InputfieldHidden");
        $field->attr("id+name", "{$step}-order");
        $field->set("value", $order_number);
        $form->add($field);

        $button = $this->modules->get("InputfieldSubmit");
        $button->value = $step === "pending" ? "Processed" : "Completed";
        $form->add($button);
      
      }

      // Product details
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

      // Order details
      $order_total = $cart->renderPrice( $total);
      $user_id = $line_item[$this["f_customer"]];
      $order_customer = $this->users->get($user_id);
      $customer_name_set = $order_customer[$this["f_display_name"]];
      $customer_display_name = $customer_name_set ? $customer_name_set : $order_customer->name;
      
      // Table row
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