<?php 
/*
	Plugin Name: Webmolen games and team info
	Plugin URI: http://www.webmolen.nl
	Description: Plugin for displaying games and team info from games database
	Author: M. van der Horst
	Version: 1.0
	Author URI: http://www.webmolen.nl
	*/
// function gti_admin_actions() {
	// add_options_page("Games and teams", "Games and teams", 1, "Games and teams", "gti_admin");
// }

// add_action('admin_menu', 'gti_admin_actions');

/** Step 2 (from text above). */
add_action( 'admin_menu', 'my_plugin_menu' );

/** Step 1. */
function my_plugin_menu() {
	add_options_page( 'Games and teams', 'Games and teams', 'manage_options', 'gti-games', 'my_plugin_options' );
}

/** Step 3. */
function my_plugin_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	echo '<div class="wrap">';
	echo '<p>Use the plugin with shortcodes:.</p>';
	echo '<ul><li>[games team="alles" beginday=0 endday=0 playfield=home max=10] (home/away/all)</li>
	<li>[results team="alles" beginday=200 max=10] (200 days back)</li>
	<li>[standings team="alles"]</li></ul>';
	echo 'The following teams are possible: <ul><li>alles</li>';
	$query = "SELECT `Team`.`team_naamkort`
		FROM `teams` AS `Team`
		WHERE (`Team`.`sport_id` = 1 OR `Team`.`sport_id` = 3)
		ORDER BY `Team`.`team_naamkort`";
	global $wpdb;
	$teams = $wpdb->get_results($query);
	
	foreach($teams as $key => $team) {
		echo '<li>';
		echo $team->team_naamkort;	
		echo '</li>';
	}
	echo '<ul>';
	echo '</div>';
	
}


// [games team="alles" beginday=0 endday=0 playfield=all] home/away/all
function gti_getgamestag( $atts ) {
    $a = shortcode_atts( array(
        'team' => 'alles',
        'beginday' => '0',
        'endday' => '0',
		'playfield' => 'all',
		'max' => '0'
    ), $atts );

    return gti_getgames($a['team'], $a['beginday'], $a['endday'], $a['playfield'], $a['max']);
}
add_shortcode( 'games', 'gti_getgamestag' );

// [results team="alles" beginday=0 endday=0]
function gti_getresultstag( $atts ) {
    $a = shortcode_atts( array(
        'team' => 'alles',
        'beginday' => '0',
        'endday' => '0',
		'max' => '0'
    ), $atts );

    return gti_getresults($a['team'], $a['beginday'], $a['endday'], $a['max']);
}
add_shortcode( 'results', 'gti_getresultstag' );

// [standings team="alles"]
function gti_getstandingstag( $atts ) {
    $a = shortcode_atts( array(
        'team' => 'alles'
    ), $atts );

    return gti_getstandings($a['team']);
}
add_shortcode( 'standings', 'gti_getstandingstag' );

