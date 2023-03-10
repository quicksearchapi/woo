<?php
/*
Plugin Name: SearchAPI for Woocommerce
Plugin URI: https://quicksearchapi.com
Description: With QuickSearchAPI, you'll enjoy lightning-fast search speeds and highly relevant search results, ensuring that your customers can easily find the products they need. Plus, our plugin is fully customizable, allowing you to tailor the search experience to your specific business needs.
Author: Andrzej Bernat
Version: 1.0.0
Author URI: https://quicksearchapi.com
 */

add_action("admin_menu", "searchapi_plugin_setup_menu");

if (get_option("searchapi_website_uuid") && get_option("searchapi_website_secret_key")) {
    add_action('woocommerce_before_main_content', 'search_input', 10);
}

/**
 *
 * @return void
 */
function search_input()
{
    if (get_option("searchapi_website_uuid")) {
        echo '
        <div id="searchapi-wrapper">
        <input type="text" id="searchapi-input" autocomplete="off" class="" placeholder="Search..." />
        <div id="searchapi-results" style="display: none;"></div>
          <script>
            var searchapiApiKey = "' . get_option("searchapi_website_uuid") . '";
            var d = document,
              b = d.getElementsByTagName("body")[0],
              searchApiConfig = {
                "containers": {
                  inputId: "searchapi-input",
                  resultsId: "searchapi-results",
                },
                "dictonary": {
                  "currency": "$",
                  "price_starts_from": "From",
                  "recent_queries": "Recently searched by me",
                  "most_popular_queries": "Popular searches",
                  "most_popular_products": "Popular products",
                  "filter_by_category": "Filter by category",
                  "filter_by_category_reset": "All",
                  "filter_by_price": "Filter by price",
                  "filter_by_price_from": "from",
                  "filter_by_price_to": "to",
                  "no_results": "Nothing found for the given conditions",
                  "products_found": "Matching products"                  
                }
              },
              l = d.createElement("link");
              s = d.createElement("script");
              s.type = "text/javascript";
              s.async = true;
              s.src = "https://quicksearchapi.com/searchapi.min.js";
              b.appendChild(s);
              l.setAttribute("rel", "stylesheet");
              l.setAttribute("href", "https://quicksearchapi.com/searchapi.min.css");
              b.appendChild(l);
          </script>
        </div>
        ';
    }
}

/**
 * Fetches and indexes given products into QuickSearchAPI.com engine
 *
 * @return array
 */
function searchapi_fetch_products_and_index()
{

    $products = wc_get_products(array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'numberposts' => -1,
    ));

    $index_log = [];
    $products_indexed_counter = 1;
    foreach ($products as $product) {
        $product = [
            "title" => $product->get_name(),
            "description" => $product->get_description(),
            "path" => str_replace(home_url(), '', $product->get_permalink()),
            "image" => wp_get_attachment_url($product->get_image_id()),
            "tags" => implode(",", wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'))),
            "price" => $product->get_price(),
            "category" => trim(explode(',', $product->get_categories( ',', ' ' . _n( ' ', '  ', $cat_count, 'woocommerce' ) . ' ', ' ' ))[0])
        ];

        $response = api_add_page($product);

        if ($response["code"] == "ok") {
            $index_log[] = "[OK] Product <b>{$product['title']}</b> at the address {$product['path']} has been indexed.";
            $products_indexed_counter++;
        } else {
            $index_log[] = "[ERR] During the indexing of product {$product['title']} errors occurred.";
        }
    }

    return [
        'index_log' => $index_log,
        'products_indexed_counter' => $products_indexed_counter - 1,
    ];
}

/**
 * Displays do-index confirmation screen
 * @type Action
 *
 * @return void
 */
