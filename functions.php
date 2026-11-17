<?php
/**
 * توابع کمکی افزونه درگاه اسنپ‌پی برای Easy Digital Downloads.
 *
 * این فایل شامل توابع بدون وضعیت (stateless) است: خواندن تنظیمات، تبدیل واحد پول،
 * نرمال‌سازی شماره موبایل، مدیریت وضعیت تراکنش و ابزارهای عمومی.
 *
 * @package SnappPay_EDD
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * بخش ۱: خواندن تنظیمات افزونه
 * ---------------------------------------------------------------------- */

/**
 * مقادیر پیش‌فرض تنظیمات، برای زمانی که هنوز چیزی ذخیره نشده یا EDD فعال نیست.
 *
 * ساختار این آرایه باید دقیقاً با خروجی snapppay_edd_get_settings() یکسان بماند.
 *
 * @return array تنظیمات پیش‌فرض.
 */
function snapppay_edd_get_default_settings() {
	return array(
		'base_url'          => 'https://api.snapppay.ir/',
		'client_id'         => '',
		'client_secret'     => '',
		'client_username'   => '',
		'client_password'   => '',
		'description'       => 'پرداخت اقساطی اسنپ پی',
		'mobile_enabled'    => false,
		'mobile_field'      => 'never',
		'default_gateway'   => false,
		'has_comission'     => false,
		'has_pdp'           => false,
		'dark_pdp'          => false,
		'success_message'   => '',
		'failed_message'    => '',
		'cancelled_message' => '',
	);
}

/**
 * بازگرداندن تنظیمات افزونه به صورت یک آرایه یکپارچه.
 *
 * تنظیمات Easy Digital Downloads همگی در یک آپشن به نام «edd_settings» ذخیره
 * می‌شوند و با پیشوند «snapppay_» از بقیه تنظیمات جدا شده‌اند. این تابع آن‌ها را
 * با کلیدهای کوتاه و بدون پیشوند برمی‌گرداند تا بقیه کدها ساده‌تر بمانند.
 *
 * @return array آرایه تنظیمات با کلیدهای بدون پیشوند.
 */
function snapppay_edd_get_settings() {
	// اگر Easy Digital Downloads فعال نباشد، تابع edd_get_option وجود ندارد؛
	// در این حالت مقادیر خنثی برگردانده می‌شود تا خطای مرگبار رخ ندهد.
	if ( ! function_exists( 'edd_get_option' ) ) {
		return apply_filters( 'snapppay_edd_settings', snapppay_edd_get_default_settings() );
	}

	$settings = array(
		// اطلاعات حساب درگاه.
		'base_url'          => trim( (string) edd_get_option( 'snapppay_base_url', 'https://api.snapppay.ir/' ) ),
		'client_id'         => trim( (string) edd_get_option( 'snapppay_client_id', '' ) ),
		'client_secret'     => trim( (string) edd_get_option( 'snapppay_client_secret', '' ) ),
		'client_username'   => trim( (string) edd_get_option( 'snapppay_client_username', '' ) ),
		'client_password'   => trim( (string) edd_get_option( 'snapppay_client_password', '' ) ),

		// تنظیمات نمایشی و رفتاری.
		'description'       => (string) edd_get_option( 'snapppay_description', 'پرداخت اقساطی اسنپ پی' ),
		'mobile_enabled'    => ! empty( edd_get_option( 'snapppay_mobile_enabled', false ) ),
		'mobile_field'      => snapppay_edd_sanitize_field_mode( edd_get_option( 'snapppay_mobile_field', 'never' ) ),
		'default_gateway'   => ! empty( edd_get_option( 'snapppay_default_gateway', false ) ),
		'has_comission'     => ! empty( edd_get_option( 'snapppay_has_comission', false ) ),
		'has_pdp'           => ! empty( edd_get_option( 'snapppay_has_pdp', false ) ),
		'dark_pdp'          => ! empty( edd_get_option( 'snapppay_dark_pdp', false ) ),

		// پیام‌هایی که پس از بازگشت از درگاه به کاربر نشان داده می‌شوند.
		'success_message'   => (string) edd_get_option( 'snapppay_success_message', '' ),
		'failed_message'    => (string) edd_get_option( 'snapppay_failed_message', '' ),
		'cancelled_message' => (string) edd_get_option( 'snapppay_cancelled_message', '' ),
	);

	/**
	 * امکان تغییر تنظیمات درگاه پیش از استفاده.
	 *
	 * @param array $settings تنظیمات خوانده‌شده از پایگاه داده.
	 */
	return apply_filters( 'snapppay_edd_settings', $settings );
}

