<?php

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *        ___             _        _                 _                       *
 *       /   |           |_|      |_|               | |                      *
 *      / /| | _________  _  _ __  _   ____   ___  _| |_                     *
 *     / /_| ||  _   _  \| || / _|| | / ___| / _ \|_   _|                    *
 *    / ___  || | | | | || ||  /  | ||___  ||  __/  | |_                     *
 *   /_/   |_||_| |_| |_||_||_|   |_||____/  \___|  |___|                    *
 *                                                                           *
 *   AMIRISET [https://amiriset.com]                                         *
 *   __________________                                                      *
 *                                                                           *
 *   Copyright (C) 2016-2026 Amiriset                                        *
 *   Licensed under GNU GPLv3 or later.                                      *
 *                                                                           *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

namespace Amiriset\MetaManager;
defined( 'ABSPATH' ) || exit;

//------------------------------------------------------------------------------
//    DESCRIPTIONS
//------------------------------------------------------------------------------
/**
 * Class <b>AttributeContainer</b> -- without description.
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-17 17:14:37
 */
abstract class AttributeContainer {
	private $content = [];

	public function setValue($key, $value) {
		if ($this->isKey($key)) {
			$this->content[$key]=$value;
		} else {
			throw new InvalidArgumentException("Invalid attribute key: $key");
		}
	}
	
	public function getValue($key) {
		return $this->content[$key];
	}
	
	public function hasKey($key) {
		return array_key_exists($key, $this->content);
	}
	
	public function fromJson($json) {
		$data = json_decode($json, true, 16);

		if (!is_array($data)) {
			return;
		}

		$this->content = [];

		foreach ($data as $key => $value) {
			$this->setValue($key, $value);
		}
	}
	
	public function toJson() {
		return json_encode($this->content);
	}
	
	public function toArray(): array {
		return $this->content;
	}
	
	/**
	 * Deterministic fingerprint of all attributes.
	 * Used for diff-merge operations on array-valued collection keys.
	 *
	 * @return string MD5 hash.
	 */
	public function hash(): string {
		return md5( json_encode( $this->content, JSON_UNESCAPED_UNICODE ) );
	}
	
	public function toHtml(array $excludeKeys = []) {
		$str = "";

		foreach ($this->content as $key => $value) {
			
			if (in_array($key, $excludeKeys, true)) continue;
			
			if (is_null($value)) {
				
				continue;
				
			} else if (is_bool($value)) {

				$str .= $this->printBool($key, $value);

			} else if (is_numeric($value)) {

				$str .= $this->printNumeric($key, $value);

			} else if (is_string($value)) {

				$str .= $this->printString($key, $value);
			}
		}
		return $str;
	
	}
	
	protected function printBool($key, $value) {
		$value = $value ? "true" : "false";
		return " $key=\"$value\"";
	}
	
	protected function printNumeric($key, $value) {
		return " $key=\"$value\"";
	}
	
	protected function printString($key, $value) {
		$value = htmlspecialchars(
					$value,
					ENT_QUOTES | ENT_HTML5,
					'UTF-8'
				);

		return " $key=\"$value\"";
	}
	
	protected function isKey($string) {
		return true;		
	}
}

