<?php
function call_xbmc($settings){
	// get system info
	$ch = curl_init();	
	$mc_url = "http://".$settings['mc_username'].":".$settings['mc_password']."@".$settings['mc_ip'].":".$settings['mc_port']."/jsonrpc";
	
	curl_setopt($ch, CURLOPT_URL,	$mc_url);
	curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, '{"jsonrpc": "2.0", "method": "System.GetInfoLabels","params":["system.buildversion", "system.builddate"], "id": 1}');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	$contents = curl_exec ($ch);
	curl_close ($ch);
	$sys = json_decode($contents);
	
	$mc_version = $sys->result->{'system.buildversion'};
	$mc_build_date = $sys->result->{'system.builddate'};;
	
	// get player status	
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_URL,	$mc_url);
	curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, '{"jsonrpc": "2.0", "method": "Player.GetActivePlayers", "id": 1}');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	$contents = curl_exec ($ch);
	curl_close ($ch);
	$res = json_decode($contents);
	
	if($res->result->video) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL,	$mc_url);
		curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS, '{"jsonrpc": "2.0", "method": "System.GetInfoLabels", "params":["VideoPlayer.TvShowTitle", "VideoPlayer.Season", "VideoPlayer.Episode", "VideoPlayer.Title", "VideoPlayer.Year", "VideoPlayer.Time", "VideoPlayer.Duration"], "id": 1}');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$contents = curl_exec ($ch);
		curl_close ($ch);
		$playing = json_decode($contents);
		
		// parse video
		/*
			["VideoPlayer.Duration"]=>
			string(8) "02:21:08"
			["VideoPlayer.Episode"]=>
			string(0) ""
			["VideoPlayer.Season"]=>
			string(0) ""
			["VideoPlayer.Time"]=>
			string(8) "00:52:36"
			["VideoPlayer.Title"]=>
			string(17) "The Pelican Brief"
			["VideoPlayer.TvShowTitle"]=>
			string(0) ""
			["VideoPlayer.Year"]=>
			string(4) "1993"
		*/
		
		$year = $playing->result->{'VideoPlayer.Year'};
		$duration = $playing->result->{'VideoPlayer.Duration'};
		$cur_time = $playing->result->{'VideoPlayer.Time'};
		
		$calcTime = explode(":",$duration);
		if (count($calcTime) > 2)
			$duration = $calcTime[0]*60 + $calcTime[1];
		else 
			$duration = $calcTime[0];
		
		$calcTime = explode(":",$cur_time);
		if (count($calcTime) > 2)
			$cur_time = $calcTime[0]*60 + $calcTime[1];
		else 
			$cur_time = $calcTime[0];
		
		$progress = round(($cur_time/$duration)*100);
		
		$scrobble_type = ($progress > 75) ? "scrobble" : "watching"; 
		
		if($playing->result->{'VideoPlayer.TvShowTitle'}) { // submit tv show
			$title = $playing->result->{'VideoPlayer.TvShowTitle'};
			$season = $playing->result->{'VideoPlayer.Season'};
			$episode = $playing->result->{'VideoPlayer.Episode'};
			
			// get tvdb id
			$ch = curl_init();
			curl_setopt_array($ch, array(
				CURLOPT_URL => 'http://' . $settings['mc_username'] . ':' . $settings['mc_password'] . '@' . $settings['mc_ip'] . ':' . $settings['mc_port'] . '/xbmcCmds/xbmcHttp?command=QueryVideoDatabase(' . urlencode("select tvshow.c12 from tvshow where lower(tvshow.c00) = lower('".$title."') limit 1") . ')',
				CURLOPT_RETURNTRANSFER => 1
			));
			
			$tvdb_id = trim(strip_tags(curl_exec($ch)));
			
			$data = json_encode(array(
				'username' 							=> $settings['trakt_username'],
				'password' 							=> sha1($settings['trakt_password']),
				'tvdb_id'								=> $tvdb_id,
				'title'									=> $title,
				'season'								=> $season,
				'episode'								=> $episode,
				'year'									=> $year,
				'duration'							=> $duration,
				'progress'							=> $progress,
				'plugin_version'				=> PLUGIN_VERSION,
				'media_center_version'	=> $mc_version,
				'media_center_date'			=> $mc_build_date
			));	
			
			$ch = curl_init();
			curl_setopt_array($ch, array(
				CURLOPT_URL => 'http://api.trakt.tv/show/'.$scrobble_type.'/' . $settings['apikey'],
				CURLOPT_POSTFIELDS => $data,
				CURLOPT_POST => 1,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_TIMEOUT => 0
			));
			return curl_exec($ch);
			
		}
		else {  // submit movie
			$title = $playing->result->{'VideoPlayer.Title'};
			
			// get imdb id
			$ch = curl_init();
			curl_setopt_array($ch, array(
				CURLOPT_URL => 'http://' . $settings['mc_username'] . ':' . $settings['mc_password'] . '@' . $settings['mc_ip'] . ':' . $settings['mc_port'] . '/xbmcCmds/xbmcHttp?command=QueryVideoDatabase(' . urlencode("select movie.c09 from movie where movie.c07 = '".$year."' and lower(movie.c00) = lower('".$title."') limit 1") . ')',
				CURLOPT_RETURNTRANSFER => 1
			));
			
			$imdb_id = trim(strip_tags(curl_exec($ch)));
			
			$data = json_encode(array(
				'username' 							=> $settings['trakt_username'],
				'password' 							=> sha1($settings['trakt_password']),
				'imdb_id'								=> $imdb_id,
				'title'									=> $title,
				'year'									=> $year,
				'duration'							=> $duration,
				'progress'							=> $progress,
				'plugin_version'				=> PLUGIN_VERSION,
				'media_center_version'	=> $mc_version,
				'media_center_date'			=> $mc_build_date
			));
			
			$ch = curl_init();
			curl_setopt_array($ch, array(
				CURLOPT_URL => 'http://api.trakt.tv/movie/'.$scrobble_type.'/' . $settings['apikey'],
				CURLOPT_POSTFIELDS => $data,
				CURLOPT_POST => 1,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_TIMEOUT => 0
			));
			return curl_exec($ch);
		}
	}
	else {
		print "nothing currently playing";
	}
}

