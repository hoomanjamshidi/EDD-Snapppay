<?php
/**
 * ثبت درگاه اسنپ‌پی در Easy Digital Downloads و مدیریت کامل جریان پرداخت.
 *
 * این فایل شامل چهار بخش است:
 *   ۱. معرفی درگاه و تنظیمات آن در پیشخوان
 *   ۲. رفتار درگاه در صفحه تسویه‌حساب (اعتبارسنجی، فیلدها، نمایش)
 *   ۳. ساخت سفارش و انتقال کاربر به صفحه پرداخت اسنپ‌پی
 *   ۴. پردازش بازگشت کاربر از درگاه و نهایی‌سازی سفارش
 *
 * @package SnappPay_EDD
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * بخش ۱: معرفی درگاه و تنظیمات
 * ====================================================================== */

/**
 * افزودن اسنپ‌پی به فهرست درگاه‌های پرداخت Easy Digital Downloads.
 *
 * @param array $gateways فهرست درگاه‌های ثبت‌شده.
 * @return array فهرست به‌همراه اسنپ‌پی.
 */
function snapppay_edd_register_gateway( $gateways ) {
	$gateways[ SNAPP_PAY_EDD_GATEWAY_ID ] = array(
		'admin_label'    => 'اسنپ‌پی',
		'checkout_label' => 'پرداخت اقساطیِ اسنپ پی',
		'supports'       => array(),
	);

	return $gateways;
}
add_filter( 'edd_payment_gateways', 'snapppay_edd_register_gateway' );

// این درگاه فرم اطلاعات کارت ندارد؛ پرداخت روی سایت اسنپ‌پی انجام می‌شود.
add_action( 'edd_' . SNAPP_PAY_EDD_GATEWAY_ID . '_cc_form', '__return_false' );

/**
 * افزودن زیربخش «اسنپ‌پی» به تب «درگاه‌های پرداخت» در تنظیمات EDD.
 *
 * @param array $sections زیربخش‌های موجود.
 * @return array زیربخش‌ها به‌همراه اسنپ‌پی.
 */
function snapppay_edd_register_settings_section( $sections ) {
	$sections[ SNAPP_PAY_EDD_GATEWAY_ID ] = 'اسنپ‌پی';

	return $sections;
}
add_filter( 'edd_settings_sections_gateways', 'snapppay_edd_register_settings_section' );

/**
 * تعریف فیلدهای تنظیمات درگاه اسنپ‌پی.
 *
 * @return array آرایه فیلدها با ساختار موردانتظار API تنظیمات EDD.
 */
