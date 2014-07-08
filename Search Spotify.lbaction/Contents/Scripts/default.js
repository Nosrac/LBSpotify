function runWithString(string)
{
	string = string || "";
	LaunchBar.openURL("spotify:search:" + encodeURIComponent(string) );
}

