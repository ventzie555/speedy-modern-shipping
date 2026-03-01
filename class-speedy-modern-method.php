<?php

/**
 * Speedy Modern Shipping Method Class
 */
if ( ! class_exists( 'WC_Speedy_Modern_Method' ) ) {

	class WC_Speedy_Modern_Method extends WC_Shipping_Method {

		/**
		 * Constructor for the shipping class
		 */
		public function __construct( $instance_id = 0 ) {
			// Fixes the "Missing parent constructor call" warning
			parent::__construct( $instance_id );

			$this->id                 = 'speedy_modern';
			$this->instance_id        = absint( $instance_id );
			$this->method_title       = __( 'Speedy Modern', 'speedy-modern' );
			$this->method_description = __( 'Fresh, conflict-free Speedy delivery for Bulgaria.', 'speedy-modern' );

			$this->supports = array(
				'shipping-zones',
				'instance-settings',
				'instance-settings-modal',
			);

			$this->init();
		}

		/**
		 * Initialize settings and hooks
		 */
		public function init(): void {
			// Load the settings API
			$this->init_form_fields();
			$this->init_settings();

			// Define user-set variables
			$this->title = $this->get_option( 'title', __( 'Speedy Delivery', 'speedy-modern' ) );

			// Save settings in admin
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		/**
		 * Processes and saves shipping method options in the admin area.
		 * We override this to clear our API cache immediately after saving.
		 */
		public function process_admin_options(): bool {
			$saved = parent::process_admin_options(); // Save the fields to the database

			$this->clear_speedy_cache(); // Clear the API transients

			return $saved;
		}

		/**
		 * Define the settings fields
		 */
		public function init_form_fields(): void {
			$this->form_fields = array(
				// --- SECTION: CONNECTION ---
				'section_api' => [
					'title' => __( 'Speedy API Connection', 'speedy-modern' ),
					'type'  => 'title',
				],
				'enabled' => [
					'title'   => __( 'Module Status', 'speedy-modern' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable/Disable', 'speedy-modern' ),
					'default' => 'yes',
				],
				'title' => [
					'title'       => __( 'Method Title', 'speedy-modern' ),
					'type'        => 'text',
					'default'     => __( 'Speedy Delivery', 'speedy-modern' ),
					'desc_tip'    => true,
				],
				'speedy_username' => [ // Changed from username to match your API methods
					'title' => __( 'Username', 'speedy-modern' ),
					'type'  => 'text'
				],
				'speedy_password' => [
					'title' => __( 'Password', 'speedy-modern' ),
					'type'  => 'password'
				],
			);

			// Only show advanced settings if API credentials are saved
			if ( $this->get_option('speedy_username') && $this->get_option('speedy_password') ) {
				$this->add_authenticated_fields();
			} else {
				$this->form_fields['info_msg'] = [
					'type'        => 'title',
					'description' => __( 'Please save your credentials to unlock shipping options.', 'speedy-modern' ),
				];
			}
		}

		/**
		 * Fields that require a valid API connection.
		 * These are merged into the main form_fields array.
		 */
		private function add_authenticated_fields(): void {
			$authenticated = [

				// --- SECTION: SENDER DETAILS ---
				'section_sender' => [
					'title' => __( 'Sender Information', 'speedy-modern' ),
					'type'  => 'title',
				],
				'sender_id' => [
					'title'   => __( 'Sender (Object)', 'speedy-modern' ),
					'type'    => 'select',
					'options' => $this->get_speedy_clients(),
				],
				'sender_name' => [
					'title' => __( 'Contact Person', 'speedy-modern' ),
					'type'  => 'text'
				],
				'sender_phone' => [
					'title' => __( 'Phone Number', 'speedy-modern' ),
					'type'  => 'text'
				],
				'sender_office' => [
					'title'   => __( 'Shipping from Office', 'speedy-modern' ),
					'type'    => 'select',
					'options' => $this->get_speedy_offices(),
				],
				'sender_time' => [
					'title'       => __( 'Working Day End Time', 'speedy-modern' ),
					'type'        => 'text',
					'placeholder' => '17:30',
					'description' => __( 'Format HH:MM', 'speedy-modern' ),
				],

				// --- SECTION: SERVICES & PRICING ---
				'section_services' => [
					'title' => __( 'Services & Pricing', 'speedy-modern' ),
					'type'  => 'title',
				],
				'uslugi' => [
					'title'    => __( 'Active Services', 'speedy-modern' ),
					'type'     => 'multiselect',
					'options'  => $this->get_speedy_services(),
					'default'  => '505',
				],
				'cenadostavka' => [
					'title'   => __( 'Pricing Method', 'speedy-modern' ),
					'type'    => 'select',
					'options' => [
						'speedycalculator' => __( 'Speedy Calculator', 'speedy-modern' ),
						'fixedprices'      => __( 'Fixed Price', 'speedy-modern' ),
						'freeshipping'     => __( 'Free Shipping', 'speedy-modern' ),
						'nadbavka'         => __( 'Calculator + Surcharge', 'speedy-modern' ),
					],
				],
				'fixed_shipping_office' => [
					'title' => __( 'Fixed Price to Office', 'speedy-modern' ),
					'type'  => 'number',
					'custom_attributes' => [ 'step' => '0.01' ],
				],
				'fixed_shipping_address' => [
					'title' => __( 'Fixed Price to Address', 'speedy-modern' ),
					'type'  => 'number',
					'custom_attributes' => [ 'step' => '0.01' ],
				],

				// --- SECTION: ADDITIONAL OPTIONS ---
				'section_extra' => [
					'title' => __( 'Additional Options', 'speedy-modern' ),
					'type'  => 'title',
				],
				'test_before_pay' => [
					'title'   => __( 'Options Before Payment', 'speedy-modern' ),
					'type'    => 'select',
					'options' => [
						'NO'   => __( 'None', 'speedy-modern' ),
						'OPEN' => __( 'Open', 'speedy-modern' ),
						'TEST' => __( 'Test', 'speedy-modern' ),
					],
				],
				'chuplivost' => [
					'title'   => __( 'Fragile', 'speedy-modern' ),
					'type'    => 'select',
					'options' => [
						'NO'  => __( 'No', 'speedy-modern' ),
						'YES' => __( 'Yes', 'speedy-modern' ),
					],
				],

				// --- SECTION: SPECIAL REQUIREMENTS ---
				'section_requirements' => [
					'title'       => __( 'Special Requirements', 'speedy-modern' ),
					'type'        => 'title',
					'description' => __( 'Configure options like Open/Test before pay and Fragile stickers.', 'speedy-modern' ),
				],
				'special_requirements' => [
					'title'    => __( 'Enabled Requirements', 'speedy-modern' ),
					'type'     => 'multiselect',
					'options'  => $this->get_speedy_special_requirements(),
				],
				'saturdayoption' => [
					'title'   => __( 'Saturday Delivery', 'speedy-modern' ),
					'type'    => 'select',
					'default' => 'NO',
					'options' => [
						'NO'  => __( 'No', 'speedy-modern' ),
						'YES' => __( 'Yes', 'speedy-modern' ),
					],
				],

				// --- SECTION: RETURNS & VOUCHERS ---
				'section_returns' => [
					'title' => __( 'Returns & Vouchers', 'speedy-modern' ),
					'type'  => 'title',
				],
				'vaucher' => [
					'title'   => __( 'Return Voucher', 'speedy-modern' ),
					'type'    => 'select',
					'options' => [
						'NO'  => __( 'No', 'speedy-modern' ),
						'YES' => __( 'Yes', 'speedy-modern' ),
					],
				],
				'vaucherpayer' => [
					'title'   => __( 'Return Payer', 'speedy-modern' ),
					'type'    => 'select',
					'options' => [
						'SENDER'    => __( 'Sender', 'speedy-modern' ),
						'RECIPIENT' => __( 'Recipient', 'speedy-modern' ),
					],
				],
			];

			$this->form_fields = array_merge( $this->form_fields, $authenticated );
		}

		/**
		 * Fetch available clients/contracts from Speedy API
		 *
		 * @return array Associative array of [clientId => Client Details]
		 */
		private function get_speedy_clients(): array {
			$cache_key = 'speedy_clients_cache_' . md5( $this->get_option( 'speedy_username' ) );
			$clients   = get_transient( $cache_key );

			// If cache exists, return it immediately
			if ( false !== $clients ) {
				return $clients;
			}

			$clients = [ '0' => __( '-- Select Client --', 'speedy-modern' ) ];

			$username = $this->get_option( 'speedy_username' );
			$password = $this->get_option( 'speedy_password' );

			if ( ! $username || ! $password ) {
				return $clients;
			}

			// Prepare API data
			$body = json_encode( [
				'userName' => $username,
				'password' => $password,
			] );

			// Execute API Call
			$ch = curl_init( 'https://api.speedy.bg/v1/client/contract' );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json',
				'Accept: application/json'
			] );

			$response = curl_exec( $ch );
			$error    = curl_error( $ch );
			curl_close( $ch );

			if ( $error ) {
				error_log( 'Speedy API Error (get_clients): ' . $error );
				return $clients;
			}

			$data = json_decode( $response, true );

			// Process and Format Data
			if ( isset( $data['clients'] ) && is_array( $data['clients'] ) ) {
				foreach ( $data['clients'] as $client ) {
					$client_id   = $client['clientId'] ?? '';
					$client_name = $client['clientName'] ?? '';
					$object_name = $client['objectName'] ?? '';
					$address     = $client['address']['fullAddressString'] ?? '';

					$clients[ $client_id ] = sprintf(
					/* translators: 1: ID, 2: Name, 3: Object, 4: Address */
						__( 'ID: %1$s, %2$s, %3$s, Address: %4$s', 'speedy-modern' ),
						$client_id,
						$client_name,
						$object_name,
						$address
					);
				}

				// Cache the results for 24 hours to prevent repeated API hits
				set_transient( $cache_key, $clients, DAY_IN_SECONDS );
			}

			return $clients;
		}

		/**
		 * Fetch available Speedy offices from API and sort them alphabetically
		 *
		 * @return array Associative array of [officeId => "Name - Address"]
		 */
		private function get_speedy_offices(): array {
			$cache_key = 'speedy_offices_cache_' . md5( $this->get_option( 'speedy_username' ) );
			$offices   = get_transient( $cache_key );

			// If cache exists, return it immediately
			if ( false !== $offices ) {
				return $offices;
			}

			$offices = [ '0' => __( '-- Select Office --', 'speedy-modern' ) ];

			$username = $this->get_option( 'speedy_username' );
			$password = $this->get_option( 'speedy_password' );

			if ( ! $username || ! $password ) {
				return $offices;
			}

			// Prepare API data (Country ID 100 is Bulgaria)
			$body = json_encode( [
				'userName'  => $username,
				'password'  => $password,
				'countryId' => 100,
			] );

			$ch = curl_init( 'https://api.speedy.bg/v1/location/office' );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json',
				'Accept: application/json'
			] );

			$response = curl_exec( $ch );
			$error    = curl_error( $ch );
			curl_close( $ch );

			if ( $error ) {
				error_log( 'Speedy API Error (get_offices): ' . $error );
				return $offices;
			}

			$data = json_decode( $response, true );

			if ( isset( $data['offices'] ) && is_array( $data['offices'] ) ) {
				$temp_offices = [];

				foreach ( $data['offices'] as $office ) {
					$id      = $office['id'];
					$name    = $office['name'] ?? '';
					$address = $office['address']['fullAddressString'] ?? '';

					// We store a sort_key to handle Bulgarian (Cyrillic) sorting correctly
					$temp_offices[ $id ] = [
						'sort_key' => mb_strtoupper( $name, 'UTF-8' ),
						'label'    => sprintf( '%s %s - %s', $id, $name, $address )
					];
				}

				// Sort alphabetically by the name (sort_key)
				uasort( $temp_offices, function ( $a, $b ) {
					return strcmp( $a['sort_key'], $b['sort_key'] );
				} );

				// Flatten the array back to [ id => label ] for the WooCommerce select field
				foreach ( $temp_offices as $id => $office_data ) {
					$offices[ $id ] = $office_data['label'];
				}

				// Cache for 24 hours
				set_transient( $cache_key, $offices, DAY_IN_SECONDS );
			}

			return $offices;
		}

		/**
		 * Fetch available Speedy services from API
		 *
		 * @return array Associative array of [serviceId => "ID - Service Name"]
		 */
		private function get_speedy_services(): array {
			$cache_key = 'speedy_services_cache_' . md5( $this->get_option( 'speedy_username' ) );
			$services_list = get_transient( $cache_key );

			// Return cached data if available
			if ( false !== $services_list ) {
				return $services_list;
			}

			$services_list = [];

			$username = $this->get_option( 'speedy_username' );
			$password = $this->get_option( 'speedy_password' );

			if ( ! $username || ! $password ) {
				return $services_list;
			}

			// Prepare API data
			$body = json_encode( [
				'userName' => $username,
				'password' => $password,
			] );

			// Execute API Call
			$ch = curl_init( 'https://api.speedy.bg/v1/services' );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json',
				'Accept: application/json'
			] );

			$response = curl_exec( $ch );
			$error    = curl_error( $ch );
			curl_close( $ch );

			if ( $error ) {
				error_log( 'Speedy API Error (get_services): ' . $error );
				return $services_list;
			}

			$data = json_decode( $response, true );

			// Process and Format Data
			if ( isset( $data['services'] ) && is_array( $data['services'] ) ) {
				foreach ( $data['services'] as $service ) {
					if ( isset( $service['id'] ) && isset( $service['name'] ) ) {
						// Format as "505 - CITY COURIER"
						$services_list[ $service['id'] ] = sprintf( '%s - %s', $service['id'], $service['name'] );
					}
				}

				// Cache for 24 hours
				set_transient( $cache_key, $services_list, DAY_IN_SECONDS );
			}

			return $services_list;
		}

		/**
		 * Fetch Special Requirements from Speedy API
		 */
		private function get_speedy_special_requirements(): array {
			$cache_key = 'speedy_requirements_cache_' . md5( $this->get_option( 'speedy_username' ) );
			$requirements = get_transient( $cache_key );

			if ( false !== $requirements ) {
				return $requirements;
			}

			$requirements = [];
			$username = $this->get_option( 'speedy_username' );
			$password = $this->get_option( 'speedy_password' );

			$body = json_encode([
				'userName' => $username,
				'password' => $password,
			]);

			$ch = curl_init( 'https://api.speedy.bg/v1/services/special' ); // Adjusted to your legacy endpoint logic
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );

			$response = curl_exec( $ch );
			curl_close( $ch );

			$data = json_decode( $response, true );

			if ( isset( $data['specialServices'] ) ) {
				foreach ( $data['specialServices'] as $service ) {
					$requirements[ $service['id'] ] = $service['name'];
				}
				set_transient( $cache_key, $requirements, DAY_IN_SECONDS );
			}

			return $requirements;
		}

		/**
		 * Clears all cached API data for this specific user
		 */
		private function clear_speedy_cache(): void {
			$user_hash = md5( $this->get_option( 'speedy_username' ) );

			delete_transient( 'speedy_clients_cache_' . $user_hash );
			delete_transient( 'speedy_offices_cache_' . $user_hash );
			delete_transient( 'speedy_services_cache_' . $user_hash );
			delete_transient( 'speedy_requirements_cache_' . $user_hash );
		}

		/**
		 * Calculate the shipping rate
		 */
		public function calculate_shipping( $package = array() ): void {
			// Only calculate the rate if enabled in settings
			if ( 'yes' !== $this->get_option( 'enabled' ) ) {
				return;
			}

			// Only calculate if the destination is Bulgaria
			if ( 'BG' !== $package['destination']['country'] ) {
				return;
			}

			// Calculate total weight of the package
			$weight = 0;
			foreach ( $package['contents'] as $item ) {
				$product = $item['data'];
				$weight  += (float) $product->get_weight() * $item['quantity'];
			}
			// Fallback to 1kg if weight is missing or 0
			$weight = $weight > 0 ? $weight : 1.0;

			// Get selected services from settings
			$services = $this->get_option( 'uslugi' );
			if ( empty( $services ) ) {
				return;
			}

			// Ensure services is an array (Multiselect returns array, but safety first)
			if ( ! is_array( $services ) ) {
				$services = array( $services );
			}

			$available_services = $this->get_speedy_services();

			// Loop through each selected service and get a quote
			foreach ( $services as $service_id ) {
				$cost = $this->fetch_speedy_api_rate( $service_id, $weight, $package['destination'] );

				if ( false !== $cost ) {
					// Use the service name from our cache as the label (e.g., "505 - City Courier")
					$label = isset( $available_services[ $service_id ] ) ? $available_services[ $service_id ] : $this->title;

					$rate = array(
						'id'      => $this->id . '_' . $service_id,
						'label'   => $label,
						'cost'    => $cost,
						'package' => $package,
					);

					$this->add_rate( $rate );
				}
			}
		}

		/**
		 * Fetch the shipping rate from Speedy API v1
		 */
		private function fetch_speedy_api_rate( $service_id, $weight, $destination ) {
			$username  = $this->get_option( 'speedy_username' );
			$password  = $this->get_option( 'speedy_password' );
			$sender_id = $this->get_option( 'sender_id' );

			if ( ! $username || ! $password || ! $sender_id ) {
				return false;
			}

			$payload = array(
				'userName'  => $username,
				'password'  => $password,
				'serviceId' => (int) $service_id,
				'sender'    => array(
					'clientId' => (int) $sender_id,
				),
				'recipient' => array(
					'countryId' => 100, // Bulgaria
					'postCode'  => $destination['postcode'],
					'siteName'  => $destination['city'],
					'address'   => $destination['address_1'] . ' ' . $destination['address_2'],
				),
				'content'   => array(
					'parcelsCount' => 1,
					'totalWeight'  => $weight,
				),
			);

			$ch = curl_init( 'https://api.speedy.bg/v1/calculate' );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $payload ) );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Accept: application/json'
			) );

			$response = curl_exec( $ch );
			$error    = curl_error( $ch );
			curl_close( $ch );

			if ( $error ) {
				return false;
			}

			$data = json_decode( $response, true );

			// Check if the API returned a valid total amount
			if ( isset( $data['amounts']['total'] ) ) {
				return $data['amounts']['total'];
			}

			return false;
		}
	}
}
