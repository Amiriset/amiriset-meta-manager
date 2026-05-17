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
 * Class <b>LinkTag</b> -- without description.
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-17 17:33:18
 */
class LinkTag extends AbstractTag {
    public static string $TAG_NAME = "link";

    public static string $REL = "rel";
    public static string $HREF = "href";
    public static string $MEDIA = "media";
    public static string $ID = "id";
    public static string $TYPE = "type";

    public function __construct()
    {
        parent::__construct(self::$TAG_NAME);
    }

    public function setRel(string $value): static
    {
        parent::setValue(self::$REL, $value);
        return $this;
    }

    public function setHref(string $value): static
    {
        parent::setValue(self::$HREF, $value);
        return $this;
    }

    public function setMedia(string $value): static
    {
        parent::setValue(self::$MEDIA, $value);
        return $this;
    }

    public function setId(string $value): static
    {
        parent::setValue(self::$ID, $value);
        return $this;
    }

    public function setType(string $value): static
    {
        parent::setValue(self::$TYPE, $value);
        return $this;
    }

    protected function isKey($string)
    {
        return in_array($string, [
            self::$REL,
            self::$HREF,
            self::$MEDIA,
            self::$ID,
            self::$TYPE,
        ], true);
    }
}