/**
 * بررسی فعال بودن درگاه اسنپ‌پی.
 *
 * برخلاف ووکامرس، فعال/غیرفعال کردن درگاه در EDD از طریق چک‌باکس اختصاصی خود
 * افزونه EDD در بخش «درگاه‌های پرداخت» انجام می‌شود.
 *
 * @return bool درست اگر درگاه در EDD فعال باشد.
 */
function snapppay_edd_is_enabled() {
	return function_exists( 'edd_is_gateway_active' ) && edd_is_gateway_active( SNAPP_PAY_EDD_GATEWAY_ID );
}

/**
 * بررسی فعال بودن ارسال «نوع کمیسیون» دسته‌بندی‌ها به اسنپ‌پی.
 *
 * @return bool
 */
function snapppay_edd_has_comission() {
	$settings = snapppay_edd_get_settings();

	return (bool) $settings['has_comission'];
}

/**
 * بررسی فعال بودن ویجت اقساط در صفحه محصول.
 *
 * @return bool
 */
function snapppay_edd_has_pdp() {
	$settings = snapppay_edd_get_settings();

	return (bool) $settings['has_pdp'];
}

/**
 * بررسی فعال بودن تم تیره برای ویجت صفحه محصول.
 *
 * @return bool
 */
function snapppay_edd_dark_pdp() {
	$settings = snapppay_edd_get_settings();

	return (bool) $settings['dark_pdp'];
}

/**
 * بررسی اینکه آیا اسنپ‌پی باید به عنوان درگاه پیش‌فرض انتخاب شود.
 *
 * @return bool
 */
function snapppay_edd_is_default_gateway() {
	$settings = snapppay_edd_get_settings();

	return (bool) $settings['default_gateway'];
}

/**
 * بررسی اجباری بودن شماره موبایل در فرم تسویه‌حساب.
 *
 * @return bool
 */
function snapppay_edd_mobile_required() {
	$settings = snapppay_edd_get_settings();

	return (bool) $settings['mobile_enabled'];
}

/**
 * پاک‌سازی مقدار انتخابی «نحوه نمایش فیلد» و بازگرداندن یکی از مقادیر مجاز.
 *
 * مقادیر مجاز: never (هرگز، پیش‌فرض)، auto (فقط اگر EDD خودش فیلد را نداشته
 * باشد) و always (همیشه).
 *
 * افزونه به‌صورت پیش‌فرض هیچ فیلدی به فرم تسویه‌حساب اضافه نمی‌کند و فیلدهای
 * فرم دقیقاً همان چیزی است که در تنظیمات خود EDD تعیین شده است؛ بنابراین مقدار
 * نامعتبر یا تنظیم‌نشده به never تبدیل می‌شود.
 *
 * @param mixed $mode مقدار ذخیره‌شده در تنظیمات.
 * @return string یکی از مقادیر never، auto یا always.
 */
function snapppay_edd_sanitize_field_mode( $mode ) {
	$mode = is_string( $mode ) ? strtolower( trim( $mode ) ) : '';

	return in_array( $mode, array( 'auto', 'always', 'never' ), true ) ? $mode : 'never';
}

/**
 * نحوه نمایش فیلد «شماره موبایل» اسنپ‌پی در فرم تسویه‌حساب.
 *
 * @return string never، auto یا always.
 */
function snapppay_edd_mobile_field_mode() {
	$settings = snapppay_edd_get_settings();

	return snapppay_edd_sanitize_field_mode( $settings['mobile_field'] );
}

/**
 * فهرست فیلدهای نشانی که خود EDD در صفحه تسویه‌حساب نمایش می‌دهد.
 *
 * از نسخه ۳.۳.۸، خود EDD امکان فعال‌سازی فیلدهایی مانند «شماره تماس» را در
 * مسیر «تنظیمات ← پرداخت‌ها ← تسویه‌حساب» دارد و این فیلدها در آپشن
 * «checkout_address_fields» ذخیره می‌شوند.
 *
 * @return array کلیدهای فیلدهای فعال؛ مانند phone، zip، city و ... .
 */
function snapppay_edd_get_core_checkout_fields() {
	if ( ! function_exists( 'edd_get_option' ) ) {
		return array();
	}

	$fields = edd_get_option( 'checkout_address_fields', false );

	if ( false === $fields || ! is_array( $fields ) ) {
		// نسخه‌های قدیمی‌تر EDD فیلدهای نشانی را از تنظیمات استرایپ می‌خواندند.
		$stripe = edd_get_option( 'stripe_billing_fields' );

		if ( 'full' === $stripe ) {
			return array( 'country', 'address', 'address_2', 'city', 'state', 'zip' );
		}

		if ( 'zip_country' === $stripe ) {
			return array( 'country', 'zip' );
		}

		return array();
	}

	return array_keys( array_filter( $fields ) );
}

