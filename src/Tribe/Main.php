<?php
class Tribe__Tickets__Main {

	/**
	 * Current version of this plugin
	 */
	const VERSION = '4.10.6';

	/**
	 * Min required The Events Calendar version
	 *
	 * @deprecated 4.10
	 */
	const MIN_TEC_VERSION = '4.9.2-dev';

	/**
	 * Min required version of Tribe Common
	 *
	 * @deprecated 4.10
	 */
	const MIN_COMMON_VERSION = '4.9.9-dev';

	/**
	* Min Version of WordPress
	*
	* @since 4.10
	*/
	protected $min_wordpress = '4.7';

	/**
	* Min Version of PHP
	*
	* @since 4.10
	*/
	protected $min_php = '5.6';

	/**
	* Min Version of The Events Calendar
	*
	* @since 4.10
	*/
	protected $min_tec_version = '4.9.2-dev';

	/**
	 * Name of the provider
	 * @var string
	 */
	public $plugin_name;

	/**
	 * Directory of the plugin
	 * @var string
	 */
	public $plugin_dir;

	/**
	 * Path of the plugin
	 * @var string
	 */
	public $plugin_path;

	/**
	 * URL of the plugin
	 * @var string
	 */
	public $plugin_url;

	/**
	 * @var Tribe__Tickets__Legacy_Provider_Support
	 */
	public $legacy_provider_support;

	/**
	 * @var Tribe__Tickets__Shortcodes__User_Event_Confirmation_List
	 */
	private $user_event_confirmation_list_shortcode;

	/**
	 * @var Tribe__Tickets__Admin__Move_Tickets
	 */
	protected $move_tickets;

	/**
	 * @var Tribe__Tickets__Attendance_Totals
	 */
	protected $attendance_totals;

	/**
	 * @var Tribe__Tickets__Admin__Move_Ticket_Types
	 */
	protected $move_ticket_types;

	/**
	 * @var Tribe__Admin__Activation_Page
	 */
	protected $activation_page;

	/**
	 * Static Singleton Holder
	 * @var self
	 */
	protected static $instance;

	/**
	 * Get (and instantiate, if necessary) the instance of the class
	 *
	 * @return self
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Where in the themes we will look for templates
	 *
	 * @since 4.9
	 *
	 * @var string
	 */
	public $template_namespace = 'tickets';

