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
 * Class <b>TagManager</b> -- without description.
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-17 17:12:37
 */
class TagManager {
    private TagParser $parser;
    private TagCollection $collection;
    private TagRenderer $renderer;
    private TagSerializer $serializer;
    
    public function __construct(
        ?TagParser $parser = null,
        ?TagCollection $collection = null,
        ?TagRenderer $renderer = null,
        ?TagSerializer $serializer = null
    ) {
        $this->parser = $parser ?? new TagParser();
        $this->collection = $collection ?? new TagCollection();
        $this->renderer = $renderer ?? new TagRenderer();
        $this->serializer = $serializer ?? new TagSerializer();
    }
    
    public function getParser(): TagParser {
        return $this->parser;
    }
    
    public function getCollection(): TagCollection {
        return $this->collection;
    }
    
    public function getRenderer(): TagRenderer {
        return $this->renderer;
    }

    public function getSerializer(): TagSerializer {
        return $this->serializer;
    }
    
    public function deserealise(string $json): void {
        $this->parser->parse($json, $this->collection);
    }
    
    public function toHtml(): void {
        $this->renderer->toHtml($this->collection);
    }

    public function toJson(): string {
        return $this->serializer->toJson($this->collection);
    }
    
    public function dump(): void {
        $this->renderer->dump($this->collection);
    }
}

