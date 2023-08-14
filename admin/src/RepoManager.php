<?php

namespace Cerberus\AdminPluginsManager;

class RepoManager
{
    private string $username;
    private string $authorizeToken;

    public function __construct()
    {
        $this->username = 'Trizelos';
        $this->authorizeToken = get_option('repo-key');
    }

    public function getRepoList(): array
    {
        $repos = [];
        foreach ($this->getRepos() as $repo) {
            $name = $repo['name'];
            $repoInfos = $this->getRepoInfoByRepoName($name);

            if (empty($repoInfos)) {
                $repoInfos = '';
            }

            $pluginFile = WP_PLUGIN_DIR . '/' . $name . '/' . $name . '.php';
            $pluginData = get_plugin_data($pluginFile);

            $repos[$name . '/' . $name . '.php']['name'] = $name;
            $repos[$name . '/' . $name . '.php']['repoInfos'] = $repoInfos;
            $repos[$name . '/' . $name . '.php']['pluginData'] = $pluginData;
        }

        return $repos;
    }

    private function getRepos(): array
    {
        $request_uri = sprintf('https://api.github.com/orgs/%s/repos', $this->username); // Build URI
        $responses = $this->getResponse($request_uri);

        if (!is_array($responses) || empty($responses[0]['name'])) {
            return [];
        }

        $repos = [];
        foreach ($responses as $response) {
            if (!str_contains($response['name'], 'cerberus')) {
                continue;
            }
            $repos[] = $response;
        }

        return $repos;
    }

    private function getResponse(string $request_uri): mixed
    {
        $args = array();

        if ($this->authorizeToken) { // Is there an access token?
            $args['headers']['Authorization'] = "bearer {$this->authorizeToken}"; // Set the headers
        }

        $response = json_decode(wp_remote_retrieve_body(wp_remote_get($request_uri, $args)), true); // Get JSON and parse it

        return $response;
    }

    public function getRepoInfoByRepoName(string $repoName): array|bool
    {
        $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases', $this->username, $repoName); // Build URI
        $response = $this->getResponse($request_uri);

        if (is_array($response)) { // If it is an array
            $response = current($response); // Get the first item
        }

        return $response;
    }
}
