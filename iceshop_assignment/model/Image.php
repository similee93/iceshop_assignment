<?php

class ImageException extends Exception {}

class Image {
    private $_id;
    private $_clientId;
    private $_originalName;
    private $_hash;
    private $_format;
    private $_sizeBites;
    private $_url;
    private $_status;

    public function __construct($id, $clientId, $originalName, $hash, $format, $sizeBites, $url, $status)
    {
        $this->setID($id);
        $this->setClientId($clientId);
        $this->setOriginalName($originalName);
        $this->setHash($hash);
        $this->setFormat($format);
        $this->setSizeBites($sizeBites);
        $this->setUrl($url);
        $this->setStatus($status);
    }

    public function getID() {
        return $this->_id;
    }

    public function getClientId() {
        return $this->_clientId;
    }

    public function getOriginalName() {
        return $this->_originalName;
    }

    public function getHash() {
        return $this->_hash;
    }

    public function getFormat() {
        return $this->_format;
    }

    public function getSizeBites() {
        return $this->_sizeBites;
    }

    public function getUrl() {
        return $this->_url;
    }

    public function getStatus() {
        return $this->_status;
    }

    public function setID($id) {
        if (($id !== null) && ((!is_numeric($id)) || $id <= 0 || $id > 922372036854775807 || $this->_id !== null)) {
            throw new ImageException('Image ID error');
        }

        $this->_id = $id;
    }

    public function setClientId($clientId) {
        $clientId = strtolower($clientId);
        if (strlen($clientId) < 0 || strlen($clientId) > 255 || !preg_match('/^client_[0-9]+$/', $clientId)) {
            throw new ImageException('Image client ID error');
        }

        $this->_clientId = $clientId;
    }

    public function setOriginalName($originalName) {
        if (strlen($originalName) < 0 || strlen($originalName) > 255) {
            throw new ImageException('Image original name error');
        }

        $this->_originalName = $originalName;
    }

    public function setHash($hash) {
        if (strlen($hash) < 0 || strlen($hash) > 255) {
            throw new ImageException('Image original name error');
        }

        $this->_hash = $hash;
    }

    public function setFormat($format) {
        if (strlen($format) < 0 || strlen($format) > 6) {
            throw new ImageException('Image format error');
        }

        $this->_format = $format;
    }

    public function setSizeBites($sizeBites) {
        if ((!is_numeric($sizeBites)) || strlen($sizeBites) < 0 || strlen($sizeBites) > 10) {
            throw new ImageException('Image size bites error');
        }

        $this->_sizeBites = $sizeBites;
    }

    public function setUrl($url) {
        if (strlen($url) < 0 || strlen($url) > 255) {
            throw new ImageException('Image url error');
        }

        $this->_url = $url;
    }

    public function setStatus($status) {
        $this->_status = $status;
    }

    public function returnImageAsArray() {
        $image = array();
        $image['id'] = $this->getID();
        $image['clientId'] = $this->getClientId();
        $image['originalName'] = $this->getOriginalName();
        $image['hash'] = $this->getHash();
        $image['format'] = $this->getFormat();
        $image['sizeBites'] = $this->getSizeBites();
        $image['url'] = $this->getUrl();
        $image['status'] = $this->getStatus();
        return $image;
    }
}
