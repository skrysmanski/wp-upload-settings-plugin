<?php
/*
Plugin Name: Upload File Type Settings Plugin
Plugin URI: https://github.com/skrysmanski/wp-upload-settings-plugin
Description: Allows admins to specify additional file types that are allowed for uploading to the blog.
Version: 1.1
Author: Sebastian Krysmanski
Author URI: https://manski.net/
*/
# ^-- https://developer.wordpress.org/plugins/the-basics/header-requirements/

class UnrestrictedUploadsPlugin
{
    const VERSION = '1.0';

    const TEXT_FILE_MIME_TYPE = 'text/plain';
    const BINARY_FILE_MIME_TYPE = 'application/octet-stream';

    private $mime_types = [];
    private $disallowed_mime_types = [];
    private $warn_list = [];
    private $error_list = [];
    private $overwritable_file_exts = [];

    private function __construct()
    {
        if (!is_admin())
        {
            # Not a backend page. Don't do anything.
            return;
        }

        $prev_version = get_option('uup_cur_version', '');
        if (empty($prev_version))
        {
            # This plugin was never installed before. Set some default options.
            update_option('uup_text_file_extensions', 'cs,vb');
            update_option('uup_binary_file_extensions', 'bin,dat');
            update_option('uup_disallowed_file_extensions', 'php,phtml,cgi,pl,py');

            update_option('uup_cur_version', self::VERSION);
        }

        $this->init_mime_types();

        # https://developer.wordpress.org/reference/hooks/upload_mimes/
        add_filter('upload_mimes', [$this, '_extend_mime_types']);

        add_action('admin_init', [$this, '_register_settings']);
        add_action('admin_menu', [$this, '_create_settings_menu']);
    }

    public static function init()
    {
        static $instance = null;
        if ($instance === null)
        {
            $instance = new UnrestrictedUploadsPlugin();
        }
    }

    /**
     * [Filter Function] Adds additional mime types for file uploads.
     */
    public function _extend_mime_types($mime_types)
    {
        foreach ($this->mime_types as $file_ext => $mime_type)
        {
            $existing_mime_type = @$mime_types[$file_ext];
            if (!empty($existing_mime_type) && ($mime_type == self::TEXT_FILE_MIME_TYPE || $mime_type == self::BINARY_FILE_MIME_TYPE))
            {
                # There exist a more specific mime type already. Don't overwrite it.
                continue;
            }

            $mime_types[$file_ext] = $mime_type;
        }

        foreach ($this->disallowed_mime_types as $file_ext => $unused)
        {
            unset($mime_types[$file_ext]);
        }

        return $mime_types;
    }

    private function init_mime_types()
    {
        # For details, see "wp_check_filetype_and_ext()"
        if (extension_loaded('fileinfo')) {
            $this->warn_list[] = 'The PHP extension "fileinfo" is loaded. It may prevent you from uploading files even though you\'ve whitelisted them '
                               . 'below (if fileinfo detects a different mime type than the one that is reported by this plugin). This is a security feature in Wordpress.';
        }

        # Load file extensions for which overwrite warnings shall be suppressed.
        $this->overwritable_file_exts = [];
        $overwritable_file_exts = trim(get_option('uup_allowed_file_ext_overwriting', ''));
        if (!empty($overwritable_file_exts))
        {
            $overwritable_file_exts = explode(',', $overwritable_file_exts);

            foreach ($overwritable_file_exts as $overwritable_file_ext)
            {
                $file_extension = trim($overwritable_file_ext);
                if (empty($file_extension))
                {
                    $this->error_list[] = "Warning: Empty file extension in setting \"Don't warn about\"";
                    continue;
                }
                if ($file_extension[0] == '.')
                {
                    $this->error_list[] = "Warning: File extension with leading dot in setting \"Don't warn about\"";
                    $file_extension = substr($file_extension, 1);
                }

                $this->overwritable_file_exts[$file_extension] = true;
            }
        }


        # Load mime types from mime-types.txt
        $list = file(dirname(__FILE__) . '/mime-types.txt');
        self::parse_mime_type_listing(
            $list,
            $this->overwritable_file_exts,
            $this->mime_types,
            $this->warn_list,
            $this->error_list
        );

        # Text files
        $list = get_option('uup_text_file_extensions', '');
        self::parse_file_ext_list(
            $list,
            self::TEXT_FILE_MIME_TYPE,
            $this->overwritable_file_exts,
            $this->mime_types,
            $this->warn_list
        );

        # Binary files
        $list = get_option('uup_binary_file_extensions', '');
        self::parse_file_ext_list(
            $list,
            self::BINARY_FILE_MIME_TYPE,
            $this->overwritable_file_exts,
            $this->mime_types,
            $this->warn_list
        );

        # Custom mime types
        $list = get_option('uup_custom_mime_types', '');
        $list = explode("\n", $list);
        self::parse_mime_type_listing(
            $list,
            $this->overwritable_file_exts,
            $this->mime_types,
            $this->warn_list,
            $this->error_list
        );

        # Disallowed mime types
        $list = get_option('uup_disallowed_file_extensions', '');
        self::parse_file_ext_list(
            $list,
            "false",
            [],
            $this->disallowed_mime_types,
            $this->warn_list
        );

        foreach ($this->disallowed_mime_types as $key => $value)
        {
            unset($this->mime_types[$key]);
        }
    }

