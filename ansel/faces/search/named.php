<?php
/**
 * Show all named faces.
 *
 * Copyright 2008-2012 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author Duck <duck@obala.net>
 */
require_once 'tabs.php';

$title = _("Named faces");
$page = Horde_Util::getFormData('page', 0);
$perpage = $prefs->getValue('facesperpage');
$results = array();
try {
    $count = $faces->countNamedFaces();
    $results = $faces->namedFaces($page * $perpage, $perpage);
} catch (Ansel_Exception $e) {
    $notification->push($count->getDebugInfo());
    $count = 0;
}

$vars = Horde_Variables::getDefaultVariables();
$pager = new Horde_Core_Ui_Pager(
    'page', $vars,
    array(
        'num' => $count,
        'url' => 'faces/search/named.php',
        'perpage' => $perpage
    )
);

require $registry->get('templates', 'horde') . '/common-header.inc';
echo Horde::menu();
$notification->notify(array('listeners' => 'status'));
include ANSEL_TEMPLATES . '/faces/faces.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