function snapppay_edd_settings_fields() {
	$server_ip = get_option( 'snapppay-edd-web-server-ip' );

	$fields = array(
		/* ---------- تنظیمات پایه ای ---------- */
		'snapppay_base_config'       => array(
			'id'   => 'snapppay_base_config',
			'name' => '<h3 style="margin:0;">تنظیمات پایه ای</h3>',
			'type' => 'header',
		),
		'snapppay_server_ip'         => array(
			'id'   => 'snapppay_server_ip',
			'name' => 'آدرس IP سرور شما',
			'desc' => $server_ip
				? '<code style="color:#008efa;font-weight:bold;font-size:14px;">' . esc_html( $server_ip ) . '</code>'
					. '<p class="description">این آدرس را در پنل اسنپ‌پی در فهرست آدرس‌های مجاز ثبت کنید.</p>'
				: '<p class="description">آدرس IP سرور هنوز دریافت نشده است. افزونه را غیرفعال و دوباره فعال کنید.</p>',
			'type' => 'descriptive_text',
		),
		'snapppay_description'       => array(
			'id'            => 'snapppay_description',
			'name'          => 'توضیحات درگاه',
			'type'          => 'text',
			'std'           => 'پرداخت اقساطی اسنپ پی',
			'tooltip_title' => 'توضیحات درگاه',
			'tooltip_desc'  => 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد.',
		),
		'snapppay_mobile_enabled'    => array(
			'id'            => 'snapppay_mobile_enabled',
			'name'          => 'شماره موبایل',
			'desc'          => 'موبایل اجباری باشد',
			'type'          => 'checkbox',
			'tooltip_title' => 'اجباری بودن موبایل',
			'tooltip_desc'  => 'برای اجباری بودن موبایل چک باکس را تیک بزنید. شماره از فیلد «شماره تماس» خود EDD (یا فیلد اختیاری افزونه) خوانده می‌شود و اسنپ‌پی از آن برای احراز هویت خریدار استفاده می‌کند.',
		),
		'snapppay_mobile_field'      => array(
			'id'            => 'snapppay_mobile_field',
			'name'          => 'نمایش فیلد شماره موبایل',
			'desc'          => 'مشخص می‌کند فیلد شماره موبایل اسنپ‌پی به فرم تسویه‌حساب اضافه شود یا نه.',
			'type'          => 'select',
			'std'           => 'never',
			'options'       => array(
				'never'  => 'هرگز نمایش داده نشود (پیش‌فرض؛ فقط فیلدهای خود EDD)',
				'auto'   => 'خودکار (فقط اگر خود EDD فیلد شماره تماس نداشته باشد)',
				'always' => 'همیشه نمایش داده شود',
			),
			'tooltip_title' => 'نمایش فیلد شماره موبایل',
			'tooltip_desc'  => 'به‌صورت پیش‌فرض افزونه هیچ فیلدی به فرم تسویه‌حساب اضافه نمی‌کند و شماره موبایل از فیلد «شماره تماس» خود EDD خوانده می‌شود؛ این فیلد را می‌توانید در «تنظیمات ← پرداخت‌ها ← تسویه‌حساب» فعال کنید. اگر مایل هستید افزونه خودش فیلد موبایل را اضافه کند، این گزینه را روی «خودکار» یا «همیشه» بگذارید.',
		),

		/* ---------- تنظیمات حساب اسنپ پی ---------- */
		'snapppay_account_config'    => array(
			'id'   => 'snapppay_account_config',
			'name' => '<h3 style="margin:0;">تنظیمات حساب اسنپ پی</h3>',
			'type' => 'header',
		),
		'snapppay_default_gateway'   => array(
			'id'            => 'snapppay_default_gateway',
			'name'          => 'درگاه پیش فرض',
			'desc'          => 'فعالسازی/غیرفعالسازی اسنپ پی به عنوان درگاه پیش فرض',
			'type'          => 'checkbox',
			'tooltip_title' => 'درگاه پیش فرض',
			'tooltip_desc'  => 'در صورتی که تمایل دارید درگاه پرداخت اسنپ پی پیشفرض باشد باید چک باکس را تیک بزنید.',
		),
		'snapppay_has_comission'     => array(
			'id'            => 'snapppay_has_comission',
			'name'          => 'نوع کمیسیون',
			'desc'          => 'فعالسازی/غیرفعالسازی نوع کمیسیون برای دسته بندی ها',
			'type'          => 'checkbox',
			'tooltip_title' => 'نوع کمیسیون دسته بندی ها',
			'tooltip_desc'  => 'برای فعالسازی نوع کمیسیون ها باید چک باکس را تیک بزنید. با فعال شدن این گزینه، فیلد «نوع کمیسیون» به دسته‌بندی محصولات دانلودی اضافه می‌شود.',
		),
		'snapppay_has_pdp'           => array(
			'id'            => 'snapppay_has_pdp',
			'name'          => 'ویجت صفحه محصول',
			'desc'          => 'فعالسازی/غیرفعالسازی ویجت اسنپ پی در صفحه محصول',
			'type'          => 'checkbox',
			'tooltip_title' => 'ویجت صفحه محصول',
			'tooltip_desc'  => 'برای فعالسازی نمایش ویجت اسنپ پی در صفحه محصول تیک بزنید.',
		),
		'snapppay_dark_pdp'          => array(
			'id'            => 'snapppay_dark_pdp',
			'name'          => 'تم ویجت',
			'desc'          => 'استفاده از تم تیره ویجت اسنپ پی در صفحه محصول',
			'type'          => 'checkbox',
			'tooltip_title' => 'تم تیره ویجت',
			'tooltip_desc'  => 'با انتخاب تم تیره، نوشته‌ها با رنگ سفید نمایش داده می‌شود و در پس‌زمینهٔ روشن خوانا نیست.',
		),
		'snapppay_base_url'          => array(
			'id'            => 'snapppay_base_url',
			'name'          => 'آدرس درگاه',
			'type'          => 'text',
			'std'           => 'https://api.snapppay.ir/',
			'tooltip_title' => 'آدرس درگاه',
			'tooltip_desc'  => 'آدرس API درگاه اسنپ پی.',
		),
		'snapppay_client_id'         => array(
			'id'            => 'snapppay_client_id',
			'name'          => 'شماره مشتری',
			'type'          => 'text',
			'tooltip_title' => 'شماره مشتری',
			'tooltip_desc'  => 'مقدار Client ID که از اسنپ‌پی دریافت کرده‌اید.',
		),
		'snapppay_client_secret'     => array(
			'id'            => 'snapppay_client_secret',
			'name'          => 'کد امنیتی مشتری',
			'type'          => 'password',
			'tooltip_title' => 'کد امنیتی مشتری',
			'tooltip_desc'  => 'مقدار Client Secret که از اسنپ‌پی دریافت کرده‌اید.',
		),
		'snapppay_client_username'   => array(
			'id'            => 'snapppay_client_username',
			'name'          => 'نام کاربری مشتری',
			'type'          => 'text',
			'tooltip_title' => 'نام کاربری مشتری',
			'tooltip_desc'  => 'نام کاربری حساب پذیرندگی اسنپ‌پی.',
		),
		'snapppay_client_password'   => array(
			'id'            => 'snapppay_client_password',
			'name'          => 'رمزعبور مشتری',
			'type'          => 'password',
			'tooltip_title' => 'رمزعبور مشتری',
			'tooltip_desc'  => 'رمزعبور حساب پذیرندگی اسنپ‌پی.',
		),

		/* ---------- تنظیمات عملیات پرداخت ---------- */
		'snapppay_payment_config'    => array(
			'id'   => 'snapppay_payment_config',
			'name' => '<h3 style="margin:0;">تنظیمات عملیات پرداخت</h3>',
			'type' => 'header',
		),
		'snapppay_success_message'   => array(
			'id'   => 'snapppay_success_message',
			'name' => 'پیام پرداخت موفق',
			'desc' => 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید. همچنین می توانید از شورت کد <code>{transactionId}</code> برای نمایش شماره تراکنش و <code>{paymentToken}</code> برای نمایش توکن پرداخت استفاده نمایید.',
			'type' => 'textarea',
			'std'  => 'با تشکر از شما . سفارش شما با موفقیت پرداخت شد .',
		),
		'snapppay_failed_message'    => array(
			'id'   => 'snapppay_failed_message',
			'name' => 'پیام پرداخت ناموفق',
			'desc' => 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید. همچنین می توانید از شورت کد <code>{fault}</code> برای نمایش دلیل خطای رخ داده استفاده نمایید. این دلیل خطا از سایت اسنپ پی ارسال میگردد.',
			'type' => 'textarea',
			'std'  => 'پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .',
		),
		'snapppay_cancelled_message' => array(
			'id'   => 'snapppay_cancelled_message',
			'name' => 'پیام انصراف از پرداخت',
			'desc' => 'متن پیامی که میخواهید بعد از انصراف کاربر از پرداخت نمایش دهید را وارد نمایید. این پیام بعد از بازگشت از درگاه نمایش داده خواهد شد.',
			'type' => 'textarea',
			'std'  => 'پرداخت به دلیل انصراف شما ناتمام باقی ماند .',
		),
	);

	/**
	 * امکان تغییر فیلدهای تنظیمات درگاه.
	 *
	 * @param array $fields فیلدهای تنظیمات.
	 */
	return apply_filters( 'snapppay_edd_settings_fields', $fields );
}

/**
 * افزودن فیلدهای درگاه اسنپ‌پی به تنظیمات EDD.
 *
 * @param array $settings تنظیمات تب «درگاه‌های پرداخت».
 * @return array تنظیمات به‌همراه بخش اسنپ‌پی.
 */
function snapppay_edd_register_settings( $settings ) {
	$settings[ SNAPP_PAY_EDD_GATEWAY_ID ] = snapppay_edd_settings_fields();

	return $settings;
}
add_filter( 'edd_settings_gateways', 'snapppay_edd_register_settings' );

/**
 * حذف توکن دسترسی ذخیره‌شده هنگام تغییر اطلاعات حساب اسنپ‌پی.
 *
 * اگر اطلاعات حساب عوض شود، توکن قبلی دیگر معتبر نیست و باید دوباره گرفته شود.
 *
 * @param mixed $old_value مقدار قبلی آپشن edd_settings.
 * @param mixed $new_value مقدار جدید آپشن edd_settings.
 * @return void
 */
