<?php
/**
 * هوک‌های چرخه عمر افزونه، پیشخوان و صفحه محصول.
 *
 * این فایل شامل هر چیزی است که خارج از جریان اصلی پرداخت اجرا می‌شود:
 * فعال‌سازی/غیرفعال‌سازی، اعلان‌های پیشخوان، همگام‌سازی وضعیت سفارش،
 * فیلد «نوع کمیسیون» دسته‌بندی‌ها و ویجت اقساط در صفحه محصول.
 *
 * @package SnappPay_EDD
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * بخش ۱: چرخه عمر افزونه
 * ====================================================================== */

/**
 * عملیات هنگام فعال‌سازی افزونه.
 *
 * @return void
 */
function snapppay_edd_activate() {
	// دریافت و ذخیره آدرس IP سرور برای ثبت در پنل اسنپ‌پی.
	snapppay_edd_fetch_server_ip();

	// ثبت رویداد فعال‌سازی.
	snapppay_edd_track_event( 'activate' );
}
register_activation_hook( SNAPP_PAY_EDD_PLUGIN_FILE, 'snapppay_edd_activate' );

/**
 * عملیات هنگام غیرفعال‌سازی افزونه.
 *
 * @return void
 */
function snapppay_edd_deactivate() {
	delete_option( 'snapppay-edd-web-server-ip' );
	delete_option( 'snapppay-edd-web-server-ip-show' );
	delete_transient( SnappPay_EDD_API::TOKEN_TRANSIENT );

	// حذف رویداد زمان‌بندی‌شده بررسی سفارش‌های معلق.
	$timestamp = wp_next_scheduled( SNAPPPAY_EDD_RECONCILE_EVENT );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, SNAPPPAY_EDD_RECONCILE_EVENT );
	}

	snapppay_edd_track_event( 'deactivate' );

	// حذف فایل لاگ.
	if ( file_exists( snapppay_edd_log_file() ) ) {
		wp_delete_file( snapppay_edd_log_file() );
	}
}
register_deactivation_hook( SNAPP_PAY_EDD_PLUGIN_FILE, 'snapppay_edd_deactivate' );

/**
 * عملیات هنگام حذف کامل افزونه.
 *
 * تنظیمات درگاه عمداً حذف نمی‌شوند تا در صورت نصب دوباره از دست نروند.
 *
 * @return void
 */
function snapppay_edd_uninstall() {
	delete_option( 'snapppay-edd-web-server-ip' );
	delete_option( 'snapppay-edd-web-server-ip-show' );
	delete_option( 'snapppay_edd_activation_tracker_data' );
	delete_option( 'snapppay_edd_checkout_fields_migrated' );
	delete_transient( SnappPay_EDD_API::TOKEN_TRANSIENT );

	if ( file_exists( ABSPATH . 'snapppay-edd.log' ) ) {
		wp_delete_file( ABSPATH . 'snapppay-edd.log' );
	}
}
register_uninstall_hook( SNAPP_PAY_EDD_PLUGIN_FILE, 'snapppay_edd_uninstall' );

/**
 * پاک‌سازی یک‌باره تنظیمات به‌جامانده از نسخه‌های پیشین.
 *
 * نسخه‌های قبلی افزونه فیلد «کدپستی» را به فرم تسویه‌حساب اضافه می‌کردند و فیلد
 * «شماره موبایل» هم به‌صورت پیش‌فرض در حالت «خودکار» بود. از این نسخه، افزونه
 * هیچ فیلدی را خودش به فرم اضافه نمی‌کند و فیلدهای فرم دقیقاً همان چیزی است که
 * در تنظیمات خود EDD تعیین شده است. این تابع تنظیمات کدپستی را حذف می‌کند و
 * حالت پیش‌فرض قدیمی فیلد موبایل را به «هرگز» تغییر می‌دهد. اگر مدیر سایت
 * عمداً «همیشه» را انتخاب کرده باشد، انتخابش دست‌نخورده می‌ماند.
 *
 * @return void
 */