/**
 * بررسی اینکه آیا خود EDD فیلد شماره تماس را در تسویه‌حساب نمایش می‌دهد یا نه.
 *
 * @return bool
 */
function snapppay_edd_core_has_mobile_field() {
	return in_array( 'phone', snapppay_edd_get_core_checkout_fields(), true );
}

/**
 * تصمیم‌گیری درباره نمایش فیلد «شماره موبایل» اختصاصی اسنپ‌پی.
 *
 * حالت پیش‌فرض never است؛ یعنی افزونه هیچ فیلدی به فرم تسویه‌حساب اضافه نمی‌کند
 * و شماره موبایل از فیلد «شماره تماس» خود EDD خوانده می‌شود. در حالت خودکار،
 * فیلد فقط وقتی اضافه می‌شود که خود EDD فیلد شماره تماس را نداشته باشد.
 *
 * @return bool
 */
function snapppay_edd_should_render_mobile_field() {
	$mode = snapppay_edd_mobile_field_mode();

	if ( 'never' === $mode ) {
		$render = false;
	} elseif ( 'always' === $mode ) {
		$render = true;
	} else {
		$render = ! snapppay_edd_core_has_mobile_field();
	}

	/**
	 * امکان تغییر تصمیم نمایش فیلد شماره موبایل اسنپ‌پی.
	 *
	 * @param bool   $render آیا فیلد نمایش داده شود.
	 * @param string $mode   حالت انتخاب‌شده در تنظیمات.
	 */
	return (bool) apply_filters( 'snapppay_edd_render_mobile_field', $render, $mode );
}

/**
 * خواندن شماره موبایل ارسال‌شده در فرم تسویه‌حساب.
 *
 * ابتدا فیلد اختصاصی اسنپ‌پی و در نبود آن فیلد شماره تماس خود EDD خوانده
 * می‌شود؛ به این ترتیب اگر فیلد اسنپ‌پی نمایش داده نشود، شماره از فیلد EDD
 * برداشته می‌شود.
 *
 * @param array $post داده‌های ارسال‌شده فرم.
 * @return string شماره خام واردشده، یا رشته خالی.
 */
function snapppay_edd_get_posted_mobile( $post ) {
	$post = is_array( $post ) ? $post : array();

	foreach ( array( 'snapppay_mobile', 'card_phone' ) as $key ) {
		if ( ! empty( $post[ $key ] ) && is_string( $post[ $key ] ) ) {
			return sanitize_text_field( wp_unslash( $post[ $key ] ) );
		}
	}

	return '';
}

/* -------------------------------------------------------------------------
 * بخش ۲: ابزارهای عمومی (اعداد، موبایل، JSON)
 * ---------------------------------------------------------------------- */

/**
 * تبدیل ارقام فارسی و عربی به ارقام انگلیسی.
 *
 * @param mixed $string رشته ورودی که ممکن است ارقام فارسی/عربی داشته باشد.
 * @return string رشته‌ای که تمام ارقام آن انگلیسی است.
 */
function snapppay_edd_num_to_en( $string ) {
	$string  = (string) $string;
	$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$arabic  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
	$english = range( 0, 9 );

	$converted = str_replace( $persian, $english, $string );

	return str_replace( $arabic, $english, $converted );
}

/**
 * نرمال‌سازی شماره موبایل ایرانی به قالب بین‌المللی «+989XXXXXXXXX».
 *
 * قالب‌های پشتیبانی‌شده: 09xxxxxxxxx، 9xxxxxxxxx، 98xxxxxxxxxx، 098xxxxxxxxx،
 * 9809xxxxxxxxx، +98xxxxxxxxxx، +980xxxxxxxxx و +9898xxxxxxxxx.
 *
 * @param mixed $mobile شماره موبایل خام واردشده توسط کاربر.
 * @return string|false شماره نرمال‌شده، یا false اگر شماره معتبر نباشد.
 */
