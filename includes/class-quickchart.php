<?php
/**
 * Simple QuickChart implementation
 * Fallback for when the QuickChart package is not available
 */
class QuickChart {
    private $config;
    private $width = 500;
    private $height = 300;
    private $backgroundColor = 'transparent';
    private $version = '2';
    
    public function __construct() {
        $this->config = array();
    }
    
    public function setConfig($config) {
        if (is_string($config)) {
            $this->config = $config;
        } else {
            $this->config = json_encode($config);
        }
    }
    
    public function setWidth($width) {
        $this->width = $width;
    }
    
    public function setHeight($height) {
        $this->height = $height;
    }
    
    public function setBackgroundColor($color) {
        $this->backgroundColor = $color;
    }
    
    public function setVersion($version) {
        $this->version = $version;
    }
    
    public function getUrl() {
        $params = array(
            'c' => $this->config,
            'w' => $this->width,
            'h' => $this->height,
            'bkg' => $this->backgroundColor,
            'v' => $this->version
        );
        
        return 'https://quickchart.io/chart?' . http_build_query($params);
    }
    
    public function getShortUrl() {
        // For simplicity, just return the regular URL
        return $this->getUrl();
    }
}