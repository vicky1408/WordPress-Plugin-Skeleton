<?php

if( $_SERVER[ 'SCRIPT_FILENAME' ] == __FILE__ )
	die( 'Access denied.' );

if( !class_exists( 'WordPressPluginSkeleton' ) )
{
	/**
	 * @package WordPressPluginSkeleton
	 * @author Ian Dunn <ian@iandunn.name>
	 */
	class WordPressPluginSkeleton
	{
		// Declare variables and constants
		protected static $callbacksRegistered, $notices;
		const VERSION			= '0.2';
		const PREFIX			= 'wpps_';
		const DEBUG_MODE		= false;

		/**
		 * Register callbacks for actions and filters
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public static function registerHookCallbacks()
		{
			if( self::$callbacksRegistered === true )
				return;

			// NOTE: Make sure you update the did_action() parameter in the corresponding callback method when changing the hooks here
			add_action( 'init',						__CLASS__ . '::init' );
			add_action( 'init',						__CLASS__ . '::upgrade', 11 );
			add_action( 'wpmu_new_blog', 			__CLASS__ . '::activateNewSite' );
			add_action( 'admin_enqueue_scripts',	__CLASS__ . '::loadResources' );
			add_action( 'shutdown',					__CLASS__ . '::shutdown' );
						
			WPPSCustomPostType::registerHookCallbacks();
			WPPSCron::registerHookCallbacks();
			WPPSSettings::registerHookCallbacks();
			
			self::$callbacksRegistered = true;
		}
		
		/**
		 * Prepares sites to use the plugin during single or network-wide activation
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param bool $networkWide
		 */
		public static function activate( $networkWide )
		{
			global $wpdb;
			
			if( did_action( 'activate_' . plugin_basename( dirname( __DIR__ ) . '/bootstrap.php' ) ) !== 1 )
				return;

			if( function_exists( 'is_multisite' ) && is_multisite() )
			{
				if( $networkWide )
				{
					$blogs = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

					foreach( $blogs as $b )
					{
						switch_to_blog( $b );
						self::singleActivate();
					}

					restore_current_blog();
				}
				else
					self::singleActivate();
			}
			else
				self::singleActivate();
		}

		/**
		 * Runs activation code on a new WPMS site when it's created
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param int $blogID
		 */
		public static function activateNewSite( $blogID )
		{
			if( did_action( 'wpmu_new_blog' ) !== 1 )
				return;

			switch_to_blog( $blogID );
			self::singleActivate();
			restore_current_blog();
		}

		/**
		 * Prepares a single blog to use the plugin
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected static function singleActivate()
		{
			WPPSCustomPostType::activate();
			WPPSCron::activate();
			WPPSSettings::activate();
			flush_rewrite_rules();
		}

		/**
		 * Rolls back activation procedures when de-activating the plugin
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public static function deactivate()
		{
			WPPSCustomPostType::deactivate();
			WPPSCron::deactivate();
			WPPSSettings::deactivate();
			flush_rewrite_rules();
		}
		
		/**
		 * Initializes variables
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public static function init()
		{
			if( did_action( 'init' ) !== 1 )
				return;

			self::$notices = IDAdminNotices::getSingleton();
			if( self::DEBUG_MODE )
				self::$notices->debugMode = true;
			
			try
			{
				$nonStatic = new WPPSNonStaticClass( 'Non-static example', '42' );
				//self::$notices->enqueue( $nonStatic->foo .' '. $nonStatic->bar );
			}
			catch( Exception $e )
			{
				self::$notices->enqueue( __METHOD__ . ' error: '. $e->getMessage(), 'error' );
			}
		}
		
		/**
		 * Checks if the plugin was recently updated and upgrades if necessary
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public static function upgrade()
		{
			if( did_action( 'init' ) !== 1 )
				return;
			
			if( version_compare( WPPSSettings::$settings[ 'db-version' ], self::VERSION, '==' ) )
				return;
			
			WPPSCustomPostType::upgrade( WPPSSettings::$settings[ 'db-version' ] );
			WPPSCron::upgrade( WPPSSettings::$settings[ 'db-version' ] );
			WPPSSettings::upgrade( WPPSSettings::$settings[ 'db-version' ] );
			
			WPPSSettings::updateSettings( array( 'db-version' => self::VERSION ) );

			self::clearCachingPlugins();
		}
		
		/**
		 * Clears caches of content generated by caching plugins like WP Super Cache
		 * @mvc Model
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected static function clearCachingPlugins()
		{
			// WP Super Cache
			if( function_exists( 'wp_cache_clear_cache' ) )
				wp_cache_clear_cache();

			// W3 Total Cache
			if( class_exists( 'W3_Plugin_TotalCacheAdmin' ) )
			{
				$w3TotalCache =& w3_instance( 'W3_Plugin_TotalCacheAdmin' );

				if( method_exists( $w3TotalCache, 'flush_all' ) )
					$w3TotalCache->flush_all();
			}
		}

		/**
		 * Enqueues CSS, JavaScript, etc
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public static function loadResources()
		{
			if( did_action( 'admin_enqueue_scripts' ) !== 1 )
				return;

			wp_register_style(
				self::PREFIX .'admin-style',
				plugins_url( 'css/admin.css', dirname( __FILE__ ) ),
				array(),
				self::VERSION,
				'all'
			);

			if( is_admin() )
				wp_enqueue_style( self::PREFIX . 'admin-style' );
		}
	} // end WordPressPluginSkeleton
	
	require_once( dirname( __DIR__  ) . '/includes/IDAdminNotices/id-admin-notices.php' );
	require_once( dirname( __FILE__ ) . '/wpps-custom-post-type.php' );
	require_once( dirname( __FILE__ ) . '/wpps-settings.php' );
	require_once( dirname( __FILE__ ) . '/wpps-cron.php' );
	require_once( dirname( __FILE__ ) . '/wpps-non-static-class.php' );
}

?>