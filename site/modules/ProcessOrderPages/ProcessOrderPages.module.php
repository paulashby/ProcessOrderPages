<?php namespace ProcessWire;

class ProcessOrderPages extends Process {

  public static function getModuleinfo() {
    return [
      "title" => "Process Order Pages",
      "summary" => "Allows order pages to be created on front end and managed via admin.",
      "author" => "Paul Ashby, primitive.co",
      "version" => 1.1,
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

    $ajax_t = $this->templates->get("order-actions");
    if(! $ajax_t) return;
    $ajax_t->filename = wire("config")->paths->root . 'site/modules/ProcessOrderPages/order-actions.php';
    $ajax_t->save();

  }
/**
 * Get names of immutable config entries 
 * - those that can't be changed after installation
 *
 * @return Array [$string Name of immutable entry]
 */
  protected function getImmutable() {

      return array(
        "order_root_location",
        "prfx"
      );
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

      // Make template to handle ajax calls
      $ajax_t = new Template();
      $ajax_t->name = "order-actions";
      $ajax_t->fieldgroup = $this->templates->get("basic-page")->fieldgroup;
      $ajax_t->compile = 0;
      $ajax_t->noPrependTemplateFile = true;
      $ajax_t->noAppendTemplateFile = true; 
      $ajax_t->save();

      $prfx = $data["prfx"];

      // Create array of required pages containing three associative arrays whose member keys are taken from module config. These hold field, template and family settings from config
      $pgs = array(
        "fields" => array(
          "{$prfx}_customer" => array("fieldtype"=>"FieldtypeText", "label"=>"Customer"),
          "{$prfx}_sku_ref" => array("fieldtype"=>"FieldtypeText", "label"=>"Record of cart item sku"),
          "{$prfx}_quantity" => array("fieldtype"=>"FieldtypeInteger", "label"=>"Number of packs"),
          "{$prfx}_purchase_price" => array("fieldtype"=>"FieldtypeInteger", "label"=>"Price when ordered")
        ),
        "templates" => array(
          "{$prfx}-line-item" => array("t_parents" => array("{$prfx}-cart-item", "{$prfx}-order"), "t_fields"=>array("{$prfx}_customer", "{$prfx}_sku_ref", "{$prfx}_quantity","{$prfx}_purchase_price")),
          "{$prfx}-cart-item" => array("t_parents" => array("{$prfx}-section"), "t_children" => array("{$prfx}-line-item")),
          "{$prfx}-order" => array("t_parents" => array("{$prfx}-user-orders"), "t_children" => array("{$prfx}-line-item")),
          "{$prfx}-user-orders" => array("t_parents" => array("{$prfx}-step"), "t_children" => array("{$prfx}-order")),
          "{$prfx}-step" => array("t_parents" => array("{$prfx}-section"), "t_children" => array("{$prfx}-user-orders")),
          "{$prfx}-section" => array("t_children" => array("{$prfx}-section", "{$prfx}-cart-item"))
        ),
        "pages" => array(
          "order-pages" => array("template" => "{$prfx}-section", "parent"=>$order_root_path, "title"=>"Order Pages"),
          "cart-items" => array("template" => "{$prfx}-cart-item", "parent"=>"{$order_root_path}order-pages/", "title"=>"Cart Items"),
          "pending-orders" => array("template" => "{$prfx}-step", "parent"=>"{$order_root_path}order-pages/", "title"=>"Pending Orders"),
          "active-orders" => array("template" => "{$prfx}-step", "parent"=>"{$order_root_path}order-pages/", "title"=>"Active Orders"),
          "completed-orders" => array("template" => "{$prfx}-step", "parent"=>"{$order_root_path}order-pages/", "title"=>"Completed Orders"),
          "order-actions" => array("template" => "order-actions", "parent"=>"{$order_root_path}order-pages/", "title"=>"Order Actions")
        )
      );
      
      // t_access is a comma-separated list of roles with view access to order pages
      $t_access = $data["t_access"];
      if(gettype($t_access) === "string" && strlen($t_access)) {
        $access_roles_array = explode(",", $t_access);
        $t_access = array("view"=>$access_roles_array);
        $t_name = "{$prfx}-section";
        $pgs["templates"][$t_name]["t_access"] = $t_access;
      }

      $made_pages = $page_maker->makePages("process_order_pages", $pgs, true, true);

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
        $f_name = "{$prfx}_display_name";
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

    } else {

      $curr_config = $this->modules->getConfig($this->className);

      if ($this->approveConfig($curr_config, $data)) {
        // Accept changes
        $event->arguments(1, $data);

      } else {

        // Reject changes. Show error for attempted changes to immutable items. Resubmit existing data.
        $this->session->error("Only 'Order admin email', 'Next order' and 'Name of sku field' can be changed after installation. If you really need to rename fields and templates, you can reinstall the module, but be aware that this will mean losing the order data currently in the system");

        $event->arguments(1, $curr_config);
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
    $order_system_pages = $page_maker_config["page_sets"]["process_order_pages"]["setup"]["pages"];

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
      $page_maker->removeSet("process_order_pages", false);

      // Remove the ajax template that was installed by init()
      $ajax_t = $this->templates->get("order-actions");
      if($ajax_t) {
        wire('templates')->delete($ajax_t);
      }

      parent::uninstall();
    } 
  }
/**
 * Get price from product page that may set price via a page reference field
 * to allow centrally managed tiered pricing
 *
 * @param Page $product 
 * @return Integer price
 */ 
  public function getPrice($product) {

    $tiered_pricing = $this["f_price"];

    if(empty($tiered_pricing)) {
      // Just using direct reference to price field on product page
      return $product->price;
    }
    // Using a page reference field to allow centrally managed tiered pricing
    return $product[$tiered_pricing]->price;
  }
/**
 * Check it's safe to delete provided pages
 *
 * @param array $ps Names of pages to check
 * @return boolean true if pages are in use
 */
  protected function inUse($ps) {

    // Check for ongoing orders
    foreach ($ps as $pg => $spec) {
      $selector = 'name=' . $pg;
      $curr_p = $this->pages->findOne($selector);

      // Removed already - presumably via button on /admin/orders/
      $missing = $curr_p->id === 0;
      // Exclude Order Pages as it's the parent page of the system and will always have children
      if( ! $missing && $pg !== "order-pages" && $curr_p->numChildren()) {

        return true;
      }
    }
    return false;
  }
/**
 * Check for changes to immutable array items
 *
 * @param Array $new_config New config array to check
 * @return Boolean false or the current config
 */
  protected function approveConfig ($curr_config, $new_config) {

   $immutable = $this->getImmutable();

    foreach ($new_config as $key => $val) {

      if( array_key_exists($key, $curr_config) &&
          $new_config[$key] !== $curr_config[$key] &&
          in_array($key, $immutable)){
        return false;
      }
    }
    return true;
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
    return "<h4>WARNING: This will remove the entire order system. Are you sure you want to delete your order data?</h4>
      <a href='./' class='ui-button ui-button--pop ui-button--cancel ui-state-default'>Cancel</a>
      <a href='./deleteorders' class='ui-button ui-state-default'>Yes, get on with it!</a>";
  }
  public function ___executeDeleteOrders() {

    // Delete order data
    $order_root = $this->pages->get($this["order_root"]);

    if($order_root->id) {

      $order_root->delete(true);
      return "<h3>Orders successfully removed</h3>
      <p>You can now uninstall the " . $this->className . " module</p>";

    } else {

      return "<h3>Something went wrong</h3>
      <p>The orders page could not be found</p>";

    }
  }
/**
 * Check for completed orders
 *
* @return Boolean
*/ 
  protected function completedExist() {

    $cart = $this->modules->get("OrderCart");

    foreach ($cart->getOrdersPage("completed")->children() as $user_orders) {

      if(count($user_orders)) {
        return true;
      }
    }
    return false;
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

    $live_orders_pg = ! array_key_exists("completed", $orders);
    $header_row_settings = ["Order Number", "Product", "Packs", "Total", "Customer"];

    if($live_orders_pg){
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
    $section_link = 1;

    if($live_orders_pg) {

      // Include link only if there are completed orders
      if($this->completedExist()){

        $out .= "<small class='buttons completed-bttn'><a href='./completed' class='ui-button ui-button--pop ui-state-default '>Completed Orders</a></small>";
        $num_orders++; // Need to include Remove all button if there are only completed orders
      }

    } else {

      // We're on Completed Orders page - always include link to live orders page
      $out .= "<small class='buttons completed-bttn'><a href='./' class='ui-button ui-button--pop ui-state-default '>Live Orders</a></small>";     
    }
    if($num_orders) {

      $out .= "<small class='buttons remove-bttn'><a href='./confirm' class='ui-button ui-button--pop ui-button--remove ui-state-default '>Remove all order data</a></small>";

    } else {

      $out .= "<p>There are no live orders currently in the system</p>";
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
      $prfx = $this["prfx"];

      foreach ($order->children() as $line_item) {
        $product_sku = $line_item["{$prfx}_sku_ref"];
        $sku_uc = strtoupper($product_sku);
        $product_page = $this->pages->findOne("sku={$product_sku}");
        $product_title = $product_page->title;
        $product_price = $line_item["{$prfx}_purchase_price"];//This is an empty string
        $product_quantity = $line_item["{$prfx}_quantity"];
        $product_detail_lis .=  "<li><span class='order-details__sku'>{$sku_uc}</span> {$product_title}</li>";
        $quantity_lis .= "<li class='order-details__qty'>{$product_quantity}</li>";
        $total += $product_price * $product_quantity;
      }

      // Order details
      $order_total = $cart->renderPrice( $total);
      $user_id = $line_item["{$prfx}_customer"];
      $order_customer = $this->users->get($user_id);
      $customer_name_set = $order_customer["{$prfx}_display_name"];
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