function call_plex($settings){
	// get system info
	$mc_url = "http://".$settings['mc_username'].":".$settings['mc_password']."@".$settings['mc_ip'].":".$settings['mc_port']."/xbmcCmds/xbmcHttp?command=GetCurrentlyPlaying";
	
	// get player status	
	$ch = curl_init();	
	
	curl_setopt($ch, CURLOPT_URL,	$mc_url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, false);
	
	$contents = curl_exec($ch);
	curl_close ($ch);
	
	echo $contents;
	
	if(strstr($contents, "Type:Video")) {
		if(strstr($contents, "Show Title:")) {
			preg_match("/Show Title:(.*)/", $contents, $title);
			$title = $title[1];
		
			preg_match("/Season:(.*)/", $contents, $season);
			$season = $season[1];
		
			preg_match("/Episode:(.*)/", $contents, $episode);
			$episode = $episode[1];
		
		}
		else {
			preg_match("/Title:(.*)/", $contents, $title);
			$title = $title[1];
			
			preg_match("/Year:(.*)/", $contents, $year);
			$year = $year[1];
		}
		
		preg_match("/Percentage:(.*)/", $contents, $progress);
		$progress = $progress[1];
		
		preg_match("/Duration:(.*)/", $contents, $duration);
		$duration = $duration[1];
	}
	else {
		return "nothing currently playing";
	}
	$scrobble_type = ($progress > 75) ? "scrobble" : "watching";
	
	$data = array(
		'username' 							=> $settings['trakt_username'],
		'password' 							=> sha1($settings['trakt_password']),
		'title'									=> $title,
		'duration'							=> $duration,
		'progress'							=> $progress,
		'plugin_version'				=> PLUGIN_VERSION,
		'media_center_version'	=> "no clue",
		'media_center_date'			=> "who knows"
	);
	
	if(isset($year)) {
		$data["year"] = $year;
	}
	else {
		$data["year"] = "";
		$data['season'] = $season;
		$data['episode'] = $episode;
	}

	$data = json_encode($data);	
	
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => 'http://api.trakt.tv/show/'.$scrobble_type.'/' . $settings['apikey'],
		CURLOPT_POSTFIELDS => $data,
		CURLOPT_POST => 1,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_TIMEOUT => 0
	));
	return curl_exec($ch);
}

