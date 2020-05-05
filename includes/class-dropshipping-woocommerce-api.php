<?php
/**
 * Knawat MP API Wrapper class.
 *
 * @link       http://knawat.com/
 * @since      2.0.0
 * @category   Class
 * @author 	   Dharmesh Patel
 *
 * @package    Knawat_Dropshipping_Woocommerce
 * @subpackage Knawat_Dropshipping_Woocommerce/includes
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Knawat_Dropshipping_Woocommerce_API {

	/**
     * Contains cURL instance
     * @access protected
     */
    protected $token;

	/**
	 * Contains API headers
	 * @access protected
	 */
	protected $headers;

	/**
     * Contains the API url
     * @access protected
     */
    protected $api_url = 'https://mp.knawat.io/api';

	/**
	* __construct function.
	*
	* @access public
	* @return void
	*/
	public function __construct() {
		// Setup Headers
		global $wp_version;
		$woo_version = 'NOT_FOUND';
		if( defined( 'WC_VERSION') ){
			$woo_version = WC_VERSION;
		}
		$this->headers = array(
			'User-Agent' 	=> 'Knawat-WP; WordPress/' . $wp_version . '; WooCommerce/' . $woo_version . '; DropShipping/' . KNAWAT_DROPWC_VERSION . '; ' . home_url( '/' ),
			'Content-Type'	=> 'application/json'
		);

		// Set API Token
		$this->token = $this->get_access_token();
	}

    /**
    * Get Access Token
    *
    *
    * @access public
    * @return string
    */
    public function get_access_token( $token_only = true ) {

		// Check for transient, if none, grab channel
		if ( false === ( $channel = get_transient( 'knawat_mp_access_channel' ) ) ) {

			// Consumer Keys.
			$consumer_keys = knawat_dropshipwc_get_consumerkeys();
			if( empty( $consumer_keys ) ){
				return false;
			}

			// post body
			$keydata = array();
			$keydata['consumerKey'] 	= $consumer_keys['consumer_key'];
			$keydata['consumerSecret']	= $consumer_keys['consumer_secret'];

			// Get Store channel
			$response = wp_remote_post($this->api_url . '/token', array(
				'body' => $keydata,
				'timeout' => 20,
				'headers' => array('User-Agent' => $this->headers['User-Agent'])
			));

			if ( is_wp_error( $response ) ) {
				return false;
			} else {
				$channel = wp_remote_retrieve_body( $response );
				if ( is_wp_error( $channel ) ) {
					return false;
				}
				$channel = json_decode( $channel );
				if( !isset( $channel->channel ) ){
					return false;
				}
				$channel = $channel->channel;
				set_transient( 'knawat_mp_access_channel', $channel, 24 * HOUR_IN_SECONDS );
			}
		}

		if( $token_only ){
			return $channel->token;
		}
		return $channel;
    }

    /**
    * get function.
    *
    * Performs an API GET request
    *
    * @access public
    * @return object
    */
    public function get( $path, $return_array = FALSE ) {

		if( empty( $this->token ) ){
			return new WP_Error( 'token_not_found', __( 'Access token not found for API. Please make sure your store is connected to knawat.com', 'dropshipping-woocommerce' ) );
		}
		$url = $this->api_url . '/' . $path;
		$this->headers['Authorization'] = 'Bearer ' . $this->token;

		$cache = apply_filters( 'knawat_dropshipwc_mp_api_cache', true, $url, $this->headers, 'GET' );
		$cache_time = apply_filters( 'knawat_dropshipwc_cache_time', 180 );
		$transient_name = 'knawat_mp_cache_' . substr( md5( $url . wp_json_encode( $this->headers ) ), 23 );
		$response = $cache ? get_transient( $transient_name ) : false;

		if (false === $response) {
			$response = wp_remote_get($url, array(
				'timeout' => 20,
				'headers' => $this->headers
			));

			if ( ! is_wp_error( $response ) ) {
				// In Case return full response
				if( !$return_array ){
					$response = wp_remote_retrieve_body( $response );
					if ( !is_wp_error( $response ) ) {
						// Return json Decoded response.
						$response = json_decode( $response );
						if( $cache ){
							set_transient( $transient_name, $response, $cache_time );
						}
					}
				}
			}
		}

		return $response;
    }


    /**
    * post function.
    *
    * Performs an API POST request
    *
    * @access public
    * @return object
    */
    public function post( $path, $data = array(), $return_array = FALSE ) {
		if( empty( $this->token ) ){
			return new WP_Error( 'token_not_found', __( 'Access token not found for API. Please make sure your store is connected to knawat.com', 'dropshipping-woocommerce' ) );
		}
		$url = $this->api_url . '/' . $path;
		$this->headers['Authorization'] = 'Bearer ' . $this->token;
		$response = wp_remote_post( $url, array(
			'body'	  => $data,
			'timeout' => 20,
			'headers' => $this->headers
		) );

		if ( ! is_wp_error( $response ) ) {
			// In Case return full response
			if( $return_array ){
				return $response;
			}

			$response = wp_remote_retrieve_body( $response );
			if ( !is_wp_error( $response ) ) {
				// Return json Decoded response.
				return json_decode( $response );
			} else {
				return $response;
			}
		}else{
			return $response;
		}
		return;
	}

	/**
    * put function.
    *
    * Performs an API PUT request
    *
    * @access public
    * @return object
    */
    public function put( $path, $data = array(), $return_array = FALSE ) {
		if( empty( $this->token ) ){
			return new WP_Error( 'token_not_found', __( 'Access token not found for API. Please make sure your store is connected to knawat.com', 'dropshipping-woocommerce' ) );
		}
		$url = $this->api_url . '/' . $path;
		$this->headers['Authorization'] = 'Bearer ' . $this->token;
		$response = wp_remote_request( $url, array(
			'method'  => 'PUT',
			'timeout' => 20,
			'body'	  => $data,
			'headers' => $this->headers
		) );

		if ( ! is_wp_error( $response ) ) {
			// In Case return full response
			if( $return_array ){
				return $response;
			}

			$response = wp_remote_retrieve_body( $response );
			if ( !is_wp_error( $response ) ) {
				// Return json Decoded response.
				return json_decode( $response );
			} else {
				return $response;
			}
		}else{
			return $response;
		}
		return;
	}

	/**
    * Delete function.
    *
    * Performs an API DELETE request
    *
    * @access public
    * @return object
    */
    public function delete( $path, $data = array(), $return_array = FALSE ) {
		if( empty( $this->token ) ){
			return new WP_Error( 'token_not_found', __( 'Access token not found for API. Please make sure your store is connected to knawat.com', 'dropshipping-woocommerce' ) );
		}
		$url = $this->api_url . '/' . $path;
		$this->headers['Authorization'] = 'Bearer ' . $this->token;
		$response = wp_remote_request( $url, array(
			'method'  => 'DELETE',
			'timeout' => 20,
			'body'	  => $data,
			'headers' => $this->headers
		) );

		if ( ! is_wp_error( $response ) ) {
			// In Case return full response
			if( $return_array ){
				return $response;
			}

			$response = wp_remote_retrieve_body( $response );
			if ( !is_wp_error( $response ) ) {
				// Return json Decoded response.
				return json_decode( $response );
			} else {
				return $response;
			}
		}else{
			return $response;
		}
		return;
	}
}