	/**
	 * Class constructor
	 */
	protected function __construct() {
		/* Set up some parent's vars */
		$this->plugin_name = 'Tickets';
		$this->plugin_slug = 'tickets';
		$this->plugin_path = trailingslashit( EVENT_TICKETS_DIR );
		$this->plugin_dir = trailingslashit( basename( $this->plugin_path ) );

		$dir_prefix = '';

		if ( false !== strstr( EVENT_TICKETS_DIR, '/vendor/' ) ) {
			$dir_prefix = basename( dirname( dirname( EVENT_TICKETS_DIR ) ) ) . '/vendor/';
		}

		$this->plugin_url = trailingslashit( plugins_url( $dir_prefix . $this->plugin_dir ) );

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 0 );
		register_activation_hook( EVENT_TICKETS_MAIN_PLUGIN_FILE, array( $this, 'on_activation' ) );
	}

	/**
	 * Fires when the plugin is activated.
	 */
	public function on_activation() {
		// Set a transient we can use when deciding whether or not to show update/welcome splash pages
		if ( ! is_network_admin() && ! isset( $_GET['activate-multi'] ) ) {
			set_transient( '_tribe_tickets_activation_redirect', 1, 30 );
		}
	}

	/**
	 * Setup of Common Library
	 */
	public function maybe_set_common_lib_info() {

		$common_version = file_get_contents( $this->plugin_path . 'common/src/Tribe/Main.php' );

		// if there isn't a tribe-common version, bail
		if ( ! preg_match( "/const\s+VERSION\s*=\s*'([^']+)'/m", $common_version, $matches ) ) {
			add_action( 'admin_head', array( $this, 'missing_common_libs' ) );

			return;
		}

		$common_version = $matches[1];

		if ( empty( $GLOBALS['tribe-common-info'] ) ) {
			$GLOBALS['tribe-common-info'] = array(
				'dir'     => "{$this->plugin_path}common/src/Tribe",
				'version' => $common_version,
			);
		} elseif ( 1 == version_compare( $GLOBALS['tribe-common-info']['version'], $common_version, '<' ) ) {
			$GLOBALS['tribe-common-info'] = array(
				'dir'     => "{$this->plugin_path}common/src/Tribe",
				'version' => $common_version,
			);
		}
	}

	/**
	 * Finalize the initialization of this plugin
	 */
	public function plugins_loaded() {

		// early check for an older version of The Events Calendar to prevent fatal error
		if (
			class_exists( 'Tribe__Events__Main' ) &&
			! version_compare( Tribe__Events__Main::VERSION, $this->min_tec_version, '>=' )
		) {
			add_action( 'admin_notices', [ $this, 'tec_compatibility_notice' ] );
			add_action( 'network_admin_notices', [ $this, 'tec_compatibility_notice' ] );
			add_action( 'tribe_plugins_loaded', [ $this, 'remove_exts' ], 0 );
			/*
			* After common was loaded by another source (e.g. The Event Calendar) let's append this plugin source files
			* to the ones the Autoloader will search. Since we're appending them the ones registered by the plugin
			* "owning" common will be searched first.
			*/
			add_action( 'tribe_common_loaded', [ $this, 'register_plugin_autoload_paths' ] );

			return;
		}

		// WordPress and PHP Version Check
		if ( ! self::supported_version( 'wordpress' ) || ! self::supported_version( 'php' ) ) {
			add_action( 'admin_notices', array( $this, 'not_supported_error' ) );
			add_action( 'network_admin_notices', array( $this, 'not_supported_error' ) );

			return;
		}

		$this->maybe_set_common_lib_info();

		/**
		 * Before any methods from this plugin are called, we initialize our Autoloading
		 * After this method we can use any `Tribe__` classes
		 */
		$this->init_autoloading();

		if (
			class_exists( 'Tribe__Events__Main' ) &&
			! version_compare( Tribe__Events__Main::VERSION, $this->min_tec_version, '>=' )
		) {
			add_action( 'admin_notices', array( $this, 'tec_compatibility_notice' ) );
			add_action( 'network_admin_notices', array( $this, 'tec_compatibility_notice' ) );
			/**
			 * Fires if Event Tickets cannot load due to compatibility or other problems.
			 */
			do_action( 'tribe_tickets_plugin_failed_to_load' );
			return;
		}

		// Start Up Common
		Tribe__Main::instance();
		add_action( 'tribe_common_loaded', array( $this, 'bootstrap' ), 0 );
	}

	/**
	 * Load Text Domain on tribe_common_loaded as it requires common
	 *
	 * @since 4.10
	 *
	 */
	public function bootstrap() {

		Tribe__Main::instance( $this )->load_text_domain( 'event-tickets', $this->plugin_dir . 'lang/' );

		// Intialize the Service Provider for Tickets
		tribe_register_provider( 'Tribe__Tickets__Service_Provider' );

		$this->hooks();

		$this->register_active_plugin();

		$this->bind_implementations();
		$this->user_event_confirmation_list_shortcode();
		$this->move_tickets();
		$this->move_ticket_types();
		$this->activation_page();

		Tribe__Tickets__JSON_LD__Order::hook();
		Tribe__Tickets__JSON_LD__Type::hook();

		tribe( 'tickets.privacy' );

		/**
		 * Fires once Event Tickets has completed basic setup.
		 */
		do_action( 'tribe_tickets_plugin_loaded' );

	}

	/**
	 * Registers the implementations in the container
	 *
	 * @since 4.7
	 */
	public function bind_implementations() {
		tribe_singleton( 'tickets.main', $this );

		tribe_singleton( 'tickets.rsvp', new Tribe__Tickets__RSVP );
		tribe_singleton( 'tickets.commerce.currency', 'Tribe__Tickets__Commerce__Currency', array( 'hook' ) );
		tribe_singleton( 'tickets.commerce.paypal', new Tribe__Tickets__Commerce__PayPal__Main );
		tribe_singleton( 'tickets.redirections', 'Tribe__Tickets__Redirections' );

		// Attendee Registration Page
		tribe_register_provider( 'Tribe__Tickets__Attendee_Registration__Service_Provider' );

		// ORM
		tribe_register_provider( 'Tribe__Tickets__Service_Providers__ORM' );

		// REST API v1
		tribe_register_provider( 'Tribe__Tickets__REST__V1__Service_Provider' );
		// REST Editor APIs
		tribe_register_provider( 'Tribe__Tickets__Editor__REST__V1__Service_Provider' );

		// Blocks editor
		tribe_register_provider( 'Tribe__Tickets__Editor__Provider' );

		// Privacy
		tribe_singleton( 'tickets.privacy', 'Tribe__Tickets__Privacy', array( 'hook' ) );
	}

	/**
	 * Registers this plugin as being active for other tribe plugins and extensions
	 */
	protected function register_active_plugin() {
		$this->registered = new Tribe__Tickets__Plugin_Register();
	}

	/**
	 * Hooked to admin_notices, this error is thrown when Event Tickets is run alongside a version of
	 * TEC that is too old
	 */
	public function tec_compatibility_notice() {
		$active_plugins = get_option( 'active_plugins' );

		$plugin_short_path = null;

		foreach ( $active_plugins as $plugin ) {
			if ( false !== strstr( $plugin, 'the-events-calendar.php' ) ) {
				$plugin_short_path = $plugin;
				break;
			}
		}

		$upgrade_path = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'upgrade-plugin',
					'plugin' => $plugin_short_path,
				), get_admin_url() . 'update.php'
			), 'upgrade-plugin_' . $plugin_short_path
		);

		$output = '<div class="error">';
		$output .= '<p>' . sprintf( __( 'When The Events Calendar and Event Tickets are both activated, The Events Calendar must be running version %1$s or greater. Please %2$supdate now.%3$s', 'event-tickets' ), $this->min_tec_version, '<a href="' . esc_url( $upgrade_path ) . '">', '</a>' ) . '</p>';
		$output .= '</div>';

		echo $output;
	}

	/**
	 * Prevents Extensions from running if TEC is on an Older Version
	 *
	 * @since 4.10.0.1
	 *
	 */
	public function remove_exts() {

		remove_all_actions( 'tribe_plugins_loaded', 10 );

	}

	/**
	 * Test whether the current version of PHP or WordPress is supported.
	 *
	 * @since 4.10
	 *
	 * @param string $system Which system to test the version of such as 'php' or 'wordpress'.
	 *
	 * @return boolean Whether the current version of PHP or WordPress is supported.
	 */
	public function supported_version( $system ) {
		if ( $supported = wp_cache_get( $system, 'tribe_version_test' ) ) {
			return $supported;
		}

		switch ( strtolower( $system ) ) {
			case 'wordpress' :
				$supported = version_compare( get_bloginfo( 'version' ), $this->min_wordpress, '>=' );
				break;
			case 'php' :
				$supported = version_compare( phpversion(), $this->min_php, '>=' );
				break;
		}

		/**
		 * Filter whether the current version of PHP or WordPress is supported.
		 *
		 * @since 4.10
		 *
		 * @param boolean $supported Whether the current version of PHP or WordPress is supported.
		 * @param string  $system    Which system to test the version of such as 'php' or 'wordpress'.
		 */
		$supported = apply_filters( 'tribe_tickets_supported_system_version', $supported, $system );

		wp_cache_set( $system, $supported, 'tribe_version_test' );

		return $supported;
	}

	/**
	 * Display a WordPress or PHP incompatibility error.
	 *
	 * @since 4.10
	 */
	public function not_supported_error() {
		if ( ! self::supported_version( 'wordpress' ) ) {
			echo '<div class="error"><p>' . esc_html( sprintf( __( 'Sorry, Event Tickets requires WordPress %s or higher. Please upgrade your WordPress install.', 'event-tickets' ), $this->min_wordpress ) ) . '</p></div>';
		}

		if ( ! self::supported_version( 'php' ) ) {
			echo '<div class="error"><p>' . esc_html( sprintf( __( 'Sorry, Event Tickets requires PHP %s or higher. Talk to your Web host about moving you to a newer version of PHP.', 'event-tickets' ), $this->min_php ) ) . '</p></div>';
		}
	}

	/**
	 * Set the Event Tickets version in the options table if it's not already set.
	 */
	public function maybe_set_et_version() {
		if ( version_compare( Tribe__Settings_Manager::get_option( 'latest_event_tickets_version' ), self::VERSION, '<' ) ) {
			$previous_versions = Tribe__Settings_Manager::get_option( 'previous_event_tickets_versions' )
				? Tribe__Settings_Manager::get_option( 'previous_event_tickets_versions' )
				: array();

			$previous_versions[] = Tribe__Settings_Manager::get_option( 'latest_event_tickets_version' )
				? Tribe__Settings_Manager::get_option( 'latest_event_tickets_version' )
				: '0';

			Tribe__Settings_Manager::set_option( 'previous_event_tickets_versions', $previous_versions );
			Tribe__Settings_Manager::set_option( 'latest_event_tickets_version', self::VERSION );
		}
	}

	/**
	 * Sets up autoloading
	 */
	protected function init_autoloading() {
		$autoloader = $this->get_autoloader_instance();
		$this->register_plugin_autoload_paths();

		require_once $this->plugin_path . 'src/template-tags/tickets.php';

		// deprecated classes are registered in a class to path fashion
		foreach ( glob( $this->plugin_path . 'src/deprecated/*.php' ) as $file ) {
			$class_name = str_replace( '.php', '', basename( $file ) );
			$autoloader->register_class( $class_name, $file );
		}

		$autoloader->register_autoloader();
	}

	/**
	 * set up hooks for this class
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'init' ) );

		// connect upgrade script
		add_action( 'init', array( $this, 'run_updates' ), 0, 0 );

		add_filter( 'tribe_post_types', array( $this, 'inject_post_types' ) );

		// Setup Help Tab texting
		add_action( 'tribe_help_pre_get_sections', array( $this, 'add_help_section_support_content' ) );
		add_action( 'tribe_help_pre_get_sections', array( $this, 'add_help_section_featured_content' ) );
		add_action( 'tribe_help_pre_get_sections', array( $this, 'add_help_section_extra_content' ) );
		add_filter( 'tribe_support_registered_template_systems', array( $this, 'add_template_updates_check' ) );
		add_action( 'tribe_tickets_plugin_loaded', array( 'Tribe__Support', 'getInstance' ) );

		// Setup Front End Display
		add_action( 'tribe_events_inside_cost', 'tribe_tickets_buy_button', 10, 0 );

		// Hook to oembeds
		add_action( 'tribe_events_embed_after_the_cost_value', array( $this, 'inject_buy_button_into_oembed' ) );
		add_action( 'embed_head', array( $this, 'embed_head' ) );

		// Attendee screen enhancements
		add_action( 'tribe_events_tickets_attendees_event_details_top', array( $this, 'setup_attendance_totals' ), 20 );

		// CSV Import options
		if ( class_exists( 'Tribe__Events__Main' ) ) {
			add_filter( 'tribe_events_import_options_rows', array( Tribe__Tickets__CSV_Importer__Rows::instance(), 'filter_import_options_rows' ) );
			add_filter( 'tribe_aggregator_csv_post_types', array( Tribe__Tickets__CSV_Importer__Rows::instance(), 'filter_csv_post_types' ) );
			add_filter( 'tribe_aggregator_csv_column_mapping', array( Tribe__Tickets__CSV_Importer__Column_Names::instance(), 'filter_rsvp_column_mapping' ) );
			add_filter( 'tribe_event_import_rsvp_tickets_column_names', array( Tribe__Tickets__CSV_Importer__Column_Names::instance(), 'filter_rsvp_column_names' ) );
			add_filter( 'tribe_events_import_rsvp_tickets_importer', array( 'Tribe__Tickets__CSV_Importer__RSVP_Importer', 'instance' ), 10, 2 );
			add_action( 'tribe_tickets_ticket_deleted', array( 'Tribe__Tickets__Attendance', 'delete_attendees_caches' ) );

			/**
			 * Hooking to "rsvp" to fetch an importer to fetch Column names is deprecated
			 *
			 * These are kept in place during the transition from the old CSV importer to the new importer
			 * driven by Event Aggregator. We should remove these hooks when the old CSV interface gets
			 * retired completely.
			 *
			 * @todo remove these two hooks when the old CSV interface is retired, maybe 5.0?
			 */
			add_filter( 'tribe_events_import_rsvp_importer', array( 'Tribe__Tickets__CSV_Importer__RSVP_Importer', 'instance' ), 10, 2 );
			add_filter( 'tribe_event_import_rsvp_column_names', array( Tribe__Tickets__CSV_Importer__Column_Names::instance(), 'filter_rsvp_column_names' ) );
		}

		// Load our assets
		add_action( 'tribe_tickets_plugin_loaded', tribe_callback( 'tickets.assets', 'enqueue_scripts' ) );
		add_action( 'tribe_tickets_plugin_loaded', tribe_callback( 'tickets.assets', 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', tribe_callback( 'tickets.assets', 'enqueue_editor_scripts' ) );
		add_filter( 'tribe_asset_data_add_object_tribe_l10n_datatables', tribe_callback( 'tickets.assets', 'add_data_strings' ) );

		// Redirections
		add_action( 'wp_loaded', tribe_callback( 'tickets.redirections', 'maybe_redirect' ) );
	}

	/**
	 * Append the text about Event Tickets to the support section on the Help page
	 *
	 * @filter "tribe_help_pre_get_sections"
	 * @param Tribe__Admin__Help_Page $help The Help Page Instance
	 * @return void
	 */
	public function add_help_section_support_content( $help ) {
		$help->add_section_content( 'support', '<strong>' . esc_html__( 'Support for Event Tickets', 'event-tickets' ) . '</strong>', 20 );
		$help->add_section_content( 'support', array(
			'<strong><a href="http://m.tri.be/18ne" target="_blank">' . esc_html__( 'Settings overview', 'event-tickets' ) . '</a></strong>',
			'<strong><a href="http://m.tri.be/18nf" target="_blank">' . esc_html__( 'Features overview', 'event-tickets' ) . '</a></strong>',
			'<strong><a href="http://m.tri.be/18jb" target="_blank">' . esc_html__( 'Troubleshooting common problems', 'event-tickets' ) . '</a></strong>',
			'<strong><a href="http://m.tri.be/18ng" target="_blank">' . esc_html__( 'Customizing Event Tickets', 'event-tickets' ) . '</a></strong>',
		), 20 );
	}

	/**
	 * Append the text about Event Tickets to the Feature box section on the Help page
	 *
	 * @filter "tribe_help_pre_get_sections"
	 * @param Tribe__Admin__Help_Page $help The Help Page Instance
	 * @return void
	 */
	public function add_help_section_featured_content( $help ) {
		// If The Events Calendar is active dont add
		if ( $help->is_active( 'the-events-calendar', true ) ) {
			return;
		}

		$link = '<a href="http://m.tri.be/18nd" target="_blank">' . esc_html__( 'New User Primer', 'event-tickets' ) . '</a>';

		$help->add_section_content( 'feature-box', sprintf( __( 'We are committed to helping you sell tickets for your event. Check out our handy %s to get started.', 'event-tickets' ), $link ), 20 );
	}

	/**
	 * Append the text about Event Tickets to the Extra Help section on the Help page
	 *
	 * @filter "tribe_help_pre_get_sections"
	 * @param Tribe__Admin__Help_Page $help The Help Page Instance
	 * @return void
	 */
	public function add_help_section_extra_content( $help ) {
		if ( ! $help->is_active( array( 'events-calendar-pro', 'event-tickets-plus' ) ) && $help->is_active( 'the-events-calendar' ) ) {
			// We just skip because it's treated on TEC
			return;
		} elseif ( ! $help->is_active( 'the-events-calendar' ) ) {
			if ( ! $help->is_active( 'event-tickets-plus' ) ) {

				$link = '<a href="https://wordpress.org/support/plugin/event-tickets/" target="_blank">' . esc_html__( 'open-source forum on WordPress.org', 'event-tickets' ) . '</a>';
				$help->add_section_content( 'extra-help', sprintf( __( 'If you have tried the above steps and are still having trouble, you can post a new thread to our %s. Our support staff monitors these forums once a week and would be happy to assist you there.', 'event-tickets' ), $link ), 20 );

				$link_forum = '<a href="http://m.tri.be/4w/" target="_blank">' . esc_html__( 'premium support on our website', 'event-tickets' ) . '</a>';
				$link_plus = '<a href="http://m.tri.be/18ni" target="_blank">' . esc_html__( 'Events Tickets Plus', 'event-tickets' ) . '</a>';
				$help->add_section_content( 'extra-help', sprintf( __( 'Looking for more immediate support? We offer %1$s with the purchase of any of our premium plugins (like %2$s). Pick up a license and you can post there directly and expect a response within 24-48 hours during weekdays.', 'event-tickets' ), $link_forum, $link_plus ), 20 );

				$link = '<a href="http://m.tri.be/4w/" target="_blank">' . esc_html__( 'post a thread', 'event-tickets' ) . '</a>';
				$help->add_section_content( 'extra-help', sprintf( __( 'Already have Events Tickets Plus? You can %s in our premium support forums. Our support team monitors the forums and will respond to your thread within 24-48 hours (during the week).', 'event-tickets' ), $link ), 20 );

			}  else {

				$link = '<a href="http://m.tri.be/4w/" target="_blank">' . esc_html__( 'post a thread', 'event-tickets' ) . '</a>';
				$help->add_section_content( 'extra-help', sprintf( __( 'If you have a valid license for one of our paid plugins, you can %s in our premium support forums. Our support team monitors the forums and will respond to your thread within 24-48 hours (during the week).', 'event-tickets' ), $link ), 20 );

			}
		}
	}

	/**
	 * Register Event Tickets with the template update checker.
	 *
	 * @param array $plugins
	 *
	 * @return array
	 */
	public function add_template_updates_check( $plugins ) {
		$plugins[ __( 'Event Tickets', 'event-tickets' ) ] = array(
			self::VERSION,
			$this->plugin_path . 'src/views/tickets',
			trailingslashit( get_stylesheet_directory() ) . 'tribe-events/tickets',
		);

		return $plugins;
	}

	/**
	 * Hooked to the init action
	 */
	public function init() {
		// Provide continued support for legacy ticketing modules
		$this->legacy_provider_support = new Tribe__Tickets__Legacy_Provider_Support;
		$this->settings_tab();
		$this->tickets_view();
		Tribe__Credits::init();
		$this->maybe_set_et_version();
	}

	/**
	 * rsvp ticket object accessor
	 */
	public function rsvp() {
		return tribe( 'tickets.rsvp' );
	}

	/**
	 * Creates the Tickets FrontEnd facing View class
	 *
	 * This will happen on `plugins_loaded` by default
	 *
	 * @return Tribe__Tickets__Tickets_View
	 */
	public function tickets_view() {
		return Tribe__Tickets__Tickets_View::hook();
	}

	/**
	 * Default attendee list shortcode handler.
	 *
	 * @return Tribe__Tickets__Shortcodes__User_Event_Confirmation_List
	 */
	public function user_event_confirmation_list_shortcode() {
		if ( empty( $this->user_event_confirmation_list_shortcode ) ) {
			$this->user_event_confirmation_list_shortcode = new Tribe__Tickets__Shortcodes__User_Event_Confirmation_List;
		}

		return $this->user_event_confirmation_list_shortcode;
	}

	/**
	 * @return Tribe__Tickets__Admin__Move_Tickets
	 */
	public function move_tickets() {
		if ( empty( $this->move_tickets ) ) {
			$this->move_tickets = new Tribe__Tickets__Admin__Move_Tickets;
			$this->move_tickets->setup();
		}

		return $this->move_tickets;
	}

	/**
	 * @return Tribe__Tickets__Admin__Move_Ticket_Types
	 */
	public function move_ticket_types() {
		if ( empty( $this->move_ticket_types ) ) {
			$this->move_ticket_types = new Tribe__Tickets__Admin__Move_Ticket_Types;
			$this->move_ticket_types->setup();
		}

		return $this->move_ticket_types;
	}

	/**
	 * @return Tribe__Admin__Activation_Page
	 */
	public function activation_page() {
		if ( empty( $this->activation_page ) ) {
			$this->activation_page = new Tribe__Admin__Activation_Page( array(
				'slug'                  => 'event-tickets',
				'version'               => self::VERSION,
				'activation_transient'  => '_tribe_tickets_activation_redirect',
				'plugin_path'           => $this->plugin_dir . 'event-tickets.php',
				'version_history_slug'  => 'previous_event_tickets_versions',
				'welcome_page_title'    => esc_html__( 'Welcome to Event Tickets!', 'event-tickets' ),
				'welcome_page_template' => $this->plugin_path . 'src/admin-views/admin-welcome-message.php',
			) );
		}

		return $this->activation_page;
	}

	/**
	 * Adds RSVP attendance totals to the summary box of the attendance
	 * screen.
	 *
	 * Expects to fire during 'tribe_tickets_attendees_page_inside', ie
	 * before the attendee screen is rendered.
	 */
	public function setup_attendance_totals() {
		$this->attendance_totals()->integrate_with_attendee_screen();
	}

	/**
	 * @return Tribe__Tickets__Attendance_Totals
	 */
	public function attendance_totals() {
		if ( empty( $this->attendance_totals ) ) {
			$this->attendance_totals = new Tribe__Tickets__Attendance_Totals;
		}

		return $this->attendance_totals;
	}

	/**
	 * Provides the CSS version number for CSS files
	 *
	 * @return string
	 */
	public function css_version() {
		static $version;

		if ( ! $version ) {
			$version = apply_filters( 'tribe_tickets_css_version', self::VERSION );
		}

		return $version;
	}

	/**
	 * Provides the JS version number for JS scripts
	 *
	 * @return string
	 */
	public function js_version() {
		static $version;

		if ( ! $version ) {
			$version = apply_filters( 'tribe_tickets_js_version', self::VERSION );
		}

		return $version;
	}

	/**
	 * settings page object accessor
	 */
	public function settings_tab() {
		static $settings;

		if ( ! $settings ) {
			$settings = new Tribe__Tickets__Admin__Ticket_Settings;
		}

		return $settings;
	}

	/**
	 * Returns the supported post types for tickets
	 */
	public function post_types() {
		$options = (array) get_option( Tribe__Main::OPTIONNAME, array() );

		// if the ticket-enabled-post-types index has never been set, default it to tribe_events
		if ( ! array_key_exists( 'ticket-enabled-post-types', $options ) ) {
			$defaults                             = array( 'tribe_events' );
			$options['ticket-enabled-post-types'] = $defaults;
			tribe_update_option( 'ticket-enabled-post-types', $defaults );
		}

		// Remove WooCommerce Product and EDD post types to prevent recursion fatal error on save.
		$filtered_post_types = array_diff( (array) $options['ticket-enabled-post-types'], [ 'product', 'download' ] );

		/**
		 * Filters the list of post types that support tickets
		 *
		 * @param array $post_types Array of post types
		 */
		return apply_filters( 'tribe_tickets_post_types', $filtered_post_types );
	}

	/**
	 * Injects post types into the tribe-common post_types array
	 */
	public function inject_post_types( $post_types ) {
		$post_types = array_merge( $post_types, $this->post_types() );
		return $post_types;
	}

	/**
	 * Injects a buy/RSVP button into oembeds for events when necessary
	 */
	public function inject_buy_button_into_oembed() {
		$event_id = get_the_ID();

		if ( ! tribe_events_has_tickets( $event_id ) ) {
			return;
		}

		$tickets      = Tribe__Tickets__Tickets::get_all_event_tickets( $event_id );
		$has_non_rsvp = false;
		$available    = false;
		$now          = current_time( 'timestamp' );

		foreach ( $tickets as $ticket ) {
			if ( 'Tribe__Tickets__RSVP' !== $ticket->provider_class ) {
				$has_non_rsvp = true;
			}

			if (
				$ticket->date_in_range( $now )
				&& $ticket->is_in_stock()
			) {
				$available = true;
			}
		}

		// if there aren't any tickets available, bail
		if ( ! $available ) {
			return;
		}

		$button_text = $has_non_rsvp ? __( 'Buy', 'event-tickets' ) : _x( 'RSVP', 'button text', 'event-tickets' );
		/**
		 * Filters the text that appears in the buy/rsvp button on event oembeds
		 *
		 * @var string The button text
		 * @var int Event ID
		 */
		$button_text = apply_filters( 'event_tickets_embed_buy_button_text', $button_text, $event_id );

		ob_start();
		?>
		<a class="tribe-event-buy" href="<?php echo esc_url( tribe_get_event_link() ); ?>" title="<?php the_title_attribute() ?>" rel="bookmark"><?php echo esc_html( $button_text ); ?></a>
		<?php
		$buy_button = ob_get_clean();

		/**
		 * Filters the buy button that appears on event oembeds
		 *
		 * @var string The button markup
		 * @var int Event ID
		 */
		echo apply_filters( 'event_tickets_embed_buy_button', $buy_button, $event_id );
	}

	/**
	 * Adds content to the embed head tag
	 *
	 * The embed header DOES NOT have wp_head() executed inside of it. Instead, any scripts/styles
	 * are explicitly output
	 */
	public function embed_head() {
		$css_path = Tribe__Template_Factory::getMinFile( $this->plugin_url . 'src/resources/css/tickets-embed.css', true );
		$css_path = add_query_arg( 'ver', self::VERSION, $css_path );
		?>
		<link rel="stylesheet" id="tribe-tickets-embed-css" href="<?php echo esc_url( $css_path ); ?>" type="text/css" media="all">
		<?php
	}

	/**
	 * Make necessary database updates on admin_init
	 *
	 * @since 4.7.1
	 *
	 */
	public function run_updates() {
		if ( ! class_exists( 'Tribe__Updater' ) ) {
			return;
		}

		$updater = new Tribe__Tickets__Updater( self::VERSION );
		if ( $updater->update_required() ) {
			$updater->do_updates();
		}
	}

		/**
		* Hooked to admin_notices, this error is thrown when Event Tickets is run alongside a version of
		* Event Tickets Plus that is too old
		*
		* @deprecated 4.10
		*
		*/
		public function et_plus_compatibility_notice() {
			_deprecated_function( __METHOD__, '4.10', '' );

			$active_plugins = get_option( 'active_plugins' );

			$plugin_short_path = null;

			foreach ( $active_plugins as $plugin ) {
				if ( false !== strstr( $plugin, 'event-tickets-plus.php' ) ) {
					$plugin_short_path = $plugin;
					break;
				}
			}

			$upgrade_path = 'https://theeventscalendar.com/knowledgebase/manual-updates/';

			$output = '<div class="error">';
			$output .= '<p>' . sprintf( esc_html__( 'When Event Tickets and Event Tickets Plus are both activated, Event Tickets Plus must be running version %1$s or greater. Please %2$smanually update now%3$s.', 'event-tickets' ), preg_replace( '/^(\d\.[\d]+).*/', '$1', self::VERSION ), '<a href="' . esc_url( $upgrade_path ) . '" target="_blank">', '</a>' ) . '</p>';
			$output .= '</div>';

			echo $output;
		}

	/**
	 * Returns the autoloader singleton instance to use in a context-aware manner.
	 *
	 * @since 4.10.6
	 *
	 * @return \Tribe__Autoloader Teh singleton common Autoloader instance.
	 */
	public function get_autoloader_instance() {
		if ( ! class_exists( 'Tribe__Autoloader' ) ) {
			require_once $GLOBALS['tribe-common-info']['dir'] . '/Autoloader.php';

			Tribe__Autoloader::instance()->register_prefixes( [
				'Tribe__' => $GLOBALS['tribe-common-info']['dir'],
			] );
		}

		return Tribe__Autoloader::instance();
	}

	/**
	 * Registers the plugin autoload paths in the Common Autoloader instance.
	 *
	 * @since 4.10.6
	 */
	public function register_plugin_autoload_paths() {
		$prefixes = array(
			'Tribe__Tickets__' => $this->plugin_path . 'src/Tribe',
		);

		$this->get_autoloader_instance()->register_prefixes( $prefixes );
	}
}
