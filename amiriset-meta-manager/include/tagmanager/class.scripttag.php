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
 * Class <b>ScriptTag</b> -- without description.
 *
 * @version 1.0.0
 * @package Amiriset
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-17 17:28:15
 */
class ScriptTag extends AbstractTag {
	
    public static string $TAG_NAME = "script";
	
    public static string $SRC = "src";
    public static string $TYPE = "type";
    public static string $ID = "id";
    public static string $ASYNC = "async";
    public static string $DEFER = "defer";
    public static string $INLINE = "__inline";
    
    public function __construct() {
        parent::__construct(self::$TAG_NAME);
    }

    public function setSrc(string $value): static {
        parent::setValue(self::$SRC, $value);
        return $this;
    }

    public function setType(string $value): static {
        parent::setValue(self::$TYPE, $value);
        return $this;
    }

    public function setId(string $value): static {
        parent::setValue(self::$ID, $value);
        return $this;
    }

    public function setAsync(bool $value = true): static {        
        if ($value) {
            parent::setValue(self::$ASYNC, null);
        }
        return $this;
    }

    public function setDefer(bool $value = true): static {
        if ($value) {
            parent::setValue(self::$DEFER, null);
        }
        return $this;
    }

    public function setInline(string $code): static {
        parent::setValue(self::$INLINE, $code);
        return $this;
    }

    protected function isKey($string) {
        return in_array($string, [
            self::$SRC,
            self::$TYPE,
            self::$ID,
            self::$ASYNC,
            self::$DEFER,
            self::$INLINE
        ], true);
    }

    public function toHtml(array $excludeKeys = []) {
		$excludes = array_merge($excludeKeys, [self::$INLINE]);
        $tag = parent::toHtml($excludes);

        if ($this->hasKey(self::$INLINE)) {
            $tag .= $this->getValue(self::$INLINE);			
        }
		$tag .= "</script>";
        return $tag;
    }

    protected function closeTag() {
        return " >" ;
    }
	
	protected function printBool($key, $value) {
		$value = $value == true ? "$key" : "";
		return " $value";
	}
}

