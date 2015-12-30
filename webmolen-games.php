<?php 
/*
	Plugin Name: Webmolen games and team info
	Plugin URI: http://www.webmolen.nl
	Description: Plugin for displaying games and team info from games database
	Author: M. van der Horst
	Version: 1.0
	Author URI: http://www.webmolen.nl
	*/
function gti_admin_actions() {
	add_options_page("Games and teams", "Games and teams", 1, "Games and teams", "gti_admin");
}

add_action('admin_menu', 'gti_admin_actions');

// [games team="alles" beginday=0, endday=0]
function gti_getgamestag( $atts ) {
    $a = shortcode_atts( array(
        'team' => 'alles',
        'beginday' => '0',
        'endday' => '0',
    ), $atts );

    return gti_getgames($a['team'], $a['beginday'], $a['endday']);
}
add_shortcode( 'games', 'gti_getgamestag' );

// [results team="alles" beginday=0, endday=0]
function gti_getresultstag( $atts ) {
    $a = shortcode_atts( array(
        'team' => 'alles',
        'beginday' => '0',
        'endday' => '0',
    ), $atts );

    return gti_getresults($a['team'], $a['beginday'], $a['endday']);
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

function gti_getgames($team='alles',$beginday=0,$endday=0) {
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
	$query .= " ORDER BY date(`Game`.`game_date`) " . $sort . ", `Team`.`team_naamkort`, `Game`.`home_game` DESC, time(`Game`.`game_date`) ASC";
	global $wpdb;
	$games = $wpdb->get_results($query);
	// print_r($query);
	// print_r($games);
	// exit;
	$results = array();
	$regex = '/[0-9]/';	
	
	$dagen = array('Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag');	
	$dagen_short = array('Zo', 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za');	
	$done_date = "";
	
	$retval .= '			<table width="100%">
			<caption>Programma</caption>';
			
			$first = true;
			if ($team=='alles')
			{ 
	$retval .=' 		<thead>
				<tr>
				<th>Nummer</th>
				<th>Tijd</th>
				<th>Team</th>
				<th>Thuis</th>
				<th>Uit</th>
				<th>V</th>
				<th>Scheids</th>
				</tr>
				</thead>	' ;

				if($games != null)
				{
				$sortedGames = array();
				foreach($games as $key => $wedstrijd) {	
						$datum = strtotime($wedstrijd->game_date);
						$weddag = date('w',$datum);
						$weddatum = date('d-m',$datum);
						$wedtijd = date('H:i',$datum);
						$weddag = $dagen[$weddag]; 
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
							$aanwdatum = strtotime($wedstrijd->time_present);
							$weddag = date('w',$datum);
							$weddatum = date('d-m',$datum);
							$wedtijd = date('H:i',$datum);
							
							$weddag = $dagen[$weddag]; 
							if(preg_match('/Alcmaria|Nighthawks/',$wedstrijd->home )){
								$wedstrijd->home = '<b>' . $wedstrijd->home . '</b>';
							} else if(preg_match('/Alcmaria|Nighthawks/',$wedstrijd->away ))  {
								$wedstrijd->away = '<b>' . $wedstrijd->away . '</b>';	
							}
							
							if($weddatum != $done_date){
								$retval .=  "<tr><td colspan='7' class='table-header table-header-date'>".$weddag. " " . $weddatum . "</td></tr>";
								$done_date = $weddatum; 
								if(!$first)
								{
									$retval .= "<tr class='table-header-gegevens'>";
									$retval .=  "<td>Nummer</td>";
									$retval .=  "<td>Tijd</td>";
									$retval .=  "<td>Team</td>";
									$retval .=  "<td>Thuis</td>";
									$retval .=  "<td>Uit</td>";
									$retval .=  "<td>V</td>";
									$retval .=  "<td>Scheids</td>";
									$retval .=  "</tr>	";
								}
									
							}
						if($home != $wedstrijd->home_game)
						{
							$first = false;
							$home = $wedstrijd->home_game;
							if($home == 1)
								$retval .=   "<tr><td colspan='7' class='table-header'>Thuis wedstrijden</td></tr>";
							else if($home == 0)
								$retval .=   "<tr><td colspan='7' class='table-header'>Uit wedstrijden</td></tr>";
								
						}		
							$retval .=   "<tr>
							<td>";
							$retval .=  $wedstrijd->game_number;
							$retval .=   "</td>
							<td>";
							$retval .= $wedtijd;
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
					// echo '<tr><td colspan="8"><a href="http://www.alcmariavictrix.nl/ics_export/' . $team . '.ics">Download ICS</a></td></tr>';		
				}
				else {
					$retval .=  "<tr><td colspan='7'>Nog geen wedstrijden bekend</td></tr>";
				} 
			} 
			else 
			{ 
		$retval .= '
				<thead>
				<tr>
				<th>Nummer</th>
				<th>Datum</th>
				<th>Tijd</th>
				<th>Team</th>
				<th>Thuis</th>
				<th>Uit</th>
				<th>V</th>
				<th>Scheids</th>
				</tr>
				</thead>';
				if($games != null)
				{
					foreach($games as $key => $wedstrijd) 
					{ 
						if(stristr($wedstrijd->home, 'Vrij') === false 
								&& stristr($wedstrijd->away, 'Vrij') === false)
						{
							$aanwtijd = "";
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
							$retval .= '<tr>';
							$retval .= '<td>';
							$retval .= $wedstrijd->game_number;
							$retval .= '</td>';
							if($wedstrijd->canceled){		
								$retval .= '<td colspan="2" class="afgelast">-=AFGELAST=-</td>';
								} else{			
								$retval .= '<td>';
								$retval .= $weddag . ' ' . $weddatum;
								$retval .= '</td>';
								$retval .= '<td>';
								$retval .= $wedtijd;
								$retval .= '</td>';
								}
							$retval .= '<td>';
							$retval .= $wedstrijd->team_naamkort;
							$retval .= '</td>';
							$retval .= '<td>';
							$retval .= $wedstrijd->home;
							$retval .= '</td>';
							$retval .= '<td>';
							$retval .= $wedstrijd->away;
							$retval .= '</td>';
							if($wedstrijd->canceled){ 
								$retval .= '<td colspan="2" class="afgelast">-=AFGELAST=-</td>';
							} else{				
								$retval .= '<td>';
								$retval .= $wedstrijd->field;
								$retval .= '</td>';
								$retval .= '<td>';
								$retval .= $wedstrijd->umpire;
								$retval .= '</td>';
							}	
							$retval .= '</tr>';
								
						} 
					}
					$retval .=  '<tr><td colspan="8"><a href="http://www.alcmariavictrix.nl/ics_export/' . $team . '.ics"><span style="height: 30px;    vertical-align: center;padding-top: 9px;display: table-cell;text-align: center;"><img src="http://www.alcmariavictrix.nl/images/calendar-icon.png" height="20" width="20" style="border:none;vertical-align: middle;padding-right: 5px;"/><span>Toevoegen aan agenda</span></span></a></td></tr>';
				} else{ echo "<tr><td colspan='8'>Nog geen wedstrijden bekend</td></tr>";} 
			}
			//print_r($events);
			// $export->setTitle("AV " . $team . " Wedstrijden");
			// $ical = $export->toICal($events);
			// file_put_contents("ics_export/" . $team. ".ics", $ical);	

			$retval .= "</table>";
			return $retval;
}

function gti_getresults($team='alles',$beginday=0,$endday=0) {
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
	if($team != 'alles'){
		$query .=  " AND `Team`.`team_naamkort` = '" . $team . "' AND `Game`.`active` = 1";
	}
	$query .= " ORDER BY date(`Game`.`game_date`) " . $sort . ", `Team`.`team_naamkort`, `Game`.`home_game` DESC, time(`Game`.`game_date`) ASC";
	global $wpdb;
	$games = $wpdb->get_results($query);
	//print_r($games);
	//exit;
	$results = array();
	$regex = '/[0-9]/';

	$dagen = array('Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag');	
	$dagen_short = array('Zon', 'Maa', 'Din', 'Woe', 'Don', 'Vri', 'Zat');	
	$done_date = "";
	
	$retval .= '
					<table width="100%">
			<caption>Uitslagen</caption>
			<thead>
			<tr width="100%">
			<th>Nummer</th>
			<th>Tijd</th>
			<th>Team</th>
			<th>Thuis</th>
			<th>Uit</th>
			<th>Uitslag</th>
			</tr>
			</thead>';

	if($games != null){
		$sortedGames = array();
		foreach($games as $game) {	
			$datum = strtotime($game->game_date);
			//$weddag = date('w',$datum);
			$weddatum = date('d-m',$datum);
			$wedtijd = date('H:i',$datum);
			//$weddag = $dagen[$weddag]; 
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
					$weddag = $dagen[$weddag]; 
					
					if($weddatum != $done_date){
						$retval .= "<tr><td colspan='6' class='table-header'>".$weddag. " " . $weddatum . "</td></tr>";
						$done_date = $weddatum;
					}
					
					if(preg_match('/Alcmaria|Nighthawks/',$wedstrijd->home )){
						$wedstrijd->home = '<b>' . $wedstrijd->home . '</b>';
					} else if(preg_match('/Alcmaria|Nighthawks/',$wedstrijd->away )){
						$wedstrijd->away = '<b>' . $wedstrijd->away . '</b>';	
					}
					if($weddatum != $done_date){
						$retval .=  "<tr><td colspan='6' class='table-header'>".$weddag. " " . $weddatum . "</td></tr>";
						$done_date = $weddatum;
					}
					$retval .= ' 
						<tr>
						<td>';
					$retval .= $wedstrijd->game_number; 
					$retval .= '</td>
						<td>'; 
					$retval .= $wedtijd;
					$retval .= '</td>
						<td>' ;
					$retval .= $wedstrijd->team_naamkort;
					$retval .= '</td>
						<td>' ;
					$retval .= $wedstrijd->home;
					$retval .= '</td>
						<td>' ;
					$retval .= $wedstrijd->away;
					$retval .= '</td>
						<td>' ;
					$retval .= $wedstrijd->score_home . " - " . $wedstrijd->score_away;
					$retval .= '</td>
						</tr>' ;
				}
			}
		} 
	} else{ 
		$retval .= "<tr><td colspan='6'>Nog geen uitslagen bekend</td></tr>";
	}
	$retval .= "</table>";
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