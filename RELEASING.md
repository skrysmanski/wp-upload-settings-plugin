# Releasing a new version

For general help on Wordpress' plugin directory, see <https://developer.wordpress.org/plugins/wordpress-org/>.

## Steps **before** releasing a new version

1. Both for the **oldest** supported PHP version (`./Start-TestEnv.ps1 -PhpVersion 5.6`) and the **newest** supported PHP version (`./Start-TestEnv.ps1`) - as defined by the [Wordpress Docker image](https://hub.docker.com/_/wordpress):
   1. Run manual tests
1. Check readme at: <https://wordpress.org/plugins/developers/readme-validator/>
1. Check issue tracker for open issues
1. Check [support forum](https://wordpress.org/support/plugin/upload-file-type-settings-plugin/) for open issue

## Steps **when** releasing a new version

1. Make sure `Tested up to` and `Stable tag` in `src/readme.txt` have been updated.
1. Make sure `Version` in `src/upload-settings-plugin.php` has been updated.
1. Run `./Create-Release.ps1 <VERSION>`
1. Check-in the SVN repository in the `dist/` directory.
1. Create SVN tag for version
1. Create Git tag version
1. Upload `dist-zip/upload-file-type-settings-plugin-<VERSION>.zip` to downloads section in GitHub
1. Close all resolved issues
1. Close all resolved support tickets
