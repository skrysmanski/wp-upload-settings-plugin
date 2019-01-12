=== Upload File Type Settings Plugin ===
Plugin Name: Upload File Type Settings Plugin
Stable tag: 1.1
Contributors: manski
Tags: mime-types, MIME, media, upload
Requires PHP: 5.6
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 3.0.0
Tested up to: 5.0.3

This plugin allows admins to specify additional file types that are allowed for uploading to the blog.


== Description ==
By default, Wordpress only allows a very limited set of file types to be uploaded. This set is defined by a so called
"white list". What's not on the white list can't be uploaded.

This plugin allows you to extend this white list through a settings page.

Additionally it comes with a wide range of file types that are enabled automatically, like audio files (ac3, mp4, ...)
and video files (avi, mkv, ...).


== Installation ==

1. Upload the **upload-settings-plugin** folder to the **/wp-content/plugins/** directory
2. *Activate* the plugin through the *Plugins* menu in WordPress


== Changelog ==

= 1.1 =
* Fix: File extensions for binary files could not be saved
* Fix: Disabled warning when overwriting an existing file extension with the same mime type it already had.
* Change: Errors and warnings from this plugin now only show on the plugin's settings page.

= 1.0 =
* First release


== Screenshots ==

1. Settings Panel
