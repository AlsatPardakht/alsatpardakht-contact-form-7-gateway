<?php

/*
Plugin Name: AlsatPardakht Payment Gateway For Contact Form 7
Plugin URI: https://alsatpardakht.com
Description: اتصال فرم های Contact Form 7 به درگاه پرداخت آل‌سات پرداخت
Author: Hamid Musavi
Author URI: https://github.com/MirHamit
Version: 1.0.0
*/

function postToAlsatPardakht($path, $parameters)
{
    try {
        $args = array(
            'body' => $parameters,
            'timeout' => '30',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(),
            'cookies' => array(),
        );
        $result = wp_safe_remote_post($path, $args);

        if (!isset($result->errors)) {
            if (isset($result['body']) && $result['body']) {
                $result = json_decode($result['body']);
            } else {
                $result = json_decode('[]');
            }

        }

        return $result;
    } catch (Exception $ex) {
        return false;
    }
}

function ALSATPARDAKHT_CF7_relative_time($ptime)
{
    if (get_option('timezone_string')) {
        date_default_timezone_set(get_option('timezone_string'));
    } else {
        date_default_timezone_set("Asia/Tehran");
    }
    $etime = time() - $ptime;
    if ($etime < 1) {
        return '0 ثانیه';
    }
    $a = array(
        12 * 30 * 24 * 60 * 60 => 'سال',
        30 * 24 * 60 * 60 => 'ماه',
        24 * 60 * 60 => 'روز',
        60 * 60 => 'ساعت',
        60 => 'دقیقه',
        1 => 'ثانیه'
    );
    foreach ($a as $secs => $str) {
        $d = $etime / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r.' '.$str.($r > 1 ? ' ' : '');
        }
    }
}


function alsatpardakht_result_payment_func()
{
    global $wpdb;
    $Return_MessageEmail = '';
    $Theme_Message = get_option('cf7pp_theme_message', '');

    $theme_error_message = get_option('cf7pp_theme_error_message', '');

    $options = get_option('cf7pp_options');
    foreach ($options as $k => $v) {
        $value[$k] = $v;
    }
    $merchantId = $value['gateway_merchantid'];
    $sucess_color = $value['sucess_color'];
    $error_color = $value['error_color'];
    $vasetIGP = $value['isVaset'];
    if (isset($_GET['iN'])) {

        $iN = sanitize_text_field($_GET['iN']);
        $iD = isset($_GET['iD']) ? sanitize_text_field($_GET['iD']) : null;
        $tref = sanitize_text_field($_GET['tref']);
        $Return_Track_Id = isset($_GET['invoice']) ? sanitize_text_field($_GET['invoice']) : $iN;
        $table_name = $wpdb->prefix.'alsatpardakht_contact_form_7';
        $cf_Form = $wpdb->get_row("SELECT * FROM $table_name WHERE transid=".$iN);
        if (isset($iN) && isset($iD)) {


            if ($cf_Form !== null) {
                $Amount = $cf_Form->cost;
            } else {
                $body = '<b style="color:'.$error_color.';">'.$theme_error_message.'<b/>';
                return alsatpardakht_createMessage_cf7("", "", $body);
            }
            $data = [
                'Api' => $merchantId,
                'tref' => $tref,
                'iN' => $iN,
                'iD' => $iD
            ];

            if ($vasetIGP) {
                $result = postToAlsatPardakht('https://alsatpardakht.com/IPGAPI/Api22/VerifyTransaction.php', $data);
            } else {
                $result = postToAlsatPardakht('https://alsatpardakht.com/API_V1/callback.php', $data);
            }
            if (isset($result->VERIFY->IsSuccess) && isset($result->PSP) && $result->PSP->IsSuccess === true) {
                if ($result->PSP->Amount == $Amount) {
                    $Return_MessageEmail = 'success';
                } else {
                    $Return_MessageEmail = 'error';
                }
            } else {
                $Return_MessageEmail = 'error';
            }
        } else {
            $Return_MessageEmail = 'error';
        }


    } else {
        $Return_MessageEmail = 'error';
    }

    if (isset($cf_Form) && $cf_Form !== null) {
        if ($Return_MessageEmail == 'success') {
            if ($cf_Form->status == 'none') {
                $wpdb->update($wpdb->prefix.'alsatpardakht_contact_form_7',
                    array(
                        'status' => 'success',
                        'TransactionReferenceID' => $result->PSP->TransactionReferenceID,
                        'TrxMaskedCardNumber' => $result->PSP->TrxMaskedCardNumber,
                        'ReferenceNumber' => $result->PSP->ReferenceNumber
                    ),
                    array('transid' => $Return_Track_Id),
                    array('%s', '%s'),
                    array('%d'));
            }
            //Dispaly
            $body = '<b style="color:'.$sucess_color.';">'.stripslashes(str_replace('[transaction_id]',
                    $result->PSP->TransactionReferenceID,
                    $Theme_Message)).'<b/>';
            return alsatpardakht_createMessage_cf7("", "", $body);
        } else {
            if ($cf_Form->status == 'none') {
                if ($Return_MessageEmail == 'error') {
                    $wpdb->update($wpdb->prefix.'alsatpardakht_contact_form_7',
                        array('status' => 'error'),
                        array('transid' => $Return_Track_Id),
                        array('%s'),
                        array('%d'));
                }
            }
            //Dispaly
            $body = '<b style="color:'.$error_color.';">'.$theme_error_message.'<b/>';
            return alsatpardakht_createMessage_cf7("", "", $body);
        }
    } else {
        $body = '<b style="color:'.$error_color.';">'.$theme_error_message.'<b/>';
        return alsatpardakht_createMessage_cf7("", "", $body);
    }


}

