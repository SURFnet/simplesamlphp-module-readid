<?php

use SimpleSAML\Auth;

/**
 * This page serves as a ReadID login page.
 *
 * @package SimpleSAMLphp
 */

$t = new \SimpleSAML\XHTML\Template($config, 'readid:failed.tpl.php');
$translator = $t->getTranslator();
$t->data['header'] = $translator->t('{readid:readid:title}');
$t->data['failed'] = $translator->t('{readid:readid:failed}');

$t->show();