function snapppay_edd_mobile( $mobile ) {
	// حذف فاصله‌ها و خط تیره‌های احتمالی و تبدیل ارقام به انگلیسی.
	$mobile = snapppay_edd_num_to_en( $mobile );
	$mobile = preg_replace( '/[\s\-()]/', '', (string) $mobile );

	if ( '' === $mobile ) {
		return false;
	}

	// پیشوند بین‌المللی «00» معادل «+» است.
	if ( 0 === strpos( $mobile, '0098' ) ) {
		$mobile = '+' . substr( $mobile, 2 );
	}

	// قالب 9809xxxxxxxxx (۱۳ رقم).
	if ( preg_match( '@^9809@', $mobile ) && 13 === strlen( $mobile ) ) {
		return '+98' . substr( $mobile, 3 );
	}

	// قالب‌های دارای پیشوند تکراری +9898 یا +98980.
	if ( preg_match( '@^\+9898@', $mobile ) && strlen( $mobile ) > 13 ) {
		if ( preg_match( '@^\+98980@', $mobile ) ) {
			return '+98' . substr( $mobile, 6 );
		}

		return '+98' . substr( $mobile, 5 );
	}

	// قالب +980xxxxxxxxx (۱۴ کاراکتر).
	if ( preg_match( '@^\+980@', $mobile ) && 14 === strlen( $mobile ) ) {
		return '+98' . substr( $mobile, 4 );
	}

	// قالب صحیح بین‌المللی.
	if ( preg_match( '@^\+98@', $mobile ) ) {
		return $mobile;
	}

	// قالب 98xxxxxxxxxx بدون علامت مثبت.
	if ( preg_match( '@^98@', $mobile ) ) {
		return '+' . $mobile;
	}

	// قالب رایج داخلی 09xxxxxxxxx.
	if ( preg_match( '@^09@', $mobile ) && 11 === strlen( $mobile ) ) {
		return '+98' . substr( $mobile, 1 );
	}

	// قالب 098xxxxxxxxx.
	if ( preg_match( '@^098@', $mobile ) ) {
		return '+98' . substr( $mobile, 3 );
	}

	// قالب بدون صفر ابتدایی 9xxxxxxxxx.
	if ( preg_match( '@^9@', $mobile ) && 10 === strlen( $mobile ) ) {
		return '+98' . $mobile;
	}

	return false;
}

/**
 * بررسی معتبر بودن ساختار JSON یک رشته.
 *
 * @param mixed $string رشته‌ای که باید بررسی شود.
 * @return bool درست اگر رشته یک JSON معتبر باشد.
 */
function snapppay_edd_is_json( $string ) {
	if ( ! is_string( $string ) || '' === $string ) {
		return false;
	}

	json_decode( $string );

	return JSON_ERROR_NONE === json_last_error();
}

/* -------------------------------------------------------------------------
 * بخش ۳: تبدیل واحد پول و محاسبه مبلغ
 * ---------------------------------------------------------------------- */

/**
 * تبدیل مبلغ از واحد پول فروشگاه به ریال ایران.
 *
 * درگاه اسنپ‌پی مبالغ را فقط به ریال می‌پذیرد، در حالی که فروشگاه‌های ایرانی
 * معمولاً واحد پول را روی تومان یا هزارتومان تنظیم می‌کنند.
 *
 * @param mixed       $amount   مبلغ در واحد پول فروشگاه.
 * @param string|null $currency کد واحد پول؛ اگر تهی باشد از تنظیمات EDD خوانده می‌شود.
 * @return int مبلغ معادل به ریال.
 */
function snapppay_edd_amount_by_currency( $amount, $currency = null ) {
	$amount = (int) $amount;

	if ( null === $currency || '' === $currency ) {
		$currency = function_exists( 'edd_get_currency' ) ? edd_get_currency() : 'IRR';
	}

	$currency = strtolower( (string) $currency );

	// فهرست نام‌های رایجی که فروشگاه‌های ایرانی برای «تومان» استفاده می‌کنند.
	$toman_currencies = array(
		'irt',
		'toman',
		'iran toman',
		'iranian toman',
		'iran-toman',
		'iranian-toman',
		'iran_toman',
		'iranian_toman',
		'تومان',
		'تومان ایران',
	);

	if ( in_array( $currency, $toman_currencies, true ) ) {
		// هر تومان معادل ۱۰ ریال است.
		$amount = $amount * 10;
	} elseif ( 'irht' === $currency ) {
		// هزار تومان: ابتدا به تومان و سپس به ریال.
		$amount = $amount * 1000 * 10;
	} elseif ( 'irhr' === $currency ) {
		// هزار ریال.
		$amount = $amount * 1000;
	}

	return $amount;
}

/**
 * محاسبه مبلغ کل یک سفارش به ریال.
 *
 * @param \EDD\Orders\Order $order شیء سفارش EDD.
 * @return int مبلغ کل سفارش به ریال.
 */
function snapppay_edd_get_order_amount_in_rial( $order ) {
	if ( empty( $order ) ) {
		return 0;
	}

	$currency = $order->currency;

	/**
	 * امکان تغییر واحد پول سفارش پیش از تبدیل به ریال.
	 *
	 * @param string $currency واحد پول سفارش.
	 * @param int    $order_id شناسه سفارش.
	 */
	$currency = apply_filters( 'snapppay_edd_order_currency', $currency, $order->id );

	$amount = snapppay_edd_amount_by_currency( intval( $order->total ), $currency );

	/**
	 * امکان تغییر مبلغ نهایی ارسالی به درگاه.
	 *
	 * @param int    $amount   مبلغ به ریال.
	 * @param string $currency واحد پول اصلی سفارش.
	 * @param int    $order_id شناسه سفارش.
	 */
	return (int) apply_filters( 'snapppay_edd_order_amount_in_rial', $amount, $currency, $order->id );
}