function snapppay_edd_settings_after_save( $old_value, $new_value ) {
	$credential_keys = array(
		'snapppay_base_url',
		'snapppay_client_id',
		'snapppay_client_secret',
		'snapppay_client_username',
		'snapppay_client_password',
	);

	$old_value = is_array( $old_value ) ? $old_value : array();
	$new_value = is_array( $new_value ) ? $new_value : array();

	foreach ( $credential_keys as $key ) {
		$old = isset( $old_value[ $key ] ) ? $old_value[ $key ] : null;
		$new = isset( $new_value[ $key ] ) ? $new_value[ $key ] : null;

		if ( $old !== $new ) {
			delete_transient( SnappPay_EDD_API::TOKEN_TRANSIENT );
			return;
		}
	}
}
add_action( 'update_option_edd_settings', 'snapppay_edd_settings_after_save', 10, 2 );

/* =========================================================================
 * بخش ۲: رفتار درگاه در صفحه تسویه‌حساب
 * ====================================================================== */

/**
 * نگهداری توضیحاتی که وب‌سرویس اسنپ‌پی برای سبد خرید جاری برمی‌گرداند.
 *
 * چون EDD برچسب درگاه را با esc_html چاپ می‌کند، توضیحات و لوگو باید جداگانه
 * زیر گزینه درگاه نمایش داده شوند.
 *
 * @param string|null $description مقدار جدید، یا null برای فقط خواندن.
 * @return string توضیحات جاری.
 */
function snapppay_edd_offer_description( $description = null ) {
	static $stored = '';

	if ( null !== $description ) {
		$stored = (string) $description;
	}

	return $stored;
}

/**
 * استعلام واجد شرایط بودن یک مبلغ برای پرداخت اقساطی، با حافظه موقت درون‌درخواستی.
 *
 * صفحه تسویه‌حساب ممکن است فهرست درگاه‌ها را چند بار در یک درخواست بخواند؛ این
 * حافظه موقت جلوی ارسال درخواست‌های تکراری به وب‌سرویس را می‌گیرد.
 *
 * @param int $amount مبلغ سبد خرید به ریال.
 * @return array|WP_Error پاسخ وب‌سرویس یا خطا.
 */
function snapppay_edd_get_eligibility( $amount ) {
	static $cache = array();

	$key = (string) $amount;

	if ( ! array_key_exists( $key, $cache ) ) {
		$api           = new SnappPay_EDD_API();
		$cache[ $key ] = $api->is_merchant_eligible( $amount );
	}

	return $cache[ $key ];
}

/**
 * محاسبه مبلغ سبد خرید جاری به ریال.
 *
 * @return int مبلغ سبد خرید به ریال.
 */
function snapppay_edd_get_cart_amount_in_rial() {
	return snapppay_edd_amount_by_currency( edd_get_cart_total(), edd_get_currency() );
}

/**
 * بررسی اینکه سبد خرید جاری واجد شرایط پرداخت اقساطی هست یا نه.
 *
 * @return bool درست اگر سبد خرید واجد شرایط باشد.
 */
function snapppay_edd_is_cart_eligible() {
	$response = snapppay_edd_get_eligibility( snapppay_edd_get_cart_amount_in_rial() );

	return ! is_wp_error( $response ) && ! empty( $response['response']['eligible'] );
}

/**
 * حذف یا به‌روزرسانی گزینه اسنپ‌پی در فهرست درگاه‌های صفحه تسویه‌حساب.
 *
 * درگاه فقط زمانی نمایش داده می‌شود که وب‌سرویس اسنپ‌پی مبلغ سبد خرید را واجد
 * شرایط پرداخت اقساطی اعلام کند.
 *
 * @param array $gateways فهرست درگاه‌های فعال.
 * @return array فهرست نهایی درگاه‌ها.
 */
function snapppay_edd_filter_enabled_gateways( $gateways ) {
	// اگر درگاه در تنظیمات EDD فعال نشده باشد کاری لازم نیست.
	if ( ! isset( $gateways[ SNAPP_PAY_EDD_GATEWAY_ID ] ) ) {
		return $gateways;
	}

	// جلوگیری از فراخوانی بازگشتی در صورتی که توابع داخلی دوباره این فیلتر را اجرا کنند.
	static $is_running = false;

	if ( $is_running ) {
		return $gateways;
	}

	// استعلام فقط در صفحه تسویه‌حساب فروشگاه انجام می‌شود، نه در پیشخوان.
	if ( is_admin() || ! function_exists( 'edd_is_checkout' ) || ! edd_is_checkout() ) {
		return $gateways;
	}

	$is_running = true;

	$response = snapppay_edd_get_eligibility( snapppay_edd_get_cart_amount_in_rial() );

	$is_running = false;

	// خطای تنظیمات یا ارتباط: درگاه نمایش داده نمی‌شود و خطا به مدیر اطلاع داده می‌شود.
	if ( is_wp_error( $response ) ) {
		edd_set_error( 'snapppay_eligibility', $response->get_error_message() );
		unset( $gateways[ SNAPP_PAY_EDD_GATEWAY_ID ] );

		return $gateways;
	}

	// مبلغ سبد خرید واجد شرایط پرداخت اقساطی نیست.
	if ( empty( $response['response']['eligible'] ) ) {
		unset( $gateways[ SNAPP_PAY_EDD_GATEWAY_ID ] );

		return $gateways;
	}

	// عنوان پیشنهادی اسنپ‌پی روی برچسب گزینه درگاه می‌نشیند.
	if ( ! empty( $response['response']['title_message'] ) ) {
		$gateways[ SNAPP_PAY_EDD_GATEWAY_ID ]['checkout_label'] = $response['response']['title_message'];
	}

	// توضیحات برای نمایش جداگانه زیر گزینه درگاه نگهداری می‌شود.
	$description = ! empty( $response['response']['description'] )
		? $response['response']['description']
		: snapppay_edd_get_settings()['description'];

	snapppay_edd_offer_description( $description );

	return $gateways;
}
add_filter( 'edd_enabled_payment_gateways', 'snapppay_edd_filter_enabled_gateways', PHP_INT_MAX );

/**
 * انتخاب خودکار اسنپ‌پی به عنوان درگاه پیش‌فرض.
 *
 * انتخاب صریح کاربر همیشه در اولویت است و بازنویسی نمی‌شود.
 *
 * @param string       $gateway درگاه انتخاب‌شده توسط EDD.
 * @param string|false $chosen  درگاهی که کاربر صراحتاً انتخاب کرده است.
 * @return string شناسه درگاه نهایی.
 */