function gti_getgames($team='alles',$beginday=0,$endday=0,$playfield='all',$max=0) {
	$retval = '';
	if (!is_numeric($beginday) || 0 == $beginday)
	{
		$start = date('Y-m-d',mktime(0,0,0,date('m'),date('d'),date('Y')));
	}
	else
	{
		$start = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-$beginday,date('Y')));	
	}
	if (!is_numeric($endday) || 0 == $endday)
	{
		if($team=='alles'){
			$end = date('Y-m-d',mktime(0,0,0,date('m'),date('d')+14,date('Y')));
		} else {
			$end = date('Y-m-d',mktime(0,0,0,date('m'),date('d')+350,date('Y')));
		}
	}
	else
	{
		$end = date('Y-m-d',mktime(0,0,0,date('m'),date('d')+$endday,date('Y'))); 
	}

	$sort = 'ASC';

	$query = "SELECT `Game`.`game_id`, `Team`.`team_naamkort`, 
		`Game`.`game_number`, `Game`.`canceled`, `Game`.`game_date`, 
		`Game`.`home`, `Game`.`away`, `Game`.`score_home`, `Game`.`score_away`, 
		`Game`.`umpire`, 
		`Competition`.`name`, `Gamefield`.`name` AS `field`, 
		`Game`.`home_game` FROM `games` AS `Game` LEFT JOIN `competitions` AS `Competition` 
		ON (`Game`.`competition_id` = `Competition`.`competition_id`) LEFT JOIN `gamefields` AS `Gamefield` 
		ON (`Game`.`gamefield_id` = `Gamefield`.`gamefield_id`) LEFT JOIN `teams` AS `Team`
		ON (`Competition`.`team_id` = `Team`.`team_id`)
		WHERE `Game`.`game_date` > '". $start . "' AND `Game`.`game_date` < '" . $end . "'	AND (`Team`.`sport_id` = 1 OR `Team`.`sport_id` = 3)";
	if($team != 'alles'){
		$query .=  " AND `Team`.`team_naamkort` = '" . $team . "' AND `Game`.`active` = 1";
	}
	if($playfield == 'home'){
		$query .= " AND `Game`.`home` = 1";
	}
	else if($playfield == 'away'){
		$query .= " AND `Game`.`home` = 0";
	}
	
	$query .= " ORDER BY date(`Game`.`game_date`) " . $sort . ", `Team`.`team_naamkort`, `Game`.`home_game` DESC, time(`Game`.`game_date`) ASC";
	if($max >0)
	{
		$query .= " LIMIT " . $max . " ";
	}
	global $wpdb;
	$games = $wpdb->get_results($query);
	// print_r($query);
	// print_r($games);
	// exit;
	$results = array();
	$regex = '/[0-9]/';	
	
	//$dagen = array('Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag');	
	$dagen_short = array('Zo', 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za');	
	$done_date = "";
	
	$retval .= '			<table width="100%">';
	//$retval .= '		'<caption>Programma</caption>';
			
	$first = true;
	$retval .=' 		<thead>
				<tr>
				<th style="width:71px;">Datum</th>
				<th>Tijd</th>
				<th>Nr</th>
				<th>Team</th>
				<th>Thuis</th>
				<th>Uit</th>
				<th>V</th>
				<th>Scheids</th>
				</tr>
				</thead>
				<tbody>';

	if($games == null)
	{	
		$retval .=  "<tr><td colspan='8'>Nog geen wedstrijden bekend</td></tr>";
		$retval .= "</tbody></table>";
		return $retval;					
	}
	$sortedGames = array();
	foreach($games as $key => $wedstrijd) {	
			$datum = strtotime($wedstrijd->game_date);
			$weddatum = date('d-m',$datum);
			$wedtijd = date('H:i',$datum);
			$wedstrijd->time = $wedtijd;
			$sortedGames[$weddatum][] = $wedstrijd;
	}
	//print_r($sortedGames);
	foreach($sortedGames as $key => $wedstrijddag)
	{
		$teams = array();
		$dates = array();
		$home = array();
		foreach ($wedstrijddag as $key => $row) {
			$home[$key] = $row->home;
			$teams[$key]  = $row->team_naamkort; 
			$dates[$key] = $row->game_date;
		}
		// print_r($wedstrijddag);
		// exit;
		$prev = '';
		$first = true;
		$prevtext = '';
		unset($time);
		foreach($wedstrijddag as $key => $wedstrijd)
		{
			if($wedstrijd->team_naamkort != $prev)
			{
				$prev = $wedstrijd->team_naamkort;							
				$prevtext = ($wedstrijd->home_game == 1 ? 0 : 1) . '-' . $wedstrijd->time . '-' . $wedstrijd->team_naamkort;
			}
			
			$wedstrijddag[$key]->test = $prevtext;
			
		}
		foreach ($wedstrijddag as $key => $val) {
			$time[$key] = $val->test;
		}

		array_multisort($time, SORT_ASC, $wedstrijddag);
		$home = -1;
		foreach($wedstrijddag as $wedstrijd) 
		{
			$first = true;
			if(stristr($wedstrijd->home, 'Vrij') === false 
					&& stristr($wedstrijd->away, 'Vrij') === false)
			{
				// $aanwtijd = "";
				$datum = strtotime($wedstrijd->game_date);
				$weddag = date('w',$datum);
				$weddatum = date('d-m',$datum);
				$wedtijd = date('H:i',$datum);
				
				$weddag = $dagen_short[$weddag]; 
				if(preg_match('/Alcmaria|Nighthawks/',$wedstrijd->home )){
					$wedstrijd->home = '<b>' . $wedstrijd->home . '</b>';
				} else if(preg_match('/Alcmaria|Nighthawks/',$wedstrijd->away ))  {
					$wedstrijd->away = '<b>' . $wedstrijd->away . '</b>';	
				}
	
				$retval .=   "<tr>
				<td>";
				$retval .=  $weddag. " " . $weddatum;
				$retval .=   "</td>
				<td>";
				$retval .= $wedtijd;
				$retval .=   "</td>			
				<td>";
				$retval .=  $wedstrijd->game_number;
				$retval .=   "</td>			
				<td>";
				$retval .=  $wedstrijd->team_naamkort;
				$retval .=   "</td>
				<td>";
				$retval .=  $wedstrijd->home;
				$retval .=   "</td>
				<td>";
				$retval .=  $wedstrijd->away;
				$retval .=   "</td>";
				if($wedstrijd->canceled){			
					$retval .=   '<td colspan="2" class="afgelast">-=AFGELAST=-</td>';
					} else {	
					$retval .=   "<td>";
					$retval .= $wedstrijd->field;
					$retval .=   "</td>
					<td>";
					$retval .= $wedstrijd->umpire;
					$retval .=   "</td>";
					}					

				$retval .=   "</tr>";
			}
		}
	}

	$retval .= "</tbody></table>";
	
	if ($team!='alles' && $games != null)
	{
		$retval .=  '<p><a href="http://www.alcmariavictrix.nl/ics_export/' . $team . '.ics"><span style="height: 30px;    vertical-align: center;padding-top: 9px;display: table-cell;text-align: center;"><img src="http://www.alcmariavictrix.nl/images/calendar-icon.png" height="20" width="20" style="border:none;vertical-align: middle;padding-right: 5px;"/><span>Toevoegen aan agenda</span></span></a></p>';
	}
	return $retval;
}