function snapppay_edd_migrate_checkout_field_settings() {
	if ( 'done' === get_option( 'snapppay_edd_checkout_fields_migrated' ) ) {
		return;
	}

	$settings = get_option( 'edd_settings' );

	if ( is_array( $settings ) ) {
		$changed = false;

		foreach ( array( 'snapppay_postal_enabled', 'snapppay_postal_field' ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				unset( $settings[ $key ] );
				$changed = true;
			}
		}

		// «auto» مقدار پیش‌فرض نسخه‌های قبلی بود، نه انتخاب آگاهانه مدیر سایت.
		if ( isset( $settings['snapppay_mobile_field'] ) && 'auto' === $settings['snapppay_mobile_field'] ) {
			$settings['snapppay_mobile_field'] = 'never';
			$changed                           = true;
		}

		if ( $changed ) {
			update_option( 'edd_settings', $settings );
		}
	}

	update_option( 'snapppay_edd_checkout_fields_migrated', 'done' );
}
add_action( 'admin_init', 'snapppay_edd_migrate_checkout_field_settings' );

/* =========================================================================
 * بخش ۲: اعلان‌ها و لینک‌های پیشخوان
 * ====================================================================== */

/**
 * بررسی نصب و فعال بودن Easy Digital Downloads.
 *
 * اگر EDD فعال نباشد این افزونه هم غیرفعال می‌شود تا خطای مرگبار رخ ندهد.
 *
 * @return void
 */
function snapppay_edd_check_dependency() {
	if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// EDD فعال است؛ کاری لازم نیست.
	if ( class_exists( 'Easy_Digital_Downloads' ) ) {
		return;
	}

	add_action( 'admin_notices', 'snapppay_edd_missing_edd_notice' );

	deactivate_plugins( SNAPP_PAY_EDD_PLUGIN );

	// جلوگیری از نمایش پیام «افزونه فعال شد» در کنار پیام خطا.
	if ( isset( $_GET['activate'] ) ) {
		unset( $_GET['activate'] );
	}
}
add_action( 'admin_init', 'snapppay_edd_check_dependency' );

/**
 * نمایش آدرس IP سرور در پیشخوان، یک بار پس از فعال‌سازی افزونه.
 *
 * @return void
 */
function snapppay_edd_server_ip_notice() {
	$should_show = get_option( 'snapppay-edd-web-server-ip-show' );
	$ip          = get_option( 'snapppay-edd-web-server-ip' );

	if ( ! $ip || 'yes' !== $should_show ) {
		return;
	}

	// اعلان فقط یک بار نمایش داده می‌شود.
	delete_option( 'snapppay-edd-web-server-ip-show' );
	?>
	<div class="updated notice is-dismissible">
		<p style="display:flex;align-items:center;">
			<img src="<?php echo esc_url( SNAPP_PAY_EDD_URL . 'assets/images/logo-bg.png' ); ?>" alt="اسنپ‌پی" style="height:30px;margin-left:10px;" />
			افزونه اسنپ پی با موفقیت نصب شد. آدرس IP شما
			<strong style="color:#008efa;font-weight:bolder;">&nbsp;<?php echo esc_html( $ip ); ?>&nbsp;</strong>
			است؛ آن را در پنل اسنپ‌پی ثبت کنید.
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'snapppay_edd_server_ip_notice' );

/**
 * افزودن لینک «تنظیمات» و «پشتیبانی» به سطر افزونه در فهرست افزونه‌ها.
 *
 * @param array $links لینک‌های موجود.
 * @return array لینک‌های نهایی.
 */
