<?php

namespace Cerberus\AdminPluginsManager;

class PluginUpdater
{
	private $file;

	private $plugin;

	private $basename;

	private $active;

	private $username;

	private $repository;

	private $authorize_token;

	private $github_response;

	public function __construct( $file, $repository, $token = "" )
	{
		$this->file            = $file;
		$this->username        = 'Trizelos';
		$this->repository      = $repository;
		$this->authorize_token = $token;

		$this->set_plugin_properties();

		$this->initialize();
	}

	private function set_plugin_properties(): void
	{
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$this->plugin   = get_plugin_data( $this->file );
		$this->basename = plugin_basename( $this->file );
		$this->active   = is_plugin_active( $this->basename );
	}

	private function initialize(): void
	{
		add_filter( 'site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( &$this, 'after_install' ), 10, 3 );

		// Add Authorization Token to download_package
		add_filter( 'upgrader_pre_download', function ()
		{
			add_filter( 'http_request_args', [ $this, 'download_package' ], 15, 2 );

			return false; // upgrader_pre_download filter default return value.
		} );
	}

	public function modify_transient( $transient ): mixed
	{
		if ( ! str_contains( parse_url( $_SERVER["REQUEST_URI"], PHP_URL_PATH ), '/wp-admin/plugins.php' ) ) {
			return $transient;
		}

		if ( empty( $checked = $transient->checked ) ) {
			return $transient;
		}

		if ( ! array_key_exists( $this->basename, $checked ) ) {
			return $transient;
		}

		$this->get_repository_info(); // Get the repo info
		if ( ! isset( $this->github_response['tag_name'] ) ) {
			return $transient;
		}

		prew( $transient );

		$out_of_date = version_compare( $this->github_response['tag_name'], $checked[ $this->basename ], 'gt' ); // Check if we're out of date

		if ( $out_of_date ) {

			$new_files = $this->github_response['zipball_url']; // Get the ZIP

			$slug = current( explode( '/', $this->basename ) ); // Create valid slug

			$plugin = array( // setup our plugin info
				'url'         => $this->plugin["PluginURI"],
				'slug'        => $slug,
				'package'     => $new_files,
				'new_version' => $this->github_response['tag_name']
			);

			$transient->response[ $this->basename ] = (object) $plugin; // Return it in response
		}

		return $transient; // Return filtered transient
	}

	private function get_repository_info(): void
	{
		if ( is_null( $this->github_response ) ) { // Do we have a response?
			$args        = array();
			$request_uri = sprintf( 'https://api.github.com/repos/%s/%s/releases', $this->username, $this->repository ); // Build URI

			if ( $this->authorize_token ) { // Is there an access token?
				$args['headers']['Authorization'] = "bearer {$this->authorize_token}"; // Set the headers
			}

			$response = json_decode( wp_remote_retrieve_body( wp_remote_get( $request_uri, $args ) ), true ); // Get JSON and parse it

			if ( is_array( $response ) ) { // If it is an array
				$response = current( $response ); // Get the first item
			}

			$this->github_response = $response; // Set it to our property
		}
	}

	public function plugin_popup( $result, $action, $args ): mixed
	{
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( $args->slug !== current( explode( '/', $this->basename ) ) ) { // And it's our slug
			return $result;
		}
		$this->get_repository_info(); // Get our repo info

		if ( isset( $this->github_response['tag_name'] ) ) {
			return $result;
		}

		// Set it to an array
		$plugin = array(
			'name'              => $this->plugin["Name"],
			'slug'              => $this->basename,
			'requires'          => '5.3',
//						'tested'            => '5.*',
//						'rating'            => '100.0',
//						'num_ratings'       => '1',
//						'downloaded'        => '1',
//						'added'             => '2016-01-05',
			'version'           => $this->github_response['tag_name'],
			'author'            => $this->plugin["AuthorName"],
			'author_profile'    => $this->plugin["AuthorURI"],
			'last_updated'      => $this->github_response['published_at'],
			'homepage'          => $this->plugin["PluginURI"],
			'short_description' => $this->plugin["Description"],
			'sections'          => array(
				'Updates'     => $this->github_response['body'],
				'Description' => $this->plugin["Description"],
			),
			'download_link'     => $this->github_response['zipball_url']
		);

		return (object) $plugin; // Return the data
	}

	public function download_package( $args, $url ): mixed
	{

		if ( null !== $args['filename'] ) {
			if ( $this->authorize_token ) {
				$args = array_merge( $args, array( "headers" => array( "Authorization" => "token {$this->authorize_token}" ) ) );
			}
		}

		remove_filter( 'http_request_args', [ $this, 'download_package' ] );

		return $args;
	}

	public function after_install( $response, $hook_extra, $result ): mixed
	{
		global $wp_filesystem; // Get global FS object

		$install_directory = plugin_dir_path( $this->file ); // Our plugin directory
		$wp_filesystem->move( $result['destination'], $install_directory ); // Move files to the plugin dir
		$result['destination'] = $install_directory; // Set the destination for the rest of the stack

		if ( $this->active ) { // If it was active
			activate_plugin( $this->basename ); // Reactivate
		}

		$this->composer_dump_autoload();

		return $result;
	}

	private function composer_dump_autoload(): void
	{
		$cwd  = getcwd();
		$path = plugin_dir_path( $this->file );

		exec( "cd $path && composer dump-autoload -o" );
		exec( "cd $cwd" );

		prew( 'updated plugin: ' . $this->file );
	}
}

