<?php

/**
 * Created by PhpStorm.
 * User: jloosli
 * Date: 12/10/14
 * Time: 9:51 AM
 */
class POM_Affiliate_Linker {
    public static $settingsInstance;

    function __construct() {
        $this->affiliate_id = get_option( 'pom_amazon_affiliate_id' );

        //Actions
        add_action( 'wp', array( $this, 'activation' ) );
        add_action( 'wp_ajax_pom_affiliates_run', array( $this, 'add_amazon_ajax' ) );

        //Filters

        //Short codes

        //Scripts

    }

    public static function getSettingsInstance() {
        if ( is_null( self::$settingsInstance ) ) {
            require_once( 'class-affiliate-linker-settings.php' );
            self::$settingsInstance = new POM_Affiliate_Linker_Settings();
        }

        return self::$settingsInstance;

    }

    function activation() {
        if ( !wp_next_scheduled( 'affiliate_linker_event' ) ) {
            wp_schedule_event( time(), 'daily', array( $this, 'add_amazon' ) );
        }
    }

    function add_amazon_tag( $matches ) {

        $url = parse_url( $matches[2] ); // split url into parts
        if (strpos(strtolower($url['host']),'amazon') === false || (isset( $url['query'] ) && strstr( $url['query'], "tag" ) )) {
            return $matches[0];
        } // ignore if already has tag query
        $prefix = isset( $url['query'] ) ? "&" : "?"; // add & if already query, ? if no query
        return str_replace( $matches[2], $matches[2] . $prefix . "tag={$this->affiliate_id}", $matches[0] ); // add tag
    }

    function add_amazon_ajax() {
        header( 'Content-Type: application/json' );
        $this->add_amazon();
        die;
    }

    function add_amazon() {

        global $wpdb;


        $has_amazon = $wpdb->get_results( <<<sql

        SELECT ID, post_content
        FROM $wpdb->posts
        WHERE post_status = 'publish'
		AND post_content LIKE '%amazon.com%';
sql
        );
        $url_find = "<a\s[^>]*href=([\"']??)([^\" >]*?)\\1[^>]*>";
        $changeCount = 0;
        foreach ( $has_amazon as $am ) {
            if($am->ID === "840") {
                $stop='here';
            }
            $new_content = preg_replace_callback( "/$url_find/siU", array(
                $this,
                "add_amazon_tag"
            ), $am->post_content );
            if ( $new_content !== $am->post_content ) {
                $changeCount ++;

                $wpdb->update(
                    $wpdb->posts,
                    array(
                        'post_content' => $new_content,
                    ),
                    array( 'ID' => $am->ID ), // where
                    array( '%s' ), // replacement: string
                    array( '%d' ) // where: string
                );
            }
        }
        echo json_encode( [
            'success' => true,
            'message' => sprintf( "Replaced %d urls in %d posts (%0.2f%%)", $changeCount, count( $has_amazon ), $changeCount / count( $has_amazon ) )
        ] );

    }

}