function snapppay_edd_chosen_gateway( $gateway, $chosen = false ) {
	// کاربر خودش درگاه را انتخاب کرده است.
	if ( ! empty( $chosen ) ) {
		return $gateway;
	}

	// این گزینه در تنظیمات فعال نشده است.
	if ( ! snapppay_edd_is_default_gateway() ) {
		return $gateway;
	}

	// سبد خرید رایگان است و EDD باید از درگاه دستی استفاده کند.
	if ( edd_get_cart_subtotal() <= 0 ) {
		return $gateway;
	}

	// فقط در صفحه تسویه‌حساب و وقتی سبد واجد شرایط باشد.
	if ( is_admin() || ! edd_is_checkout() || ! snapppay_edd_is_cart_eligible() ) {
		return $gateway;
	}

	return SNAPP_PAY_EDD_GATEWAY_ID;
}
add_filter( 'edd_chosen_gateway', 'snapppay_edd_chosen_gateway', 10, 2 );

/**
 * نمایش لوگوی اسنپ‌پی کنار عنوان درگاه در فهرست روش‌های پرداخت.
 *
 * EDD برچسب درگاه را با esc_html چاپ می‌کند و امکان افزودن تصویر به آن نیست؛
 * بنابراین لوگو با CSS و به‌صورت ::before روی برچسب گزینه درگاه می‌نشیند.
 *
 * @return void
 */
function snapppay_edd_render_gateway_icon_style() {
	$logo       = snapppay_edd_get_gateway_logo_url();
	$gateway_id = SNAPP_PAY_EDD_GATEWAY_ID;
	?>
	<style>
		#edd-gateway-option-<?php echo esc_attr( $gateway_id ); ?>::before,
		label[for="edd-gateway-<?php echo esc_attr( $gateway_id ); ?>"]::before {
			content: "";
			display: inline-block;
			flex: 0 0 auto;
			vertical-align: middle;
			width: 28px;
			height: 28px;
			margin-inline-end: 8px;
			border-radius: 6px;
			background-image: url("<?php echo esc_url( $logo ); ?>");
			background-repeat: no-repeat;
			background-position: center;
			background-size: contain;
		}
	</style>
	<?php
}
add_action( 'edd_payment_mode_top', 'snapppay_edd_render_gateway_icon_style' );

/**
 * نمایش لوگو و توضیحات اسنپ‌پی زیر فهرست روش‌های پرداخت.
 *
 * @return void
 */
function snapppay_edd_render_gateway_description() {
	$description = snapppay_edd_offer_description();

	if ( '' === trim( $description ) ) {
		return;
	}
	?>
	<div class="snapppay-gateway-note" id="snapppay-gateway-note">
		<img src="<?php echo esc_url( snapppay_edd_get_gateway_logo_url() ); ?>" alt="اسنپ‌پی" class="snapppay-gateway-note__logo" />
		<span class="snapppay-gateway-note__text"><?php echo esc_html( $description ); ?></span>
	</div>
	<style>
		.snapppay-gateway-note {
			display: flex;
			align-items: center;
			gap: 8px;
			margin-top: 8px;
			padding: 8px 12px;
			border: 1px solid #ebecf2;
			border-radius: 8px;
			font-size: 13px;
			line-height: 1.8;
		}
		.snapppay-gateway-note__logo {
			width: 32px;
			height: auto;
			flex: 0 0 auto;
		}
	</style>
	<?php
}
add_action( 'edd_payment_mode_after_gateways', 'snapppay_edd_render_gateway_description' );

/* -------------------------------------------------------------------------
 * فیلدهای اختصاصی اسنپ‌پی در فرم تسویه‌حساب
 * ---------------------------------------------------------------------- */

/**
 * بررسی اینکه آیا اسنپ‌پی در حال حاضر درگاه انتخاب‌شده است یا نه.
 *
 * @return bool
 */
function snapppay_edd_is_chosen_gateway() {
	return function_exists( 'edd_get_chosen_gateway' )
		&& SNAPP_PAY_EDD_GATEWAY_ID === edd_get_chosen_gateway();
}

/**
 * خواندن شماره موبایل ذخیره‌شده کاربر برای پیش‌پر کردن فیلد تسویه‌حساب.
 *
 * @return string شماره موبایل، یا رشته خالی.
 */
function snapppay_edd_get_saved_user_mobile() {
	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return '';
	}

	// ابتدا مقدار ذخیره‌شده توسط خود افزونه، سپس شماره تماس مشتری در EDD و در
	// نهایت مقدار رایج سایر افزونه‌ها.
	$mobile = get_user_meta( $user_id, '_snapppay_mobile', true );

	if ( empty( $mobile ) && function_exists( 'edd_get_customer_address' ) ) {
		$address = edd_get_customer_address( $user_id );
		$mobile  = ! empty( $address['phone'] ) ? $address['phone'] : '';
	}

	if ( empty( $mobile ) ) {
		$mobile = get_user_meta( $user_id, 'billing_phone', true );
	}

	return is_string( $mobile ) ? $mobile : '';
}

/**
 * نمایش فیلد اختیاری «شماره موبایل» در فرم تسویه‌حساب.
 *
 * افزونه به‌صورت پیش‌فرض هیچ فیلدی به فرم تسویه‌حساب اضافه نمی‌کند و فیلدهای فرم
 * دقیقاً همان چیزی است که در «تنظیمات ← پرداخت‌ها ← تسویه‌حساب» خود EDD تعیین
 * شده است. فقط اگر مدیر سایت گزینه «نمایش فیلد شماره موبایل» را روی «خودکار» یا
 * «همیشه» بگذارد، این فیلد اضافه می‌شود.
 *
 * @return void
 */
function snapppay_edd_render_checkout_fields() {
	// فیلد فقط وقتی نمایش داده می‌شود که اسنپ‌پی درگاه انتخاب‌شده باشد.
	if ( ! snapppay_edd_is_chosen_gateway() ) {
		return;
	}

	if ( ! snapppay_edd_should_render_mobile_field() ) {
		return;
	}

	$mobile_required = snapppay_edd_mobile_required();
	$mobile          = snapppay_edd_get_saved_user_mobile();
	?>
	<p id="edd-snapppay-mobile-wrap">
		<label class="edd-label" for="snapppay-mobile">
			شماره موبایل
			<?php if ( $mobile_required ) : ?>
				<span class="edd-required-indicator">*</span>
			<?php endif; ?>
		</label>
		<span class="edd-description">شماره موبایلی که حساب اسنپ‌پی شما با آن ثبت شده است.</span>
		<input
			class="edd-input<?php echo $mobile_required ? ' required' : ''; ?>"
			type="tel"
			inputmode="numeric"
			autocomplete="tel"
			name="snapppay_mobile"
			id="snapppay-mobile"
			placeholder="۰۹۱۲۳۴۵۶۷۸۹"
			value="<?php echo esc_attr( $mobile ); ?>"
			<?php echo $mobile_required ? 'required' : ''; ?>
		/>
	</p>
	<?php
}
add_action( 'edd_purchase_form_user_info_fields', 'snapppay_edd_render_checkout_fields' );

