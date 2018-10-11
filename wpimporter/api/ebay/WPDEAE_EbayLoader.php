<?php

/**
 * Description of WPDEAE_EbayLoader
 *
 * @author Geometrix
 */
include_once(dirname(__FILE__) . '/WPDEAE_EbaySession.php');
include_once(dirname(__FILE__) . '/WPDEAE_EbayAccount.php');

if (!class_exists('WPDEAE_EbayLoader')):

    class WPDEAE_EbayLoader {

        public $account;
        
		public function __construct() {
            $this->account = new WPDEAE_EbayAccount();
        }
        
        public function load_list($filter, $page = 1) {
            $per_page = get_option('wpdeae_ebay_per_page', 20);
            $result = array("total" => 0, "per_page" => $per_page, "items" => array(), "error" => "");

            if ((isset($filter['wpdeae_productId']) && !empty($filter['wpdeae_productId'])) || ( isset($filter['wpdeae_query']) && !empty($filter['wpdeae_query']) ) || ( isset($filter['store']) && !empty($filter['store']) ) || (isset($filter['category_id']) && $filter['category_id'] != 0)) {
                $endpoint = 'http://svcs.ebay.com/services/search/FindingService/v1';
                $responseEncoding = 'XML';

                $productId = isset($filter['wpdeae_productId']) ? $filter['wpdeae_productId'] : "";

                $safeQuery = isset($filter['wpdeae_query']) ? urlencode(utf8_encode($filter['wpdeae_query'])) : "";
                $site = isset($filter['sitecode']) ? $filter['sitecode'] : "EBAY-US";

                $price_min = (isset($filter['wpdeae_min_price']) && floatval($filter['wpdeae_min_price']) > 0.009) ? floatval($filter['wpdeae_min_price']) : 0;
                $price_max = (isset($filter['wpdeae_max_price']) && floatval($filter['wpdeae_max_price']) > 0.009) ? floatval($filter['wpdeae_max_price']) : 0;

                $feedback_min = (isset($filter['min_feedback']) && intval($filter['min_feedback']) > 0) ? intval($filter['min_feedback']) : 0;
                $feedback_max = (isset($filter['max_feedback']) && intval($filter['max_feedback']) > 0) ? intval($filter['max_feedback']) : 0;
                if ($feedback_max < $feedback_min) {
                    $feedback_max = 0;
                }

                $available_to = (isset($filter['available_to']) && $filter['available_to']) ? $filter['available_to'] : "";

                $condition = (isset($filter['condition']) && $filter['condition']) ? $filter['condition'] : "";
                
                $free_shipping_only = (isset($filter['free_shipping_only']) && $filter['free_shipping_only']);

                //$shipment_price_min = (isset($filter['shipment_min_price']) && floatval($filter['shipment_min_price']) > 0.009) ? floatval($filter['shipment_min_price']) : 0;
                //$shipment_price_max = (isset($filter['shipment_max_price']) && floatval($filter['shipment_max_price']) > 0.009) ? floatval($filter['shipment_max_price']) : 0;

                $category_id = (isset($filter['category_id']) && IntVal($filter['category_id'])) ? IntVal($filter['category_id']) : 0;
                $link_category_id = (isset($filter['link_category_id']) && IntVal($filter['link_category_id'])) ? IntVal($filter['link_category_id']) : 0;

                $store_name = isset($filter['store']) ? $filter['store'] : ""; //urlencode(utf8_encode($filter['store'])) : "";            

                $listing_type = isset($filter['listing_type']) && is_array($filter['listing_type']) ? $filter['listing_type'] : array();

                $pagenum = (intval($page)) ? $page : 1;

                if ($productId) {
                    $tmp_res = $this->load_detail(new WPDEAE_Goods("ebay#$productId"), array("init_load" => true, "link_category_id" => $link_category_id, "site_code" => $site));
                    if ($tmp_res['state'] == 'ok') {
                        $result["total"] = 1;
                        $result["items"] = array($tmp_res['goods']);
                    } else {
                        $result["error"] = $tmp_res['message'];
                    }
                } else {
                    $apicall = "$endpoint?OPERATION-NAME=".($store_name?"findItemsIneBayStores":"findItemsAdvanced")
                            . "&SERVICE-VERSION=1.12.0"
                            . "&GLOBAL-ID=$site"
                            . "&SECURITY-APPNAME=" . $this->account->appID
                            . "&RESPONSE-DATA-FORMAT=$responseEncoding"
                            . ($safeQuery ? "&keywords=" . $safeQuery : "")
                            . ($store_name ? "&storeName=" . $store_name : "")
                            . "&paginationInput.entriesPerPage=$per_page"
                            . "&paginationInput.pageNumber=$pagenum"
                            . "&sortOrder=BestMatch"
                            . "&outputSelector(0)=SellerInfo"
                            . "&outputSelector(1)=StoreInfo"
                            . "&descriptionSearch(1)=true"
                            . ($category_id ? "&categoryId=" . $category_id : "");


                    if ($this->account->use_affiliate_urls()) {
                        if (get_option('wpdeae_ebay_custom_id')) {
                            $apicall.="&affiliate.customId=" . get_option('wpdeae_ebay_custom_id');
                        }
                        if (get_option('wpdeae_ebay_geo_targeting', false)) {
                            $apicall.="&affiliate.geoTargeting=true";
                        }
                        if (get_option('wpdeae_ebay_network_id')) {
                            $apicall.="&affiliate.networkId=" . get_option('wpdeae_ebay_network_id');
                        }
                        if (get_option('wpdeae_ebay_tracking_id')) {
                            $apicall.="&affiliate.trackingId=" . get_option('wpdeae_ebay_tracking_id');
                        }
                    }

                    $filter_index = 0;

                    $apicall.="&itemFilter($filter_index).name=HideDuplicateItems&itemFilter($filter_index).value=true";
                    $filter_index++;

                    if ($feedback_min) {
                        $apicall.="&itemFilter($filter_index).name=FeedbackScoreMin&itemFilter($filter_index).value=$feedback_min";
                        $filter_index++;
                    }
                    if ($feedback_max) {
                        $apicall.="&itemFilter($filter_index).name=FeedbackScoreMax&itemFilter($filter_index).value=$feedback_max";
                        $filter_index++;
                    }
                    if ($price_min) {
                        $apicall.="&itemFilter($filter_index).name=MinPrice&itemFilter($filter_index).value=$price_min";
                        $filter_index++;
                    }
                    if ($price_max) {
                        $apicall.="&itemFilter($filter_index).name=MaxPrice&itemFilter($filter_index).value=$price_max";
                        $filter_index++;
                    }

                    if ($available_to) {
                        $apicall.="&itemFilter($filter_index).name=AvailableTo&itemFilter($filter_index).value=$available_to";
                        $filter_index++;
                    }

                    if ($condition) {
                        $apicall.="&itemFilter($filter_index).name=Condition&itemFilter($filter_index).value=$condition";
                        $filter_index++;
                    }
                    
                    /* show only USD
                    if (true) {
                        $apicall.="&itemFilter($filter_index).name=Currency&itemFilter($filter_index).value=USD";
                        $filter_index++;
                    }*/
                    
                    if ($free_shipping_only) {
                        $apicall.="&itemFilter($filter_index).name=FreeShippingOnly&itemFilter($filter_index).value=true";
                        $filter_index++;
                    }

                    if ($listing_type) {
                        $apicall.="&itemFilter($filter_index).name=ListingType";
                        for ($i = 0; $i < count($listing_type); $i++) {
                            $apicall.="&itemFilter($filter_index).value($i)=" . $listing_type[$i];
                        }
                        $filter_index++;
                    } else {
                        $apicall.="&itemFilter($filter_index).name=ListingType&itemFilter($filter_index).value=FixedPrice";
                    }

                    if (isset($_GET['orderby'])) {
                        switch ($_GET['orderby']) {
                            case 'price':
                                if ($_GET['order'] == 'asc') {
                                    $apicall.="&sortOrder=PricePlusShippingLowest";
                                } elseif ($_GET['order'] == 'desc') {
                                    $apicall.="&sortOrder=CurrentPriceHighest";
                                    //$apicall.="&sortOrder=PricePlusShippingHighest";
                                }
                                break;
                            default:
                                break;
                        }
                    }
                                        
                    $tmp_response = wpdeae_remote_get($apicall);
                    if (is_wp_error($tmp_response)) {
                        $result["error"] = 'eBay api not response!';
                    } else {
                        $body = wp_remote_retrieve_body($tmp_response);
                        $resp = simplexml_load_string($body);

                        if (isset($resp->errorMessage->error)) {
                            $result["error"] = "Error code: " . strval($resp->errorMessage->error->errorId) . ". " . strval($resp->errorMessage->error->message);
                        } else {
                            if ($resp && $resp->paginationOutput->totalEntries > 0) {
                                $result["total"] = (IntVal($resp->paginationOutput->totalEntries) > ($per_page * 100)) ? ($per_page * 100) : IntVal($resp->paginationOutput->totalEntries);
                                $result["total"] = IntVal($resp->paginationOutput->totalEntries);

                                $currency_conversion_factor = floatval(str_replace(",", ".", strval(get_option('wpdeae_currency_conversion_factor', 1))));
                                $tmp_variation_cnt = array();
                                foreach ($resp->searchResult->item as $item) {
                                    echo "<pre>";print_r($item);echo "</pre>";

                                    $goods = new WPDEAE_Goods();
                                    $goods->type = "ebay";
                                    $goods->external_id = strval($item->itemId);

                                    //set var=xxx params for use variation!!!
                                    $goods->detail_url = str_replace("item=0", "item=" . $item->itemId, $item->viewItemURL);

                                    $goods->load();

                                    $goods->link_category_id = $link_category_id;

                                    $goods->image = ($item->galleryURL) ? strval($item->galleryURL) : WPDEAE_NO_IMAGE_URL;
                                    
                                    if(isset($item->storeInfo->storeURL)){
                                        $goods->seller_url = strval($item->storeInfo->storeURL);
                                    }else if(isset($item->sellerInfo->sellerUserName)){
                                        $goods->seller_url = "http://www.ebay.com/usr/" . $item->sellerInfo->sellerUserName;
                                    }

                                    $goods->title = strval($item->title);
                                    $goods->subtitle = strval($item->subtitle);

                                    $goods->category_id = strval($item->primaryCategory->categoryId);
                                    $goods->category_name = strval($item->primaryCategory->categoryName);

                                    if (strlen(trim($goods->keywords)) == 0) {
                                        $goods->keywords = "#needload#";
                                    }

                                    if (strlen(trim($goods->description)) == 0) {
                                        $goods->description = "#needload#";
                                    }

                                    if (strlen(trim($goods->photos)) == 0) {
                                        $goods->photos = "#needload#";
                                    }

                                    $goods->additional_meta['filters'] = array('site_code' => $site);

                                    $goods->additional_meta['condition'] = strval($item->condition->conditionDisplayName);
                                    $goods->additional_meta['ship'] = WPDEAE_Goods::get_normalize_price($item->shippingInfo->shippingServiceCost);
                                    $goods->additional_meta['ship_to_locations'] = "";
                                    foreach ($item->shippingInfo->shipToLocations as $sl) {
                                        $goods->additional_meta['ship_to_locations'] .= (strlen($goods->additional_meta['ship_to_locations']) > 0 ? ", " : "") . $sl;
                                    }

                                    $goods->price = round(WPDEAE_Goods::get_normalize_price($item->sellingStatus->convertedCurrentPrice), 2);

                                    if (get_option('wpdeae_ebay_using_woocommerce_currency', false) && get_woocommerce_currency() === trim(strval($item->sellingStatus->currentPrice['currencyId']))) {
                                        $goods->price = round(WPDEAE_Goods::get_normalize_price($item->sellingStatus->currentPrice), 2);
                                        $goods->curr = trim(strval($item->sellingStatus->currentPrice['currencyId']));
                                    } else {
                                        $goods->price = round(WPDEAE_Goods::get_normalize_price($item->sellingStatus->convertedCurrentPrice), 2);
                                        $goods->curr = trim(strval($item->sellingStatus->convertedCurrentPrice['currencyId']));
                                    }

                                    //$goods->save("API");

                                    if (strlen(trim(strval($goods->user_price))) == 0) {
                                        $goods->user_price = round($goods->price * $currency_conversion_factor, 2);
                                        //$goods->save_field("user_price", sprintf("%01.2f", $goods->user_price));
                                    }

                                    if (strlen(trim(strval($goods->user_image))) == 0) {
                                        //$goods->save_field("user_image", $goods->image);
                                    }

                                    $result["items"][] = apply_filters('wpdeae_modify_goods_data', $goods, $item, "ebay_load_list");
                                }
                            }
                        }
                    }
                }
            } else {
                $result["error"] = 'Please enter some search keywords or input specific prodcutId or specifc store name or select item from category list!';
            }

            return $result;
        }

        public function load_detail(/* @var $goods WPDEAE_Goods */ $goods, $params = array()) {
            $site_id = 0;
            // if (isset($params["site_code"])) {
            //     $sites_list = $this->api->get_sites();
            //     foreach ($sites_list as $s) {
            //         if ($s['id'] === $params["site_code"]) {
            //             $site_id = $s['code'];
            //             break;
            //         }
            //     }
            // }

            //$init_load = isset($params["init_load"]) ? $params["init_load"] : false;
            $init_load = true;

            $api_url = "http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . $this->account->appID .
                    "&siteid=" . $site_id .
                    //"&version=515".
                    "&version=889" .
                    "&ItemID=" . $goods->external_id . "&IncludeSelector=ItemSpecifics,Description,Details,ShippingCosts,Variations,StoreInfo";	

            if ($init_load) {
                if ($this->account->use_affiliate_urls()) {
                    if (get_option('wpdeae_ebay_custom_id')) {
                        $api_url.="&trackingid=" . get_option('wpdeae_ebay_custom_id');
                    }
                    if (get_option('wpdeae_ebay_network_id')) {
                        $api_url.="&trackingpartnercode=" . get_option('wpdeae_ebay_network_id');
                    }
                    if (get_option('wpdeae_ebay_tracking_id')) {
                        $api_url.="&affiliateuserid=" . get_option('wpdeae_ebay_tracking_id');
                    }
                }
            }
            
            $tmp_response = wpdeae_remote_get($api_url);
            if (is_wp_error($tmp_response)) {
                $result = array("state" => "error", "message" => "eBay api not response!");
            } else {
                $body = wp_remote_retrieve_body($tmp_response);

                $detail_xml = simplexml_load_string($body);
				
				if (isset($detail_xml->Item)) {
                    if ($init_load) {                        
                        $currency_conversion_factor = floatval(str_replace(",", ".", strval(get_option('wpdeae_currency_conversion_factor', 1))));

                        $goods->type = "ebay";
                        $goods->external_id = strval($detail_xml->Item->ItemID);
                        //$goods->load();

                        // check_availability
                        $end_time = isset($detail_xml->Item->EndTime) ? strtotime($detail_xml->Item->EndTime) : (time() + 60);
                        $goods->availability = $end_time > time();

                        $goods->link_category_id = isset($params["link_category_id"]) ? $params["link_category_id"] : 0;

                        $goods->image = ($detail_xml->Item->GalleryURL) ? strval($detail_xml->Item->GalleryURL) : WPDEAE_NO_IMAGE_URL;

                        $goods->detail_url = $detail_xml->Item->ViewItemURLForNaturalSearch;
                        
                        $goods->seller_id = trim($detail_xml->Item->Seller->UserID);
                        if(isset($detail_xml->Item->Storefront->StoreURL)){
                            $goods->seller_url = $detail_xml->Item->Storefront->StoreURL;
                        }else if(isset($detail_xml->Item->Seller->UserID)){
                            $goods->seller_url = "http://www.ebay.com/usr/" . $detail_xml->Item->Seller->UserID;
                        }

                        $goods->title = strval($detail_xml->Item->Title);
                        $goods->subtitle = strval($detail_xml->Item->Subtitle);

                        $goods->category_id = strval($detail_xml->Item->PrimaryCategoryID);
                        $goods->category_name = strval($detail_xml->Item->PrimaryCategoryName);

                        if (strlen(trim($goods->keywords)) == 0) {
                            $goods->keywords = "#needload#";
                        }

                        if (strlen(trim($goods->description)) == 0) {
                            $goods->description = "#needload#";
                        }

                        if (strlen(trim($goods->photos)) == 0) {
                            $goods->photos = "#needload#";
                        }

                        if (isset($params["site_code"])) {
                            $goods->additional_meta['filters'] = array('site_code' => $params["site_code"]);
                        }

                        $goods->additional_meta['condition'] = "";
                        $goods->additional_meta['ship'] = "0.00";

                        $goods->additional_meta['ship_to_locations'] = "";
                        foreach ($detail_xml->Item->ShipToLocations as $sl) {
                            $goods->additional_meta['ship_to_locations'] .= (strlen($goods->additional_meta['ship_to_locations']) > 0 ? ", " : "") . $sl;
                        }

                        if (get_option('wpdeae_ebay_using_woocommerce_currency', false) && get_woocommerce_currency() === trim(strval($detail_xml->Item->CurrentPrice['currencyID']))) {
                            $goods->price = round(WPDEAE_Goods::get_normalize_price($detail_xml->Item->CurrentPrice), 2);
                            $goods->curr = trim(strval($detail_xml->Item->CurrentPrice['currencyID']));
                        } else {
                            $goods->price = round(WPDEAE_Goods::get_normalize_price($detail_xml->Item->ConvertedCurrentPrice), 2);
                            $goods->curr = trim(strval($detail_xml->Item->ConvertedCurrentPrice['currencyID']));
                        }

                        //$goods->save("API");

                        if (strlen(trim(strval($goods->user_price))) == 0) {
                            $goods->user_price = round($goods->price * $currency_conversion_factor, 2);
                            //$goods->save_field("user_price", sprintf("%01.2f", $goods->user_price));
                        }

                        if (strlen(trim(strval($goods->user_image))) == 0) {
                            //$goods->save_field("user_image", $goods->image);
                        }
                    }

                    $goods->description = $detail_xml->Item->Description;
                    $goods->description = $this->clear_html($goods->description);
                    $goods->description = WPDEAE_Utils::remove_tags($goods->description);

                    $attr_list = array();
                    if (isset($detail_xml->Item->ItemSpecifics)) {
                        foreach ($detail_xml->Item->ItemSpecifics->NameValueList as $attr) {
                            $value = "";
                            foreach ($attr->Value as $v) {
                                $value.=($value == "" ? "" : ", ") . $v;
                            }
                            $attr_list[] = array("name" => strval($attr->Name), "value" => $value);
                        }
                    }

                    $goods->additional_meta['attribute'] = $attr_list ? $attr_list : array();
                    
                    if (isset($detail_xml->Item->Variations)) {
                        foreach ($detail_xml->Item->Variations->Variation as $variation) {
                            $variation_meta = array();
                            if (isset($variation->VariationSpecifics)) {
                                foreach ($variation->VariationSpecifics->NameValueList as $value) {
                                    $variation_meta[] = array('name' => $value->Name, 'value' => $value->Value);
                                }
                                //if (get_option('wpdeae_ebay_using_woocommerce_currency', false) && get_woocommerce_currency() === trim(strval($detail_xml->Item->CurrentPrice['currencyID']))) {
                                    $var_price = round(WPDEAE_Goods::get_normalize_price($variation->StartPrice), 2);
                                    $var_curr = trim(strval($detail_xml->Item->StartPrice['currencyID']));
                                //} else {
                                //    $var_price = round(WPDEAE_Goods::get_normalize_price($variation->StartPrice), 2);
                                //    $var_curr = trim(strval($variation->StartPrice['currencyID']));
                                //}
                            }
                            
                            $goods->variations_meta[] = array('list' => $variation_meta, 'quantity' => $variation->Quantity,  'quantity_sold' => $variation->SellingStatus->QuantitySold, 'price' => $var_price, 'cur' => $var_curr);
                        }
                    }
                    if (isset($detail_xml->Item->Quantity)) {
                        $quantity = intval($detail_xml->Item->Quantity);
                        if (isset($detail_xml->Item->QuantitySold) && intval($detail_xml->Item->QuantitySold)) {
                            $quantity -= intval($detail_xml->Item->QuantitySold);
                        }
                        $goods->additional_meta['quantity'] = $quantity;
                    }

                    $tmp_p = "";
                    $new_prew = "";
                    foreach ($detail_xml->Item->PictureURL as $img_url) {
                        $img_url = preg_replace('/\$\_(\d+)\.JPG/i', '$_10.JPG', $img_url);
                        if (!$new_prew) {
                            $new_prew = strval($img_url);
                        }
                        $tmp_p .= ($tmp_p ? "," : "") . $img_url;
                    }
                    $goods->photos = $tmp_p;

                    if ($goods->detail_url) {
                        try {
                            $page_meta = get_meta_tags($goods->detail_url);
                            $goods->keywords = (isset($page_meta["keywords"]) ? $page_meta["keywords"] : "");
                        } catch (Exception $e) {
                            
                        }
                    }

                    //$goods->save("API");

                    if ($new_prew && (strlen(trim(strval($goods->user_image))) == 0 || trim(strval($goods->user_image)) === trim(strval($goods->image)))) {
                        if($goods->image === WPDEAE_NO_IMAGE_URL){
                            $goods->image = $new_prew;
                        }
                        //$goods->save_field("user_image", $new_prew);
                    }

                    $result = array("state" => "ok", "message" => "", "goods" => apply_filters('wpdeae_modify_goods_data', $goods, $detail_xml, "ebay_load_detail"));
                } else {
                    $result = array("state" => "error", "message" => "" . "Error code: ".$detail_xml->Errors->ErrorCode.". ".$detail_xml->Errors->LongMessage, "goods" => $goods);
                }
            }

            return $result;
        }

        public function load_details($item_infos, $params = array()) {
            $site_id = 0;
            $init_load = true;
            $item_ids = array();
            $item_result = array();
            $result = array(
                'state' => 'error',
                'message' => '',
                'items' => null
            );

            if ($mh = curl_multi_init()) {
                foreach ($item_infos as $item_id => $variations)
                {
                    $api_url = "http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . $this->account->appID .
                            "&siteid=" . $site_id .
                            //"&version=515".
                            "&version=889" .
                            "&ItemID=" . $item_id . "&IncludeSelector=ItemSpecifics,Description,Details,ShippingCosts,Variations,StoreInfo";
                    if ($init_load) {
                        if ($this->account->use_affiliate_urls()) {
                            if (get_option('wpdeae_ebay_custom_id')) {
                                $api_url.="&trackingid=" . get_option('wpdeae_ebay_custom_id');
                            }
                            if (get_option('wpdeae_ebay_network_id')) {
                                $api_url.="&trackingpartnercode=" . get_option('wpdeae_ebay_network_id');
                            }
                            if (get_option('wpdeae_ebay_tracking_id')) {
                                $api_url.="&affiliateuserid=" . get_option('wpdeae_ebay_tracking_id');
                            }
                        }
                    }
                    if ($curl[$item_id ] = curl_init()) {            
                        curl_setopt($curl[$item_id], CURLOPT_URL, $api_url);
                        curl_setopt($curl[$item_id], CURLOPT_HEADER, 0);
                        curl_setopt($curl[$item_id], CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($curl[$item_id], CURLOPT_TIMEOUT, 30);
                        
                        curl_setopt($curl[$item_id], CURLOPT_FOLLOWLOCATION, 1);
                        
                        curl_multi_add_handle($mh, $curl[$item_id]);

                        $item_ids[(int)$curl[$item_id]] = $item_id;
                    }
                }
            } else {
                $result["message"] = "curl init failed!";
                return $result;
            }
            
            $running = null;
            do {
                while (($status = curl_multi_exec($mh, $running)) == CURLM_CALL_MULTI_PERFORM);
                if ($status != CURLM_OK) break;
                while ($info = curl_multi_info_read($mh)) {
                    $handle = $info['handle'];
                    $body = curl_multi_getcontent($handle);
                    
                    $detail_xml = simplexml_load_string($body);
                    $item_id = $item_ids[(int)$handle];

                    $price = $quantity = $ship_price = 0;
                    $curr = $seller_id = "";
                    $status = 'Deleted';
                    if (isset($detail_xml->Item)) {
                        $end_time = isset($detail_xml->Item->EndTime) ? strtotime($detail_xml->Item->EndTime) : (time() + 60);
                        if ($end_time > time()) {
                            $status = 'active';
                        } else {
                            $status = 'Ended';
                        }
                        $seller_id = trim($detail_xml->Item->Seller->UserID);

                        if ($status == 'active') {
                            $ship_price = WPDEAE_Goods::get_normalize_price($detail_xml->Item->ShippingCostSummary->ShippingServiceCost);
    
                            if (get_option('wpdeae_ebay_using_woocommerce_currency', false) && get_woocommerce_currency() === trim(strval($detail_xml->Item->CurrentPrice['currencyID']))) {
                                $price = round(WPDEAE_Goods::get_normalize_price($detail_xml->Item->CurrentPrice), 2);
                            } else {
                                $price = round(WPDEAE_Goods::get_normalize_price($detail_xml->Item->ConvertedCurrentPrice), 2);
                            }
                            
                            if (isset($detail_xml->Item->Quantity)) {
                                $quantity = intval($detail_xml->Item->Quantity);
                                if (isset($detail_xml->Item->QuantitySold) && intval($detail_xml->Item->QuantitySold)) {
                                    $quantity -= intval($detail_xml->Item->QuantitySold);
                                }
                            }
                            $variation = $item_infos[$item_id];
                            if ($variation) {
                                $variation_split = explode(',', $variation);
                                if (isset($detail_xml->Item->Variations)) {
                                    foreach ($detail_xml->Item->Variations->Variation as $variation) {
                                        $variation_meta = array();
                                        $count = 0;
                                        if (isset($variation->VariationSpecifics)) {
                                            foreach ($variation->VariationSpecifics->NameValueList as $value) {
                                                foreach ($variation_split as $variation) {
                                                    if (strtolower($value) == strtolower($variation)) {
                                                        $count = $count + 1;
                                                        break;
                                                    }
                                                }
                                            }
                                            if ($count >= count($variation_split)) {
                                                $price = round(WPDEAE_Goods::get_normalize_price($variation->StartPrice), 2);
                                                break;
                                            }
                                        }
                                    }
                                } 
                            }
                        }
                    }

                    $result['items'][$item_id] = array('estado'=> $status, 'quantity'=> $quantity, 'price'=> $price, 'delivery price'=> $ship_price, 'Total price'=> $ship_price + $price, 'seller'=> $seller_id);

                    if ($running && curl_multi_select($mh) === -1) usleep(50);
                    curl_multi_remove_handle($mh, $handle);
                    curl_close($handle);
                }
            }  while($running);
    
            curl_multi_close($mh);

            $result["state"] = "ok";
            return $result;
        }
        
        public function parse_items($table, $item_infos) {
            $site_id = 0;
            $init_load = true;
            $item_ids = array();
            global $wpdb;
            if ($mh = curl_multi_init()) {
                foreach ($item_infos as $item_id => $item_info)
                {
                    $api_url = "http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . $this->account->appID .
                            "&siteid=" . $site_id .
                            //"&version=515".
                            "&version=889" .
                            "&ItemID=" . $item_id . "&IncludeSelector=ItemSpecifics,Description,Details,ShippingCosts,Variations,StoreInfo";
                    if ($init_load) {
                        if ($this->account->use_affiliate_urls()) {
                            if (get_option('wpdeae_ebay_custom_id')) {
                                $api_url.="&trackingid=" . get_option('wpdeae_ebay_custom_id');
                            }
                            if (get_option('wpdeae_ebay_network_id')) {
                                $api_url.="&trackingpartnercode=" . get_option('wpdeae_ebay_network_id');
                            }
                            if (get_option('wpdeae_ebay_tracking_id')) {
                                $api_url.="&affiliateuserid=" . get_option('wpdeae_ebay_tracking_id');
                            }
                        }
                    }
                    if ($curl[$item_id ] = curl_init()) {            
                        curl_setopt($curl[$item_id], CURLOPT_URL, $api_url);
                        curl_setopt($curl[$item_id], CURLOPT_HEADER, 0);
                        curl_setopt($curl[$item_id], CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($curl[$item_id], CURLOPT_TIMEOUT, 30);
                        
                        curl_setopt($curl[$item_id], CURLOPT_FOLLOWLOCATION, 1);
                        
                        curl_multi_add_handle($mh, $curl[$item_id]);

                        $item_ids[(int)$curl[$item_id]] = $item_id;
                    }
                }
            } else {
                $result["message"] = "curl init failed!";
                return $result;
            }
            
            $running = null;
            do {
                while (($status = curl_multi_exec($mh, $running)) == CURLM_CALL_MULTI_PERFORM);
                if ($status != CURLM_OK) break;
                while ($info = curl_multi_info_read($mh)) {
                    $handle = $info['handle'];
                    $body = curl_multi_getcontent($handle);
                    
                    $detail_xml = simplexml_load_string($body);
                    $item_id = $item_ids[(int)$handle];

                    $price = $quantity = $ship_price = 0;
                    $curr = $seller_id = "";
                    $status = 'Deleted';
                    if (isset($detail_xml->Item)) {
                        $end_time = isset($detail_xml->Item->EndTime) ? strtotime($detail_xml->Item->EndTime) : (time() + 60);
                        if ($end_time > time()) {
                            $status = 'active';
                        } else {
                            $status = 'Ended';
                        }
                        $seller_id = trim($detail_xml->Item->Seller->UserID);

                        if ($status == 'active') {
                            $ship_price = WPDEAE_Goods::get_normalize_price($detail_xml->Item->ShippingCostSummary->ShippingServiceCost);

                            $variation = $item_infos[$item_id]['variation'];
                            if (get_option('wpdeae_ebay_using_woocommerce_currency', false) && get_woocommerce_currency() === trim(strval($detail_xml->Item->CurrentPrice['currencyID']))) {
                                $price = round(WPDEAE_Goods::get_normalize_price($detail_xml->Item->CurrentPrice), 2);
                            } else {
                                $price = round(WPDEAE_Goods::get_normalize_price($detail_xml->Item->ConvertedCurrentPrice), 2);
                            }
                            if (isset($detail_xml->Item->Quantity)) {
                                $quantity = intval($detail_xml->Item->Quantity);
                                if (isset($detail_xml->Item->QuantitySold) && intval($detail_xml->Item->QuantitySold)) {
                                    $quantity -= intval($detail_xml->Item->QuantitySold);
                                }
                            }
                            if ($variation) {
                                $variation_split = explode(',', $variation);
                                if (isset($detail_xml->Item->Variations)) {
                                    foreach ($detail_xml->Item->Variations->Variation as $variation) {
                                        $variation_meta = array();
                                        $count = 0;
                                        if (isset($variation->VariationSpecifics)) {
                                            foreach ($variation->VariationSpecifics->NameValueList as $value) {
                                                foreach ($variation_split as $variation) {
                                                    if (strtolower($value) == strtolower($variation)) {
                                                        $count = $count + 1;
                                                        break;
                                                    }
                                                }
                                            }
                                            if ($count >= count($variation_split)) {
                                                $quantity = $variation->Quantity - $variation->SellingStatus->QuantitySold;
                                                $price = round(WPDEAE_Goods::get_normalize_price($variation->StartPrice), 2);
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        $total_price = $ship_price + $price;
                        $wdt_id = $item_infos[$item_id]['row_id'];
                        $update_statement = "UPDATE {$table}
                           SET wdtcolumn2 = \"{$status}\", wdtcolumn3 = \"{$quantity}\",
                           wdtcolumn4 = \"{$price}\", wdtcolumn5 = \"{$ship_price}\",
                           wdtcolumn6 = \"{$total_price}\", seller = \"{$seller_id}\"
                           WHERE userid = " . get_current_user_id() . " AND wdt_ID = {$wdt_id}"; 

                        $wpdb->query($update_statement);
                    }

                    if ($running && curl_multi_select($mh) === -1) usleep(50);
                    curl_multi_remove_handle($mh, $handle);
                    curl_close($handle);
                }
            }  while($running);
    
            curl_multi_close($mh);
        }

        public function check_availability(/* @var $goods WPDEAE_Goods */ $goods) {
            $site_id = 0;

            $api_url = "http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . $this->account->appID . "&siteid=" . $site_id . "&version=515&ItemID=" . $goods->external_id . "&IncludeSelector=Description,Details,Variations";

            $tmp_response = wpdeae_remote_get($api_url);
            if (is_wp_error($tmp_response)) {
                // if ebay is not response? just return true (product availabile)
                return true;
            }
            $body = wp_remote_retrieve_body($tmp_response);
            $detail_xml = simplexml_load_string($body);

            $end_time = isset($detail_xml->Item->EndTime) ? strtotime($detail_xml->Item->EndTime) : (time() + 60);

            return $end_time > time();
        }

        public function get_detail($productId, $params = array()) {
            $site_id = 0;
            if (isset($params["site_code"])) {
                $sites_list = $this->api->get_sites();
                foreach ($sites_list as $s) {
                    if ($s['id'] === $params["site_code"]) {
                        $site_id = $s['code'];
                        break;
                    }
                }
            }

            $api_url = "http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . $this->account->appID . "&siteid=" . $site_id . "&version=515&ItemID=" . $productId . "&IncludeSelector=Description,Details,ShippingCosts,Variations";

            $tmp_response = wpdeae_remote_get($api_url);
            if (is_wp_error($tmp_response)) {
                $result = array("state" => "error", "message" => "eBay api not response!");
            } else {
                $body = wp_remote_retrieve_body($tmp_response);
                $detail_xml = simplexml_load_string($body);

                if (!isset($detail_xml->Errors)) {
                    $goods = new WPDEAE_Goods("ebay#" . $detail_xml->Item->ItemID);

                    // check_availability
                    $end_time = isset($detail_xml->Item->EndTime) ? strtotime($detail_xml->Item->EndTime) : (time() + 60);
                    $goods->availability = $end_time > time();

                    $goods->image = ($detail_xml->Item->GalleryURL) ? $detail_xml->Item->GalleryURL : WPDEAE_NO_IMAGE_URL;
                    $goods->detail_url = $detail_xml->Item->ViewItemURLForNaturalSearch;
                    $goods->seller_url = "http://www.ebay.com/usr/" . $detail_xml->Item->Seller->UserID;
                    $goods->title = $detail_xml->Item->Title;
                    $goods->subtitle = $detail_xml->Item->Subtitle;
                    $goods->category_id = $detail_xml->Item->PrimaryCategoryID;
                    $goods->category_name = $detail_xml->Item->PrimaryCategoryName;
                    $goods->keywords = "#needload#";
                    $goods->description = "#needload#";
                    $goods->photos = "#needload#";
                    $goods->additional_meta['condition'] = "";
                    $currency_conversion_factor = floatval(str_replace(",", ".", strval(get_option('wpdeae_currency_conversion_factor', 1))));

                    $goods->additional_meta['ship'] = WPDEAE_Goods::get_normalize_price($detail_xml->Item->ShippingCostSummary->ShippingServiceCost);
                    
                    if (isset($params["site_code"])) {
                        $goods->additional_meta['filters'] = array('site_code' => $params["site_code"]);
                    }

                    if (get_option('wpdeae_ebay_using_woocommerce_currency', false) && get_woocommerce_currency() === trim(strval($detail_xml->Item->CurrentPrice['currencyID']))) {
                        $goods->price = round(WPDEAE_Goods::get_normalize_price($detail_xml->Item->CurrentPrice), 2);
                        $goods->curr = trim(strval($detail_xml->Item->CurrentPrice['currencyID']));
                    } else {
                        $goods->price = round(WPDEAE_Goods::get_normalize_price($detail_xml->Item->ConvertedCurrentPrice), 2);
                        $goods->curr = trim(strval($detail_xml->Item->ConvertedCurrentPrice['currencyID']));
                    }

                    $goods->additional_meta['ship_to_locations'] = "";
                    foreach ($detail_xml->Item->ShipToLocations as $sl) {
                        $goods->additional_meta['ship_to_locations'] .= (strlen($goods->additional_meta['ship_to_locations']) > 0 ? ", " : "") . $sl;
                    }

                    if (isset($detail_xml->Item->Quantity)) {
                        $quantity = intval($detail_xml->Item->Quantity);
                        if (isset($detail_xml->Item->QuantitySold) && intval($detail_xml->Item->QuantitySold)) {
                            $quantity -= intval($detail_xml->Item->QuantitySold);
                        }
                        $goods->additional_meta['quantity'] = $quantity;
                    }

                    $goods->user_price = round($goods->price * $currency_conversion_factor, 2);
                    
                    $result = array("state" => "ok", "message" => "", "goods" => apply_filters('wpdeae_modify_goods_data', $goods, $detail_xml, "ebay_get_detail"));
                } else {
                    $result = array("state" => "error", "message" => "" . $detail_xml->Errors->LongMessage);
                }
            }
            return $result;
        }

        private function clear_html($in_html) {
            if (!$in_html)
                return "";
            $html = $in_html;
            $html = preg_replace('/<span class="ebay"[^>]*?>.*?<\/span>/i', '', $html);
            $html = preg_replace("/<\/?h[1-9]{1}[^>]*\>/i", "", $html);
            $html = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $html);
            $html = preg_replace('/(<[^>]+) class=".*?"/i', '$1', $html);
            $html = preg_replace('/(<[^>]+) width=".*?"/i', '$1', $html);
            $html = preg_replace('/(<[^>]+) height=".*?"/i', '$1', $html);
            $html = preg_replace('/(<[^>]+) alt=".*?"/i', '$1', $html);

            $html = force_balance_tags($html);
            return $html;
        }
    }
endif;