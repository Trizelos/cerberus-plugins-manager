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
	}

	public final function admin_enqueue_scripts(): void
	{
		wp_enqueue_style( 'cpm-admin', plugin_dir_url( $this->file_name ) . 'admin/assets/css/admin.css' );
	}

	public final function admin_menu(): void
	{
		$title = __( 'Cerberus Plugins Manager Settings', 'cerberus-plugins-manager' );
		add_submenu_page(
			'woocommerce',
			$title,
			$title,
			'administrator',
			'cerberus_plugins_manager',
			array( $this, 'settingsPage' )
		);
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

		$responses = apply_filters( 'cerberus-core-repos-settings', [] );

		?>
        <br>
        <table class="cpm">
            <thead>
            <tr>
                <th>Plugin</th>
                <th>Connection Status to Repo</th>
            </tr>
            </thead>
            <tbody>
			<?php
			foreach ( $responses as $name => $respons ) {
				?>
                <tr>
                    <td><?= $name; ?></td>
                    <td><?= ( ! empty( $respons ) && is_array( $respons ) ? 'connected' : ( ! empty( $respons ) ? $respons : 'no release found' ) ); ?></td>
                </tr>
			<?php } ?>
            </tbody>
        </table>
		<?php
	}

	public final function admin_post_cpm_settings(): void
	{
		$data = $_POST;
		if ( ! empty( $data['repo-key'] ) ) {
			update_option( 'repo-key', $data['repo-key'] );
		}

		wp_redirect( wp_get_referer() );
	}
}
