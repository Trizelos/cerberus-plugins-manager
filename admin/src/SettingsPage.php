<?php

namespace Cerberus\AdminPluginsManager;

class SettingsPage
{
	private $file_name;

	public function __construct( string $file_name )
	{
		$this->file_name = $file_name;
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 1000 );
		add_action( 'admin_post_cpm_settings', [ $this, 'admin_post_cpm_settings' ] );
		add_action( 'admin_post_nopriv_cpm_settings', [ $this, 'admin_post_cpm_settings' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

		add_action( 'admin_post_download_and_extract_repo', [ $this, 'download_and_extract_repo' ] );
		add_action( 'admin_post_nopriv_download_and_extract_repo', [ $this, 'download_and_extract_repo' ] );
	}

	public final function admin_enqueue_scripts(): void
	{
		wp_enqueue_style( 'cpm-admin', plugin_dir_url( $this->file_name ) . 'admin/assets/css/admin.css' );
	}

	public final function admin_menu(): void
	{
		$title = __( 'Cerberus Plugins Manager Settings', 'cerberus-plugins-manager' );
		add_submenu_page( 'woocommerce', $title, $title, 'administrator', 'cerberus_plugins_manager', array(
			$this,
			'settingsPage'
		) );
	}

	public final function settingsPage(): void
	{
		$repoKey = get_option( 'repo-key', '' );
		$title   = __( 'Cerberus Plugins Manager Settings', 'cerberus-plugins-manager' );

		?>
        <div>
            <h3><?= $title; ?> </h3>
        </div>
        <div>
            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"
                  id="cpm_settings">
                <input type="hidden" name="action" value="cpm_settings"/>
                <label for="repo-key">
                    <span>Repo Key: </span>
                    <input type="text" name="repo-key" minlength="1">
                </label>
				<?php
				if ( ! empty( $repoKey ) ) {
					echo '<span>Your repo key is set but hidden away.</span>';
				} ?>
                <div>
                    <button class="button-primary" type="submit">Save</button>
                </div>
            </form>
        </div>
		<?php

		$repoManager = new RepoManager();
		$repoList    = $repoManager->getRepoList();

		?>
        <br>
        <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" ,
              id="download_and_repo_install">
            <input type="hidden" name="action" value="download_and_extract_repo"/>
            <input type="hidden" name="repo-to-install" id="repo-to-install" value=""/>
            <table class="cpm">
                <thead>
                <tr>
                    <th>Plugin</th>
                    <th>Connection Status to Repo</th>
                    <th>Newest Version</th>
                    <th>Current Version</th>
                    <th>Install</th>
                </tr>
                </thead>
                <tbody>
				<?php
				foreach ( $repoList as $name => $data ) {
					if ( empty( $data ) || $name == 'cerberus-plugins-manager/cerberus-plugins-manager.php' ) {
						continue;
					}
					$response    = $data['repoInfos'];
					$plugin_data = $data['pluginData'];
					$state       = empty( $response ) ? 'install' : 'update';

					if ( ! empty( $response['tag_name'] ) ) {
						$state = version_compare( $response['tag_name'], $plugin_data['Version'], 'gt' ) ? 'out-of-date' : $state;
					}
					if ( empty( $plugin_data['Version'] ) ) {
						$state = 'install';
					}
					?>
                    <tr class="<?= $state ?>">
                        <td><?= $name; ?></td>
                        <td><?= ( ! empty( $response ) && is_array( $response ) ? 'connected' : ( ! empty( $response ) ? $response : 'no release found' ) ); ?></td>
                        <td>
							<?= ! empty( $response['tag_name'] ) ? $response['tag_name'] : 'No version Found'; ?>
                        </td>
                        <td>
							<?= ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : 'Not installed'; ?>
                        </td>
                        <td>
                            <button class="install-button primary button"
                                    data-repo="<?= empty( $data['name'] ) ? '' : $data['name']; ?>"
								<?= $state == 'update' ? 'disabled' : ''; ?>
                                    onclick="document.querySelector('#repo-to-install').value =this.dataset.repo;">
								<?= $state == 'install' ? 'Install' : 'Update' ?>
                            </button>
                        </td>
                    </tr>
				<?php } ?>
                </tbody>
            </table>
        </form>
		<?php
	}

	public final function download_and_extract_repo(): void
	{
		if ( empty( $_POST['repo-to-install'] ) ) {
			wp_redirect( wp_get_referer() );

			return;
		}

		$repository = $_POST['repo-to-install'];

		$repoInstaller = new RepoInstaller();
		$repoInstaller->downloadExtractRepo( $repository );
		wp_redirect( wp_get_referer() );
	}
}