function snapppay_edd_plugin_action_links( $links ) {
	$settings_url = add_query_arg(
		array(
			'post_type' => 'download',
			'page'      => 'edd-settings',
			'tab'       => 'gateways',
			'section'   => SNAPP_PAY_EDD_GATEWAY_ID,
		),
		admin_url( 'edit.php' )
	);

	$plugin_links = array(
		'<a href="' . esc_url( $settings_url ) . '">تنظیمات</a>',
		'<a href="https://snapppay.ir/" target="_blank" rel="noopener noreferrer">پشتیبانی</a>',
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . SNAPP_PAY_EDD_PLUGIN, 'snapppay_edd_plugin_action_links' );

/* =========================================================================
 * بخش ۳: همگام‌سازی وضعیت سفارش با درگاه
 * ====================================================================== */

/**
 * واکنش به تغییر وضعیت سفارش‌های پرداخت‌شده با اسنپ‌پی.
 *
 * دو رفتار پیاده‌سازی می‌شود:
 *   - تغییر وضعیت به «عودت‌شده» یا «باطل‌شده» → لغو تراکنش نزد اسنپ‌پی
 *   - تکمیل دستی سفارش توسط مدیر → اعلام اقلام و مبلغ نهایی به اسنپ‌پی
 *
 * @param string $old_status وضعیت پیشین سفارش.
 * @param string $new_status وضعیت جدید سفارش.
 * @param int    $order_id   شناسه سفارش.
 * @return void
 */
function snapppay_edd_order_status_changed( $old_status, $new_status, $order_id ) {
	// این توابع خودشان وضعیت سفارش را تغییر می‌دهند؛ گارد از اجرای بازگشتی جلوگیری می‌کند.
	static $processed = array();

	$order_id = absint( $order_id );

	if ( isset( $processed[ $order_id ] ) ) {
		return;
	}

	$order = edd_get_order( $order_id );

	// فقط سفارش‌های پرداخت‌شده با اسنپ‌پی بررسی می‌شوند.
	if ( empty( $order ) || SNAPP_PAY_EDD_GATEWAY_ID !== $order->gateway ) {
		return;
	}

	// اگر درگاه غیرفعال شده باشد نباید درخواستی به وب‌سرویس ارسال شود.
	if ( ! snapppay_edd_is_enabled() ) {
		return;
	}

	$processed[ $order_id ] = true;

	// سفارش عودت یا باطل شده است؛ تراکنش نزد اسنپ‌پی هم باید لغو شود.
	if ( in_array( $new_status, array( 'refunded', 'revoked' ), true ) ) {
		snapppay_edd_cancel_order( $order_id, $old_status );

		return;
	}

	// مدیر فروشگاه سفارش در انتظار را دستی تکمیل کرده است.
	$is_manual_completion = is_admin()
		&& 'complete' === $new_status
		&& in_array( $old_status, array( 'pending', 'processing' ), true );

	if ( ! $is_manual_completion ) {
		return;
	}

	$token = snapppay_edd_get_order_payment_token( $order_id );

	if ( ! $token ) {
		return;
	}

	$api      = new SnappPay_EDD_API();
	$response = $api->update( $order_id, $token );

	snapppay_edd_handle_response( $order_id, $response, 'update' );
}
add_action( 'edd_transition_order_status', 'snapppay_edd_order_status_changed', PHP_INT_MAX, 3 );

/* -------------------------------------------------------------------------
 * پیگیری خودکار سفارش‌های تأییدشده‌ای که تسویه نشده‌اند
 * ---------------------------------------------------------------------- */

/**
 * نام رویداد زمان‌بندی‌شده برای بررسی دوباره سفارش‌های معلق.
 */
const SNAPPPAY_EDD_RECONCILE_EVENT = 'snapppay_edd_reconcile_orders';

/**
 * زمان‌بندی بررسی ساعتی سفارش‌های تأییدشده ولی تسویه‌نشده.
 *
 * اگر در لحظه بازگشت از درگاه، درخواست تسویه با خطا مواجه شود، سفارش در وضعیت
 * «در انتظار» می‌ماند. این رویداد آن سفارش‌ها را دوباره تعیین تکلیف می‌کند تا
 * مبلغ تأییدشده بلاتکلیف نماند.
 *
 * @return void
 */
function snapppay_edd_schedule_reconcile() {
	if ( ! wp_next_scheduled( SNAPPPAY_EDD_RECONCILE_EVENT ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', SNAPPPAY_EDD_RECONCILE_EVENT );
	}
}
add_action( 'init', 'snapppay_edd_schedule_reconcile' );

/**
 * بررسی دوباره سفارش‌های اسنپ‌پی که تأیید شده‌اند ولی تسویه نشده‌اند.
 *
 * @return void
 */
function snapppay_edd_reconcile_orders() {
	if ( ! snapppay_edd_is_enabled() || ! function_exists( 'edd_get_orders' ) ) {
		return;
	}

	// فقط سفارش‌های در انتظارِ هفت روز اخیر بررسی می‌شوند.
	$orders = edd_get_orders(
		array(
			'gateway'      => SNAPP_PAY_EDD_GATEWAY_ID,
			'status'       => 'pending',
			'number'       => 25,
			'date_created' => array( 'after' => gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) ) ),
		)
	);

	if ( empty( $orders ) ) {
		return;
	}

	$api = new SnappPay_EDD_API();

	foreach ( $orders as $order ) {
		// فقط سفارش‌هایی که تراکنششان تأیید شده ولی تسویه نشده است.
		if ( 'VERIFY' !== edd_get_order_meta( $order->id, 'order_spp_status', true ) ) {
			continue;
		}

		$token = snapppay_edd_get_order_payment_token( $order->id );

		if ( ! $token ) {
			continue;
		}

		// وضعیت واقعی تراکنش از درگاه استعلام و بر اساس آن تصمیم‌گیری می‌شود.
		$status_response = $api->status( $token );

		if ( is_wp_error( $status_response ) || empty( $status_response['response']['status'] ) ) {
			continue;
		}

		snapppay_edd_handle_status_response(
			$order->id,
			$status_response['response']['status'],
			$api,
			$token,
			'pending'
		);
	}
}
add_action( SNAPPPAY_EDD_RECONCILE_EVENT, 'snapppay_edd_reconcile_orders' );

