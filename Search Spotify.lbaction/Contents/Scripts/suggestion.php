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
			'original_title' => $track->name,
			'icon' => "at.obdev.LaunchBar:AudioTrackTemplate",
			'url' => $track->href,
			// 'children' => [
			// 	[
			// 		'title' => $track->name,
			// 		'icon' => "at.obdev.LaunchBar:AudioTrackTemplate",
			// 		'url' => $track->href,
			// 	],
			// 	$this->suggestionForArtist($track->artists[0]),
			// 	$this->suggestionForAlbum($track->album, $track->artists[0])
			// ]
		];
	}

	function suggestionForArtist($artist)
	{
		return [
					'title' => $artist->name,
					'original_title' => $artist->name,
					'icon' => "at.obdev.LaunchBar:ArtistTemplate",
					'url' => $artist->href,
				];
	}

	function suggestionForAlbum($album, $artist)
	{
		return [
					'title' => $album->name . ' by ' . $artist->name,
					'original_title' => $album->name,
					'icon' => "at.obdev.LaunchBar:AlbumTemplate",
					'url' => $album->href,
				];
	}

	function sortSuggestions($suggestions)
	{
		foreach($suggestions as $i => $suggestion)
		{
			$position = stripos( strtolower($suggestion['original_title']), $this->search );
			if ($position === 0)
			{
				$suggestions[$i]['score'] = 0;

			} else {
				$suggestions[$i]['score'] = $i + 1;
			}
		}

		usort($suggestions, function($a, $b)
		{
		    $aVal = $a['score'];
		    $bVal = $b['score'];

		    if ($aVal < $bVal) return -1;
		    if ($aVal > $bVal) return 1;
		    return 0;
		});

		return $suggestions;
	}

	function suggestions($max = 15)
	{
		$cachekey = "suggestions_" . $this->search;

		$cache = $this->getCache($cachekey);
		if ($cache) return $cache;

		if (strlen($this->search) == 0) return [];

		$result = json_decode(file_get_contents('http://ws.spotify.com/search/1/track.json?q=' . urlencode($this->search)));

		if (count($result->tracks) == 0) return [];

		$suggestions = [ ];
		foreach($result->tracks as $track)
		{
			$extras = [ $this->suggestionForArtist( $track->artists[0] ), $this->suggestionForAlbum( $track->album, $track->artists[0] ) ];
			foreach( $extras as $result)
			{
				$adjustedTitle = preg_replace('/[^a-zA-Z0-9]+/', '', $result['title']);
				$adjustedSearch = preg_replace('/[^a-zA-Z0-9]+/', '', $this->search);

				if (stripos( $adjustedTitle, $adjustedSearch) !== false)
				{
					$suggestions[] = $result;
				}
			}
			$suggestions[] = $this->suggestionForTrack( $track );
		}

		$suggestions =  array_filter($suggestions);

		$suggestions = $this->sortSuggestions($suggestions);

		$suggestions = $this->removeDuplicateSuggestions($suggestions);

		$suggestions = array_slice($suggestions, 0, $max);

		$cache = $this->setCacheValue($cachekey, $suggestions);

		return $suggestions;
	}

	function removeDuplicateSuggestions($suggestions)
	{
		foreach($suggestions as $i => $suggestion)
		{
			if (! $suggestion)
			{
				unset($suggestions[$i]);
				continue;
			}
			for($j = 0; $j < $i; $j++)
			{
				if (! isSet($suggestions[$j])) continue;
				if ($suggestion['url'] == $suggestions[$j]['url'])
				{
					unset($suggestions[$i]);
					break;
				}
			}
		}
		return array_values($suggestions);
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
				'value' => $this->suggestionForAlbum( $track->album, $track->artists[0] ),
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

	///////////
	/////////// Cache
	///////////

	private $cacheFile = "/tmp/LBSpotifyCache.json";

	function getCache($key = false)
	{
		$cache = [];
		if (file_exists( $this->cacheFile ))
		{
			$cache = json_decode( file_get_contents( $this->cacheFile), true );
		}
		if (! is_array($cache))
		{
			$cache = [];
		}

		if ($key)
		{
			if (isSet($cache[$key]))
			{
				return $cache[$key];
			}
			return null;
		}

		return $cache;
	}

	function setCache($cache)
	{
		if (! is_array($cache)) return;

		file_put_contents( $this->cacheFile, json_encode( $cache ) );
	}

	function setCacheValue($key, $value)
	{
		$cache = $this->getCache();
		$cache[$key] = $value;

		$this->setCache($cache);
	}

	///////////
	/////////// End Cache
	///////////
}

$query = new SpotifyQuery( $argv[1] );

$suggestions = $query->suggestions();

echo json_encode($suggestions);

?>