/* -------------------------------------------------------------------------
 * بخش ۴: کار با متای سفارش
 * ---------------------------------------------------------------------- */

/**
 * فهرست کلیدهای متایی که ممکن است توکن پرداخت در آن‌ها ذخیره شده باشد.
 *
 * نسخه‌های مختلف افزونه از کلیدهای متفاوتی استفاده کرده‌اند؛ این فهرست برای
 * سازگاری با سفارش‌های قدیمی نگه داشته شده است.
 *
 * @return string[] فهرست کلیدهای متا به ترتیب اولویت.
 */
function snapppay_edd_get_token_meta_keys() {
	return array( '_order_spp_token', '_paymentToken', 'order_spp_token', 'paymentToken' );
}

/**
 * خواندن توکن پرداخت اسنپ‌پی از متای سفارش.
 *
 * @param int $order_id شناسه سفارش EDD.
 * @return string|false توکن پرداخت، یا false اگر پیدا نشود.
 */
function snapppay_edd_get_order_payment_token( $order_id ) {
	$order_id = absint( $order_id );

	if ( ! $order_id ) {
		return false;
	}

	foreach ( snapppay_edd_get_token_meta_keys() as $meta_key ) {
		$token = edd_get_order_meta( $order_id, $meta_key, true );

		if ( ! empty( $token ) ) {
			return $token;
		}
	}

	return false;
}

/**
 * خواندن شناسه تراکنش تولیدشده توسط افزونه از متای سفارش.
 *
 * @param int $order_id شناسه سفارش EDD.
 * @return string شناسه تراکنش، یا رشته خالی اگر ثبت نشده باشد.
 */
function snapppay_edd_get_order_transaction_id( $order_id ) {
	$transaction_id = edd_get_order_meta( absint( $order_id ), '_transactionId', true );

	return is_string( $transaction_id ) ? $transaction_id : '';
}

/* -------------------------------------------------------------------------
 * بخش ۵: مدیریت وضعیت تراکنش (لغو، تأیید، تسویه)
 * ---------------------------------------------------------------------- */

/**
 * ساخت متن یادداشت فارسی برای نتیجه یک عملیات روی تراکنش.
 *
 * @param mixed  $response پاسخ درگاه (آرایه یا WP_Error).
 * @param string $type     نوع عملیات: cancel، update یا status.
 * @return string متن یادداشت آماده درج در سفارش.
 */
function snapppay_edd_prepare_message( $response, $type ) {
	// تعیین برچسب فارسی عملیات.
	switch ( $type ) {
		case 'status':
			$operation = 'بررسی وضعیت';
			break;
		case 'update':
			$operation = 'بروزرسانی';
			break;
		case 'cancel':
		default:
			$operation = 'لغو';
			break;
	}

	// خطای سطح شبکه یا تنظیمات.
	if ( is_wp_error( $response ) ) {
		return sprintf(
			'اسنپ پی: عملیات <strong style="color:red">%s سفارش ناموفق بود: %s</strong>',
			$operation,
			$response->get_error_message()
		);
	}

	// پاسخ نامعتبر یا ناموفق از سمت درگاه.
	if ( ! is_array( $response ) || empty( $response['successful'] ) ) {
		$error_message = '';

		if ( is_array( $response ) && ! empty( $response['errorData']['message'] ) ) {
			$error_message = $response['errorData']['message'];
		}

		return sprintf(
			'اسنپ پی: عملیات <strong style="color:red">%s سفارش ناموفق بود. %s</strong>',
			$operation,
			$error_message
		);
	}

	return sprintf(
		'اسنپ پی: عملیات <strong style="color:green">%s سفارش با موفقیت انجام شد</strong>.',
		$operation
	);
}

/**
 * درج نتیجه یک عملیات درگاه به صورت یادداشت روی سفارش.
 *
 * @param int    $order_id شناسه سفارش EDD.
 * @param mixed  $response پاسخ درگاه.
 * @param string $type     نوع عملیات: cancel، update یا status.
 * @return void
 */
function snapppay_edd_handle_response( $order_id, $response, $type = 'cancel' ) {
	edd_insert_payment_note( absint( $order_id ), snapppay_edd_prepare_message( $response, $type ) );
}