/**
 * اعتبارسنجی فیلدهای اسنپ‌پی هنگام ثبت سفارش.
 *
 * @param array $valid_data داده‌های اعتبارسنجی‌شده EDD (استفاده نمی‌شود).
 * @param array $post       داده‌های خام ارسال‌شده از فرم.
 * @return void
 */
function snapppay_edd_validate_checkout_fields( $valid_data, $post ) {
	// اعتبارسنجی فقط برای سفارش‌هایی که با اسنپ‌پی پرداخت می‌شوند.
	$gateway = isset( $post['edd-gateway'] ) ? sanitize_key( $post['edd-gateway'] ) : '';

	if ( SNAPP_PAY_EDD_GATEWAY_ID !== $gateway ) {
		return;
	}

	// بررسی شماره موبایل؛ اگر فیلد اسنپ‌پی نمایش داده نشده باشد، مقدار از فیلد
	// شماره تماس خود EDD خوانده می‌شود.
	if ( snapppay_edd_mobile_required() ) {
		$mobile = snapppay_edd_get_posted_mobile( $post );

		if ( ! snapppay_edd_mobile( $mobile ) ) {
			edd_set_error( 'snapppay_mobile', 'لطفاً شماره موبایل معتبر وارد کنید.' );
		}
	}
}
add_action( 'edd_checkout_error_checks', 'snapppay_edd_validate_checkout_fields', 10, 2 );

/* =========================================================================
 * بخش ۳: ساخت سفارش و انتقال به درگاه
 * ====================================================================== */

/**
 * ساخت نشانی بازگشت از درگاه برای یک سفارش.
 *
 * @param int $order_id شناسه سفارش EDD.
 * @return string نشانی کامل بازگشت.
 */
function snapppay_edd_get_callback_url( $order_id ) {
	return add_query_arg(
		array(
			'edd-listener' => SNAPP_PAY_EDD_GATEWAY_ID,
			'edd_order'    => absint( $order_id ),
		),
		home_url( 'index.php' )
	);
}

/**
 * پردازش خرید: ساخت سفارش «در انتظار پرداخت» و انتقال مستقیم کاربر به درگاه.
 *
 * @param array $purchase_data داده‌های خرید که EDD ارسال می‌کند.
 * @return void
 */
function snapppay_edd_process_purchase( $purchase_data ) {
	// بررسی nonce برای جلوگیری از ثبت سفارش جعلی.
	if ( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'edd-gateway' ) ) {
		wp_die( 'اعتبارسنجی درخواست ناموفق بود.', 'خطا', array( 'response' => 403 ) );
	}

	$order_data = array(
		'price'        => $purchase_data['price'],
		'date'         => $purchase_data['date'],
		'user_email'   => $purchase_data['user_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency'     => edd_get_currency(),
		'downloads'    => $purchase_data['downloads'],
		'user_info'    => $purchase_data['user_info'],
		'cart_details' => $purchase_data['cart_details'],
		'gateway'      => SNAPP_PAY_EDD_GATEWAY_ID,
		'status'       => 'pending',
	);

	$order_id = edd_insert_payment( $order_data );

	// ساخت سفارش ناموفق بود؛ خطا ثبت و کاربر به تسویه‌حساب بازگردانده می‌شود.
	if ( ! $order_id ) {
		edd_record_gateway_error(
			'خطای ساخت سفارش',
			sprintf( 'ساخت سفارش برای درگاه اسنپ‌پی ناموفق بود. داده سفارش: %s', wp_json_encode( $order_data ) )
		);
		edd_set_error( 'snapppay_order_failed', 'ثبت سفارش با خطا مواجه شد. لطفاً دوباره تلاش کنید.' );
		edd_send_back_to_checkout( '?payment-mode=' . SNAPP_PAY_EDD_GATEWAY_ID );
	}

	// ذخیره شماره موبایل روی سفارش و پروفایل کاربر.
	snapppay_edd_store_customer_fields( $order_id, $purchase_data );

	// شناسه تراکنش یکتا برای پیگیری در پنل اسنپ‌پی.
	$transaction_id = time() . '-' . $order_id;
	edd_update_order_meta( $order_id, '_transactionId', $transaction_id );

	// انتقال مستقیم کاربر به صفحه پرداخت اسنپ‌پی؛ صفحه واسطی نمایش داده نمی‌شود.
	snapppay_edd_send_to_gateway( $order_id );
	exit;
}
add_action( 'edd_gateway_' . SNAPP_PAY_EDD_GATEWAY_ID, 'snapppay_edd_process_purchase' );

/**
 * ذخیره شماره موبایل واردشده در فرم تسویه‌حساب.
 *
 * @param int   $order_id      شناسه سفارش EDD.
 * @param array $purchase_data داده‌های خرید ارسالی EDD.
 * @return void
 */
function snapppay_edd_store_customer_fields( $order_id, $purchase_data ) {
	$post_data = isset( $purchase_data['post_data'] ) && is_array( $purchase_data['post_data'] )
		? $purchase_data['post_data']
		: array();

	$user_id = ! empty( $purchase_data['user_info']['id'] ) ? absint( $purchase_data['user_info']['id'] ) : 0;

	// شماره موبایل: به قالب استاندارد تبدیل می‌شود تا اسنپ‌پی آن را بپذیرد.
	$raw_mobile = snapppay_edd_get_posted_mobile( $post_data );

	// اگر هیچ‌کدام از فیلدهای فرم پر نشده باشد، شماره تماس نشانی سفارش استفاده می‌شود.
	if ( '' === $raw_mobile && ! empty( $purchase_data['user_info']['address']['phone'] ) ) {
		$raw_mobile = sanitize_text_field( $purchase_data['user_info']['address']['phone'] );
	}

	if ( '' !== $raw_mobile ) {
		$mobile = snapppay_edd_mobile( $raw_mobile );

		if ( $mobile ) {
			edd_update_order_meta( $order_id, '_snapppay_mobile', $mobile );

			if ( $user_id ) {
				update_user_meta( $user_id, '_snapppay_mobile', $mobile );
			}
		}
	}
}

/**
 * دریافت توکن پرداخت و انتقال کاربر به صفحه پرداخت اسنپ‌پی.
 *
 * این تابع همیشه با redirect و exit پایان می‌یابد؛ در صورت خطا کاربر با پیام
 * خطا به صفحه تسویه‌حساب بازگردانده می‌شود.
 *
 * @param int $order_id شناسه سفارش EDD.
 * @return void
 */
