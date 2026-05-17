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
 * Class <b>class.metatag</b> -- without description.
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-17 17:18:19
 */
abstract class MetaTag extends AbstractTag {	
	public static string $TAG_NAME = "meta";
	
	public static string $NAME = "name";
	public static string $HTTP_EQUIV = "http-equiv";
	public static string $PROPERTY = "property";
	public static string $CONTENT = "content";
	public static string $CHARSET = "charset";
	public static string $ITEMPROP = "itemprop";
	
	public function __construct() {
		parent::__construct(self::$TAG_NAME);
	}
	
	public function setContent($value) : static {
		parent::setValue(self::$CONTENT, $value);
		return $this;
	}
	
	public function getContent() { 
		return parent::getValue(self::$CONTENT);
	}
	
	 protected function isKey($string) {
        return in_array($string, [
            self::$NAME,
            self::$CONTENT,
            self::$PROPERTY,
            self::$HTTP_EQUIV,
            self::$CHARSET,
            self::$ITEMPROP,
        ], true);
    }
}