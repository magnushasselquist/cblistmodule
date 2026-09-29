<?php

/**
 * @package     Community Builder list module
 * @copyright   Copyright (C) 2014 Magnus Hasselquist. All rights reserved.
 * @copyright   Copyright (C) 2021 Tazzios. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

/**
 * Layout variables
 *
 * @var  \Joomla\Registry\Registry  $params     Module parameters
 * @var  array                      $users      List of ['id' => int, 'html' => string]
 * @var  array                      $debug      ['sql' => string, 'messages' => string[]] (empty unless a Super User enabled debug)
 * @var  string                     $columns    Number of grid columns or 'auto-fit'
 * @var  float                      $minWidth   Minimum column width in rem
 * @var  string                     $textAbove  Administrator HTML shown above the list
 * @var  string                     $textBelow  Administrator HTML shown below the list
 */

$style = sprintf(
    'margin: 0 auto; display: grid; grid-gap: 0.2rem; grid-template-columns: repeat(%s, minmax(%srem, 1fr));',
    htmlspecialchars($columns, ENT_QUOTES, 'UTF-8'),
    htmlspecialchars((string) $minWidth, ENT_QUOTES, 'UTF-8')
);
?>
<?php echo $textAbove; ?>
<?php if ($debug['sql'] !== '' || $debug['messages'] !== []) : ?>
    <div class="cblist-debug alert alert-info">
        <strong>DEBUG</strong>
        <?php foreach ($debug['messages'] as $message) : ?>
            <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endforeach; ?>
        <?php if ($debug['sql'] !== '') : ?>
            <pre><?php echo htmlspecialchars($debug['sql'], ENT_QUOTES, 'UTF-8'); ?></pre>
        <?php endif; ?>
    </div>
<?php endif; ?>
<div class="cblist" style="<?php echo $style; ?>">
    <?php foreach ($users as $user) : ?>
        <div class="cblist-user" style="padding: 5px; overflow-wrap: break-word;" data-user-id="<?php echo (int) $user['id']; ?>">
            <?php echo $user['html']; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php echo $textBelow; ?>
