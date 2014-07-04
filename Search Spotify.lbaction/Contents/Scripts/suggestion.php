<?
class SpotifyQuery
{
	var $search;
	function __construct($search)
	{
		$this->search = strtolower(trim($search));
	}

	function suggestionForTrack($track)
	{
		return [
			'title' => $track->name . ' by ' . $track->artists[0]->name,
			'icon' => "at.obdev.LaunchBar:AudioTrackTemplate",
			'url' => $track->href,
			// 'children' => [
			// 	[
			// 		'title' => $track->name,
			// 		'icon' => "at.obdev.LaunchBar:AudioTrackTemplate",
			// 		'url' => $track->href,
			// 	],
			// 	$this->suggestionForArtist($track->artists[0]),
			// 	$this->suggestionForAlbum($track->album)
			// ]
		];
	}

	function suggestionForArtist($artist)
	{
		return [
					'title' => $artist->name,
					'icon' => "at.obdev.LaunchBar:ArtistTemplate",
					'url' => $artist->href,
				];
	}

	function suggestionForAlbum($album)
	{
		return [
					'title' => $album->name,
					'icon' => "at.obdev.LaunchBar:AlbumTemplate",
					'url' => $album->href,
				];
	}

	function suggestions($max = 10)
	{
		if (strlen($this->search) == 0) return [];

		$result = json_decode(file_get_contents('http://ws.spotify.com/search/1/track.json?q=' . urlencode($this->search)));

		if (count($result->tracks) == 0) return [];

		$suggestions = [ $this->determinePrimaryResult( $result->tracks[0] ) ];
		foreach($result->tracks as $track)
		{
			$suggestions[] = $this->suggestionForTrack($track);
		}

		$suggestions = array_filter($suggestions);

		$suggestions = array_slice($suggestions, 0, $max);

		return $suggestions;
	}

	function determinePrimaryResult($track)
	{
		$results = [
			[
				'title' => $track->name,
				'url' => $track->href,
				'value' => false,
			], [
				'title' => $track->artists[0]->name,
				'url' => $track->artists[0]->href,
				'value' => $this->suggestionForArtist( $track->artists[0] ),
			], [
				'title' => $track->album->name,
				'url' => $track->album->href,
				'value' => $this->suggestionForAlbum( $track->album ),
			],
		];

		usort($results, function($a, $b)
		{
		    $aVal = levenshtein($a['title'], $this->search, 10, 10, 2);
		    $bVal = levenshtein($b['title'], $this->search, 10, 10, 2);

		    if ($aVal < $bVal) return -1;
		    if ($aVal > $bVal) return 1;

		    if ($a['value'] && ! $b['value'])
		    {
		    	return -1;
		    } else if ($b['value'] && ! $a['value'])
		    {
		    	return 1;
		    }

		    return 0;
		});

		$primaryResult = $results[0];

		return $primaryResult['value'];
	}
}

$query = new SpotifyQuery( $argv[1] );

$suggestions = $query->suggestions();

echo json_encode($suggestions);

?>