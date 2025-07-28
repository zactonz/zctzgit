<?php
/**
 * Zactonz Git Plugin - Configuration Constants
 *
 * This file defines the necessary constants for the Zactonz Git plugin, including
 * the paths for the data and log directories.
 *
 * @author Team Zactonz
 * @copyright Zactonz Technologies
 * @link https://zactonz.com/
 * @version 1.0
 */

define('GITDEPLOY_HOME', getenv("HOME") ?: '/home/username');

define('GITDEPLOY_DATA_DIR', dirname(__DIR__) . '/data/');

define('GITDEPLOY_LOG_DIR', dirname(__DIR__) . '/logs/');

if (!file_exists(GITDEPLOY_DATA_DIR)) {
    
    mkdir(GITDEPLOY_DATA_DIR, 0755, true);
    
}

if (!file_exists(GITDEPLOY_LOG_DIR)) {
    
    mkdir(GITDEPLOY_LOG_DIR, 0755, true);
    
}

?>