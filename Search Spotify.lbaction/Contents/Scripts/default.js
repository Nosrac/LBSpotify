function runWithString(string)
{
	urlFile = "/tmp/LBSpotifySuggestionCache";

	if (! File.exists(urlFile))
	{
		LaunchBar.alert("LBSpotify failed");
		return;
	}

	var object = File.readJSON(urlFile);

	if (! string in object)
	{
		LaunchBar.alert("LBSpotify messed up");
		return;
	}

	LaunchBar.openURL(object[string]);
}