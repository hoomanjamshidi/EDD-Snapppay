<?php
/*
Plugin Name: SnappPay EDD Gateway
Version: 1.0.3
Author: Hooman Jamshidi
Author URI: https://Hoomanjamshidi.ir
Update URI: false
Description: افزونه درگاه پرداخت اقساطی اسنپ‌پی برای افزونه Easy Digital Downloads
Requires at least: 5.0
Requires PHP: 7.4
*/

/**
 * فایل بوت‌استرپ افزونه.
 *
 * این فایل فقط ثابت‌های افزونه را تعریف می‌کند و بقیه فایل‌ها را بارگذاری می‌کند.
 * هیچ منطق تجاری‌ای نباید در این فایل نوشته شود.
 *
 * @package SnappPay_EDD
 */

// جلوگیری از دسترسی مستقیم به فایل از طریق مرورگر.
if ( ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

// مسیر نسبی افزونه، برای استفاده در هوک‌هایی مثل plugin_action_links_.
if ( ! defined( 'SNAPP_PAY_EDD_PLUGIN' ) ) {
	define( 'SNAPP_PAY_EDD_PLUGIN', plugin_basename( __FILE__ ) );
}

// مسیر مطلق فایل اصلی افزونه، برای هوک‌های فعال‌سازی/غیرفعال‌سازی.
if ( ! defined( 'SNAPP_PAY_EDD_PLUGIN_FILE' ) ) {
	define( 'SNAPP_PAY_EDD_PLUGIN_FILE', __FILE__ );
}

// مسیر پوشه افزونه روی دیسک (با اسلش پایانی).
if ( ! defined( 'SNAPP_PAY_EDD_PATH' ) ) {
	define( 'SNAPP_PAY_EDD_PATH', plugin_dir_path( __FILE__ ) );
}

// نشانی اینترنتی پوشه افزونه (با اسلش پایانی)، برای بارگذاری تصاویر.
if ( ! defined( 'SNAPP_PAY_EDD_URL' ) ) {
	define( 'SNAPP_PAY_EDD_URL', plugin_dir_url( __FILE__ ) );
}

// نسخه افزونه، برای گزارش‌گیری و نسخه‌بندی دارایی‌ها.
if ( ! defined( 'SNAPP_PAY_EDD_VERSION' ) ) {
	define( 'SNAPP_PAY_EDD_VERSION', '1.0.3' );
}

// شناسه درگاه در Easy Digital Downloads.
if ( ! defined( 'SNAPP_PAY_EDD_GATEWAY_ID' ) ) {
	define( 'SNAPP_PAY_EDD_GATEWAY_ID', 'snapppay' );
}

/**
 * حذف اعلان به‌روزرسانی افزونه در پیشخوان وردپرس.
 *
 * این افزونه از مخزن وردپرس توزیع نمی‌شود؛ بنابراین اگر افزونه دیگری با نام
 * مشابه در مخزن وجود داشته باشد نباید اعلان به‌روزرسانی اشتباه نمایش داده شود.
 *
 * @param mixed $value مقدار ترنزینت به‌روزرسانی افزونه‌ها.
 * @return mixed مقدار پاک‌سازی‌شده.
 */
function snapppay_edd_remove_update_notifications( $value ) {
	if ( isset( $value ) && is_object( $value ) && isset( $value->response ) ) {
		unset( $value->response[ SNAPP_PAY_EDD_PLUGIN ] );
	}

	return $value;
}
add_filter( 'site_transient_update_plugins', 'snapppay_edd_remove_update_notifications' );

// بارگذاری اجزای افزونه. ترتیب مهم است: ابتدا توابع کمکی، سپس کلاس API و درگاه، در پایان هوک‌ها.
require_once SNAPP_PAY_EDD_PATH . 'functions.php';
require_once SNAPP_PAY_EDD_PATH . 'class-snapppay-api.php';
require_once SNAPP_PAY_EDD_PATH . 'class-edd-gateway-snapppay.php';
require_once SNAPP_PAY_EDD_PATH . 'hooks.php';
