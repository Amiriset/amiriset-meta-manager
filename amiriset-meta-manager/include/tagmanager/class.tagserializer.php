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
 * Class <b>TagSerializer</b> -- without description.
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-17 17:41:55
 */
class TagSerializer {
    public function toArray(TagCollection $collection): array {
		$result = [];
		foreach ($collection->getAll() as $key => $value) {
			$parts = explode('::', $key);
			$current = &$result;
			foreach ($parts as $part) {
				if (!isset($current[$part])) {
					$current[$part] = [];
				}
				$current = &$current[$part];
			}
			$current = $this->serializeValue($key, $value);
		}
		
        return $result;
    }
	
	private function serializeValue($key, $value) {
		$result = new \stdClass();
		if (str_starts_with($key, 'data::')) {
			$result = $value;
		} elseif ($key === 'jsonld') {
            if ($value instanceof ScriptTag) {
                $inline = $value->getValue(ScriptTag::$INLINE);
                if ($inline) {
                    $result = json_decode($inline, true);
                }
            }
        } elseif ($key === LinkTag::$TAG_NAME) {
            if (is_array($value)) {
				$result = [];
                foreach ($value as $tag) {
                    if ($tag instanceof LinkTag) {
                        $result[] = $tag->toArray();
                    }
                }
            }
        } elseif ($key === ScriptTag::$TAG_NAME) {
            if (is_array($value)) {
				$result = [];
                foreach ($value as $tag) {
                    if ($tag instanceof ScriptTag) {
                        $result[] = $tag->toArray();
					}
                }
            }
        } elseif (str_starts_with($key, 'meta::name::') ||
			str_starts_with($key, 'meta::http-equiv::') || 
			str_starts_with($key, 'meta::property::')) {
            $result = $this->extractContents($value);
        }
		return $result;
	}

    private function extractContents($value) {
        if (is_array($value)) {
            $contents = [];
            foreach ($value as $tag) {
                $contents[] = $tag->getContent();
            }
            return count($contents) === 1 ? $contents[0] : $contents;
        } else {
            return $value->getContent();
        }
    }

    public function toJson(TagCollection $collection): string {
        return json_encode(
            $this->toArray($collection),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
    }
}
