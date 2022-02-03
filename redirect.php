<?php

  global $wpdb;
  global $postid;

    $wpcf7 = WPCF7_ContactForm::get_current();
        $submission = WPCF7_Submission::get_instance();
        $user_email = '';
        $user_mobile = '';
        $description = '';
        $user_price = '';

        if ($submission) {
            $data = $submission->get_posted_data();
            $user_email = isset($data['user_email']) ? $data['user_email'] : "";
            $user_mobile = isset($data['user_mobile']) ? $data['user_mobile'] : "";
            $description = isset($data['description']) ? $data['description'] : "";
            $user_price = isset($data['user_price']) ? $data['user_price'] : "";
        }

        $price = get_post_meta($postid, "_cf7pp_price", true);
                if ($price == "") {
                    $price = $user_price;
                }
                $options = get_option('cf7pp_options');

                foreach ($options as $k => $v) {
                    $value[$k] = $v;
                }
                if($value['gateway_merchantid']) {
                    $merchantId = $value['gateway_merchantid'];
                } else {
                    echo 'لطفا API آل‌سات پرداخت را در تنظیمات وارد نمایید.';
                    die();
                }

                $url_return = $value['return'];


                $table_name = $wpdb->prefix . "alsatpardakht_contact_form_7";
                $table = array();
                $table['idform'] = $postid;
                $table['transid'] = ''; // create dynamic or id_get
                $table['gateway'] = 'AlsatPardakht';
                $table['cost'] = $price;
                $table['created_at'] = time();
                $table['email'] = $user_email;
                $table['user_mobile'] = $user_mobile;
                $table['description'] = $description;
                $table['status'] = 'none';
                $table_fill = array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s');

                $table['transid'] = time();

                $sql = $wpdb->insert($table_name, $table, $table_fill);
                $callbackUrl = get_site_url().'/'.$url_return; // Required

                if (isset($value['isVaset']) && $value['isVaset'] === '1') {
                    $Tashim[] = [];
                    $data     = array(
                        'Amount'              => $price,
                        'ApiKey'              => $merchantId,
                        'Tashim'              => json_encode( $Tashim ),
                        'RedirectAddressPage' => $callbackUrl . "?invoice={$table['transid']}"
                    );

                    $result = postToAlsatPardakht( 'IPGAPI/Api22/send.php',
                        $data );
                } else {
                    $data = [
                        'Api' => $merchantId,
                        'Amount' => $price,
                        'InvoiceNumber' => $table['transid'],
                        'RedirectAddress' => $callbackUrl . "?invoice={$table['transid']}"
                    ];

                    $result = postToAlsatPardakht('API_V1/sign.php', $data);
                }
                    if ( ! $result || isset( $result->errors ) || isset($result->Error) ) {
                        $tmp = '<br><br>';
                        if (isset($result->errors) && $result->errors) {
                            if ( is_array( $result->errors[ $result->get_error_code() ] ) ) {
                                foreach ( $result->errors[ $result->get_error_code() ] as $error ) {
                                    $tmp .= esc_html( $error ) . "<br>";
                                }
                            } else {
                                $tmp .= esc_html( $result->errors[ $result->get_error_code() ] );
                            }
                        } else {
                            $tmp .= 'خطا در تنظیمات درگاه آل‌سات پرداخت<br>';
                        }

                        $tmp .= '<br><a href="' . get_option('siteurl') . '" class="mrbtn_red" > بازگشت به سایت </a>';
                        echo CreatePage_cf7('خطا در عملیات پرداخت', $tmp);

                    } elseif ( isset( $result->IsSuccess ) && isset( $result->Token ) && $result->IsSuccess === 1 && $result->Token ) {
                        if ( isset($value['isVaset']) && $value['isVaset'] === '1' ) {
                            wp_redirect( sprintf( 'https://www.alsatpardakht.com/IPGAPI/Api2/Go.php?Token=' . $result->Token,
                                $result->Token ) );
                        } else {
                            wp_redirect( sprintf( 'https://www.alsatpardakht.com/API_V1/Go.php?Token=' . $result->Token,
                                $result->Token ) );
                        }
                        exit;
                    } else {
                        $tmp = 'خطایی رخ داده در اطلاعات پرداختی درگاه' . '<br>Error:' . $result->status . '<br> لطفا به مدیر اطلاع دهید <br><br>';
                        $tmp .= '<a href="' . get_option('siteurl') . '" class="mrbtn_red" > بازگشت به سایت </a>';
                        echo CreatePage_cf7('خطا در عملیات پرداخت', $tmp);
                    }
