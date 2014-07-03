<?
function findSuggestions($search, $max = 10)
{
	$search = strtolower(trim($search));

	if (strlen($search) == 0) return [];

	$result = json_decode(file_get_contents('http://ws.spotify.com/search/1/track.json?q=' . urlencode($search)));

	file_put_contents("/tmp/last.txt", json_encode($result));

	$suggestions = [];
	$urls = [];

	$icon = 'com.spotify.client';

	foreach($result->tracks as $track)
	{
		$longTitleText = $track->name . ' by ' . $track->artists[0]->name;

		$urls[ $longTitleText ] = $track->href;
		$urls[ $track->name ] = $track->href;

		$artistText = 'Artist: ' . $track->artists[0]->name;
		$urls[ $artistText ] = $track->href;

		$albumText = 'Album: ' . $track->album->name;
		$urls[ $albumText ] = $track->href;

		$suggestions[] = [
			'title' => $longTitleText,
			'icon' => $icon . ":collection-songs@2x",
			// 'action' => "open.sh",
			// 'actionArgument' => $track->href,
			// 'children' => [
			// 	[
			// 		'title' => $track->name,
			// 		'icon' => $icon,
			// 		'action' => "open.sh",
			// 		'actionArgument' => $track->href,
			// 	],
			// 	[
			// 		'title' => $artistText,
			// 		'icon' => $icon,
			// 		'action' => "open.sh",
			// 		'actionArgument' => $track->artists[0]->href,
			// 	],
			// 	[
			// 		'title' => $albumText,
			// 		'icon' => $icon,
			// 		'action' => "open.sh",
			// 		'actionArgument' => $track->album->href,
			// 	]
			// ]
		];
	}

	$urlFile = "/tmp/LBSpotifySuggestionCache";
	file_put_contents($urlFile, json_encode($urls));

	file_put_contents("/tmp/last.txt", json_encode($suggestions));

	$suggestions = array_slice($suggestions, 0, $max);
	return $suggestions;
}

$suggestions = findSuggestions( $argv[1] );

echo json_encode($suggestions);

?>