    private static function parse_file_ext_list($list, $mime_type, $overwritable_file_exts, &$mime_types_map, &$warn_list)
    {
        $list = trim($list);
        if (empty($list))
        {
            return;
        }

        $file_extensions = explode(',', $list);
        foreach ($file_extensions as $file_extension)
        {
            $file_extension = trim($file_extension);
            if (empty($file_extension))
            {
                $warn_list[] = "Empty file extension in '$list'";
                continue;
            }
            if ($file_extension[0] == '.')
            {
                $warn_list[] = "File extension with leading dot in '$list'";
                $file_extension = substr($file_extension, 1);
            }

            # Warn about overwriting an existing mime type.
            if (!isset($overwritable_file_exts[$file_extension]))
            {
                $existing_val = @$mime_types_map[$file_extension];
                if (!empty($existing_val) && $mime_type != $existing_val)
                {
                    $warn_list[] = "Overwriting existing mime type '$existing_val' for file extension '.$file_extension' with new mime type '$mime_type'.";
                }
            }

            $mime_types_map[$file_extension] = $mime_type;
        }
    }

    private static function parse_mime_type_listing($lines, $overwritable_file_exts, &$mime_types_map, &$warn_list, &$error_list)
    {
        foreach ($lines as $line)
        {
            $line = trim($line);
            if (empty($line) || $line[0] == '#')
            {
                # Line is empty or a comment.
                continue;
            }

            $parts = explode(':', $line);
            if (count($parts) != 2)
            {
                $error_list[] = "Invalid mime types line '$line'";
                continue;
            }

            list($file_extensions, $mime_type) = $parts;
            $mime_type = trim($mime_type);
            if (empty($mime_type))
            {
                $error_list[] = "No mime type specified in line '$line'";
                continue;
            }

            self::parse_file_ext_list($file_extensions, $mime_type, $overwritable_file_exts, $mime_types_map, $warn_list, $error_list);
        }
    }

    public function _create_settings_menu()
    {
        # create new top-level menu
        add_options_page('Upload File Type Settings', 'Upload Settings', 'manage_options', 'uup-settings', [$this, '_settings_page']);
    }

    /**
     * [Action Callback] Registers the settings of this plugin.
     */
    public function _register_settings()
    {
        //register our settings
        register_setting('uup-settings-group', 'uup_text_file_extensions');
        register_setting('uup-settings-group', 'uup_binary_file_extensions');
        register_setting('uup-settings-group', 'uup_disallowed_file_extensions');
        register_setting('uup-settings-group', 'uup_custom_mime_types');
        register_setting('uup-settings-group', 'uup_allowed_file_ext_overwriting');
    }

    public function _settings_page()
    {
        if (isset($this->mime_types['php']) && !isset($this->overwritable_file_exts['php']))
        {
?>
<div class="notice notice-error">
    <p>
        <b>Important:</b> The file extension <code>.php</code> is allowed for upload. This is potentially a <b>very serious security risk</b>
        unless you know what you're doing. If you've added this file extension by accident, remove it in the Unrestricted
        Uploads Plugin settings.
    </p>
</div>
<?php
        }

        if (count($this->error_list) != 0)
        {
            echo '<div class="notice notice-error">';
            foreach ($this->error_list as $notice)
            {
                echo "<p>Error: $notice</p>\n";
            }
            echo '</div>';
        }

        if (count($this->warn_list) != 0)
        {
            echo '<div class="notice notice-warning">';
            foreach ($this->warn_list as $notice)
            {
                echo "<p>Warning: $notice</p>\n";
            }
            echo '</div>';
        }
?>
<div class="wrap">
    <h2>Upload File Type Settings</h2>

    <p>To be able to upload a file to your blog, its file extension (e.g. <code>.txt</code>, <code>.zip</code>, &hellip;) needs
        to be listed here.</p>

    <p>If the file is a text file, add its file extension to "Text File Extensions". If it's a binary file, add it to
        "Binary File Extensions". If you know the file's MIME type, add it to "Custom MIME Types".</p>

    <h3>Allowed File Extensions</h3>

    <p>Currently files with the following file extensions are allowed for upload:</p>

    <style type="text/css">
        #allowed-file-extensions-list > span {
            cursor: help;
            border-bottom: 1px dashed #999;
        }
    </style>

