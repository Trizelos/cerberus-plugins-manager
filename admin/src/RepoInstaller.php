<?php

namespace Cerberus\AdminPluginsManager;

use ZipArchive;

class RepoInstaller
{
	private RepoManager $repoManager;

	public function __construct()
	{
		$this->repoManager = new RepoManager();
	}

	public function downloadExtractRepo( string $repository ): void
	{
		$response = $this->repoManager->getRepoInfoByRepoName( $repository );

		if ( ! isset( $response['zipball_url'] ) ) {
			wp_redirect( wp_get_referer() );

			return;
		}

		$zip_url          = $response['zipball_url'];
		$destination_path = WP_PLUGIN_DIR . '/' . $repository . '.zip';

		if ( ! $this->downloadZipFile( $zip_url, $destination_path ) ) {
			wp_redirect( wp_get_referer() );

			return;
		}

		$zip = new ZipArchive();
		if ( $zip->open( $destination_path ) === true ) {
			$pluginDestination = WP_PLUGIN_DIR;

			$name = $zip->getNameIndex( 0 );

			// extract the plugin
			$success = $zip->extractTo( $pluginDestination );
			$zip->close();

			$pluginRepoPath          = $pluginDestination . '/' . $repository;
			$pluginRepoGeneratedName = $pluginDestination . '/' . $name;

			// if old repo data exists delete it
			if ( $success && is_dir( $pluginRepoPath ) ) {
				$deletedOldRepo = $this->delTree( $pluginRepoPath );
			}

//             rename the plugin to the correct name
			if ( is_dir( $pluginRepoGeneratedName ) ) {
				rename( $pluginRepoGeneratedName, $pluginRepoPath );
			}

			// removes the zip file
			unlink( $destination_path );

			// generate autoload files
			$this->composer_dump_autoload( $pluginRepoPath );
		}
	}


	private function downloadZipFile( $url, $filepath ): bool
	{
		$token = get_option( 'repo-key' );

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.140 Safari/537.36 Edge/18.17763' );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $token
		) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );

		$result      = curl_exec( $ch );
		$status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );   //get status code
		curl_close( $ch );

		file_put_contents( $filepath, $result );

		return ( filesize( $filepath ) > 0 ) ? true : false;
	}

	private function delTree( $dir ): bool
	{
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			( is_dir( "$dir/$file" ) ) ? $this->delTree( "$dir/$file" ) : unlink( "$dir/$file" );
		}

		return rmdir( $dir );

	}

	private function composer_dump_autoload( string $filePath ): void
	{
		exec( "cd $filePath && composer dump-autoload -o" );
	}
}
