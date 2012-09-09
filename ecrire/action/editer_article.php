<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2012                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

/**
 * Gestion de l'action editer_article et de l'API d'édition d'un article
 * 
 * @package SPIP\Core\Articles\Edition
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Point d'entrée pour la modification d'un article
 * 
 * @link http://doc.spip.org/@action_editer_article_dist
 * @param string $arg 
 *    identifiant de l'article ou 'new' lors d'une création d'article
 * @return array
 */
function action_editer_article_dist($arg=null) {
	include_spip('inc/autoriser');
	$err="";
	if (is_null($arg)){
		$securiser_action = charger_fonction('securiser_action', 'inc');
		$arg = $securiser_action();
	}

	// si id_article n'est pas un nombre, c'est une creation 
	// mais on verifie qu'on a toutes les donnees qu'il faut.
	if (!$id_article = intval($arg)) {
		$id_parent = _request('id_parent');
		if (!$id_parent)
			$err = _L("creation interdite d'un article sans rubrique");
		elseif(!autoriser('creerarticledans','rubrique',$id_parent))
			$err = _T("info_creerdansrubrique_non_autorise");
		else
			$id_article = article_inserer($id_parent);
	}

	// Enregistre l'envoi dans la BD
	if ($id_article > 0) $err = article_modifier($id_article);

	if ($err)
		spip_log("echec editeur article: $err",_LOG_ERREUR);

	return array($id_article,$err);
}

/**
 * Appelle toutes les fonctions de modification d'un article
 * La variable de retour est une chaine de langue en cas d'erreur, ou est vide sinon
 * 
 * @link http://doc.spip.org/@articles_set
 * @param int $id_article
 * @param null $set
 * @return string
 */
function article_modifier($id_article, $set=null) {

	// unifier $texte en cas de texte trop long
	trop_longs_articles();

	include_spip('inc/modifier');
	include_spip('inc/filtres');
	$c = collecter_requests(
		// white list
		objet_info('article','champs_editables'),
		// black list
		array('date','statut','id_parent'),
		// donnees eventuellement fournies
		$set
	);

	// Si l'article est publie, invalider les caches et demander sa reindexation
	$t = sql_getfetsel("statut", "spip_articles", "id_article=".intval($id_article));
	$invalideur = $indexation = false;
	if ($t == 'publie') {
		$invalideur = "id='article/$id_article'";
		$indexation = true;
	}

	if ($err = objet_modifier_champs('article', $id_article,
		array(
			'nonvide' => array('titre' => _T('info_nouvel_article')." "._T('info_numero_abbreviation').$id_article),
			'invalideur' => $invalideur,
			'indexation' => $indexation,
			'date_modif' => 'date_modif' // champ a mettre a date('Y-m-d H:i:s') s'il y a modif
		),
		$c))
		return $err;

	// Modification de statut, changement de rubrique ?
	$c = collecter_requests(array('date', 'statut', 'id_parent'),array(),$set);
	$err = article_instituer($id_article, $c);

	return $err;
}

/**
 * Inserer un nouvel article en base
 * 
 * @link http://doc.spip.org/@insert_article
 * @param int $id_rubrique
 * @global array $GLOBALS['meta']
 * @global array $GLOBALS['visiteur_session']
 * @global string $GLOBALS['spip_lang']
 * @return int
 */
