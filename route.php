<?php

class Route
{
    public function __construct()
    {
        $url = $this->url();
        print_r($url);
    }
    public function url()
    {
        if (isset($_SERVER["REQUEST_URI"])) {
            $url = $_SERVER["REQUEST_URI"];
            // $url = trim($url, "/");
            $url = explode("/", $url);
            return $url;
        }
    }
}