/**
 * مدیریت حالتی که استعلام وضعیت تراکنش از درگاه ناموفق بوده است.
 *
 * در اولین شکست، وضعیت سفارش به حالت قبلی بازگردانده می‌شود تا فرصت تلاش مجدد
 * وجود داشته باشد؛ در شکست دوم سفارش «ناموفق» علامت می‌خورد.
 *
 * @param int    $order_id    شناسه سفارش EDD.
 * @param string $from_status وضعیت سفارش پیش از تغییر.
 * @return bool همیشه false، چون عملیات به نتیجه قطعی نرسیده است.
 */
function snapppay_edd_handle_error_status( $order_id, $from_status ) {
	$already_checked = edd_get_order_meta( $order_id, '_snapppay_check_status', true );

	if ( 'yes' === $already_checked ) {
		// بار دوم است که استعلام شکست می‌خورد؛ سفارش را ناموفق در نظر می‌گیریم.
		edd_update_order_status( $order_id, 'failed' );

		return false;
	}

	// اولین شکست: علامت‌گذاری و بازگرداندن سفارش به وضعیت قبلی.
	edd_update_order_meta( $order_id, '_snapppay_check_status', 'yes' );
	edd_update_order_status( $order_id, $from_status );

	return false;
}

/**
 * تسویه نهایی تراکنش تأییدشده و تکمیل سفارش.
 *
 * @param int                 $order_id    شناسه سفارش EDD.
 * @param SnappPay_EDD_API    $api         نمونه کلاینت API اسنپ‌پی.
 * @param string              $token       توکن پرداخت.
 * @param string              $from_status وضعیت سفارش پیش از تغییر.
 * @return bool درست اگر تسویه موفق بوده باشد.
 */
function snapppay_edd_process_verification( $order_id, $api, $token, $from_status = 'pending' ) {
	$settle_response = $api->settle( $token );

	if ( ! is_wp_error( $settle_response ) && ! empty( $settle_response['successful'] ) ) {
		edd_update_order_meta( $order_id, 'order_spp_status', 'SETTLE' );
		edd_update_order_status( $order_id, 'complete' );

		return true;
	}

	// تسویه ناموفق بود؛ سفارش به وضعیت قبلی بازمی‌گردد تا دوباره بررسی شود.
	edd_update_order_status( $order_id, $from_status );

	return false;
}

/**
 * مدیریت تراکنشی که هنوز در وضعیت «در انتظار» است: ابتدا تأیید، سپس تسویه.
 *
 * @param int              $order_id شناسه سفارش EDD.
 * @param SnappPay_EDD_API $api      نمونه کلاینت API اسنپ‌پی.
 * @param string           $token    توکن پرداخت.
 * @return bool درست اگر تراکنش نهایی و سفارش تکمیل شده باشد.
 */
function snapppay_edd_process_pending( $order_id, $api, $token ) {
	$verify_response = $api->verify( $token );

	if ( ! is_wp_error( $verify_response ) && ! empty( $verify_response['successful'] ) ) {
		// تأیید موفق بود؛ همان وضعیت «pending» را به عنوان مرجع بازگشت می‌دهیم.
		return snapppay_edd_process_verification( $order_id, $api, $token, 'pending' );
	}

	// تأیید ناموفق بود؛ تراکنش قطعاً ناتمام مانده است.
	edd_update_order_status( $order_id, 'failed' );

	return false;
}

/**
 * تصمیم‌گیری بر اساس وضعیتی که درگاه اسنپ‌پی برای تراکنش گزارش می‌کند.
 *
 * @param int              $order_id    شناسه سفارش EDD.
 * @param string           $status      وضعیت گزارش‌شده توسط درگاه.
 * @param SnappPay_EDD_API $api         نمونه کلاینت API اسنپ‌پی.
 * @param string           $token       توکن پرداخت.
 * @param string           $from_status وضعیت سفارش پیش از تغییر.
 * @return bool درست اگر وضعیت سفارش به صورت قطعی تعیین شده باشد.
 */
function snapppay_edd_handle_status_response( $order_id, $status, $api, $token, $from_status ) {
	switch ( $status ) {
		case 'REVERT':
			// تراکنش توسط درگاه برگشت خورده است.
			edd_update_order_status( $order_id, 'failed' );
			return true;

		case 'VERIFY':
			// تراکنش تأیید شده ولی تسویه نشده است.
			return snapppay_edd_process_verification( $order_id, $api, $token, $from_status );

		case 'SETTLE':
			// تراکنش کامل تسویه شده است.
			edd_update_order_meta( $order_id, 'order_spp_status', 'SETTLE' );
			edd_update_order_status( $order_id, 'complete' );
			return true;

		case 'PENDING':
			// تراکنش هنوز نهایی نشده است.
			return snapppay_edd_process_pending( $order_id, $api, $token );

		default:
			// وضعیت ناشناخته؛ تصمیمی گرفته نمی‌شود.
			return false;
	}
}