/* =========================================================================
 * بخش ۴: فیلد «نوع کمیسیون» برای دسته‌بندی محصولات
 * ====================================================================== */

/**
 * ثبت هوک‌های فیلد «نوع کمیسیون» در صورت فعال بودن این قابلیت.
 *
 * ثبت هوک‌ها روی «init» انجام می‌شود تا تنظیمات در زمان بارگذاری فایل خوانده نشود.
 *
 * @return void
 */
function snapppay_edd_register_category_fields() {
	if ( ! snapppay_edd_has_comission() ) {
		return;
	}

	add_action( 'download_category_add_form_fields', 'snapppay_edd_add_commission_field' );
	add_action( 'download_category_edit_form_fields', 'snapppay_edd_edit_commission_field' );
	add_action( 'created_download_category', 'snapppay_edd_save_commission_field' );
	add_action( 'edited_download_category', 'snapppay_edd_save_commission_field' );
}
add_action( 'init', 'snapppay_edd_register_category_fields' );

/**
 * نمایش فیلد «نوع کمیسیون» در فرم افزودن دسته‌بندی جدید.
 *
 * @return void
 */
function snapppay_edd_add_commission_field() {
	?>
	<div class="form-field">
		<label for="snp_commission_type">نوع کمیسیون</label>
		<input type="text" name="snp_commission_type" id="snp_commission_type" value="" />
		<p class="description">نوع کمیسیون مشخص‌شده برای این دسته‌بندی را وارد کنید.</p>
	</div>
	<?php
}

/**
 * نمایش فیلد «نوع کمیسیون» در فرم ویرایش دسته‌بندی.
 *
 * @param WP_Term $term ترم در حال ویرایش.
 * @return void
 */