function snapppay_edd_send_to_gateway( $order_id ) {
	$order_id       = absint( $order_id );
	$transaction_id = snapppay_edd_get_order_transaction_id( $order_id );

	// اگر شناسه تراکنش به هر دلیلی ثبت نشده باشد، اینجا ساخته می‌شود.
	if ( '' === $transaction_id ) {
		$transaction_id = time() . '-' . $order_id;
		edd_update_order_meta( $order_id, '_transactionId', $transaction_id );
	}

	$api      = new SnappPay_EDD_API();
	$response = $api->get_payment_token( $order_id, $transaction_id );

	// خطای شبکه یا پاسخ ناموفق از درگاه.
	if ( is_wp_error( $response ) || empty( $response['successful'] ) || empty( $response['response']['paymentPageUrl'] ) ) {
		$error_message = is_wp_error( $response )
			? $response->get_error_message()
			: ( ! empty( $response['errorData']['message'] ) ? $response['errorData']['message'] : 'پاسخ نامعتبر از درگاه دریافت شد.' );

		$notice = 'در هنگام اتصال به درگاه خطایی رخ داده است : ' . $error_message;

		snapppay_edd_finish( $order_id, 'failed', $notice, edd_get_checkout_uri( '?payment-mode=' . SNAPP_PAY_EDD_GATEWAY_ID ) );
	}

	$payment_token = $response['response']['paymentToken'];

	// توکن پرداخت برای مراحل تأیید و تسویه ذخیره می‌شود.
	edd_update_order_meta( $order_id, '_order_spp_token', $payment_token );
	edd_update_order_meta( $order_id, '_paymentToken', $payment_token );

	edd_insert_payment_note(
		$order_id,
		sprintf(
			'اسنپ پی: انتقال به درگاه با توکن پرداخت:<br>%s<br>شناسه تراکنش:<br>%s',
			esc_html( $payment_token ),
			esc_html( $transaction_id )
		)
	);

	// انتقال به دامنه اسنپ‌پی؛ چون دامنه بیرونی است از wp_redirect استفاده می‌شود.
	wp_redirect( $response['response']['paymentPageUrl'] );
	exit;
}

/* =========================================================================
 * بخش ۴: بازگشت از درگاه و نهایی‌سازی سفارش
 * ====================================================================== */

/**
 * ساخت آرایه شورت‌کدهای قابل استفاده در پیام‌های تنظیمات.
 *
 * @param int    $order_id شناسه سفارش EDD.
 * @param string $fault    متن خطا برای شورت‌کد {fault}.
 * @return array نگاشت شورت‌کد به مقدار.
 */
function snapppay_edd_get_shortcode_values( $order_id, $fault = '' ) {
	return array(
		'{transactionId}' => snapppay_edd_get_order_transaction_id( $order_id ),
		'{paymentToken}'  => (string) snapppay_edd_get_order_payment_token( $order_id ),
		'{fault}'         => $fault,
	);
}

/**
 * ساخت پیام نهایی برای نمایش به کاربر بر اساس قالب تنظیم‌شده در پیشخوان.
 *
 * @param int    $order_id شناسه سفارش EDD.
 * @param string $status   وضعیت: success، failed یا cancelled.
 * @param string $fault    متن خطا یا توضیح تکمیلی.
 * @return string پیام آماده نمایش.
 */
function snapppay_edd_build_message( $order_id, $status, $fault = '' ) {
	$settings = snapppay_edd_get_settings();
	$key      = $status . '_message';
	$template = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

	// اگر مدیر پیامی تنظیم نکرده باشد، متن خطای خود درگاه نمایش داده می‌شود.
	if ( '' === trim( $template ) ) {
		return $fault;
	}

	$values  = snapppay_edd_get_shortcode_values( $order_id, $fault );
	$message = str_ireplace( array_keys( $values ), array_values( $values ), $template );
	$message = wpautop( wptexturize( trim( $message ) ) );

	return '' !== trim( wp_strip_all_tags( $message ) ) ? $message : $fault;
}

/**
 * نهایی‌سازی یک تلاش پرداخت: ثبت یادداشت، ذخیره پیام و انتقال کاربر.
 *
 * @param int    $order_id شناسه سفارش EDD.
 * @param string $status   وضعیت: success، failed یا cancelled.
 * @param string $fault    متن خطا یا توضیح تکمیلی.
 * @param string $redirect نشانی مقصد.
 * @return void این تابع همیشه با redirect و exit پایان می‌یابد.
 */
function snapppay_edd_finish( $order_id, $status, $fault, $redirect ) {
	$message = snapppay_edd_build_message( $order_id, $status, $fault );

	// ثبت یادداشت روی سفارش برای پیگیری توسط مدیر فروشگاه.
	if ( '' !== trim( (string) $fault ) ) {
		edd_insert_payment_note( $order_id, 'اسنپ پی: ' . wp_strip_all_tags( $fault ) );
	}

	// ذخیره پیام تا در صفحه مقصد به کاربر نمایش داده شود.
	edd_update_order_meta( $order_id, 'snapppay_order_notice', $message );

	// خطاها به سیستم خطای EDD هم اضافه می‌شوند تا در صفحه تسویه‌حساب دیده شوند.
	if ( 'success' !== $status ) {
		edd_set_error( 'snapppay_' . $status, wp_strip_all_tags( $message ) );
	}

	wp_safe_redirect( add_query_arg( 'snapppay_order', absint( $order_id ), $redirect ) );
	exit;
}

/**
 * خواندن کلید خرید (payment key) یک سفارش.
 *
 * صفحه «رسید خرید» EDD برای پیدا کردن سفارش به این کلید نیاز دارد؛ بدون آن
 * پیام «Sorry, trouble retrieving order receipt.» نمایش داده می‌شود.
 *
 * @param object $order شیء سفارش EDD.
 * @return string کلید خرید یا رشته خالی.
 */
function snapppay_edd_get_order_payment_key( $order ) {
	if ( ! empty( $order->payment_key ) ) {
		return (string) $order->payment_key;
	}

	if ( function_exists( 'edd_get_payment_key' ) ) {
		return (string) edd_get_payment_key( $order->id );
	}

	return '';
}

/**
 * بازسازی نشست خرید EDD پس از بازگشت از درگاه.
 *
 * اسنپ‌پی کاربر را از دامنه بیرونی (و گاهی با درخواست POST) برمی‌گرداند؛ در این
 * حالت مرورگر کوکی نشست را ارسال نمی‌کند و نشست خرید EDD خالی می‌ماند. نتیجه
 * این است که صفحه رسید نمی‌تواند سفارش را پیدا کند و لینک دانلودها نمایش داده
 * نمی‌شود. اینجا نشست دوباره با کلید خرید همین سفارش ساخته می‌شود.
 *
 * @param object $order       شیء سفارش EDD.
 * @param string $payment_key کلید خرید سفارش.
 * @return void
 */