function searchapi_confirm_index_products_action()
{
    $index_updated_at = get_option("searchapi_index_products_action_updated_at");

    if ($index_updated_at) {
        $msg = "The last product index update took place {$index_updated_at}.";
    } else {
        $msg = "The search engine index has never been updated before.";
    }

    echo '
    <div class="wrap">
        <h1 class="wp-heading-inline">E-commerce search tool QuickSearchAPI.com</h1>
        <hr class="wp-header-end">
        <div class="notice notice-info">
            <p><strong>Index products from the catalog</strong></p>
            <p>In order for your products to appear in search results, you must index them. If you have a lot of products, this may take some time. After indexing, the products should appear within a few hours, but usually it is faster.' . $msg . '.</p>
            <p class="submit" style="margin-top:0px; padding-top:0px;">
                <form action="/wp-admin/admin.php?page=searchapi&action=do_index" method="POST">
                    <button type="submit" class="button-primary">Update products</button>&nbsp;&nbsp;&nbsp;&nbsp;
                    <a href="/wp-admin/admin.php?page=searchapi&action=flush_index" class="button-secondary">Regenerate index</a>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <a href="/wp-admin/admin.php?page=searchapi&action=create_account" class="button-secondary">API keys</a>
                </form>
            </p>
            <p>If you have added new products or updated existing ones, use the "Update Products" option. If, on the other hand, you have removed some products from your offer, use the "Regenerate Index" option. The difference between these two options is that the first one allows for updating the index without removing it, so that the products are still available for the search engine. The second option involves completely removing and recreating the index, as a result, the products will only be available after re-indexing by QuickSearchAPI.com, which occurs every few hours.</p>
        </div>
    </div>
    ';
}

/**
 * Flushes the index and re-indexes the products
 * @type Action
 *
 * @return void
 */
function searchapi_flush_products_action()
{
    $response = api_website_flush();
    if ($response["code"] == "ok") {
        header("Location: /wp-admin/admin.php?page=searchapi&action=do_index");
    } else {
        $label = "Errors occurred";
        $noticeBox = "<div class='notice notice-error'>
            <p>{$response["message"]}</p>
        </div>";
        echo "
        <div class='wrap'>
            <h1 class='wp-heading-inline'>{$label}</h1>
            <hr class='wp-header-end'>
            {$noticeBox}
			<p class='submit'>
				<a href='/wp-admin/admin.php?page=searchapi' class='button-primary'>Back</a>
			</p>
        </div>
    ";
    }
}

/**
 * Displays after-index page
 * @type Action
 *
 * @return void
 */
function searchapi_index_products_action()
{
    update_option("searchapi_index_products_action_updated_at", date('Y-m-d H:i'));
    $result = searchapi_fetch_products_and_index();

    $index_log = implode("<br/>", $result['index_log']);
    $counter = $result['products_indexed_counter'];

    echo "
    <div class='wrap'>
        <h1 class='wp-heading-inline'>Products ({$counter}) have been indexed.</h1>
        <hr class='wp-header-end'>
        <div class='notice notice-info'>
            <p>Your products will appear in the search engine in a few hours. If you would like the process to be faster, you can write to us at andrzej@itma.pl, call +48 530 861 858 or use chat online. We will be happy to help you.</p>
            <div style='width:100%; height:200px; overflow-y: scroll; margin-top:30px;'>
                {$index_log}
            </div>
            <p class='submit'>
                <a href='/wp-admin/admin.php?page=searchapi' class='button-primary'>Back</a>
            </p>
        </div>
    </div>
    ";
}

/**
 * Shows new account form to join Searchapi.pl
 * @type Action
 *
 * @return void
 */
function searchapi_new_account_action()
{
    echo '
        <div class="wrap">
            <h1 class="wp-heading-inline">QuickSearchAPI.com Product Search Engine.</h1>
            <hr class="wp-header-end">
            <div class="notice notice-info">
                <p><strong>Complete the installation.</strong></p>
                <p>To allow your customers to use the auto-suggest feature in the search, you need to index your store\'s offerings. The indexing will be possible after the installation is completed. By clicking the `Finish Installation` button, a store account will be created in the QuickSearchAPI.com service, where the store\'s offerings will be processed so that your customers can easily find products in the search. The account is free.</p>
                <p class="submit">
                    <form action="/wp-admin/admin.php?page=searchapi&action=create_account" method="POST">
                    <input type="text" name="searchapi_account_email" placeholder="Adres e-mail administratora" />
                        ' . (isset($_GET["err"]) && $_GET["err"] == 1 ? '<div style="padding: 4px 0 0px 8px; color: #ff0000;">Podany email nie jest poprawny</div><br/>' : "") . '
                        <button type="submit" class="button-primary">Zapisz i doko??cz instalacj??</button>
                        </form>
                    </p>
            </div>
        </div>
    ';
}