add_shortcode('result_payment', 'alsatpardakht_result_payment_func');

// remove ajax from contact form 7 to allow for php redirects
add_filter('wpcf7_load_js', '__return_false');

function alsatpardakht_createMessage_cf7($alsatForm_title, $alsatForm_body, $alsatForm_endstr = "")
{
    if ($alsatForm_endstr != "") {
        return $alsatForm_endstr;
    }
    $tmp = '<div style="border:#CCC 1px solid; width:90%;"> 
    '.$alsatForm_title.'<br />'.$alsatForm_body.'</div>';
    return $tmp;
}


function alsatpardakht_createPage_cf7($title, $body)
{
    $tmp = '
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>'.$title.'</title>
	</head>
	<body class="vipbody">	
	<div class="mrbox2" > 
	<h3><span>'.$title.'</span></h3>
	'.$body.'	
	</div>
	</body>
	</html>';
    return $tmp;
}


function ALSATPARDAKHT_Contant_Form_7_Gateway_install()
{

}


$dir = plugin_dir_path(__FILE__);

//  plugin functions
register_activation_hook(__FILE__, "alsatpardakht_cf7pp_activate");
register_deactivation_hook(__FILE__, "alsatpardakht_cf7pp_deactivate");
register_uninstall_hook(__FILE__, "cf7pp_uninstall");