    <p id="allowed-file-extensions-list">
<?php
$first_mime_type = true;
$allowed_mime_types_regexp = get_allowed_mime_types();
$allowed_mime_types = [];
foreach ($allowed_mime_types_regexp as $file_ext_regexp => $mime_type)
{
    $file_exts = explode('|', $file_ext_regexp);
    foreach ($file_exts as $file_ext)
    {
        $allowed_mime_types[$file_ext] = $mime_type;
    }
}
ksort($allowed_mime_types);

foreach ($allowed_mime_types as $file_ext => $mime_type)
{
    if ($first_mime_type)
    {
        $first_mime_type = false;
    }
    else
    {
        echo ', ';
    }

    echo "<span title=\"$mime_type\">$file_ext</span>";
}
?>
    </p>

<h3>Settings</h3>

<form method="post" action="options.php">
    <?php settings_fields('uup-settings-group'); ?>

    <table class="form-table">
        <tr valign="top">
            <th scope="row">Text File Extensions</th>
            <td>
                <input type="text" name="uup_text_file_extensions" value="<?php echo get_option('uup_text_file_extensions'); ?>" class="regular-text ltr"/>
                <p class="description">File extensions to be treated as text files (mime type: <code><?php echo self::TEXT_FILE_MIME_TYPE; ?></code>); separated by commas, e.g. "cs,vb,rst".</p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">Binary File Extensions</th>
            <td>
                <input type="text" name="uup_binary_file_extensions" value="<?php echo get_option('uup_binary_file_extensions'); ?>" class="regular-text ltr"/>
                <p class="description">File extensions to be treated as binary files (mime type: <code><?php echo self::BINARY_FILE_MIME_TYPE; ?></code>); separated by commas, e.g. "bin,dat".</p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">Custom MIME Types</th>
            <td>
                <p>
                    <label for="uup_custom_mime_types">Allows you to specify custom <a href="http://en.wikipedia.org/wiki/Internet_media_type" target="_blank">MIME types</a>
                        for file extensions. Each line associates a MIME type with a collection of file extensions. A line is constructed like this:
                        <code>fileextension : mimetype</code> (e.g. <code>mp4 : video/mp4</code>) or <code>fileext1, fileext2 : mimetype</code>
                        (e.g. <code>jpg, jpeg : images/jpeg</code>). If a line starts with <code>#</code>, it's considered a comment and will be ignored.</label>
                </p>
                <textarea name="uup_custom_mime_types" id="uup_custom_mime_types" rows="10" cols="50" class="large-text code"><?php echo get_option('uup_custom_mime_types'); ?></textarea>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">Disallowed File Extensions</th>
            <td>
                <input type="text" name="uup_disallowed_file_extensions" value="<?php echo get_option('uup_disallowed_file_extensions'); ?>" class="regular-text ltr"/>
                <p class="description">File extensions that will never be allowed for upload, regardless of whether they're specified in the list that
                    ships with this plugin or whether they're specified above; separated by commas, e.g. "php,pl,rb,cgi".</p>

                <p class="description"><b>Note:</b> You should list file extensions here
                    that <b>can be executed on your server</b> (instead of being viewed or downloaded), like <code>php</code>. Note also that if the file extension isn't listed
                    in any of the other settings listing it here is merely a precaution (but wont have any effect until you actually try to allow the file extension for upload).</p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">Don't Warn About</th>
            <td>
                <input type="text" name="uup_allowed_file_ext_overwriting" value="<?php echo get_option('uup_allowed_file_ext_overwriting'); ?>" class="regular-text ltr"/>
                <p class="description">Normally when one setting overwrites an existing mime type, you'll get a warning. This setting lets you
                    specify file extensions that you deliberately overwrote. Use this to get rid of warnings for these file types should they appear; separated by commas,
                    e.g. "php,pl,rb,cgi".</p>
            </td>
        </tr>
    </table>

    <?php submit_button(); ?>

</form>
</div>
<?php
    }
}

# Initialize plugin
UnrestrictedUploadsPlugin::init();