/**
 * Perform the api call and deletes the account, after that shows the confirmation screen.
 * @type Action
 *
 * @return void
 */
function searchapi_delete_account_action()
{
    $response = api_delete_account();
    if ($response["code"] == "ok") {

        update_option("searchapi_account_uuid", false);
        update_option("searchapi_account_secret_key", false);
        update_option("searchapi_website_uuid", false);
        update_option("searchapi_website_secret_key", false);

        $label = "Account has been deleted.";
        $noticeBox = "<div class='notice notice-success'>
            <p>Your account and all its data have been deleted from the QuickSearchAPI.com service. You can set up a new account with any email address and start using it again in this store.</p>
        </div>";

    } else {
        $label = "Errors occurred.";
        $noticeBox = "<div class='notice notice-error'>
            <p>{$response["message"]}</p>
        </div>";
    }

    echo "
        <div class='wrap'>
            <h1 class='wp-heading-inline'>{$label}</h1>
            <hr class='wp-header-end'>
            {$noticeBox}
			<p class='submit'>
				<a href='/wp-admin/admin.php?page=searchapi' class='button-primary'>Powr??t</a>
			</p>
        </div>
    ";
}

/**
 * Perform the api call and creates new account, after that shows the confirmation screen.
 * @type Action
 *
 * @return void
 */
function searchapi_create_account_action()
{
    if (get_option("searchapi_account_uuid") == false) {
        if (filter_var($_POST["searchapi_account_email"], FILTER_VALIDATE_EMAIL)) {
            $response = api_create_account($_POST);
            if ($response["code"] == "ok") {
                $label = "A new account has been created.";

                update_option("searchapi_account_uuid", $response["data"]["X-Auth-Key"]);
                update_option("searchapi_account_secret_key", $response["data"]["X-Auth-Secret"]);

                $response = api_create_website($response);
                if ($response["code"] == "ok") {

                    update_option("searchapi_website_uuid", $response["data"]["X-Auth-Key"]);
                    update_option("searchapi_website_secret_key", $response["data"]["X-Auth-Secret"]);

                    $label = "A new website has been created";
                    $noticeBox = "<div class='notice notice-success'>
                        <p>Your new account has been successfully created. You will only need the following information if you want to make advanced changes to the plugin's communication with the QuickSearchAPI.com service. If you do not plan to make these changes, there is no need to save this information.</p>
                    </div>";

                } else {
                    $label = "Errors occurred.";
                    $noticeBox = "<div class='notice notice-error'>
                        <p>{$response["message"]}</p>
                    </div>";
                }
            } else {
                $label = "Errors occurred.";
                $noticeBox = "<div class='notice notice-error'>
                    <p>{$response["message"]}</p>
                </div>";
            }
        } else {
            header("Location: /wp-admin/admin.php?page=searchapi&err=1");
        }
    } else {
        $label = "Account details";
    }

    $account_uuid = get_option("searchapi_account_uuid");
    $account_secret = get_option("searchapi_account_secret_key");
    $website_uuid = get_option("searchapi_website_uuid");
    $website_secret = get_option("searchapi_website_secret_key");

    echo "
        <div class='wrap'>
            <h1 class='wp-heading-inline'>{$label}</h1>
            <hr class='wp-header-end'>
            {$noticeBox}

			<div class='notice notice-info'>
			    <h3>Account`s API keys at QuickSearchAPI.com</h3>
                <p>If you want to add a new store under one account on your own, you can use the store's API keys. This is a more advanced method and is intended for more experienced users. You can find more information <a href='https://quicksearchapi.com' target='_blank'>in the documentation</a>.</p>
			    <p>
                    <strong>Account ID:</strong>
                    {$account_uuid}
                </p>
			    <p>
                    <strong>Secret Key:</strong>
                    {$account_secret}
                </p>
			</div>

			<div class='notice notice-info'>
			    <h3>Shop`s API keys at QuickSearchAPI.com</h3>
                <p>If you want to update your store's offerings independently, you can use API keys. This is a more advanced method and is aimed at more experienced users. You can find more information <a href='https://quicksearchapi.com' target='_blank'>in the documentation</a>.</p>
			    <p>
                    <strong>Shop ID:</strong>
                    {$website_uuid}
                </p>
			    <p>
                    <strong>Secret Key:</strong>
                    {$website_secret}
                </p>
			</div>
			<p class='submit'>
				<a href='/wp-admin/admin.php?page=searchapi' class='button-primary'>Back</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <a href='/wp-admin/admin.php?page=searchapi&action=delete_account' class='button-secondary' onclick='return confirm(\"By deleting your account on QuickSearchAPI.com, you will remove all your products from the index, making it impossible to search for them. This operation is final and cannot be undone. Are you sure you want to continue?\");'>Remove this account</a>
			</p>
        </div>
    ";
}