function snapppay_edd_restore_purchase_session( $order, $payment_key ) {
	if ( '' === $payment_key || ! function_exists( 'edd_set_purchase_session' ) ) {
		return;
	}

	$session = function_exists( 'edd_get_purchase_session' ) ? edd_get_purchase_session() : array();

	if ( ! is_array( $session ) ) {
		$session = array();
	}

	// فقط کلیدهای موردنیاز صفحه رسید بازنویسی می‌شوند تا بقیه داده‌های نشست حفظ شود.
	$session['purchase_key'] = $payment_key;
	$session['user_email']   = ! empty( $order->email ) ? $order->email : '';
	$session['date']         = ! empty( $order->date_created ) ? $order->date_created : current_time( 'mysql' );

	edd_set_purchase_session( $session );
}

/**
 * انتقال کاربر به صفحه رسید خرید به‌همراه کلید خرید سفارش.
 *
 * جایگزین edd_send_to_success_page() است؛ چون آن تابع فقط رشته پرس‌وجو را به
 * انتهای نشانی می‌چسباند و کلید خرید را اضافه نمی‌کند.
 *
 * @param object $order شیء سفارش EDD.
 * @return void این تابع همیشه با redirect و exit پایان می‌یابد.
 */
function snapppay_edd_send_to_receipt( $order ) {
	$payment_key = snapppay_edd_get_order_payment_key( $order );

	snapppay_edd_restore_purchase_session( $order, $payment_key );

	// نسخه‌های جدید EDD خودشان نشانی رسید را با کلید خرید می‌سازند.
	$redirect = function_exists( 'edd_get_receipt_page_uri' )
		? edd_get_receipt_page_uri( $order->id )
		: edd_get_success_page_uri();

	$args = array( 'snapppay_order' => absint( $order->id ) );

	if ( '' !== $payment_key && false === strpos( $redirect, 'payment_key=' ) ) {
		$args['payment_key'] = $payment_key;
	}

	$redirect = add_query_arg( $args, $redirect );

	// این فیلتر در edd_send_to_success_page هم اجرا می‌شود؛ برای سازگاری حفظ شده است.
	$redirect = apply_filters( 'edd_success_page_redirect', $redirect, SNAPP_PAY_EDD_GATEWAY_ID, '' );

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * پردازش بازگشت کاربر از درگاه اسنپ‌پی.
 *
 * @return void
 */
function snapppay_edd_listen_for_callback() {
	// این شنونده فقط با پارامتر edd-listener مخصوص اسنپ‌پی فعال می‌شود.
	if ( ! isset( $_GET['edd-listener'] ) || SNAPP_PAY_EDD_GATEWAY_ID !== strtolower( sanitize_key( $_GET['edd-listener'] ) ) ) {
		return;
	}

	$order_id = isset( $_GET['edd_order'] ) ? absint( $_GET['edd_order'] ) : 0;
	$order    = $order_id ? edd_get_order( $order_id ) : null;

	// شماره سفارش نامعتبر است.
	if ( empty( $order ) ) {
		edd_set_error( 'snapppay_missing_order', 'شماره سفارش وجود ندارد.' );
		wp_safe_redirect( edd_get_failed_transaction_uri() );
		exit;
	}

	// وضعیت سفارش قبلاً مشخص شده است؛ از پردازش دوباره جلوگیری می‌شود.
	if ( 'pending' !== $order->status ) {
		// سفارش پیش‌تر با موفقیت تکمیل شده است (مثلاً کاربر صفحه را دوباره بارگذاری کرده).
		if ( 'complete' === $order->status ) {
			snapppay_edd_send_to_receipt( $order );
		}

		snapppay_edd_finish(
			$order->id,
			'failed',
			'وضعیت تراکنش قبلا مشخص شده است.',
			edd_get_failed_transaction_uri( '?payment-id=' . $order->id )
		);
	}

	// درگاه نتیجه را در پارامتر state برمی‌گرداند؛ ممکن است با GET یا POST ارسال شود.
	$state = isset( $_REQUEST['state'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_REQUEST['state'] ) ) ) : '';

	// کاربر از پرداخت انصراف داده یا درگاه خطا برگردانده است.
	if ( 'OK' !== $state ) {
		edd_update_order_status( $order->id, 'failed' );

		snapppay_edd_finish(
			$order->id,
			'cancelled',
			'پرداخت به دلیل انصراف شما یا خطای درگاه پرداخت ناتمام باقی ماند.',
			edd_get_failed_transaction_uri( '?payment-id=' . $order->id )
		);
	}

	$payment_token = snapppay_edd_get_order_payment_token( $order->id );

	// توکن پرداخت روی سفارش ثبت نشده است.
	if ( ! $payment_token ) {
		edd_update_order_status( $order->id, 'failed' );

		snapppay_edd_finish(
			$order->id,
			'failed',
			'خطای سفارش: توکن پرداخت نامعتبر است.',
			edd_get_failed_transaction_uri( '?payment-id=' . $order->id )
		);
	}

	$api             = new SnappPay_EDD_API();
	$verify_response = $api->verify( $payment_token );

	// تأیید تراکنش ناموفق بود.
	if ( is_wp_error( $verify_response ) || empty( $verify_response['successful'] ) ) {
		$error_message = is_wp_error( $verify_response )
			? $verify_response->get_error_message()
			: ( ! empty( $verify_response['errorData']['message'] ) ? $verify_response['errorData']['message'] : 'تأیید تراکنش ناموفق بود.' );

		edd_update_order_status( $order->id, 'failed' );

		snapppay_edd_finish(
			$order->id,
			'failed',
			'خطای درگاه: ' . $error_message,
			edd_get_failed_transaction_uri( '?payment-id=' . $order->id )
		);
	}

	// تراکنش تأیید شد؛ حالا باید تسویه شود تا مبلغ نهایی برداشت شود.
	$settled = snapppay_edd_settle_order( $order->id, $payment_token, $api );

	// ثبت شناسه‌های تراکنش روی سفارش برای پیگیری.
	edd_insert_payment_note(
		$order->id,
		sprintf(
			'اسنپ پی: تراکنش تأیید شد.<br>شماره تراکنش :<br>%s<br>توکن پرداخت :<br>%s',
			esc_html( snapppay_edd_get_order_transaction_id( $order->id ) ),
			esc_html( $payment_token )
		)
	);

	if ( $settled ) {
		// تسویه موفق: سفارش تکمیل شده و سبد خرید خالی می‌شود.
		edd_empty_cart();

		$message = snapppay_edd_build_message( $order->id, 'success', 'تراکنش با موفقیت انجام شد.' );
	} else {
		/*
		 * تسویه ناموفق: مبلغ نزد اسنپ‌پی رزرو شده ولی برداشت نشده است. سفارش عمداً
		 * در وضعیت «در انتظار» می‌ماند تا محصول پیش از قطعی‌شدن پرداخت تحویل نشود.
		 * پیام موفقیت نمایش داده نمی‌شود چون سفارش هنوز نهایی نشده است.
		 */
		$message = 'پرداخت شما تأیید شد اما نهایی‌سازی سفارش کامل نشد. سفارش شما در حال بررسی است و به‌محض تأیید نهایی فعال می‌شود.';
	}

	edd_update_order_meta( $order->id, 'snapppay_order_notice', $message );

	// سفارش دوباره خوانده می‌شود تا وضعیت و کلید خرید به‌روز باشد.
	$fresh_order = edd_get_order( $order->id );

	snapppay_edd_send_to_receipt( $fresh_order ? $fresh_order : $order );
}

