<?php
/**
 * DIMP ListMessages view logic.  Abstracted out here to prevent imp.php from
 * becoming too cluttered.
 *
 * Copyright 2005-2008 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @package IMP
 */
class DIMP_Views_ListMessages
{
    /**
     * Returns a list of messages for use with ViewPort.
     *
     * @var array $args  TODO
     *
     * @return array  TODO
     */
    public function ListMessages($args)
    {
        $c_ptr = &$_SESSION['imp']['cache'];
        $folder = $args['folder'];
        $search_id = null;

        $sortpref = IMP::getSort($folder);

        /* If we're searching, do search. */
        if (!empty($args['filter']) &&
            !empty($args['searchfolder']) &&
            !empty($args['searchmsg'])) {
            /* Create the search query. */
            $query = new Horde_Imap_Client_Search_Query();

            /* Create message search list. */
            switch ($args['searchmsg']) {
            case 'msgall':
                $query->text($args['filter'], false);
                break;

            case 'from':
                $query->headerText('From', $args['filter']);
                break;

            case 'to':
                $query->headerText('To', $args['filter']);
                break;

            case 'subject':
                $query->headerText('Subject', $args['filter']);
                break;
            }

            /* Create folder search list. */
            switch ($args['searchfolder']) {
            case 'all':
                $imptree = &IMP_IMAP_Tree::singleton();
                $folder_list = $imptree->folderList();
                break;

            case 'current':
                $folder_list = array($folder);
                break;
            }

            /* Set the search in the IMP session. */
            $search_id = $GLOBALS['imp_search']->createSearchQuery($query, $folder_list, array(), _("Search Results"), isset($c_ptr['dimp_searchquery']) ? $c_ptr['dimp_searchquery'] : null);

            /* Folder is now the search folder. */
            $folder = $c_ptr['dimp_searchquery'] = $GLOBALS['imp_search']->createSearchID($search_id);
        }

        $label = IMP::getLabel($folder);

        /* Set the current time zone. */
        NLS::setTimeZone();

        /* Run filters now. */
        if (!empty($_SESSION['imp']['filteravail']) &&
            ($folder == 'INBOX') &&
            $GLOBALS['prefs']->getValue('filter_on_display')) {
            $imp_filter = new IMP_Filter();
            $imp_filter->filter($folder);
        }

        /* Generate the sorted mailbox list now. */
        $imp_mailbox = &IMP_Mailbox::singleton($folder);
        $sorted_list = $imp_mailbox->getSortedList();
        $msgcount = count($sorted_list['s']);

        /* Create the base object. */
        $result = new stdClass;
        $result->id = $folder;
        $result->totalrows = $msgcount;
        $result->label = $label;
        // @todo
        $result->cacheid = time();

        /* Determine the row slice to process. */
        if (isset($args['slice_rownum'])) {
            $rownum = max(1, $args['slice_rownum']);
            $slice_start = $args['slice_start'];
            $slice_end = $args['slice_end'];
        } else {
            list($u_uid, $u_mbox) = explode(IMP_IDX_SEP, $args['search_uid']);
            $result->rownum = $rownum = 1;
            foreach (array_keys($sorted_list['s'], $u_uid) as $val) {
                if (empty($sorted_list['m'][$val]) ||
                    ($sorted_list['m'][$val] == $u_mbox)) {
                    $rownum = $val;
                    break;
                }
            }

            $slice_start = $rownum - $args['search_before'];
            $slice_end = $rownum + $args['search_after'];
            if ($slice_start < 1) {
                $slice_end += abs($slice_start) + 1;
            } elseif ($slice_end > $msgcount) {
                $slice_start -= $slice_end - $msgcount;
            }
        }
        $slice_start = max(1, $slice_start);
        $slice_end = min($msgcount, $slice_end);

        /* Mail-specific viewport information. */
        $result->other = new stdClass;
        $md = &$result->other;
        if (!IMP::threadSortAvailable($folder)) {
            $md->nothread = 1;
        }
        $md->sortby = intval($sortpref['by']);
        $md->sortdir = intval($sortpref['dir']);
        if ($sortpref['limit']) {
            $md->sortlimit = 1;
        }
        if (IMP::isSpecialFolder($folder)) {
            $md->special = 1;
        }

        /* Check for mailbox existence now. If there are no messages, there
         * is a chance that the mailbox doesn't exist. If there is at least
         * 1 message, we don't need this check. */
        if (empty($msgcount) && is_null($search_id)) {
            $imp_folder = &IMP_Folder::singleton();
            if (!$imp_folder->exists($folder)) {
                $GLOBALS['notification']->push(sprintf(_("Mailbox %s does not exist."), $label), 'horde.error');
            }

            $result->data = $result->rowlist = array();
            return $result;
        }

        /* Generate the message list and the UID -> rownumber list. */
        $msglist = $rowlist = array();
        foreach (range($slice_start, $slice_end) as $key) {
            $msglist[$key] = $sorted_list['s'][$key];
            $rowlist[$msglist[$key] . ((isset($sorted_list['m'][$key]['m'])) ? $sorted_list['m'][$key]['m'] : $folder)] = $key;
        }
        $result->rowlist = $rowlist;

        /* Determine the list of UIDs that are currently cached on the
         * browser. Not technically necessary for ViewPort to work, but saves
         * a bunch of duplicative info being sent to browser. */
        $cached = array();
        if (is_null($search_id)) {
            if (!isset($c_ptr['dimp_msglist'])) {
                $c_ptr['dimp_msglist'] = array();
            }
            if (!empty($args['cacheid']) &&
                isset($c_ptr['dimp_msglist'][$folder])) {
                $cachestr = IMP::parseRangeString($c_ptr['dimp_msglist'][$folder]);
                $cached = reset($cachestr);
            }
            $c_ptr['dimp_msglist'][$folder] = IMP::toRangeString(array($folder => array_keys(array_flip(array_merge($cached, array_values($msglist))))));
        }

        /* Build the overview list. */
        $result->data = $this->_getOverviewData($imp_mailbox, $folder, array_keys(array_diff($msglist, $cached)));

        /* Get unseen/thread information. */
        if (is_null($search_id)) {
            $imptree = &IMP_IMAP_Tree::singleton();
            $info = $imptree->getElementInfo($folder);
            if (!empty($info)) {
                $md->unseen = $info['unseen'];
            }

            if ($sortpref['by'] == SORTTHREAD) {
                $threadob = $imp_mailbox->getThreadOb();
                $md->thread = array_filter($threadob->getThreadTreeOb($msglist, $sortpref['dir']));
            }
        } else {
            $result->search = true;
        }

        return $result;
    }