/**
 * لغو یا تعیین تکلیف تراکنش اسنپ‌پی یک سفارش.
 *
 * اگر سفارش هنوز در وضعیت «در انتظار پرداخت» باشد، ابتدا وضعیت واقعی تراکنش از
 * درگاه استعلام می‌شود؛ در غیر این صورت درخواست لغو ارسال می‌گردد.
 *
 * @param int    $order_id    شناسه سفارش EDD.
 * @param string $from_status وضعیت سفارش پیش از تغییر.
 * @return bool نتیجه عملیات.
 */
function snapppay_edd_cancel_order( $order_id, $from_status ) {
	$order = edd_get_order( absint( $order_id ) );

	if ( empty( $order ) ) {
		return false;
	}

	// فقط سفارش‌هایی که با اسنپ‌پی پرداخت شده‌اند پردازش می‌شوند.
	if ( SNAPP_PAY_EDD_GATEWAY_ID !== $order->gateway ) {
		return false;
	}

	$token = snapppay_edd_get_order_payment_token( $order->id );

	if ( ! $token ) {
		return false;
	}

	$api = new SnappPay_EDD_API();

	// سفارش‌های در انتظار پرداخت ممکن است در واقع پرداخت شده باشند؛ ابتدا استعلام می‌کنیم.
	if ( 'pending' === $from_status ) {
		$status_response = $api->status( $token );

		if ( is_wp_error( $status_response ) || empty( $status_response['successful'] ) ) {
			return snapppay_edd_handle_error_status( $order->id, $from_status );
		}

		if ( ! empty( $status_response['response']['status'] ) ) {
			return snapppay_edd_handle_status_response(
				$order->id,
				$status_response['response']['status'],
				$api,
				$token,
				$from_status
			);
		}

		return false;
	}

	// سفارش پرداخت‌شده بود؛ درخواست لغو/عودت به درگاه ارسال می‌شود.
	$response = $api->cancel( $token );
	snapppay_edd_handle_response( $order->id, $response, 'cancel' );

	return ! is_wp_error( $response ) && ! empty( $response['successful'] );
}

/* -------------------------------------------------------------------------
 * بخش ۶: ابزارهای پیشخوان و گزارش‌گیری
 * ---------------------------------------------------------------------- */

/**
 * مسیر فایل لاگ افزونه.
 *
 * نام فایل با نسخه ووکامرس متفاوت است تا اگر هر دو افزونه روی یک سایت فعال
 * باشند لاگ‌هایشان روی هم نوشته نشود.
 *
 * @return string مسیر مطلق فایل لاگ.
 */
function snapppay_edd_log_file() {
	return ABSPATH . 'snapppay-edd.log';
}

/**
 * نوشتن یک پیام در فایل لاگ افزونه.
 *
 * @param mixed $message پیامی که باید ثبت شود.
 * @return void
 */
function snapppay_edd_log( $message ) {
	$line = gmdate( 'Y-m-d H:i:s' ) . ' === ' . wp_json_encode( array( 'message' => $message ) ) . PHP_EOL . PHP_EOL;

	file_put_contents( snapppay_edd_log_file(), $line, FILE_APPEND );
}

/**
 * دریافت و ذخیره آدرس IP خروجی سرور فروشگاه.
 *
 * این آدرس باید در پنل اسنپ‌پی در فهرست مجاز ثبت شود، بنابراین در تنظیمات و در
 * یک اعلان پیشخوان به مدیر نمایش داده می‌شود.
 *
 * @return void
 */
function snapppay_edd_fetch_server_ip() {
	$response = wp_remote_get(
		'https://whatisip.snapppay.ir/whatis/ip',
		array(
			'timeout'   => 30,
			'sslverify' => false,
		)
	);

	// در صورت بروز خطای شبکه، آدرس IP ذخیره نمی‌شود.
	if ( is_wp_error( $response ) ) {
		return;
	}

	$body = trim( (string) wp_remote_retrieve_body( $response ) );

	if ( '' === $body ) {
		return;
	}

	// پاسخ سرویس به شکل «Your IP: 1.2.3.4» است؛ بخش پس از دونقطه آدرس IP است.
	$parts = explode( ':', $body );
	$ip    = isset( $parts[1] ) ? trim( $parts[1] ) : $body;

	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return;
	}

	update_option( 'snapppay-edd-web-server-ip', $ip );
	update_option( 'snapppay-edd-web-server-ip-show', 'yes' );
}

