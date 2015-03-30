function runWithString(string)
{
	var url = string
	if ( ! string.match(/^spotify:/))
	{
		string = string || "";

		url = "spotify:search:" + encodeURIComponent(string);
	}

	LaunchBar.openURL( url );
}

function run()
{
	LaunchBar.openURL("spotify:")
}