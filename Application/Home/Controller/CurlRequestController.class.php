<?php
namespace Home\Controller;
use Think\Controller;
class CurlRequestController {
    public $url         = '';
    public $method      = 'GET';
    public $post_data   = null;
    public $headers     = null;
    public $options     = null;
    public $user_id     = null;
    public $list_id = null;
    public $amount  = null;
    public $order_id  = null;
    public $accesstoken = null;
    public $strategy_id = null;
    /**
     *
     * @param string $url
     * @param string $method
     * @param string $post_data
     * @param string $headers
     * @param array $options
     * @return void
     */
    public function __construct($url, $method = 'GET', $post_data = null, $headers = null, $options = null) {
        $this->url = $url;
        $this->method = strtoupper( $method );
        $this->post_data = $post_data;
        $this->headers = $headers;
        $this->options = $options;
    }
    /**
     * @return void
     */
    public function __destruct() {
        unset ( $this->url, $this->method, $this->post_data, $this->headers, $this->options ,$this->user_id, $this->list_id);
    }
}