function article_inserer($id_rubrique) {


	// Si id_rubrique vaut 0 ou n'est pas definie, creer l'article
	// dans la premiere rubrique racine
	if (!$id_rubrique = intval($id_rubrique)) {
		$row = sql_fetsel("id_rubrique, id_secteur, lang", "spip_rubriques", "id_parent=0",'', '0+titre,titre', "1");
		$id_rubrique = $row['id_rubrique'];
	} else $row = sql_fetsel("lang, id_secteur", "spip_rubriques", "id_rubrique=$id_rubrique");

	// eviter $id_secteur = NULL (erreur sqlite) si la requete precedente echoue 
	// cas de id_rubrique = -1 par exemple avec plugin "pages"
	$id_secteur = isset($row['id_secteur']) ? $row['id_secteur'] : 0;
	
	$lang_rub = $row['lang'];

	$lang = "";
	$choisie = 'non';
	// La langue a la creation : si les liens de traduction sont autorises
	// dans les rubriques, on essaie avec la langue de l'auteur,
	// ou a defaut celle de la rubrique
	// Sinon c'est la langue de la rubrique qui est choisie + heritee
	if (in_array('spip_articles',explode(',',$GLOBALS['meta']['multi_objets']))) {
		lang_select($GLOBALS['visiteur_session']['lang']);
		if (in_array($GLOBALS['spip_lang'],
		explode(',', $GLOBALS['meta']['langues_multilingue']))) {
			$lang = $GLOBALS['spip_lang'];
			$choisie = 'oui';
		}
	}

	if (!$lang) {
		$choisie = 'non';
		$lang = $lang_rub ? $lang_rub : $GLOBALS['meta']['langue_site'];
	}

	$champs = array(
		'id_rubrique' => $id_rubrique,
		'id_secteur' =>  $id_secteur,
		'statut' =>  'prepa',
		'date' => date('Y-m-d H:i:s'),
		'lang' => $lang,
		'langue_choisie' =>$choisie);

	// Envoyer aux plugins
	$champs = pipeline('pre_insertion',
		array(
			'args' => array(
				'table' => 'spip_articles',
			),
			'data' => $champs
		)
	);

	$id_article = sql_insertq("spip_articles", $champs);

	pipeline('post_insertion',
		array(
			'args' => array(
				'table' => 'spip_articles',
				'id_objet' => $id_article
			),
			'data' => $champs
		)
	);

	// controler si le serveur n'a pas renvoye une erreur
	if ($id_article > 0 AND $GLOBALS['visiteur_session']['id_auteur']) {
		include_spip('action/editer_auteur');
		auteur_associer($GLOBALS['visiteur_session']['id_auteur'], array('article'=>$id_article));
	}

	return $id_article;
}


/** 
 * Modification d'un article
 * 
 * 
 * @link http://doc.spip.org/@instituer_article
 * @param int $id_article 
 * @param array $c 
 *    un array ('statut', 'id_parent' = changement de rubrique)
 *    statut et rubrique sont lies, car un admin restreint peut deplacer
 *    un article publie vers une rubrique qu'il n'administre pas
 * @param bool $calcul_rub
 * @global array $GLOBALS['meta'] 
 * @return string
 */
function article_instituer($id_article, $c, $calcul_rub=true) {

	include_spip('inc/autoriser');
	include_spip('inc/rubriques');
	include_spip('inc/modifier');

	$row = sql_fetsel("statut, date, id_rubrique", "spip_articles", "id_article=$id_article");
	$id_rubrique = $row['id_rubrique'];
	$statut_ancien = $statut = $row['statut'];
	$date_ancienne = $date = $row['date'];
	$champs = array();

	$d = isset($c['date'])?$c['date']:null;
	$s = isset($c['statut'])?$c['statut']:$statut;

	// cf autorisations dans inc/instituer_article
	if ($s != $statut OR ($d AND $d != $date)) {
		if (autoriser('publierdans', 'rubrique', $id_rubrique))
			$statut = $champs['statut'] = $s;
		else if (autoriser('modifier', 'article', $id_article) AND $s != 'publie')
			$statut = $champs['statut'] = $s;
		else
			spip_log("editer_article $id_article refus " . join(' ', $c));

		// En cas de publication, fixer la date a "maintenant"
		// sauf si $c commande autre chose
		// ou si l'article est deja date dans le futur
		// En cas de proposition d'un article (mais pas depublication), idem
		if ($champs['statut'] == 'publie'
		 OR ($champs['statut'] == 'prop' AND ($d OR !in_array($statut_ancien, array('publie', 'prop'))))
		) {
			if ($d OR strtotime($d=$date)>time())
				$champs['date'] = $date = $d;
			else
				$champs['date'] = $date = date('Y-m-d H:i:s');
		}
	}

	// Verifier que la rubrique demandee existe et est differente
	// de la rubrique actuelle
	if (isset($c['id_parent'])
	AND $id_parent = $c['id_parent']
	AND $id_parent != $id_rubrique
	AND (sql_fetsel('1', "spip_rubriques", "id_rubrique=$id_parent"))) {
		$champs['id_rubrique'] = $id_parent;

		// si l'article etait publie
		// et que le demandeur n'est pas admin de la rubrique
		// repasser l'article en statut 'propose'.
		if ($statut == 'publie'
		AND !autoriser('publierdans', 'rubrique', $id_rubrique))
			$champs['statut'] = 'prop';
	}

	// Envoyer aux plugins
	$champs = pipeline('pre_edition',
		array(
			'args' => array(
				'table' => 'spip_articles',
				'id_objet' => $id_article,
				'action'=>'instituer',
				'statut_ancien' => $statut_ancien,
				'date_ancienne' => $date_ancienne,
			),
			'data' => $champs
		)
	);

	if (!count($champs)) return '';

	// Envoyer les modifs.

	editer_article_heritage($id_article, $id_rubrique, $statut_ancien, $champs, $calcul_rub);

	// Invalider les caches
	include_spip('inc/invalideur');
	suivre_invalideur("id='article/$id_article'");

	if ($date) {
		$t = strtotime($date);
		$p = @$GLOBALS['meta']['date_prochain_postdate'];
		if ($t > time() AND (!$p OR ($t < $p))) {
			ecrire_meta('date_prochain_postdate', $t);
		}
	}

	// Pipeline
	pipeline('post_edition',
		array(
			'args' => array(
				'table' => 'spip_articles',
				'id_objet' => $id_article,
				'action'=>'instituer',
				'statut_ancien' => $statut_ancien,
				'date_ancienne' => $date_ancienne,
			),
			'data' => $champs
		)
	);

	// Notifications
	if ($notifications = charger_fonction('notifications', 'inc')) {
		$notifications('instituerarticle', $id_article,
			array('statut' => $statut, 'statut_ancien' => $statut_ancien, 'date'=>$date, 'date_ancienne' => $date_ancienne)
		);
	}

	return ''; // pas d'erreur
}

