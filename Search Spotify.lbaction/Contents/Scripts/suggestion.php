<?
// This class is designed to make it easy to run multiple curl requests in parallel, rather than
// waiting for each one to finish before starting the next. Under the hood it uses curl_multi_exec
// but since I find that interface painfully confusing, I wanted one that corresponded to the tasks
// that I wanted to run.
//
// To use it, first create the ParallelCurl object:
//
// $parallelcurl = new ParallelCurl(10);
//
// The first argument to the constructor is the maximum number of outstanding fetches to allow
// before blocking to wait for one to finish. You can change this later using setMaxRequests()
// The second optional argument is an array of curl options in the format used by curl_setopt_array()
//
// Next, start a URL fetch:
//
// $parallelcurl->startRequest('http://example.com', 'on_request_done', array('something'));
//
// The first argument is the address that should be fetched
// The second is the callback function that will be run once the request is done
// The third is a 'cookie', that can contain arbitrary data to be passed to the callback
//
// This startRequest call will return immediately, as long as less than the maximum number of
// requests are outstanding. Once the request is done, the callback function will be called, eg:
//
// on_request_done($content, 'http://example.com', $ch, array('something'));
//
// The callback should take four arguments. The first is a string containing the content found at
// the URL. The second is the original URL requested, the third is the curl handle of the request that
// can be queried to get the results, and the fourth is the arbitrary 'cookie' value that you
// associated with this object. This cookie contains user-defined data.
//
// By Pete Warden <pete@petewarden.com>, freely reusable, see http://petewarden.typepad.com for more

class ParallelCurl {

    public $max_requests;
    public $options;

    public $outstanding_requests;
    public $multi_handle;

    public function __construct($in_max_requests = 10, $in_options = array()) {
        $this->max_requests = $in_max_requests;
        $this->options = $in_options;

        $this->outstanding_requests = array();
        $this->multi_handle = curl_multi_init();
    }

    //Ensure all the requests finish nicely
    public function __destruct() {
    	$this->finishAllRequests();
    }

    // Sets how many requests can be outstanding at once before we block and wait for one to
    // finish before starting the next one
    public function setMaxRequests($in_max_requests) {
        $this->max_requests = $in_max_requests;
    }

    // Sets the options to pass to curl, using the format of curl_setopt_array()
    public function setOptions($in_options) {

        $this->options = $in_options;
    }

    // Start a fetch from the $url address, calling the $callback function passing the optional
    // $user_data value. The callback should accept 3 arguments, the url, curl handle and user
    // data, eg on_request_done($url, $ch, $user_data);
    public function startRequest($url, $callback, $user_data = array(), $post_fields=null) {

		if( $this->max_requests > 0 )
	        $this->waitForOutstandingRequestsToDropBelow($this->max_requests);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt_array($ch, $this->options);
        curl_setopt($ch, CURLOPT_URL, $url);

        if (isset($post_fields)) {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        }

        curl_multi_add_handle($this->multi_handle, $ch);

        $ch_array_key = (int)$ch;

        $this->outstanding_requests[$ch_array_key] = array(
            'url' => $url,
            'callback' => $callback,
            'user_data' => $user_data,
        );

        $this->checkForCompletedRequests();
    }

    // You *MUST* call this function at the end of your script. It waits for any running requests
    // to complete, and calls their callback functions
    public function finishAllRequests() {
        $this->waitForOutstandingRequestsToDropBelow(1);
    }

