<?php

require_once 'phing/filters/BaseParamFilterReader.php';
include_once 'phing/filters/ChainableReader.php';

class SafeReplaceURLS extends BaseParamFilterReader implements ChainableReader {

	function read($len = null) {
		$buffer = $this->in->read($len);

		if ($buffer === -1) {
			return -1;
		}

		$params = $this->getParamsArray(array(
			'url.find' => null,
            'url.replace' => null,
		));

		if (empty($params['url.find'])) {
			throw new BuildException("\"url.find\" parameter not set");
		}

        if (empty($params['url.replace'])) {
            throw new BuildException("\"url.replace\" parameter not set");
        }

        $this->replaceURLS($params['url.find'], $params['url.replace'], $buffer);

		return $buffer;
	}

	function chain(Reader $reader) {
		$newFilter = new SafeReplaceURLS($reader);
		$newFilter->setProject($this->getProject());
		return $newFilter;
	}

	function getParamsArray($defaults = array()) {
		$params = array();

		foreach ($this->getParameters() as $param) {
			$params[$param->getName()] = $param->getValue();
		}

		return array_merge($defaults, $params);
	}

    function replaceURLS($old_url, $new_url, &$dump) {
        $new_domain = preg_replace('|http[s]?:\/\/|i', '', $new_url);
        $old_domain = preg_replace('|http[s]?:\/\/|i', '', $old_url);

        $this->replaceSerialized($old_url, $new_url, $dump);
        $this->replaceSerialized($old_domain, $new_domain, $dump);

    }

    function replaceSerialized($find, $replace, &$dump) {
        $this->_find = $find;
        $this->_replace = $replace;

        $dump = preg_replace_callback('|(s:)([0-9]+)(:\\\")(.*?)(\\\";)|', array($this, 'replaceSerializedCallback'), $dump);
        $dump = preg_replace_callback('|(s:)([0-9]+)(:\")(.*?)(\";)|', array($this, 'replaceSerializedCallback'), $dump);
        $dump = str_replace($find, $replace, $dump);
    }

    function replaceSerializedCallback($matches) {
        $content = $matches[4];

        if (false !== strpos($content, $this->_find)) {
            $content = str_replace($this->_find, $this->_replace, $content);
            return $matches[1] . strlen(stripcslashes($content)) . $matches[3] . $content . $matches[5];
        }

        return $matches[0];
    }
}