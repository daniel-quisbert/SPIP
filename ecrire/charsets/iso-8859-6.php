<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2008                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined("_ECRIRE_INC_VERSION")) return;


// arabic iso-8859-6 - http://czyborra.com/charsets/iso8859.html#ISO-8859-6

load_charset('iso-8859-1');

$trans = $GLOBALS['CHARSET']['iso-8859-1'];

$mod = Array(
0xA0=>0x00A0, 0xA4=>0x00A4, 0xAC=>0x060C, 0xAD=>0x00AD, 0xBB=>0x061B,
0xBF=>0x061F, 0xC1=>0x0621, 0xC2=>0x0622, 0xC3=>0x0623, 0xC4=>0x0624,
0xC5=>0x0625, 0xC6=>0x0626, 0xC7=>0x0627, 0xC8=>0x0628, 0xC9=>0x0629,
0xCA=>0x062A, 0xCB=>0x062B, 0xCC=>0x062C, 0xCD=>0x062D, 0xCE=>0x062E,
0xCF=>0x062F, 0xD0=>0x0630, 0xD1=>0x0631, 0xD2=>0x0632, 0xD3=>0x0633,
0xD4=>0x0634, 0xD5=>0x0635, 0xD6=>0x0636, 0xD7=>0x0637, 0xD8=>0x0638,
0xD9=>0x0639, 0xDA=>0x063A, 0xE0=>0x0640, 0xE1=>0x0641, 0xE2=>0x0642,
0xE3=>0x0643, 0xE4=>0x0644, 0xE5=>0x0645, 0xE6=>0x0646, 0xE7=>0x0647,
0xE8=>0x0648, 0xE9=>0x0649, 0xEA=>0x064A, 0xEB=>0x064B, 0xEC=>0x064C,
0xED=>0x064D, 0xEE=>0x064E, 0xEF=>0x064F, 0xF0=>0x0650, 0xF1=>0x0651,
0xF2=>0x0652
);

foreach ($mod as $num=>$val)
	$trans[$num]=$val;

$GLOBALS['CHARSET']['iso-8859-6'] = $trans;

?>
