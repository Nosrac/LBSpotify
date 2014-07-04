<?
function findSuggestions($search, $max = 10)
{
	$search = strtolower(trim($search));

	if (strlen($search) == 0) return [];

	$result = json_decode(file_get_contents('http://ws.spotify.com/search/1/track.json?q=' . urlencode($search)));

	$suggestions = [];
	$urls = [];

	foreach($result->tracks as $track)
	{
		$suggestions[] = [
			'title' => $track->name . ' by ' . $track->artists[0]->name,
			'icon' => "at.obdev.LaunchBar:AudioTrackTemplate",
			'url' => $track->href,
			// 'children' => [
			// 	[
			// 		'title' => $track->name,
			// 		'icon' => "at.obdev.LaunchBar:AudioTrackTemplate",
			// 		'url' => $track->href,
			// 	],
			// 	[
			// 		'title' => $artistText,
			// 		'icon' => "at.obdev.LaunchBar:ArtistTemplate",
			// 		'url' => $track->artists[0]->href,
			// 	],
			// 	[
			// 		'title' => $albumText,
			// 		'icon' => "at.obdev.LaunchBar:AlbumTemplate",
			// 		'url' => $track->album->href,
			// 	]
			// ]
		];
	}

	$suggestions = array_slice($suggestions, 0, $max);
	return $suggestions;
}

$suggestions = findSuggestions( $argv[1] );

echo json_encode($suggestions);

?>