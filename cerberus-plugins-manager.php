<?php

/**
 * Plugin Name:       Cerberus Plugins Manager
 * Description:       Manages the cerberus plugins
 * Version:           1.1.2
 * Requires at least: 5.7
 * Author:            Casey
 */

use Cerberus\AdminPluginsManager\PluginUpdater;
use Cerberus\AdminPluginsManager\SettingsPage;

require_once 'vendor/autoload.php';

if (is_admin()) {
    $settingsPage = new SettingsPage(__FILE__);
    $pluginUpdater = new PluginUpdater(__FILE__, 'cerberus-plugins-manager');
}