function alsatpardakht_cf7pp_activate()
{

    global $wpdb;


    $table_name = $wpdb->prefix."alsatpardakht_contact_form_7";
    if ($wpdb->get_var("show tables like '$table_name'") != $table_name) {
        $sql = "CREATE TABLE ".$table_name." (
			id mediumint(11) NOT NULL AUTO_INCREMENT,
			idform bigint(11) DEFAULT '0' NOT NULL,
			transid VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			gateway VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			cost bigint(11) DEFAULT '0' NOT NULL,
			created_at bigint(11) DEFAULT '0' NOT NULL,
			email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			description VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			user_mobile VARCHAR(11) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			TransactionReferenceID VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			TrxMaskedCardNumber VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			ReferenceNumber VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			PRIMARY KEY id (id)
		);";

        require_once(ABSPATH.'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // write initical options
    $cf7pp_options = array(
        'merchant' => '',
        'return' => '',
        'error_color' => '#f44336',
        'sucess_color' => '#8BC34A',
        'isVaset' => 0
    );

    add_option("cf7pp_options", $cf7pp_options);


}


function alsatpardakht_cf7pp_deactivate()
{
    delete_option("cf7pp_options");
    delete_option("cf7pp_my_plugin_notice_shown");
}


function cf7pp_uninstall()
{
}

// display activation notice
add_action('admin_notices', 'cf7pp_my_plugin_admin_notices');

function cf7pp_my_plugin_admin_notices()
{
    if (!get_option('cf7pp_my_plugin_notice_shown')) {
        echo wp_kses_post("<div class='updated'><p><a href='admin.php?page=cf7pp_admin_table'>برای تنظیم اطلاعات درگاه  کلیک کنید</a>.</p></div>");
        update_option("cf7pp_my_plugin_notice_shown", "true");
    }
}


// check to make sure contact form 7 is installed and active
include_once(ABSPATH.'wp-admin/includes/plugin.php');
if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {

    // add paypal menu under contact form 7 menu
    add_action('admin_menu', 'cf7pp_admin_menu', 20);
    function cf7pp_admin_menu()
    {
        $addnew = add_submenu_page('wpcf7',
            __('تنظیمات آل‌سات پرداخت', 'contact-form-7'),
            __('تنظیمات آل‌سات پرداخت', 'contact-form-7'),
            'wpcf7_edit_contact_forms', 'cf7pp_admin_table',
            'cf7pp_admin_table');

        $addnew = add_submenu_page('wpcf7',
            __('لیست تراکنش ها', 'contact-form-7'),
            __('لیست تراکنش ها', 'contact-form-7'),
            'wpcf7_edit_contact_forms', 'cf7pp_admin_list_trans',
            'cf7pp_admin_list_trans');

    }


    // hook into contact form 7 - before send
    add_action('wpcf7_before_send_mail', 'cf7pp_before_send_mail');
    function cf7pp_before_send_mail($cf7)
    {
    }


    // hook into contact form 7 - after send
    add_action('wpcf7_mail_sent', 'cf7pp_after_send_mail');
    function cf7pp_after_send_mail($cf7)
    {
        global $wpdb;
        global $postid;
        $postid = $cf7->id();


        $enable = get_post_meta($postid, "_cf7pp_enable", true);
        $email = get_post_meta($postid, "_cf7pp_email", true);
        if ($enable == "1") {
            if ($email == "2") {

                include_once('redirect.php');


                exit;

            }
        }

    } // End Function


    // hook into contact form 7 form
    add_action('wpcf7_admin_after_additional_settings', 'cf7pp_admin_after_additional_settings');
    function cf7pp_editor_panels($panels)
    {

        $new_page = array(
            'PricePay' => array(
                'title' => __('اطلاعات پرداخت', 'contact-form-7'),
                'callback' => 'cf7pp_admin_after_additional_settings'
            )
        );

        $panels = array_merge($panels, $new_page);

        return $panels;

    }

    add_filter('wpcf7_editor_panels', 'cf7pp_editor_panels');


    function cf7pp_admin_after_additional_settings($cf7)
    {

        $post_id = sanitize_text_field($_GET['post']);
        $enable = get_post_meta($post_id, "_cf7pp_enable", true);
        $price = get_post_meta($post_id, "_cf7pp_price", true);
        $email = get_post_meta($post_id, "_cf7pp_email", true);
        $user_mobile = get_post_meta($post_id, "_cf7pp_mobile", true);
        $description = get_post_meta($post_id, "_cf7pp_description", true);

        if ($enable == "1") {
            $checked = "CHECKED";
        } else {
            $checked = "";
        }

        if ($email == "1") {
            $before = "SELECTED";
            $after = "";
        } elseif ($email == "2") {
            $after = "SELECTED";
            $before = "";
        } else {
            $before = "";
            $after = "";
        }
        ?>
        <form>
            <div id='additional_settings-sortables' class='meta-box-sortables ui-sortable'>
                <div id='additionalsettingsdiv' class='postbox'>
                    <div class='handlediv' title='Click to toggle'><br></div>
                    <h3 class='hndle ui-sortable-handle'><span>اطلاعات پرداخت برای فرم</span></h3>
                    <div class='inside'>

                        <div class='mail-field'>
                            <input name='enable' id='cf71' value='1' type='checkbox' <?php echo esc_html($checked) ?> >
                            <label for='cf71'>فعال سازی امکان پرداخت آنلاین</label>
                        </div>

                        <table>
                            <tr>
                                <td>مبلغ:</td>
                                <td><input type='text' name='price' style='text-align:left;direction:ltr;'
                                           value='<?php echo esc_html($price) ?>'></td>
                                <td>(مبلغ به ریال)</td>
                            </tr>

                        </table>
                        <br> برای اتصال به درگاه پرداخت میتوانید از نام فیلدهای زیر استفاده نمایید
                        <br/>
                        <span style='color:#F00;'>
                            user_email نام فیلد دریافت ایمیل کاربر بایستی user_email انتخاب شود.
                            <br/>
                            description نام فیلد  توضیحات پرداخت بایستی description انتخاب شود.
                            <br/>
                            user_mobile نام فیلد  موبایل بایستی user_mobile انتخاب شود.
                            <br/>
                            user_price اگر کادر مبلغ در بالا خالی باشد می توانید به کاربر اجازه دهید مبلغ را خودش انتخاب نماید . کادر متنی با نام user_price ایجاد نمایید
                            <br/>
                            مانند [text* user_price]
                            </span>
                        <input type='hidden' name='email' value='2'>
                        <input type='hidden' name='post' value='<?php echo esc_html($post_id) ?>'>
                    </div>
                </div>
            </div>
        </form>
        <?php
    }


    // hook into contact form 7 admin form save
    add_action('wpcf7_save_contact_form', 'cf7pp_save_contact_form');
    function cf7pp_save_contact_form($cf7)
    {

        $post_id = sanitize_text_field($_POST['post']);

        if (!empty($_POST['enable'])) {
            $enable = sanitize_text_field($_POST['enable']);
            update_post_meta($post_id, "_cf7pp_enable", $enable);
        } else {
            update_post_meta($post_id, "_cf7pp_enable", 0);
        }

        /*$name = sanitize_text_field($_POST['name']);
        update_post_meta($post_id, "_cf7pp_name", $name);
        */
        $price = sanitize_text_field($_POST['price']);
        update_post_meta($post_id, "_cf7pp_price", $price);

        /*$id = sanitize_text_field($_POST['id']);
        update_post_meta($post_id, "_cf7pp_id", $id);
        */
        $email = sanitize_text_field($_POST['email']);
        update_post_meta($post_id, "_cf7pp_email", $email);


    }


    function cf7pp_admin_list_trans()
    {
        if (!current_user_can("manage_options")) {
            wp_die(__("You do not have sufficient permissions to access this page."));
        }

        global $wpdb;

        $pagenum = isset($_GET['pagenum']) ? absint($_GET['pagenum']) : 1;
        $limit = 6;
        $offset = ($pagenum - 1) * $limit;
        $table_name = $wpdb->prefix."alsatpardakht_contact_form_7";

        $transactions = $wpdb->get_results("SELECT * FROM $table_name where (status NOT like 'none') ORDER BY $table_name.id DESC LIMIT $offset, $limit",
            ARRAY_A);
        $total = $wpdb->get_var("SELECT COUNT($table_name.id) FROM $table_name where (status NOT like 'none') ");
        $num_of_pages = ceil($total / $limit);
        $cntx = 0;

        ?>

        <div class="wrap">
        <h2>تراکنش فرم ها</h2>
        <table class="widefat post fixed" cellspacing="0">
            <thead>
            <tr>
                <th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
                <th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
                <th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
                <th scope="col" id="name" width="" class="manage-column" style="">شماره تماس</th>
                <th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
                <th scope="col" id="name" width="15%" class="manage-column" style="">شماره کارت و تراکنش</th>
                <th scope="col" id="name" width="8%" class="manage-column" style="">وضعیت</th>
            </tr>
            </thead>
            <tfoot>
            <tr>
                <th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
                <th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
                <th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
                <th scope="col" id="name" width="" class="manage-column" style="">شماره تماس</th>
                <th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
                <th scope="col" id="name" width="15%" class="manage-column" style="">شماره کارت و تراکنش</th>
                <th scope="col" id="name" width="8%" class="manage-column" style="">وضعیت</th>
            </tr>
            </tfoot>
            <tbody>

            <?php


            if (count($transactions) == 0) {
                ?>
                <tr class="alternate author-self status-publish iedit" valign="top">
                    <td class="" colspan="6">هيج تراکنش وجود ندارد.</td>
                </tr>
                <?php
            } else {
                foreach ($transactions as $transaction) {
                    ?>
                    <tr class="alternate author-self status-publish iedit" valign="top"
                    <td class=""><?php echo esc_html(get_the_title($transaction['idform'])); ?></td>
                    <td class=""><?php echo esc_html(get_the_title($transaction['idform'])); ?></td>
                    <td class=""><?php echo esc_html(strftime("%a, %B %e, %Y %r", $transaction['created_at'])); ?>
                        <br/>( <?php
                        echo esc_html(ALSATPARDAKHT_CF7_relative_time($transaction["created_at"])); ?>
                        قبل)
                    </td>

                    <td class=""><?php echo esc_html($transaction['email']); ?></td>
                    <td class=""><?php echo esc_html($transaction['user_mobile']); ?></td>
                    <td class=""><?php echo esc_html(wp_kses_post($transaction['cost'])); ?> ریال</td>
                    <td class=""
                        style="direction: ltr; text-align: right"><?php echo esc_html($transaction['TrxMaskedCardNumber']).'<br>'.esc_html($transaction['TransactionReferenceID']) ?></td>
                    <td class="">

                        <?php

                        if ($transaction['status'] == "success") {
                            ?>
                            <b style="color:#0C9F55">موفقیت آمیز</b>
                            <?php
                        } else {
                            ?>
                            <b style="color:#f00">انجام نشده</b>
                            <?php
                        }
                        ?>
                    </td></tr>
                    <?php

                }
            }
            ?>
            </tbody>
        </table>
        <br>
        <?php


        $page_links = paginate_links(array(
            'base' => add_query_arg('pagenum', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;', 'aag'),
            'next_text' => __('&raquo;', 'aag'),
            'total' => $num_of_pages,
            'current' => $pagenum
        ));

        if ($page_links) {
            ?>
            <center><div class="tablenav"><div class="tablenav-pages"  style="float:none; margin: 1em 0"><?php echo wp_kses_post($page_links)?></div></div>
		</center>
            <?php
        }

        ?>
            <br>
            <hr>
        </div>
<?php
    }


    function cf7pp_admin_table()
    {
        global $wpdb;
        if (!current_user_can("manage_options")) {
            wp_die(__("You do not have sufficient permissions to access this page."));
        }

        echo '<form method="post" action='.$_SERVER["REQUEST_URI"].' enctype="multipart/form-data">';

        // save and update options
        if (isset($_POST['update'])) {


            $options['gateway_merchantid'] = sanitize_text_field($_POST['gateway_merchantid']);
            $options['return'] = sanitize_text_field($_POST['return']);
            $options['sucess_color'] = sanitize_text_field($_POST['sucess_color']);
            $options['error_color'] = sanitize_text_field($_POST['error_color']);
            $options['isVaset'] = isset($_POST['isVaset']) ? sanitize_text_field($_POST['isVaset']) : 0;

            update_option("cf7pp_options", $options);

            update_option('cf7pp_theme_message', wp_filter_post_kses($_POST['theme_message']));
            update_option('cf7pp_theme_error_message', wp_filter_post_kses($_POST['theme_error_message']));
            echo "<br /><div class='updated'><p><strong>";
            _e("Settings Updated.");
            echo "</strong></p></div>";

        }

        $options = get_option('cf7pp_options');
        foreach ($options as $k => $v) {
            $value[$k] = $v;
        }

        if ($value['isVaset'] == "1") {
            $vasetChecked = "CHECKED";
        } else {
            $vasetChecked = "";
        }
        $theme_message = get_option('cf7pp_theme_message', '');
        $theme_error_message = get_option('cf7pp_theme_error_message', '');

        echo wp_kses_post("<div class='wrap'><h2>Contact Form 7 - Gateway Settings</h2></div><br />
		<table width='90%'><tr><td>");

        echo wp_kses_post('<div style="background-color:#333333;padding:8px;color:#eee;font-size:12pt;font-weight:bold;">
		&nbsp; پرداخت آنلاین برای فرم های Contact Form 7
		</div><div style="background-color:#fff;border: 1px solid #E5E5E5;padding:5px;"><br />

    <q1 style="color:#60F;">
    لینک بازگشت از تراکنش بایستی به یکی از برگه های سایت باشد 
    <br>
    در این برگه بایستی از شورت کد زیر استفاده شود
    <br>
    [result_payment]   
<br/>
    <q1> 

    

    <q1></q1></q1></q1></q1></b></b></div><b><b>

		
		
		
		<br /><br />
		
		</div><br /><br />
		
		<div style="background-color:#333333;padding:8px;color:#eee;font-size:12pt;font-weight:bold;">
		&nbsp; اطلاعات درگاه پرداخت
		</div>
		<div style="background-color:#fff;border: 1px solid #E5E5E5;padding:20px;">
					

        <table> 
        <tr>
            <td>API کد</td>');
        echo '<td>
                    <input type="text" style="width:450px;text-align:left;direction:ltr;" name="gateway_merchantid" value="'.$value['gateway_merchantid'].'">
              </td>
          <td ><label for="isVaset">درگاه واسط</label><br></td>
            <td>
                <input type="checkbox" id="isVaset" name="isVaset" value="1" '.wp_kses_post($vasetChecked).'>
            </td>
          
          <tr>
            <td>لینک بازگشت از تراکنش :<br><br><br></td>
            <td><hr><input type="text" name="return" style="width:450px;text-align:left;direction:ltr;" value="'.wp_kses_post($value['return']).'">
            الزامی
            <br />
            فقط  عنوان  برگه را قرار دهید مانند  Vpay
             <br />
         حتما باید یک برگه ایجادکنید
 و کد [result_payment]  را در ان قرار دهید 
 		 <hr>
            </td>
            
            <td></td>
          </tr>
		  <tr>
            <td>قالب تراکنش موفق :<br><br><br><br></td>
            <td>
			<textarea name="theme_message" style="width:450px;text-align:left;direction:ltr;">'.wp_kses_post($theme_message).'</textarea>
			<br/>
			متنی که میخواهید در هنگام موفقیت آمیز بودن تراکنش نشان دهید
			<br/>
			<b>از شورتکد [transaction_id] برای نمایش شماره تراکنش در قالب های نمایشی استفاده کنید</b><hr>
            </td>
                  <td></td>

          </tr>
          <tr><td></td></tr>
           <tr>
            <td>قالب تراکنش ناموفق :<br><br></td>
            <td>
			<textarea name="theme_error_message" style="width:450px;text-align:left;direction:ltr;">'.wp_kses_post($theme_error_message).'</textarea>
			<br/>
			متنی که میخواهید در هنگام موفقیت آمیز نبودن تراکنش نشان دهید
			<br/><hr>

            </td>
            <td></td>
          </tr>
          <tr>
          
           <td>رنگ متن موفقیت آمیز بودن تراکنش :  </td>

            <td>
            <input type="text" name="sucess_color" style="width:150px;text-align:left;direction:ltr;color:'.wp_kses_post($value['sucess_color']).'" value="'.wp_kses_post($value['sucess_color']).'">
           
 مانند :     #8BC34A     یا نام رنگ  
 green
          <hr> </td>
          
          </tr>
          
          <tr>
          
           <td>رنگ متن موفقیت آمیز نبودن تراکنش :  </td>

            <td>
            <input type="text" name="error_color" style="width:150px;text-align:left;direction:ltr;color:'.wp_kses_post($value['error_color']).'" value="'.wp_kses_post($value['error_color']).'">
            مانند : #f44336 یا نام رنگ  red
            </td>
          </tr>
          <tr><td></td></tr><tr><td></td></tr>
		  
		   <tr>
          <td colspan="3">
          <input type="submit" name="btn2" class="button-primary" style="font-size: 17px;line-height: 28px;height: 32px;float: right;" value="ذخیره تنظیمات">
          </td>
          </tr>
        </table>
        
        </div>
        <br /><br />';
        echo "
		<br />		
		<input type='hidden' name='update'>
		</form>		
		</td></tr></table>";

    }
} else {
    // give warning if contact form 7 is not active
    function cf7pp_my_admin_notice()
    {
        echo wp_kses_post('<div class="error">
			<p>'._e('<b> افزونه درگاه بانکی برای افزونه Contact Form 7 :</b> Contact Form 7 باید فعال باشد ',
                'my-text-domain').'</p>
		</div>
		');
    }

    add_action('admin_notices', 'cf7pp_my_admin_notice');
}
?>
