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
 * Class <b>TagParser</b> -- without description.
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-17 17:37:12
 */

class TagParser {
    public function parse(string $json, TagCollection $collection): void {
        $data = json_decode($json, true, 16);
        
        if (is_array($data)) {
            $collection->clear();
            
            foreach ($data as $key => $value) {
                switch ($key) {
                    case MetaTag::$TAG_NAME: 
                        $this->deserealiseMetaTag($value, $collection);
                        break;	
                        
                    case ScriptTag::$TAG_NAME:
                        $this->deserealiseScript($value, $collection);
                        break;
         
                    case LinkTag::$TAG_NAME:
                        $this->deserealiseLink($value, $collection);
                        break;
						
					case "jsonld":
                        $this->deserealiseJsonLd($value, $collection);
                        break;
                    
					case "data":
                        $this->createData($value, $collection);
                        break;
					
                    default:
                        break;
                }		
            }
        }		
    }

    private function deserealiseMetaTag(array $attributes, TagCollection $collection): void {
        foreach($attributes as $key => $value) {
            switch ($key) {
                case MetaTag::$PROPERTY :
                    $this->createMetaProperty($value, '', $collection);				
                    break;
                case MetaTag::$NAME:
                    $this->createMetaName($value, $collection);
                    break;
                case MetaTag::$HTTP_EQUIV:
                    $this->createMetaHttpEquiv($value, $collection);
                    break;
                default:
                    break;
            }
        }
    }
    
    private function createMetaName(array $attributes, TagCollection $collection): void {
        foreach($attributes as $key => $value) {			
            if (is_array($value)) {
                $count = count($value);
                $tags = [];
                for ($i = 0; $i < $count; $i++) {
                    $tags[] = (new NameMetaTag())
                        ->setName($key)
                        ->setContent($value[$i]);
                }	
                $collection->set("meta::name::$key", $tags);
            } else {
                $collection->set("meta::name::$key", (new NameMetaTag())
                    ->setName($key)
                    ->setContent($value));
            }
        }
    }
    
    private function createMetaProperty(array $attributes, string $prefix, TagCollection $collection): void {
        foreach($attributes as $key => $value) {			
            switch ($key) {
                case "og":
                case "twitter":					
                case "article":
                    $this->createMetaProperty($value, "$key:", $collection);
                    break;
                default:
                    if (is_array($value)) {
                        $count = count($value);
                        $tags = [];
                        for ($i = 0; $i < $count; $i++) {
                            $tags[] = (new PropertyMetaTag())
                                ->setProperty("$prefix$key")
                                ->setContent($value[$i]);
                        }	
                        $collection->set("meta::property::$prefix$key", $tags);
                    } else {
                        $collection->set("meta::property::$prefix$key", (new PropertyMetaTag())
                            ->setProperty("$prefix$key")
                            ->setContent($value));
                    }
            }
        }
    }
	
    private function createMetaHttpEquiv(array $attributes, TagCollection $collection): void {
        foreach($attributes as $key => $value) {
            if (is_array($value)) {
                $count = count($value);
                $tags = [];
                for ($i = 0; $i < $count; $i++) {
                    $tags[] = (new HttpEquivMetaTag())
                        ->setHttpEquiv($key)
                        ->setContent($value[$i]);
                }   
                $collection->set("meta::http-equiv::$key", $tags);
            } else {
                $collection->set("meta::http-equiv::$key", (new HttpEquivMetaTag())
                    ->setHttpEquiv($key)
                    ->setContent($value));
            }
        }
    }

    private function deserealiseScript(array $attributes, TagCollection $collection): void {		
        $tags = [];
        $count = count($attributes);
        for ($i = 0; $i < $count; $i++) {
            $tags[] = $this->createScript($attributes[$i]);	
        }
        $collection->set("script", $tags);
    }
    
    private function createScript(array $attributes): ScriptTag {
        $script = new ScriptTag();
        foreach($attributes as $key => $value) {
            $script->setValue($key, $value);
        }
        return $script;
    }
    
    private function deserealiseJsonLd($value, TagCollection $collection): void {
        $script = (new ScriptTag())
            ->setType('application/ld+json')
            ->setInline(json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

        $collection->set('jsonld', $script);
    }
	
	private function deserealiseLink(array $attributes, TagCollection $collection): void {
		$tags = [];

		foreach ($attributes as $item) {
			if (is_array($item)) {
				$tags[] = $this->createLink($item);
			}
		}

		$collection->set("link", $tags);
	}

	private function createLink(array $attributes): LinkTag {
		$link = new LinkTag();

		foreach ($attributes as $key => $value) {
			$link->setValue($key, $value);
		}

		return $link;
	}
	
	private function createData(array $attributes, TagCollection $collection): void {
		foreach ($attributes as $key => $value) {
			$collection->set("data::$key", $value);
		}
	}
}
