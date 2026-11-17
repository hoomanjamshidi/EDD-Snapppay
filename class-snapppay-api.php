<?php
/**
 * کلاینت ارتباط با وب‌سرویس اسنپ‌پی.
 *
 * تمام درخواست‌های HTTP به سرورهای اسنپ‌پی از طریق این کلاس انجام می‌شود.
 * این کلاس هیچ وابستگی‌ای به رابط کاربری ندارد و فقط داده برمی‌گرداند.
 *
 * @package SnappPay_EDD
 */

defined( 'ABSPATH' ) || exit;

/**
 * کلاس SnappPay_EDD_API
 */
class SnappPay_EDD_API {

	/**
	 * نام ترنزینتی که توکن دسترسی (Bearer) در آن نگهداری می‌شود.
	 *
	 * @var string
	 */
	const TOKEN_TRANSIENT = 'snapppay_edd_bearer_token';

	/**
	 * تنظیمات افزونه.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * سازنده کلاس؛ تنظیمات را یک بار می‌خواند و نگه می‌دارد.
	 */
	public function __construct() {
		$this->settings = snapppay_edd_get_settings();
	}

	/**
	 * بازگرداندن تنظیمات جاری درگاه.
	 *
	 * @return array
	 */
	public function get_settings() {
		return $this->settings;
	}

	/* ---------------------------------------------------------------------
	 * احراز هویت
	 * ------------------------------------------------------------------ */

	/**
	 * ساخت نشانی پایه وب‌سرویس با تضمین وجود اسلش پایانی.
	 *
	 * @return string نشانی پایه API.
	 */
	public function get_api_base_url() {
		$base_url = trim( (string) $this->settings['base_url'] );

		if ( '' === $base_url ) {
			return '';
		}

		return trailingslashit( $base_url );
	}