/**
 * fabrique la requete de modification de l'article, avec champs herites
 * 
 * @link http://doc.spip.org/@editer_article_heritage
 * @param int $id_article 
 * @param int $id_rubrique 
 * @param string $statut 
 * @param array $champs 
 * @param bool $cond 
 * @global array $GLOBALS['meta']
 * @return void
 */
function editer_article_heritage($id_article, $id_rubrique, $statut, $champs, $cond=true) {

	// Si on deplace l'article
	//  changer aussi son secteur et sa langue (si heritee)
	if (isset($champs['id_rubrique'])) {

		$row_rub = sql_fetsel("id_secteur, lang", "spip_rubriques", "id_rubrique=".sql_quote($champs['id_rubrique']));

		$langue = $row_rub['lang'];
		$champs['id_secteur'] = $row_rub['id_secteur'];
		if (sql_fetsel('1', 'spip_articles', "id_article=$id_article AND langue_choisie<>'oui' AND lang<>" . sql_quote($langue))) {
			$champs['lang'] = $langue;
		}
	}

	if (!$champs) return;

	sql_updateq('spip_articles', $champs, "id_article=$id_article");

	// Changer le statut des rubriques concernees 

	if ($cond) {
		include_spip('inc/rubriques');
		$postdate = ($GLOBALS['meta']["post_dates"] == "non" AND isset($champs['date']) AND (strtotime($champs['date']) < time()))?$champs['date']:false;
		calculer_rubriques_if($id_rubrique, $champs, $statut, $postdate);
	}
}

/**
 * Reunit les textes decoupes parce que trop longs
 * 
 * @link http://doc.spip.org/@trop_longs_articles
 * @return void
 */
function trop_longs_articles() {
	if (is_array($plus = _request('texte_plus'))) {
		foreach ($plus as $n=>$t) {
			$plus[$n] = preg_replace(",<!--SPIP-->[\n\r]*,","", $t);
		}
		set_request('texte', join('',$plus) . _request('texte'));
	}
}


// obsoletes
function revisions_articles ($id_article, $c=false) {
	return article_modifier($id_article,$c);
}
function revision_article ($id_article, $c=false) {
	return article_modifier($id_article,$c);
}
function articles_set($id_article, $set=null) {
	return article_modifier($id_article,$set);
}
function insert_article($id_rubrique) {
	return article_inserer($id_rubrique);
}
function instituer_article($id_article, $c, $calcul_rub=true) {
	return article_instituer($id_article,$c,$calcul_rub);
}
?>