    /**
     * Return a reduced message list for use with ViewPort -- only a unique
     * ID/Rownum/UID/Mailbox mapping.  Used to select slices without needing
     * to obtain IMAP information for all messages in the slice.
     *
     * @var string $folder   The current folder.
     * @var integer $start   Starting row number.
     * @var integer $length  Slice length.
     *
     * @return array  The minimal message list.
     */
    public function getSlice($folder, $start, $length)
    {
        $start += 1;
        $end = $start + $length;

        $imp_mailbox = &IMP_Mailbox::singleton($folder);
        $sorted_list = $imp_mailbox->getSortedList();
        $data = array();
        for ($i = $start; $i < $end; ++$i) {
            $mbox = (empty($sorted_list['m'][$i])) ? $folder : $sorted_list['m'][$i];
            $id = $sorted_list['s'][$i];
            $data[$id . $mbox] = array(
                'imapuid' => $id,
                'rownum' => $i
            );
        }

        $result = new stdClass;
        $result->data = $data;
        $result->id = $folder;
        return $result;
    }

    /**
     * Obtains IMAP overview data for a given set of message UIDs.
     *
     * @var object IMP_Mailbox $imp_mailbox  An IMP_Mailbox:: object.
     * @var string $folder                   The current folder.
     * @var array $msglist                   The list of message sequence
     *                                       numbers to process.
     *
     * @return array TODO
     */
    private function _getOverviewData($imp_mailbox, $folder, $msglist)
    {
        $msgs = array();

        if (empty($msglist)) {
            return $msgs;
        }

        require_once 'Horde/Identity.php';
        require_once 'Horde/Text.php';

        /* Get mailbox information. */
        $overview = $imp_mailbox->getMailboxArray($msglist, false, array('list-post'));

        $charset = NLS::getCharset();
        $identity = &Identity::singleton(array('imp', 'imp'));
        $imp_ui = new IMP_UI_Mailbox($folder, $charset, $identity);

        /* Display message information. */
        reset($overview['overview']);
        while (list($msgIndex, $ob) = each($overview['overview'])) {
            /* Initialize the header fields. */
            $msg = array(
                'imapuid' => $ob['uid'],
                'menutype' => 'message',
                'rownum' => $msgIndex,
                'view' => $ob['mailbox'],
            );

            /* Get all the flag information. */
            $bg = array('msgRow');
            if ($_SESSION['imp']['protocol'] != 'pop') {
                if (!in_array('\\seen', $ob['flags'])) {
                    $bg[] = 'unseen';
                }
                if (in_array('\\answered', $ob['flags'])) {
                    $bg[] = 'answered';
                }
                if (in_array('\\draft', $ob['flags'])) {
                    $bg[] = 'draft';
                    $msg['menutype'] = 'draft';
                    $msg['draft'] = 1;
                }
                if (in_array('\\flagged', $ob['flags'])) {
                    $bg[] = 'flagged';
                }
                if (in_array('\\deleted', $ob['flags'])) {
                    $bg[] = 'deletedmsg';
                }
            }

            $attachment = '';
            if (!empty($GLOBALS['dimp_conf']['hooks']['msglist_format'])) {
                $ob_f = Horde::callHook('_dimp_hook_msglist_format', array($ob['mailbox'], $ob['uid']), 'dimp');
                if (is_a($ob_f, 'PEAR_Error')) {
                    Horde::logMessage($ob_f, __FILE__, __LINE__, PEAR_LOG_ERR);
                } else {
                    $attachment = empty($ob_f['atc']) ? '' : $ob_f['atc'];
                    if (!empty($ob_f['class'])) {
                        $bg = array_merge($bg, $ob_f['class']);
                    }
                }
            }

            $msg['bg'] = $bg;

            /* Format attachment information. */
            if (!empty($attachment)) {
                $msg['atc'] = $attachment;
            }

            /* Format size information. */
            $msg['size'] = htmlspecialchars($imp_ui->getSize($ob['size']), ENT_QUOTES, $charset);

            /* Format the Date: Header. */
            $msg['date'] = htmlspecialchars($imp_ui->getDate($ob['envelope']['date']), ENT_QUOTES, $charset);

            /* Format the From: Header. */
            $getfrom = $imp_ui->getFrom($ob['envelope'], false);
            $msg['from'] = htmlspecialchars($getfrom['from'], ENT_QUOTES, $charset);

            /* Format the Subject: Header. */
            $msg['subject'] = str_replace('&nbsp;', '&#160;', Text::htmlSpaces($imp_ui->getSubject($ob['envelope']['subject'])));

            if (!empty($GLOBALS['conf']['hooks']['mailboxarray'])) {
                $result = Horde::callHook('_dimp_hook_mailboxarray', array($msg, $ob), 'dimp');
                if (is_a($result, 'PEAR_Error')) {
                    Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
                } else {
                    $msg = $result;
                }
            }

            /* Check to see if this is a list message. Namely, we want to
             * check for 'List-Post' information because that is the header
             * that gives the e-mail address to reply to, which is all we
             * care about. */
            if ($ob['headers']->getValue('list-post')) {
                $msg['listmsg'] = 1;
            }

            /* Need both UID and mailbox to create a unique ID string. */
            $msgs[$ob['uid'] . $ob['mailbox']] = $msg;
        }

        return $msgs;
    }
}