function gti_getresults($team='alles',$beginday=0,$endday=0,$max=0) {
	$retval = '';
	if (!is_numeric($beginday) || 0 == $beginday)
	{
		if($team=='alles'){
			$start = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-14,date('Y')));
		} else {
			$start = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-150,date('Y')));
		}			
	}
	else
	{
		$start = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-$beginday,date('Y')));	
	}
	
	if (!is_numeric($endday) || 0 == $endday)
	{
		$end = date('Y-m-d',mktime(0,0,0,date('m'),date('d')+1,date('Y')));
	}
	else
	{
		$end = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-$endday,date('Y'))); 
	}	
	
	$sort = "DESC";
	
	$query = "SELECT `Game`.`game_id`, `Team`.`team_naamkort`, 
		`Game`.`game_number`, `Game`.`canceled`, `Game`.`game_date`, 
		`Game`.`home`, `Game`.`away`, `Game`.`score_home`, `Game`.`score_away`, 
		`Game`.`umpire`, 
		`Competition`.`name`, `Gamefield`.`name` AS `field`, 
		`Game`.`home_game` FROM `games` AS `Game` LEFT JOIN `competitions` AS `Competition` 
		ON (`Game`.`competition_id` = `Competition`.`competition_id`) LEFT JOIN `gamefields` AS `Gamefield` 
		ON (`Game`.`gamefield_id` = `Gamefield`.`gamefield_id`) LEFT JOIN `teams` AS `Team`
		ON (`Competition`.`team_id` = `Team`.`team_id`)
		WHERE `Game`.`game_date` > '". $start . "' AND `Game`.`game_date` < '" . $end . "'	AND (`Team`.`sport_id` = 1 OR `Team`.`sport_id` = 3)";		
		if($max >0)
		{
			$query .= " AND `Game`.`score_home` > 0";
		}
	if($team != 'alles'){
		$query .=  " AND `Team`.`team_naamkort` = '" . $team . "' AND `Game`.`active` = 1";
	}
	$query .= " ORDER BY date(`Game`.`game_date`) " . $sort . ", `Team`.`team_naamkort`, `Game`.`home_game` DESC, time(`Game`.`game_date`) ASC";	
	
	if($max >0)
	{
		$query .= " LIMIT " . $max . " ";
	}
	global $wpdb;
	$games = $wpdb->get_results($query);
	//print_r($games);
	//exit;
	$results = array();
	$regex = '/[0-9]/';

	$dagen = array('Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag');	
	$dagen_short = array('Zon', 'Maa', 'Din', 'Woe', 'Don', 'Vri', 'Zat');	
	$done_date = "";
	
	$retval .= '			<table width="100%">';
	$retval .=' 		<thead>
			<tr>
			<th style="width:71px;">Datum</th>
			<th>Tijd</th>
			<th>Nr</th>
			<th>Team</th>
			<th>Thuis</th>
			<th>Uit</th>
			<th>Uitslag</th>
			</tr>
			</thead>
			<tbody>';

	if($games == null){
		$retval .= "<tr><td colspan='7'>Nog geen uitslagen bekend</td></tr>";
		$retval .= "</tbody></table>";
		return $retval;
	}
	$sortedGames = array();
	foreach($games as $game) {	
		$datum = strtotime($game->game_date);
		$weddatum = date('d-m',$datum);
		$wedtijd = date('H:i',$datum);
		$game->time = $wedtijd;
		$sortedGames[$weddatum][] = $game;
	}

	// print_r($sortedGames);
	// exit;
	foreach($sortedGames as $wedstrijddag)
	{
		$prev = '';
		$first = true;
		$prevtext = '';
		unset($time);
		foreach($wedstrijddag as $key => $wedstrijd)
		{
			if($wedstrijd->team_naamkort != $prev)
			{
				$prev = $wedstrijd->team_naamkort;							
				$prevtext = ($wedstrijd->home_game == 1 ? 0 : 1) . '-' . $wedstrijd->time . '-' . $wedstrijd->team_naamkort;
			}
			
			$wedstrijddag[$key]->test = $prevtext;
			
		}
		foreach ($wedstrijddag as $key => $val) {
			$time[$key] = $val->test;
		}

		array_multisort($time, SORT_ASC, $wedstrijddag);
		// print_r($wedstrijddag);
		// exit;
		foreach($wedstrijddag as $wedstrijd)
		{
			if(!$wedstrijd->canceled){
				
				$datum = strtotime($wedstrijd->game_date);
				$weddag = date('w',$datum);
				$weddatum = date('d-m',$datum);
				$wedtijd = date('H:i',$datum);
				
				$weddag = $dagen_short[$weddag]; 
				if(preg_match('/Alcmaria|Nighthawks/',$wedstrijd->home )){
					$wedstrijd->home = '<b>' . $wedstrijd->home . '</b>';
				} else if(preg_match('/Alcmaria|Nighthawks/',$wedstrijd->away ))  {
					$wedstrijd->away = '<b>' . $wedstrijd->away . '</b>';	
				}
				$retval .=   "<tr>
				<td>";
				$retval .=  $weddag. " " . $weddatum;
				$retval .=   "</td>
				<td>";
				$retval .= $wedtijd;
				$retval .=   "</td>			
				<td>";
				$retval .=  $wedstrijd->game_number;
				$retval .=   "</td>			
				<td>";
				$retval .=  $wedstrijd->team_naamkort;
				$retval .=   "</td>
				<td>";
				$retval .=  $wedstrijd->home;
				$retval .=   "</td>
				<td>";
				$retval .=  $wedstrijd->away;
				$retval .=   "</td>
				<td>";
				$retval .= $wedstrijd->score_home . " - " . $wedstrijd->score_away;
				$retval .= '</td>
					</tr>' ;
			}
		}
	} 
	$retval .= "</tbody></table>";
	return $retval;
}