/**
 * تسویه نهایی تراکنش و تکمیل سفارش، با یک بار تلاش مجدد در صورت شکست.
 *
 * @param int              $order_id      شناسه سفارش EDD.
 * @param string           $payment_token توکن پرداخت.
 * @param SnappPay_EDD_API $api           نمونه کلاینت API اسنپ‌پی.
 * @return bool درست اگر تسویه انجام و سفارش تکمیل شده باشد.
 */
function snapppay_edd_settle_order( $order_id, $payment_token, $api ) {
	// تا دو بار تلاش می‌شود تا خطاهای گذرای شبکه باعث معلق ماندن سفارش نشوند.
	for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
		$response = $api->settle( $payment_token );

		if ( ! is_wp_error( $response ) && ! empty( $response['successful'] ) ) {
			edd_update_order_meta( $order_id, 'order_spp_status', 'SETTLE' );
			edd_set_payment_transaction_id( $order_id, snapppay_edd_get_order_transaction_id( $order_id ) );
			edd_update_order_status( $order_id, 'complete' );

			return true;
		}
	}

	// تسویه انجام نشد؛ وضعیت برای پیگیری بعدی ثبت می‌شود.
	edd_update_order_meta( $order_id, 'order_spp_status', 'VERIFY' );
	edd_insert_payment_note(
		$order_id,
		'اسنپ پی: تراکنش تأیید شد اما تسویه انجام نشد. سفارش در وضعیت «در انتظار» می‌ماند و به‌صورت خودکار دوباره بررسی می‌شود.'
	);

	return false;
}
add_action( 'init', 'snapppay_edd_listen_for_callback' );

/**
 * نمایش پیام ذخیره‌شده اسنپ‌پی در صفحه‌های سبد خرید، رسید و تراکنش ناموفق.
 *
 * @return void
 */
function snapppay_edd_print_stored_notice() {
	// پیام فقط یک بار در هر درخواست نمایش داده می‌شود.
	static $printed = false;

	if ( $printed || empty( $_GET['snapppay_order'] ) ) {
		return;
	}

	$order_id = absint( $_GET['snapppay_order'] );
	$order    = edd_get_order( $order_id );

	if ( empty( $order ) ) {
		return;
	}

	$notice = edd_get_order_meta( $order_id, 'snapppay_order_notice', true );

	if ( empty( $notice ) ) {
		return;
	}

	// وضعیت سفارش تعیین می‌کند پیام موفق نمایش داده شود یا خطا.
	$is_success = in_array( $order->status, array( 'complete', 'processing' ), true );
	$css_class  = $is_success ? 'edd_success' : 'edd_error';

	printf(
		'<div class="edd_errors %1$s" style="margin-bottom:16px;"><p>%2$s</p><p><a href="%3$s">بازگشت به فروشگاه</a> &nbsp;|&nbsp; <a href="%4$s">بازگشت به سبد خرید</a></p></div>',
		esc_attr( $css_class ),
		wp_kses_post( $notice ),
		esc_url( home_url( '/' ) ),
		esc_url( edd_get_checkout_uri() )
	);

	$printed = true;
}
add_action( 'edd_before_checkout_cart', 'snapppay_edd_print_stored_notice' );
add_action( 'edd_payment_receipt_before_table', 'snapppay_edd_print_stored_notice' );

/**
 * افزودن پیام ذخیره‌شده اسنپ‌پی به ابتدای محتوای صفحه «رسید خرید».
 *
 * اگر شورت‌کد رسید به هر دلیلی نتواند سفارش را نمایش دهد (مثلاً نشست خرید از
 * بین رفته باشد)، هوک edd_payment_receipt_before_table اجرا نمی‌شود و کاربر
 * هیچ پیامی درباره نتیجه پرداخت نمی‌بیند. این فیلتر پیام را مستقل از شورت‌کد
 * به ابتدای برگه اضافه می‌کند. اولویت ۹ یعنی پیش از اجرای شورت‌کدها.
 *
 * @param string $content محتوای برگه.
 * @return string محتوا به‌همراه پیام اسنپ‌پی.
 */
function snapppay_edd_prepend_notice_to_success_page( $content ) {
	if ( ! is_main_query() || ! in_the_loop() || empty( $_GET['snapppay_order'] ) ) {
		return $content;
	}

	if ( ! function_exists( 'edd_is_success_page' ) || ! edd_is_success_page() ) {
		return $content;
	}

	ob_start();
	snapppay_edd_print_stored_notice();
	$notice = ob_get_clean();

	return $notice . $content;
}
add_filter( 'the_content', 'snapppay_edd_prepend_notice_to_success_page', 9 );

/**
 * افزودن پیام ذخیره‌شده اسنپ‌پی به ابتدای محتوای صفحه «تراکنش ناموفق».
 *
 * صفحه تراکنش ناموفق یک برگه معمولی وردپرس است و هوک اختصاصی ندارد؛ بنابراین
 * پیام از طریق فیلتر محتوا به آن اضافه می‌شود.
 *
 * @param string $content محتوای برگه.
 * @return string محتوا به‌همراه پیام اسنپ‌پی.
 */
function snapppay_edd_prepend_notice_to_failed_page( $content ) {
	// فقط در حلقه اصلی صفحه تراکنش ناموفق و وقتی شناسه سفارش در نشانی موجود باشد.
	if ( ! is_main_query() || ! in_the_loop() || empty( $_GET['snapppay_order'] ) ) {
		return $content;
	}

	if ( ! function_exists( 'edd_is_failed_transaction_page' ) || ! edd_is_failed_transaction_page() ) {
		return $content;
	}

	ob_start();
	snapppay_edd_print_stored_notice();
	$notice = ob_get_clean();

	return $notice . $content;
}
add_filter( 'the_content', 'snapppay_edd_prepend_notice_to_failed_page' );
