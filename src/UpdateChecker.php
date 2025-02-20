<?php
namespace Koodiini\UpdateChecker;

use stdClass;

class UpdateChecker
{
    protected $pluginSlug;
    protected $pluginId;
    protected $version;
    protected $updateUrl;

    /**
     * Constructor.
     *
     * @param string $pluginSlug The plugin slug (e.g., 'plugin-folder/plugin-file.php').
     * @param string $pluginId The plugin ID (e.g., 'plugin-folder').
     * @param string $version The current plugin version.
     * @param string $updateUrl The remote update URL.
     */
    public function __construct($pluginSlug, $pluginId, $version, $updateUrl)
    {
        $this->pluginSlug = $pluginSlug;
        $this->pluginId = $pluginId;
        $this->version = $version;
        $this->updateUrl = $updateUrl;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdates']);
    }

    /**
     * Check for plugin updates.
     *
     * @param stdClass $transient
     * @return stdClass
     */
    public function checkForUpdates($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remoteData = $this->fetchRemoteData();

        if ($remoteData) {
            $pluginData = $this->findPluginData($remoteData, $this->pluginId);

            if ($pluginData && version_compare($this->version, $pluginData['version'], '<')) {
                $transient->response[$this->pluginSlug] = (object) [
                    'slug'        => $this->pluginSlug,
                    'new_version' => $pluginData['version'],
                    'package'     => $pluginData['package'],
                ];
            }
        }

        return $transient;
    }

    /**
     * Fetch data from the remote server.
     *
     * @return array|false
     */
    private function fetchRemoteData()
    {
        $response = wp_remote_get($this->updateUrl);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log('Failed to fetch update data: ' . print_r($response, true));
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Find data for this specific plugin in the remote response.
     *
     * @param array $remoteData
     * @param string $pluginId
     * @return array|false
     */
    private function findPluginData($remoteData, $pluginId)
    {
        if (empty($remoteData['plugins']) || !is_array($remoteData['plugins'])) {
            return false;
        }

        foreach ($remoteData['plugins'] as $plugin) {
            if (isset($plugin['slug']) && $plugin['slug'] === $pluginId) {
                return $plugin;
            }
        }

        return false;
    }
}