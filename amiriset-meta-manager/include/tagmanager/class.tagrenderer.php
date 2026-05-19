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
 * Class <b>TagRenderer</b> -- without description.
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-17 17:40:07
 */
class TagRenderer {

    public function getHtml(TagCollection $collection): string {
        $html = "";
        foreach($collection->getAll() as $key => $value) {
			if (str_starts_with($key, 'data::')) continue;
            if (is_array($value)) {
                $count = count($value);
                for ($i = 0; $i < $count; $i++) {
                    $html .= $value[$i]->toHtml() . "\n";
                }	
            } else {
                $html .= $value->toHtml() . "\n";
            }
        }
        return $html;
    }
    
    public function toHtml(TagCollection $collection): void {
        echo $this->getHtml($collection);
    }
    
    public function dump(TagCollection $collection): void {
        echo "<ul>\n";
        foreach($collection->getAll() as $key => $value) {
            echo "<li><p>$key</p>\n";
            echo "<code>\n";
            var_dump($value);
            echo "</code>\n</li>\n";
        }
        echo "</ul>\n";
    }
}
