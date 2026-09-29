<?php

/**
 * @package     Community Builder list module
 * @copyright   Copyright (C) 2014 Magnus Hasselquist. All rights reserved.
 * @copyright   Copyright (C) 2021 Tazzios. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Hasselquist\Module\CbList\Site\Dispatcher;

use Hasselquist\Module\CbList\Site\Helper\CbListHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_cblistmodule.
 *
 * @since  4.0.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data.
     *
     * @return  array
     *
     * @since   4.0.0
     */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();

        /** @var CbListHelper $helper */
        $helper = $this->getHelperFactory()->getHelper('CbListHelper');
        $result = $helper->getUsers($data['params'], $this->getApplication());

        $data['users']     = $result['users'];
        $data['debug']     = $result['debug'];
        $data['columns']   = $helper->getColumns($data['params']);
        $data['minWidth']  = $helper->getMinWidth($data['params']);
        $data['textAbove'] = (string) $data['params']->get('text-above', '');
        $data['textBelow'] = (string) $data['params']->get('text-below', '');

        return $data;
    }
}