/**
 * Calls Searchapi. API endpoint
 * @type API
 *
 * @return void
 */
function api_delete_account()
{
    $ch = curl_init("https://quicksearchapi.com/api/account/delete");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Auth-Key: " . get_option("searchapi_account_uuid"),
        "X-Auth-Secret: " . get_option("searchapi_account_secret_key"),
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $json = curl_exec($ch);
    curl_close($ch);
    return json_decode($json, true);
}

/**
 * Calls Searchapi. API endpoint
 * @type API
 *
 * @return void
 */
function api_create_account($input)
{
    $ch = curl_init("https://quicksearchapi.com/api/account/create");
    $payload = json_encode([
        "email" => $input["searchapi_account_email"],
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type:application/json",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $json = curl_exec($ch);
    curl_close($ch);
    return json_decode($json, true);
}

/**
 * Calls Searchapi. API endpoint
 * @type API
 *
 * @return void
 */
function api_create_website($input)
{
    $ch = curl_init("https://quicksearchapi.com/api/website/create");
    $payload = json_encode([
        "host" => $_SERVER['SERVER_NAME'],
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Auth-Key: " . $input["data"]["X-Auth-Key"],
        "X-Auth-Secret: " . $input["data"]["X-Auth-Secret"],
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $json = curl_exec($ch);
    curl_close($ch);
    return json_decode($json, true);
}

/**
 * Calls Searchapi. API endpoint
 * @type API
 *
 * @return void
 */
function api_add_page(array $data)
{
    $ch = curl_init("https://quicksearchapi.com/api/page/add");

    $payload = json_encode($data);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Auth-Key: " . get_option("searchapi_website_uuid"),
        "X-Auth-Secret: " . get_option("searchapi_website_secret_key"),
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $json = curl_exec($ch);
    curl_close($ch);
    return json_decode($json, true);
}

/**
 * Calls Searchapi. API endpoint
 * @type API
 *
 * @return void
 */
function api_website_flush()
{
    $ch = curl_init("https://quicksearchapi.com/api/website/flush");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Auth-Key: " . get_option("searchapi_website_uuid"),
        "X-Auth-Secret: " . get_option("searchapi_website_secret_key"),
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    $json = curl_exec($ch);
    curl_close($ch);
    return json_decode($json, true);
}

/**
 * Action router
 */

switch ($_GET["action"]) {
    case 'create_account':
        $function = "searchapi_create_account_action";
        break;
    case 'delete_account':
        $function = "searchapi_delete_account_action";
        break;
    case 'do_index':
        $function = "searchapi_index_products_action";
        break;
    case 'flush_index':
        $function = "searchapi_flush_products_action";
        break;
    default:
        $function = null;
}

if (is_null($function)) {
    if (get_option("searchapi_account_uuid") == false) {
        $function = "searchapi_new_account_action";
    } else {
        $function = "searchapi_confirm_index_products_action";
    }
}

/**
 * Admin menu hook
 *
 * @return void
 */
function searchapi_plugin_setup_menu()
{
    global $function;
    add_menu_page(
        "Search engine",
        "QuickSearch",
        "manage_options",
        "searchapi",
        $function
    );
}
