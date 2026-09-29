<?php

/**
 * @package     Community Builder list module
 * @copyright   Copyright (C) 2014 Magnus Hasselquist. All rights reserved.
 * @copyright   Copyright (C) 2021 Tazzios. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;

/**
 * Installer script for mod_cblistmodule.
 *
 * Checks the PHP and Joomla versions and removes the files of the legacy (pre 4.0) module layout.
 *
 * @since  4.0.0
 */
return new class () implements InstallerScriptInterface {
    /**
     * Minimum PHP version.
     *
     * @var    string
     * @since  4.0.0
     */
    private string $minimumPhp = '8.1.0';

    /**
     * Minimum Joomla version.
     *
     * @var    string
     * @since  4.0.0
     */
    private string $minimumJoomla = '4.4.0';

    /**
     * Files from the legacy module layout that must be removed on update.
     *
     * @var    string[]
     * @since  4.0.0
     */
    private array $legacyFiles = [
        'mod_cblistmodule.php',
        'helper.php',
        'cblisthelper.php',
        'index.html',
        'tmpl/index.html',
    ];

    /**
     * @inheritDoc
     */
    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('MOD_CBLISTMODULE_ERROR_PHP_VERSION', $this->minimumPhp, PHP_VERSION),
                'error'
            );

            return false;
        }

        if (version_compare((new Version())->getShortVersion(), $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('MOD_CBLISTMODULE_ERROR_JOOMLA_VERSION', $this->minimumJoomla, (new Version())->getShortVersion()),
                'error'
            );

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type !== 'update' && $type !== 'install') {
            return true;
        }

        $base = JPATH_SITE . '/modules/mod_cblistmodule/';

        foreach ($this->legacyFiles as $file) {
            $path = $base . $file;

            if (is_file($path)) {
                @unlink($path);
            }
        }

        return true;
    }
};