function call_boxee($settings){
	// get system info
	$mc_url = "http://".$settings['mc_username'].":".$settings['mc_password']."@".$settings['mc_ip'].":".$settings['mc_port']."/xbmcCmds/xbmcHttp?command=GetCurrentlyPlaying";
	
	// get player status	
	$ch = curl_init();	
	
	curl_setopt($ch, CURLOPT_URL,	$mc_url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, false);
	
	$contents = curl_exec($ch);
	curl_close ($ch);
	
	// echo $contents;
	
	if(strstr($contents, "Type:Video")) {
		if(strstr($contents, "Show Title:")) {
			preg_match("/Show Title:(.*)/", $contents, $title);
			$title = $title[1];
		
			preg_match("/Season:(.*)/", $contents, $season);
			$season = $season[1];
		
			preg_match("/Episode:(.*)/", $contents, $episode);
			$episode = $episode[1];
		
		}
		else {
			preg_match("/Title:(.*)/", $contents, $title);
			$title = $title[1];
			
			preg_match("/Year:(.*)/", $contents, $year);
			$year = $year[1];
		}
		
		preg_match("/Percentage:(.*)/", $contents, $progress);
		$progress = $progress[1];
		
		preg_match("/Duration:(.*)/", $contents, $duration);
		$duration = $duration[1];
	}
	else {
		return "nothing currently playing";
	}
	$scrobble_type = ($progress > 75) ? "scrobble" : "watching";
	
	$data = array(
		'username' 							=> $settings['trakt_username'],
		'password' 							=> sha1($settings['trakt_password']),
		'title'									=> $title,
		'duration'							=> $duration,
		'progress'							=> $progress,
		'plugin_version'				=> PLUGIN_VERSION,
		'media_center_version'	=> "no clue",
		'media_center_date'			=> "who knows"
	);
	
	if(isset($year)) {
		$data["year"] = $year;
	}
	else {
		$data["year"] = "";
		$data['season'] = $season;
		$data['episode'] = $episode;
	}

	$data = json_encode($data);	
	
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => 'http://api.trakt.tv/show/'.$scrobble_type.'/' . $settings['apikey'],
		CURLOPT_POSTFIELDS => $data,
		CURLOPT_POST => 1,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_TIMEOUT => 0
	));
	return curl_exec($ch);
}

function scrobble($settings) {
	// leave alone
	define('PLUGIN_VERSION', '0.0.1');
	define('XBMC_API_KEY', '06f22003e28b5ce3745ddf75503b126973d73de1');
	define('PLEX_API_KEY', 'aebda823a279b219476c565be863d83739999502');
	define('BOXEE_API_KEY', '42920cadcb31ff648cb5fe2865473c9bf164c5bd');
	
	// check what mc type and set funcs and apikey
	switch ($settings['mc_type']) {
		case 'BOXEE':
			$settings['apikey'] = BOXEE_API_KEY;
			$resp = call_boxee($settings);
			break;
		case 'PLEX':
			$settings['apikey'] = PLEX_API_KEY;
			$resp = call_plex($settings);
			break;
		case 'XBMC':
		default:
			$settings['apikey'] = XBMC_API_KEY;
			$resp = call_xbmc($settings);
			break;
	}
	print $resp."<br/>";
}

$conf_dir = dirname(__FILE__) . '/conf/';
$fhandle = opendir($conf_dir);

while(false !== ($file = readdir($fhandle))) {
	if($file == "." || $file == ".." || !strstr($file, ".ini")) continue;
	
	$settings = parse_ini_file($conf_dir.$file);
	scrobble($settings);
}
?>