/**
 * جمع‌آوری اطلاعات پایه سایت برای گزارش رویدادهای نصب/حذف.
 *
 * @param string $event_name نام رویداد (activate یا deactivate).
 * @return array اطلاعات سایت.
 */
function snapppay_edd_gather_site_data( $event_name ) {
	$additional_info = array(
		'wordpress_version' => get_bloginfo( 'version' ),
		'php_version'       => phpversion(),
		'locale'            => get_locale(),
		'timezone'          => wp_timezone_string(),
		'plugin_version'    => SNAPP_PAY_EDD_VERSION,
		'platform'          => 'edd',
		'action'            => $event_name,
	);

	return array(
		'sitename'        => sanitize_text_field( get_bloginfo( 'name' ) ),
		'siteurl'         => esc_url_raw( get_site_url() ),
		'user_agent'      => sanitize_text_field( isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '' ),
		'additional_info' => $additional_info,
	);
}

/**
 * ارسال اطلاعات رویداد به سرویس گزارش‌گیری اسنپ‌پی.
 *
 * @param array $data اطلاعات سایت.
 * @return array|WP_Error پاسخ درخواست.
 */
function snapppay_edd_send_tracking_data( $data ) {
	$endpoint = 'https://script.google.com/macros/s/AKfycbwzSSUqPescvvu7rO7fyBnTTH2LSJXzXiQq8LiEXHnKZS9pPGGoOHobog4WptcnRtOI/exec';

	$response = wp_remote_post(
		$endpoint,
		array(
			'body'        => wp_json_encode( $data ),
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'timeout'     => 30,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'data_format' => 'body',
		)
	);

	// ثبت نتیجه در لاگ برای عیب‌یابی.
	if ( is_wp_error( $response ) ) {
		snapppay_edd_log( 'Activation Tracker Error: ' . $response->get_error_message() );
	} else {
		snapppay_edd_log(
			sprintf(
				'Activation Tracker Response: Code %s - %s',
				wp_remote_retrieve_response_code( $response ),
				wp_remote_retrieve_body( $response )
			)
		);
	}

	return $response;
}

/**
 * ثبت یک رویداد چرخه عمر افزونه (فعال‌سازی یا غیرفعال‌سازی).
 *
 * @param string $event_name نام رویداد.
 * @return void
 */
function snapppay_edd_track_event( $event_name ) {
	$site_data = snapppay_edd_gather_site_data( $event_name );
	$result    = snapppay_edd_send_tracking_data( $site_data );

	$succeeded     = ! is_wp_error( $result );
	$response_body = $succeeded ? json_decode( wp_remote_retrieve_body( $result ), true ) : null;

	update_option(
		'snapppay_edd_activation_tracker_data',
		array(
			'site_data' => $site_data,
			'sent_at'   => current_time( 'mysql' ),
			'success'   => $succeeded,
			'response'  => $response_body,
			'error'     => $succeeded ? null : $result->get_error_message(),
		)
	);
}

/**
 * نشانی تصویر لوگوی اسنپ‌پی برای استفاده در ویجت صفحه محصول.
 *
 * @return string نشانی کامل تصویر.
 */
function snapppay_edd_get_widget_logo_url() {
	// در تم تیره لوگوی بدون پس‌زمینه و در تم روشن لوگوی دارای پس‌زمینه استفاده می‌شود.
	$file = snapppay_edd_dark_pdp() ? 'logo.png' : 'logo-bg.png';

	/**
	 * امکان جایگزینی لوگوی ویجت.
	 *
	 * @param string $url نشانی تصویر.
	 */
	return apply_filters( 'snapppay_edd_widget_logo', SNAPP_PAY_EDD_URL . 'assets/images/' . $file );
}

/**
 * نشانی آیکون درگاه برای نمایش در صفحه تسویه‌حساب.
 *
 * @return string نشانی کامل تصویر.
 */
function snapppay_edd_get_gateway_logo_url() {
	/**
	 * امکان جایگزینی آیکون درگاه.
	 *
	 * @param string $url نشانی تصویر.
	 */
	return apply_filters( 'snapppay_edd_gateway_logo', SNAPP_PAY_EDD_URL . 'assets/images/logo-bg.png' );
}

/**
 * نمایش اعلان خطا وقتی Easy Digital Downloads نصب یا فعال نیست.
 *
 * @return void
 */
function snapppay_edd_missing_edd_notice() {
	echo '<div class="notice notice-error is-dismissible"><p>';
	echo 'افزونه <b>Easy Digital Downloads</b> فعال نیست. برای استفاده از درگاه پرداخت اسنپ‌پی ابتدا آن را نصب و فعال کنید.';
	echo '</p></div>';
}
