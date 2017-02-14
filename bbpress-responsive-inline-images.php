<?php
/**
* Plugin Name: Bonnier bbPress responsive inline images
* Version: 0.1.0
* Plugin URI: https://github.com/BenjaminMedia/bbpress-responsive-inline-images
* Description: This plugin gives you the ability to select a post in bbPress as the best answer
* Author: Bonnier - Michael Sørensen
* License: MIT
*/

namespace Bonnier\ResponsiveInlineImages;

// Do not access this file directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle autoload so we can use namespaces
spl_autoload_register(function ($className) {
    if (strpos($className, __NAMESPACE__) !== false) {
        $className = str_replace("\\", DIRECTORY_SEPARATOR, $className);
        require_once(__DIR__ . DIRECTORY_SEPARATOR . Plugin::CLASS_DIR . DIRECTORY_SEPARATOR . $className . '.php');
    }
});

class Plugin
{
    /**
    * Text domain for translators
    */
    const TEXT_DOMAIN = 'bp-bbprii';

    const CLASS_DIR = '';

    /**
    * @var object Instance of this class.
    */
    private static $instance;

    public $settings;

    /**
    * @var string Filename of this class.
    */
    public $file;

    /**
    * @var string Basename of this class.
    */
    public $basename;

    /**
    * @var string Plugins directory for this plugin.
    */
    public $plugin_dir;

    /**
    * @var string Plugins url for this plugin.
    */
    public $plugin_url;

    const IMG_REGEX = '/(<img[^>]+\>)/i';
    const IMG_SRC_REGEX = '/src="?([^ ]+\/.*?)["> ]/i';

    /**
    * Do not load this more than once.
    */
    private function __construct()
    {
        // Set plugin file variables
        $this->file = __FILE__;
        $this->basename = plugin_basename($this->file);
        $this->plugin_dir = plugin_dir_path($this->file);
        $this->plugin_url = plugin_dir_url($this->file);
        
        // Load textdomain
        load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname($this->basename) . '/languages');
    }
    
    private function boostrap()
    {
        add_filter('bbp_get_reply_content', [$this, 'modify_reply_content_with_link'], 10, 2);
        add_filter('bbp_get_topic_content', [$this, 'modify_reply_content_with_link'], 10, 2);
    }
    
    /**
     * Returns the instance of this class.
     */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self;
            global $bbpress_responsive_inline_images;
            $bbpress_responsive_inline_images = self::$instance;
            self::$instance->boostrap();
            
            /**
             * Run after the plugin has been loaded.
             */
            do_action('bbpress-responsive-inline-images_loaded');
        }
        
        return self::$instance;
    }
    
    private function split_html_on_image($html) {
        if(is_array($html)) {
            return array_map([$this, 'split_html_on_image'], $html);
        }
        return preg_split(static::IMG_REGEX, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    }
    
    public function modify_reply_content_with_link($content, $reply_id)
    {
        $splitHtml = $this->split_html_on_image($content);
        
        $args = [
            'order' => 'ASC',
            'post_mime_type' => 'image',
            'post_parent' => $reply_id,
            'post_status' => null,
            'post_type' => 'attachment',
        ];
        
        $attachments = get_children($args);
        
        foreach($attachments as $attachment) {
            
            $attachmentUrlFullSize = wp_get_attachment_url($attachment->ID);
            $attachmentSrcLarge = wp_get_attachment_url($attachment->ID, 'large');
            $attachment_url = wp_get_attachment_image_url($attachment->ID, 'medium');
            $attachment_srcset = wp_get_attachment_image_srcset($attachment->ID, 'medium');
            $attachment_size = wp_get_attachment_image_sizes($attachment->ID, 'medium');
            $attachmentUrlFullSizeDimensions = wp_get_attachment_metadata($attachment->ID);
            
            $imageData = '<a href="'.$attachmentUrlFullSize.'" class="lightbox" data-dimensions="'.
            $attachmentUrlFullSizeDimensions['width'].'x'.$attachmentUrlFullSizeDimensions['height'].'">
            <img src="'.$attachment_url.'" srcset="'.$attachment_srcset.'" sizes="'.$attachment_size
            .'" /></a>';

            $filteredImage = apply_filters( 'bbp-rii-image', $imageData, $attachment->ID);
            
            foreach ($splitHtml as $html) {
                $matches = [];
                preg_match(static::IMG_SRC_REGEX, $html, $matches);
                if(count($matches) > 1) {
                    $imgSrc = $matches[1];
                    if ($imgSrc === $attachmentSrcLarge) {
                        $content = str_replace($html, $filteredImage, $content);
                    }
                }
            }
        }
        
        return $content;
    }
}

/**
 * @return Plugin $instance returns an instance of the plugin
 */
function instance()
{
    return Plugin::instance();
}

add_action('plugins_loaded', __NAMESPACE__ . '\instance', 0);