    // Checks to see if any of the outstanding requests have finished
    private function checkForCompletedRequests() {
	/*
        // Call select to see if anything is waiting for us
        if (curl_multi_select($this->multi_handle, 0.0) === -1)
            return;

        // Since something's waiting, give curl a chance to process it
        do {
            $mrc = curl_multi_exec($this->multi_handle, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        */
        // fix for https://bugs.php.net/bug.php?id=63411
	do {
		$mrc = curl_multi_exec($this->multi_handle, $active);
	} while ($mrc == CURLM_CALL_MULTI_PERFORM);

	while ($active && $mrc == CURLM_OK) {
		if (curl_multi_select($this->multi_handle) != -1) {
			do {
				$mrc = curl_multi_exec($this->multi_handle, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		}
		else
			return;
	}

        // Now grab the information about the completed requests
        while ($info = curl_multi_info_read($this->multi_handle)) {

            $ch = $info['handle'];
            $ch_array_key = (int)$ch;

            if (!isset($this->outstanding_requests[$ch_array_key])) {
                die("Error - handle wasn't found in requests: '$ch' in ".
                    print_r($this->outstanding_requests, true));
            }

            $request = $this->outstanding_requests[$ch_array_key];

            $url = $request['url'];
            $content = curl_multi_getcontent($ch);
            $callback = $request['callback'];
            $user_data = $request['user_data'];

            call_user_func($callback, $content, $url, $ch, $user_data);

            unset($this->outstanding_requests[$ch_array_key]);

            curl_multi_remove_handle($this->multi_handle, $ch);
        }

    }

    // Blocks until there's less than the specified number of requests outstanding
    private function waitForOutstandingRequestsToDropBelow($max)
    {
        while (1) {
            $this->checkForCompletedRequests();
            if (count($this->outstanding_requests)<$max)
            	break;

            usleep(10000);
        }
    }

}

class SpotifyQuery
{
	var $search;
	function __construct($search)
	{
		$this->search = strtolower(trim($search));
	}

	/**
	 * Begins looking for suggestions
	 * @param  function $callback called with $suggestions.  e.g.,  $callback( $suggestions );
	 */
	function getSuggestions($callback)
	{
		if (strlen($this->search) == 0) return [];

		$cachekey = "suggestions_" . $this->search;

		$cache = $this->getCache($cachekey);
		if ($cache)
		{
			$callback($cache);
			return;
		} else {
			$this->pullSuggestions( $callback );
		}
	}

	private function suggestionForTrack($track)
	{
		return [
			'title' => $track->name,
			'subtitle' => $track->artists[0]->name,
			'icon' => "at.obdev.LaunchBar:AudioTrackTemplate",
			'url' => $track->href,
		];
	}

	private function suggestionForArtist($artist)
	{
		return [
					'title' => $artist->name,
					'icon' => "at.obdev.LaunchBar:ArtistTemplate",
					'url' => $artist->href,
				];
	}

	private function suggestionForAlbum($album)
	{
		return [
					'title' => $album->name,
					'subtitle' => $album->artists[0]->name,
					'icon' => "at.obdev.LaunchBar:AlbumTemplate",
					'url' => $album->href,
				];
	}

	private function pullSuggestions($callback)
	{
		$curl = new ParallelCurl(3);

		foreach ([
			"track" => "http://ws.spotify.com/search/1/track.json?q=". urlencode($this->search),
			"artist" => "http://ws.spotify.com/search/1/artist.json?q=". urlencode($this->search),
			"album" => "http://ws.spotify.com/search/1/album.json?q=". urlencode($this->search)
			] as $type => $url)
		{
			$curl->startRequest( $url, [ $this, 'processRequest' ], [ 'type' => $type, 'callback' => $callback ] );
		}
	}

	private $trackResults;
	private $artistResults;
	private $albumResults;

	/**
	 * Used by ParallelCurl.  Will return suggestions when everything is accounted for
	 * @param  string $content
	 * @param  string $url
	 * @param  class $ch      curl object
	 * @param  array $cookies
	 */
	function processRequest( $content, $url, $ch, $cookies)
	{
		$result = json_decode( $content );
		if ($cookies['type'] == 'track')
		{
			$this->trackResults = $result;
		}
		if ($cookies['type'] == 'artist')
		{
			$this->artistResults = $result;
		}
		if ($cookies['type'] == 'album')
		{
			$this->albumResults = $result;
		}

		if (isSet($this->trackResults) && isSet($this->artistResults) && isSet($this->albumResults))
		{
			$this->returnSuggestions( $cookies['callback'] );
		}
	}

	private function returnSuggestions( $callback )
	{
		$suggestions = [ ];
		foreach($this->trackResults->tracks as $i => $track)
		{
			if ($i > 2) break;

			$suggestions[] = $this->suggestionForTrack( $track );
		}
		foreach($this->artistResults->artists as $i => $artist)
		{
			if ($i > 2) break;

			$suggestions[] = $this->suggestionForArtist( $artist );
		}
		foreach($this->albumResults->albums as $i => $album)
		{
			if ($i > 2) break;

			$suggestions[] = $this->suggestionForAlbum( $album );
		}

		$primaryResult = $this->primaryResult($suggestions);
		if ($primaryResult)
		{
			// If the top suggestion is a song, it's already on top
			if ($primaryResult['icon'] != "at.obdev.LaunchBar:AudioTrackTemplate")
			{
				array_unshift( $suggestions, $primaryResult );
			}
		}

		$cachekey = "suggestions_" . $this->search;
		$this->setCacheValue($cachekey, $suggestions);

		$callback( $suggestions );
	}

	/**
	 * Returns the ideal result
	 * @param  array $results [description]
	 * @return array $result
	 */
	function primaryResult($results)
	{
		// We're going to filter our the 2nd/3rd song/artist/album because Spotify knows it's less relevant
		$keptResults = [];
		foreach($results as $i => $result)
		{
			for ($j = 0; $j < $i; $j++)
			{
				$keptResult = $results[$j];

				if ($result['icon'] == $keptResult['icon'])
				{
					continue 2;
				}
			}
			$keptResults[] = $result;
		}

		usort($keptResults, function($a, $b)
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

		return $keptResults[0];
	}

	///////////
	/////////// Cache
	///////////

	private function cacheFile()
	{
		// Hash THIS file's contents
		$hash = sha1( file_get_contents( __FILE__ ) );
		return "/tmp/LBSpotifyCache-$hash.json";
	}

	private function getCache($key = false)
	{
		$cache = [];
		if (file_exists( $this->cacheFile() ))
		{
			$cache = json_decode( file_get_contents( $this->cacheFile() ), true );
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

	private function setCache($cache)
	{
		if (! is_array($cache)) return;

		file_put_contents( $this->cacheFile(), json_encode( $cache ) );
	}

	private function setCacheValue($key, $value)
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

$query->getSuggestions(function($suggestions) {
	echo json_encode( $suggestions );
});

?>