function snapppay_edd_edit_commission_field( $term ) {
	$commission_type = get_term_meta( $term->term_id, 'snp_commission_type', true );
	?>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="snp_commission_type">نوع کمیسیون</label>
		</th>
		<td>
			<input type="text" name="snp_commission_type" id="snp_commission_type" value="<?php echo esc_attr( $commission_type ); ?>" />
			<p class="description">نوع کمیسیون مشخص‌شده برای این دسته‌بندی را وارد کنید.</p>
		</td>
	</tr>
	<?php
}

/**
 * ذخیره مقدار فیلد «نوع کمیسیون» هنگام ساخت یا ویرایش دسته‌بندی.
 *
 * @param int $term_id شناسه ترم.
 * @return void
 */
function snapppay_edd_save_commission_field( $term_id ) {
	// اگر فیلد ارسال نشده باشد (مثلاً ویرایش سریع) مقدار قبلی حفظ می‌شود.
	if ( ! isset( $_POST['snp_commission_type'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	$commission_type = sanitize_text_field( wp_unslash( $_POST['snp_commission_type'] ) );

	update_term_meta( $term_id, 'snp_commission_type', $commission_type );
}

/* =========================================================================
 * بخش ۵: ویجت اقساط در صفحه محصول
 * ====================================================================== */

/**
 * ثبت هوک ویجت صفحه محصول در صورت فعال بودن این قابلیت.
 *
 * @return void
 */
function snapppay_edd_register_product_widget() {
	if ( ! snapppay_edd_has_pdp() ) {
		return;
	}

	add_action( 'edd_purchase_link_end', 'snapppay_edd_render_product_widget', 10, 2 );
}
add_action( 'init', 'snapppay_edd_register_product_widget' );

/**
 * بررسی اینکه مبلغ محصول در بازه مجاز پرداخت اقساطی قرار دارد یا نه.
 *
 * @param mixed $amount مبلغ محصول در واحد پول فروشگاه.
 * @return bool درست اگر مبلغ در بازه مجاز باشد.
 */
function snapppay_edd_validate_widget_amount( $amount ) {
	$amount     = snapppay_edd_amount_by_currency( $amount );
	$min_amount = snapppay_edd_amount_by_currency( 50000 );
	$max_amount = snapppay_edd_amount_by_currency( 100000000 );

	return $amount >= $min_amount && $amount <= $max_amount;
}

/**
 * نمایش ویجت «هر قسط با اسنپ‌پی» زیر دکمه خرید در صفحه محصول.
 *
 * @param int   $download_id شناسه محصول دانلودی.
 * @param array $args        پارامترهای لینک خرید (استفاده نمی‌شود).
 * @return void
 */
function snapppay_edd_render_product_widget( $download_id, $args = array() ) {
	// ویجت فقط در صفحه اختصاصی محصول و یک بار نمایش داده می‌شود.
	if ( ! is_singular( 'download' ) ) {
		return;
	}

	static $rendered = array();

	$download_id = absint( $download_id );

	if ( isset( $rendered[ $download_id ] ) ) {
		return;
	}

	$rendered[ $download_id ] = true;

	// برای محصولات دارای قیمت متغیر، کمترین قیمت مبنا قرار می‌گیرد.
	$price = edd_has_variable_prices( $download_id )
		? edd_get_lowest_price_option( $download_id )
		: edd_get_download_price( $download_id );

	if ( empty( $price ) || ! snapppay_edd_validate_widget_amount( $price ) ) {
		return;
	}

	// اسنپ‌پی مبلغ را در چهار قسط ماهانه دریافت می‌کند.
	$installment = edd_currency_filter( edd_format_amount( $price / 4 ) );
	$logo        = snapppay_edd_get_widget_logo_url();
	?>
	<div class="snapppay-widget">
		<img src="<?php echo esc_url( $logo ); ?>" alt="اسنپ‌پی" class="snapppay-widget-image" />
		<div class="snapppay-widget-content">
			<div class="snapppay-widget-title">هر قسط با اسنپ‌پی: <?php echo esc_html( $installment ); ?></div>
			<div class="snapppay-widget-description">۴ قسط ماهانه. بدون سود، چک و ضامن.</div>
		</div>
	</div>
	<?php
	snapppay_edd_print_widget_styles();
}

/**
 * چاپ استایل‌های ویجت صفحه محصول، فقط یک بار در هر صفحه.
 *
 * @return void
 */
function snapppay_edd_print_widget_styles() {
	static $printed = false;

	if ( $printed ) {
		return;
	}

	$printed = true;

	// در تم تیره متن سفید و در تم روشن متن تیره استفاده می‌شود.
	$text_color = snapppay_edd_dark_pdp() ? '#ffffff' : '#1a1c23';
	?>
	<style>
		@font-face {
			font-family: IRANSansMobile-fa;
			font-style: normal;
			font-weight: 300;
			src: url('https://web-cdn.snapp.ir/fintech/fonts/iran-sans-fa/IRANSansWeb_Light.woff2') format('woff2'),
				url('https://web-cdn.snapp.ir/fintech/fonts/iran-sans-fa/IRANSansWeb_Light.woff') format('woff'),
				url('https://web-cdn.snapp.ir/fintech/fonts/iran-sans-fa/IRANSansWeb_Light.ttf') format('truetype');
			font-display: fallback;
		}

		@font-face {
			font-family: IRANSansMobile-fa;
			font-style: normal;
			font-weight: bold;
			src: url('https://web-cdn.snapp.ir/fintech/fonts/iran-sans-fa/IRANSansWeb_Bold.woff2') format('woff2'),
				url('https://web-cdn.snapp.ir/fintech/fonts/iran-sans-fa/IRANSansWeb_Bold.woff') format('woff'),
				url('https://web-cdn.snapp.ir/fintech/fonts/iran-sans-fa/IRANSansWeb_Bold.ttf') format('truetype');
			font-display: fallback;
		}

		.snapppay-widget {
			border-radius: 16px;
			border: 0.5px solid #ebecf2;
			padding: 16px;
			display: flex;
			align-items: center;
			gap: 8px;
			width: 100%;
			margin-top: 12px;
			box-sizing: border-box;
		}

		.snapppay-widget-image {
			width: 40px;
			height: auto;
			border-radius: 15px;
			flex: 0 0 auto;
		}

		.snapppay-widget-content {
			color: <?php echo esc_html( $text_color ); ?>;
		}

		.snapppay-widget-title {
			font-family: IRANSansMobile-fa, sans-serif;
			font-size: 14px;
			font-weight: bold;
			line-height: 180%;
			margin-bottom: 2px;
		}

		.snapppay-widget-description {
			font-family: IRANSansMobile-fa, sans-serif;
			font-weight: 300;
			font-size: 12px;
			line-height: 180%;
			letter-spacing: -0.36px;
		}
	</style>
	<?php
}

/* =========================================================================
 * بخش ۶: تنظیمات ارتباط شبکه
 * ====================================================================== */

/**
 * تنظیم زمان انتظار و گزینه‌های SSL برای درخواست‌های cURL.
 *
 * @param resource|CurlHandle $handle دسته cURL.
 * @return void
 */
function snapppay_edd_http_api_curl( $handle ) {
	curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 30 );
	curl_setopt( $handle, CURLOPT_TIMEOUT, 30 );
	curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, false );
	curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
}
add_action( 'http_api_curl', 'snapppay_edd_http_api_curl', PHP_INT_MAX );

// غیرفعال کردن بررسی گواهی SSL؛ برخی سرورهای میزبان ایرانی گواهی‌نامه‌های به‌روز ندارند.
add_filter( 'https_ssl_verify', '__return_false' );
