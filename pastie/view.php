<?php
/**
 * View a paste.
 *
 * Copyright 2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (BSD). If you
 * did not receive this file, see http://www.fsf.org/copyleft/bsd.html.
 *
 * @author Ben Klang <ben@alkaloid.net>
 */

require_once dirname(__FILE__) . '/lib/Application.php';
$pastie = Horde_Registry::appInit('pastie');

require_once PASTIE_BASE . '/lib/Forms/Paste.php';
$uuid = trim(Horde_Util::getPathInfo(), '/');

if (!empty($uuid)) {
    try {
        $paste = $pastie->driver->getPaste(array('uuid' => $uuid));
    } catch (Horde_Exception $e) {
        $notification->push($e);
        $paste = null;
    }
}

$pasteurl = Horde::applicationUrl('paste.php');
$vars = new Horde_Variables($paste);
$form = new PasteForm($vars);

try {
    $engine = 'Pastie_Highlighter_' . $GLOBALS['conf']['highlighter']['engine'];
    $output = $engine::output($paste['paste'], $paste['syntax']);
} catch (Pastie_Exception $e) {
    $output = _("Error parsing the paste.");
}

$title = _("View Paste");

require PASTIE_TEMPLATES . '/common-header.inc';
require PASTIE_TEMPLATES . '/menu.inc';

require PASTIE_TEMPLATES . '/view.inc';

$form->renderActive(null, null, $pasteurl, 'post');

require $registry->get('templates', 'horde') . '/common-footer.inc';
