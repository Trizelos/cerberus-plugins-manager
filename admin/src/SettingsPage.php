<?php

namespace Cerberus\AdminPluginsManager;

use ZipArchive;

class SettingsPage
{
    private $file_name;

    public function __construct(string $file_name)
    {
        $this->file_name = $file_name;
        add_action('admin_menu', [$this, 'admin_menu'], 1000);
        add_action('admin_post_cpm_settings', [$this, 'admin_post_cpm_settings']);
        add_action('admin_post_nopriv_cpm_settings', [$this, 'admin_post_cpm_settings']);

        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        add_action('admin_post_download_and_extract_repo', [$this, 'download_and_extract_repo']);
        add_action('admin_post_nopriv_download_and_extract_repo', [$this, 'download_and_extract_repo']);
    }

    public final function admin_enqueue_scripts(): void
    {
        wp_enqueue_style('cpm-admin', plugin_dir_url($this->file_name) . 'admin/assets/css/admin.css');
    }

    public final function admin_menu(): void
    {
        $title = __('Cerberus Plugins Manager Settings', 'cerberus-plugins-manager');
        add_submenu_page('woocommerce', $title, $title, 'administrator', 'cerberus_plugins_manager', array(
            $this,
            'settingsPage'
        ));
    }

    public final function settingsPage(): void
    {
        $repoKey = get_option('repo-key', '');
        $title = __('Cerberus Plugins Manager Settings', 'cerberus-plugins-manager');

        ?>
        <div>
            <h3><?= $title; ?> </h3>
        </div>
        <div>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"
                  id="cpm_settings">
                <input type="hidden" name="action" value="cpm_settings"/>
                <label for="repo-key">
                    <span>Repo Key: </span>
                    <input type="text" name="repo-key" minlength="1">
                </label>
                <?php
                if (!empty($repoKey)) {
                    echo '<span>Your repo key is set but hidden away.</span>';
                } ?>
                <div>
                    <button class="button-primary" type="submit">Save</button>
                </div>
            </form>
        </div>
        <?php

        $repoKey = get_option('repo-key');
        $repos = $this->getRepos('Trizelos', $repoKey);

        $responses = apply_filters('cerberus-core-repos-settings', $repos);

        ?>
        <br>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" ,
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
                foreach ($responses as $name => $data) {
                    if ($name == 'cerberus-plugins-manager/cerberus-plugins-manager.php') {
                        continue;
                    }
                    $response = $data['response'];
                    $plugin_data = $data['plugin_data'];
                    $installed = !empty($response);

                    $out_of_date = false;
                    if (!empty($response['tag_name'])) {
                        $out_of_date = version_compare($response['tag_name'], $plugin_data['Version'], 'gt');
                    }
                    ?>
                    <tr class="<?= $out_of_date ? 'out-of-date' : ''; ?>">
                        <td><?= $name; ?></td>
                        <td><?= (!empty($response) && is_array($response) ? 'connected' : (!empty($response) ? $response : 'no release found')); ?></td>
                        <td>
                            <?= !empty($response['tag_name']) ? $response['tag_name'] : 'No version Found'; ?>
                        </td>
                        <td>
                            <?= !empty($plugin_data['Version']) ? $plugin_data['Version'] : 'Base'; ?>
                        </td>
                        <td>
                            <button class="install-button primary button"
                                    data-repo="<?= empty($data['name']) ? '' : $data['name']; ?>"
                                    onclick="document.querySelector('#repo-to-install').value =this.dataset.repo;">
                                <?= $installed ? "Update" : "Install" ?>
                            </button>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </form>
        <?php
    }

    public function getRepos(string $repository, string $authorizeToken): array
    {
        $args = array();
        $request_uri = sprintf('https://api.github.com/orgs/%s/repos', $repository); // Build URI

        if ($authorizeToken) { // Is there an access token?
            $args['headers']['Authorization'] = "bearer {$authorizeToken}"; // Set the headers
        }

        $responses = json_decode(wp_remote_retrieve_body(wp_remote_get($request_uri, $args)), true); // Get JSON and parse it

        $repos = [];
        foreach ($responses as $response) {
            if (!str_contains($response['name'], 'cerberus')) {
                continue;
            }
            $repos[$response['name'] . '/' . $response['name'] . '.php']['name'] = $response['name'];
            $repos[$response['name'] . '/' . $response['name'] . '.php']['response'] = "";
            $repos[$response['name'] . '/' . $response['name'] . '.php']['plugin_data'] = "";
        }

        return $repos;
    }

    public final function download_and_extract_repo(): void
    {
        if (empty($_POST['repo-to-install'])) {
            wp_redirect(wp_get_referer());
            return;
        }

        $repository = $_POST['repo-to-install'];

        $response = $this->getRepoResponse($repository);

        if (!isset($response["zipball_url"])) {
            wp_redirect(wp_get_referer());
            return;
        }

        $zip_url = $response["zipball_url"];
        $destination_path = WP_PLUGIN_DIR . '/' . $repository . ".zip";

        if (!$this->downloadZipFile($zip_url, $destination_path)) {
            wp_redirect(wp_get_referer());
            return;
        }


        $zip = new ZipArchive();
        if ($zip->open($destination_path) === true) {
            $pluginDestination = WP_PLUGIN_DIR;

            $name = $zip->getNameIndex(0);

            // extract the plugin
            $success = $zip->extractTo($pluginDestination);
            $zip->close();

            $pluginRepoPath = $pluginDestination . '/' . $repository;
            $pluginRepoGeneratedName = $pluginDestination . '/' . $name;

            // if old repo data exists delete it
            if ($success && is_dir($pluginRepoPath)) {
                $deletedOldRepo = $this->delTree($pluginRepoPath);
            }

//             rename the plugin to the correct name
            if (is_dir($pluginRepoGeneratedName)) {
                rename($pluginRepoGeneratedName, $pluginRepoPath);
            }

            // removes the zip file
            unlink($destination_path);

            // generate autoload files
            $this->composer_dump_autoload($pluginRepoPath);
        }
        wp_redirect(wp_get_referer());
    }

    private function getRepoResponse(string $repository): mixed
    {
        $username = 'Trizelos';
        $authorize_token = get_option('repo-key');
        $args = array();
        $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases', $username, $repository); // Build URI

        if ($authorize_token) { // Is there an access token?
            $args['headers']['Authorization'] = "bearer {$authorize_token}"; // Set the headers
        }

        $response = json_decode(wp_remote_retrieve_body(wp_remote_get($request_uri, $args)), true); // Get JSON and parse it

        if (is_array($response)) { // If it is an array
            $response = current($response); // Get the first item
        }

        return $response;
    }

    function downloadZipFile($url, $filepath): bool
    {
        $token = get_option('repo-key');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.140 Safari/537.36 Edge/18.17763');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $result = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        curl_close($ch);

        file_put_contents($filepath, $result);

        return (filesize($filepath) > 0) ? true : false;
    }

    private function delTree($dir): bool
    {
        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {

            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }

        return rmdir($dir);

    }

    private function composer_dump_autoload(string $filePath): void
    {
        exec("cd $filePath && composer dump-autoload -o");

        prew('updated plugin: ' . $filePath);
    }

    public final function admin_post_cpm_settings(): void
    {
        $data = $_POST;
        if (!empty($data['repo-key'])) {
            update_option('repo-key', $data['repo-key']);
        }

        wp_redirect(wp_get_referer());
    }
}
