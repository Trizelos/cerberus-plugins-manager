<?php

/**
 * Plugin Name:       Cerberus Plugins Manager
 * Description:       Manages the cerberus plugins
 * Version:           1.0.6
 * Requires at least: 5.7
 * Author:            Casey
 */

use Cerberus\AdminPluginsManager\SettingsPage;

require_once "vendor/autoload.php";

$settingsPage = new SettingsPage( __FILE__ );