function gti_getteam($team='') {

	$retval = '';
	return $retval;
}

function gti_getstandings($team='alles') {
	$retval = '';
	if($team == 'alles'){
		$query ="SELECT `Stand`.`content`, `Stand`.`stamp`, `Team`.`team_naam` FROM `standings` AS `Stand` LEFT JOIN `competitions` AS `Competition` 
		ON (`Stand`.`competition_id` = `Competition`.`competition_id`) LEFT JOIN `teams` AS `Team` ON (`Competition`.`team_id` = `Team`.`team_id`) ORDER BY Competition.sortordernr ASC";
	//$query = "SELECT standings.* FROM standings INNER JOIN teams ON stand.team_id = teams.team_id ORDER BY teams.sport_id ASC, teams.compNr ASC";

	} else {
		//if (in_array($team,$teams)){
		$query ="SELECT `Stand`.`content`, `Stand`.`stamp` FROM `standings` AS `Stand` LEFT JOIN `competitions` AS `Competition` 
		ON (`Stand`.`competition_id` = `Competition`.`competition_id`) LEFT JOIN `teams` AS `Team` ON (`Competition`.`team_id` = `Team`.`team_id`)
		WHERE `Team`.`team_naamkort` = '" . $team . "' ORDER BY `Competition`.`name` ASC";	 
		//$query = "SELECT stand.* FROM stand WHERE team_id = " . $team;		
	} 
	
	global $wpdb;
	$standen = $wpdb->get_results($query);
	
		
	$even = false;
	if($standen[0]){
	$retval .= 'standen per ' . date('d-m',strtotime($standen[0]->stamp)) . ', bron knbsb.nl';
	$retval .= '<div style="width:100%">';
	foreach($standen as $key => $stand) {
		if($team == 'alles'){
			$retval .= '<div class="standen"><h3>' . $stand->team_naam . '</h3>' . $stand->content . '</div>';
		} else {
			$retval .= $stand['content'];
		}
	}
	$retval .= '</div>';
	$retval .= '<div class="clear"></div>';

	} else 
		$retval .= 'Geen standen beschikbaar';
	
	return $retval;
}
?>