	/**
	 * بررسی اینکه آیا همه اطلاعات حساب در تنظیمات وارد شده است یا نه.
	 *
	 * @return bool درست اگر تنظیمات کامل باشد.
	 */
	public function has_credentials() {
		$required = array( 'base_url', 'client_id', 'client_secret', 'client_username', 'client_password' );

		foreach ( $required as $key ) {
			if ( empty( $this->settings[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * ساخت هدرهای احراز هویت پایه (Basic) برای دریافت توکن دسترسی.
	 *
	 * @return array هدرهای درخواست.
	 */
	protected function get_request_basic_token() {
		// برخی نصب‌ها هنگام ذخیره تنظیمات، «&» را به «&amp;» تبدیل می‌کنند؛ اینجا اصلاح می‌شود.
		$secret = str_replace( 'amp;', '', $this->settings['client_secret'] );
		$token  = 'Basic ' . base64_encode( $this->settings['client_id'] . ':' . $secret );

		return array(
			'Authorization' => $token,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		);
	}

	/**
	 * ساخت هدرهای احراز هویت Bearer.
	 *
	 * توکن دسترسی تا زمان انقضا در ترنزینت نگهداری می‌شود تا در هر درخواست
	 * دوباره از سرور گرفته نشود.
	 *
	 * @return array|false هدرهای درخواست، یا false اگر دریافت توکن ناموفق باشد.
	 */
	protected function get_request_bearer_token() {
		$bearer_token = get_transient( self::TOKEN_TRANSIENT );

		if ( ! $bearer_token ) {
			$response = $this->request(
				'api/online/v1/oauth/token',
				'POST',
				'Basic',
				array(
					'grant_type' => 'password',
					'scope'      => 'online-merchant',
					'username'   => $this->settings['client_username'],
					'password'   => str_replace( 'amp;', '', $this->settings['client_password'] ),
				)
			);

			// خطای شبکه یا پاسخ نامعتبر.
			if ( is_wp_error( $response ) || empty( $response['access_token'] ) ) {
				return false;
			}

			$bearer_token = $response['access_token'];

			// مدت اعتبار توکن؛ اگر سرور مقداری نفرستاد یک ساعت در نظر گرفته می‌شود.
			$ttl = ! empty( $response['expires_in'] ) ? absint( $response['expires_in'] ) : HOUR_IN_SECONDS;

			set_transient( self::TOKEN_TRANSIENT, $bearer_token, $ttl );
		}

		return array(
			'Authorization' => 'Bearer ' . $bearer_token,
			'Content-Type'  => 'application/json',
		);
	}

	/* ---------------------------------------------------------------------
	 * عملیات‌های درگاه
	 * ------------------------------------------------------------------ */

	/**
	 * استعلام امکان پرداخت اقساطی برای یک مبلغ مشخص.
	 *
	 * @param int $amount مبلغ سبد خرید به ریال.
	 * @return array|WP_Error پاسخ درگاه یا خطا.
	 */
	public function is_merchant_eligible( $amount ) {
		if ( ! $this->has_credentials() ) {
			return new WP_Error( '401', 'خطای تنظیمات پلاگین درگاه پرداخت اسنپ! پی' );
		}

		$response = $this->request( 'api/online/offer/v1/eligible', 'GET', 'Bearer', array( 'amount' => $amount ) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( '401', 'خطای درگاه ' . $response->get_error_message() );
		}

		return $response;
	}

	/**
	 * دریافت توکن پرداخت و نشانی صفحه پرداخت برای یک سفارش.
	 *
	 * @param int    $order_id       شناسه سفارش EDD.
	 * @param string $transaction_id شناسه تراکنش یکتای تولیدشده توسط افزونه.
	 * @return array|WP_Error پاسخ درگاه یا خطا.
	 */
	public function get_payment_token( $order_id, $transaction_id ) {
		$order = edd_get_order( absint( $order_id ) );

		if ( empty( $order ) ) {
			return new WP_Error( '404', 'سفارش موردنظر یافت نشد.' );
		}

		/**
		 * اجرای عملیات دلخواه پیش از انتقال کاربر به درگاه.
		 *
		 * @param int $order_id شناسه سفارش.
		 */
		do_action( 'snapppay_edd_before_payment', $order->id );

		$data = array(
			'amount'               => snapppay_edd_get_order_amount_in_rial( $order ),
			'paymentMethodTypeDto' => 'INSTALLMENT',
			'returnURL'            => snapppay_edd_get_callback_url( $order->id ),
			'transactionId'        => $transaction_id,
		);

		// شماره موبایل در صورت وجود ارسال می‌شود؛ اسنپ‌پی برای احراز هویت از آن استفاده می‌کند.
		// اگر فیلد اختصاصی افزونه نمایش داده نشده باشد، شماره تماسی که خود EDD
		// روی سفارش ذخیره کرده است استفاده می‌شود.
		$raw_mobile = edd_get_order_meta( $order->id, '_snapppay_mobile', true );

		if ( empty( $raw_mobile ) ) {
			$raw_mobile = edd_get_order_meta( $order->id, '_edd_phone', true );
		}

		$mobile = snapppay_edd_mobile( $raw_mobile );

		if ( $mobile ) {
			$data['mobile'] = $mobile;
		}

		$data['cartList'][]     = $this->build_cart_list( $order );
		$data['discountAmount'] = snapppay_edd_amount_by_currency( $order->discount, $order->currency );

		return $this->request( 'api/online/payment/v1/token', 'POST', 'Bearer', $data );
	}

	/**
	 * تأیید تراکنش پس از بازگشت کاربر از درگاه.
	 *
	 * @param string $payment_token توکن پرداخت.
	 * @return array|WP_Error پاسخ درگاه یا خطا.
	 */
	public function verify( $payment_token ) {
		return $this->request( 'api/online/payment/v1/verify', 'POST', 'Bearer', array( 'paymentToken' => $payment_token ) );
	}

	/**
	 * تسویه نهایی تراکنش تأییدشده.
	 *
	 * @param string $payment_token توکن پرداخت.
	 * @return array|WP_Error پاسخ درگاه یا خطا.
	 */
	public function settle( $payment_token ) {
		return $this->request( 'api/online/payment/v1/settle', 'POST', 'Bearer', array( 'paymentToken' => $payment_token ) );
	}

	/**
	 * لغو یا عودت تراکنش.
	 *
	 * @param string $payment_token توکن پرداخت.
	 * @return array|WP_Error پاسخ درگاه یا خطا.
	 */
	public function cancel( $payment_token ) {
		return $this->request( 'api/online/payment/v1/cancel', 'POST', 'Bearer', array( 'paymentToken' => $payment_token ) );
	}

	/**
	 * استعلام وضعیت جاری تراکنش.
	 *
	 * @param string $payment_token توکن پرداخت.
	 * @return array|WP_Error پاسخ درگاه یا خطا.
	 */
	public function status( $payment_token ) {
		return $this->request( 'api/online/payment/v1/status', 'GET', 'Bearer', array( 'paymentToken' => $payment_token ) );
	}

	/**
	 * به‌روزرسانی اقلام و مبلغ سفارش نزد درگاه.
	 *
	 * وقتی مدیر فروشگاه سفارشی را که با اسنپ‌پی ثبت شده ویرایش یا تأیید می‌کند،
	 * اطلاعات جدید باید به درگاه هم اعلام شود.
	 *
	 * @param int    $order_id      شناسه سفارش EDD.
	 * @param string $payment_token توکن پرداخت.
	 * @return array|WP_Error پاسخ درگاه یا خطا.
	 */
	public function update( $order_id, $payment_token ) {
		$order = edd_get_order( absint( $order_id ) );

		if ( empty( $order ) ) {
			return new WP_Error( '404', 'سفارش موردنظر یافت نشد.' );
		}

		$data = array(
			'amount'               => snapppay_edd_get_order_amount_in_rial( $order ),
			'paymentMethodTypeDto' => 'INSTALLMENT',
			'paymentToken'         => $payment_token,
		);

		$data['cartList'][]     = $this->build_cart_list( $order );
		$data['discountAmount'] = snapppay_edd_amount_by_currency( $order->discount, $order->currency );

		return $this->request( 'api/online/payment/v1/update', 'POST', 'Bearer', $data );
	}

	/* ---------------------------------------------------------------------
	 * ساخت داده سبد خرید
	 * ------------------------------------------------------------------ */

	/**
	 * یافتن نوع کمیسیون و نام دسته‌بندی یک محصول دانلودی.
	 *
	 * اولویت با دسته‌بندی سطح آخر است؛ اگر مقداری نداشت، از دسته‌بندی ریشه
	 * استفاده می‌شود.
	 *
	 * @param int $download_id شناسه محصول دانلودی.
	 * @return array آرایه دو عضوی: [نوع کمیسیون، نام دسته‌بندی].
	 */
	private function get_product_categories( $download_id ) {
		// مقداردهی اولیه تا در هیچ مسیری متغیر تعریف‌نشده باقی نماند.
		$commission_type      = '';
		$category_name        = '';
		$main_commission_type = '';
		$main_category_name   = '';

		$terms = get_the_terms( $download_id, 'download_category' );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array( $commission_type, $category_name );
		}

		foreach ( $terms as $term ) {
			// دسته‌بندی ریشه (بدون والد) به عنوان گزینه جایگزین نگهداری می‌شود.
			if ( 0 === (int) $term->parent ) {
				$main_commission_type = (string) get_term_meta( $term->term_id, 'snp_commission_type', true );
				$main_category_name   = (string) $term->name;
			}

			$commission_type = (string) get_term_meta( $term->term_id, 'snp_commission_type', true );
			$category_name   = (string) $term->name;
		}

		// اگر آخرین دسته‌بندی مقداری نداشت، از دسته‌بندی ریشه استفاده می‌شود.
		$commission_type = '' !== $commission_type ? $commission_type : $main_commission_type;
		$category_name   = '' !== $category_name ? $category_name : $main_category_name;

		return array( $commission_type, $category_name );
	}

	/**
	 * ساخت ساختار «cartList» موردنیاز وب‌سرویس اسنپ‌پی از روی سفارش.
	 *
	 * @param \EDD\Orders\Order $order شیء سفارش EDD.
	 * @return array ساختار سبد خرید.
	 */
	private function build_cart_list( $order ) {
		$currency       = $order->currency;
		$send_comission = snapppay_edd_has_comission();

		$cart_list = array(
			'cartId'      => $order->id,
			'totalAmount' => snapppay_edd_get_order_amount_in_rial( $order ),
		);

		$items = array();

		foreach ( $order->get_items() as $item ) {
			list( $commission_type, $category_name ) = $this->get_product_categories( $item->product_id );

			$item_params = array(
				'name'     => $item->product_name,
				'count'    => (int) $item->quantity,
				'amount'   => snapppay_edd_amount_by_currency( $item->amount, $currency ),
				'id'       => (int) $item->product_id,
				'category' => $category_name,
			);

			if ( $send_comission ) {
				$item_params['commissionType'] = $commission_type;
			}

			$items[] = $item_params;
		}

		$cart_list['cartItems'] = $items;

		// مالیات سفارش، در صورت فعال بودن مالیات در فروشگاه.
		$tax_amount = snapppay_edd_amount_by_currency( $order->tax, $currency );

		$cart_list['taxAmount']    = $tax_amount;
		$cart_list['isTaxIncluded'] = $tax_amount > 0;

		// محصولات دانلودی حمل‌ونقل ندارند، بنابراین هزینه ارسال همیشه صفر است.
		$cart_list['shippingAmount']     = 0;
		$cart_list['isShipmentIncluded'] = false;

		return $cart_list;
	}

	/* ---------------------------------------------------------------------
	 * لایه ارتباط با شبکه
	 * ------------------------------------------------------------------ */

	/**
	 * تبدیل پاسخ خام wp_remote_request به آرایه، یا ساخت WP_Error.
	 *
	 * @param array|WP_Error $response پاسخ خام درخواست HTTP.
	 * @return array|WP_Error آرایه پاسخ یا خطا.
	 */
	public function process_response( $response ) {
		// خطای سطح شبکه (قطع اتصال، تایم‌اوت و ...).
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		// اگر بدنه پاسخ JSON نباشد یعنی خطایی رخ داده که سرور آن را ساختاریافته برنگردانده است.
		if ( ! snapppay_edd_is_json( $body ) ) {
			$code    = wp_remote_retrieve_response_code( $response );
			$message = wp_remote_retrieve_response_message( $response );

			return new WP_Error(
				$code ? $code : 'snapppay_invalid_response',
				$message ? $message : 'پاسخ نامعتبر از درگاه پرداخت دریافت شد.'
			);
		}

		return json_decode( $body, true );
	}

	/**
	 * ساخت هدرهای درخواست بر اساس نوع احراز هویت.
	 *
	 * @param string $token_type نوع توکن: Basic یا Bearer.
	 * @return array|false هدرها، یا false اگر دریافت توکن Bearer ناموفق باشد.
	 */
	private function prepare_request_headers( $token_type ) {
		$headers = ( 'Basic' === $token_type )
			? $this->get_request_basic_token()
			: $this->get_request_bearer_token();

		// دریافت توکن Bearer ناموفق بوده است.
		if ( ! is_array( $headers ) ) {
			return false;
		}

		// شناسایی افزونه برای تیم پشتیبانی اسنپ‌پی.
		$headers['user-agent'] = 'SnappPay-EDD, ' . $this->settings['client_id'];

		return $headers;
	}

	/**
	 * ارسال درخواست به وب‌سرویس اسنپ‌پی.
	 *
	 * اگر پاسخ ۴۰۱ باشد، توکن ذخیره‌شده منقضی فرض شده، حذف می‌شود و درخواست یک
	 * بار دیگر با توکن تازه تکرار می‌گردد.
	 *
	 * @param string $endpoint   مسیر نسبی وب‌سرویس.
	 * @param string $method     متد HTTP.
	 * @param string $token_type نوع احراز هویت: Basic یا Bearer.
	 * @param array  $args       پارامترهای درخواست.
	 * @return array|WP_Error پاسخ پردازش‌شده یا خطا.
	 */
	private function request( $endpoint, $method = 'GET', $token_type = 'Basic', $args = array() ) {
		$base_url = $this->get_api_base_url();

		if ( '' === $base_url ) {
			return new WP_Error( 'snapppay_missing_base_url', 'آدرس درگاه اسنپ‌پی در تنظیمات وارد نشده است.' );
		}

		$headers = $this->prepare_request_headers( $token_type );

		if ( false === $headers ) {
			return new WP_Error( 'snapppay_auth_failed', 'دریافت توکن دسترسی از درگاه اسنپ‌پی ناموفق بود.' );
		}

		$url     = $base_url . $endpoint;
		$request = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 30,
		);

		if ( 'GET' === $method ) {
			// پارامترهای درخواست GET به نشانی اضافه می‌شوند.
			if ( ! empty( $args ) ) {
				$url = add_query_arg( $args, $url );
			}
		} elseif ( 'Basic' === $token_type ) {
			// اندپوینت توکن، بدنه را به صورت form-urlencoded می‌پذیرد.
			$request['body'] = http_build_query( $args );
		} else {
			$request['body'] = wp_json_encode( $args );
		}

		$response = wp_remote_request( $url, $request );

		// توکن منقضی شده است؛ یک بار با توکن تازه دوباره تلاش می‌کنیم.
		if ( 401 === (int) wp_remote_retrieve_response_code( $response ) ) {
			delete_transient( self::TOKEN_TRANSIENT );

			$retry_headers = $this->prepare_request_headers( $token_type );

			if ( false === $retry_headers ) {
				return new WP_Error( 'snapppay_auth_failed', 'دریافت توکن دسترسی از درگاه اسنپ‌پی ناموفق بود.' );
			}

			$request['headers'] = $retry_headers;
			$response           = wp_remote_request( $url, $request );
		}

		return $this->process_response( $response );
	}
}
