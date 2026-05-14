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
 * Class <b>Keywords</b> -- Keyword extraction utility.
 * Original algorithm by the project owner — 
 * adapted for UTF-8 / WordPress environment.
 *
 * @version 1.0.0-a.1
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-14 11:35:13
 */
class Keywords {
    
    
     /**
     * Extract keywords from text.
     *
     * @param string $text Source text (HTML allowed — will be stripped).
     * @param int $MinSymbol Minimum characters per word.
     * @param int $MaxWords Maximum keywords to return.
     * @param string $lang Language hint: 'en' | 'ru' | 'uk' | '' (auto).
     * @return string[] Sorted array of keywords (most frequent first).
     */
    public static function genKeywords(
        string $text,
        int $MinSymbol = 4,
        int $MaxWords  = 25,
        string $lang   = ''
    ): array {
        // 1. Strip markup
        $text = wp_strip_all_tags( $text );

        // 2. Decode HTML entities (&amp; &nbsp; etc.)
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // 3. Remove residual entity-like tokens
        $text = preg_replace( '~&[^;\s]{1,10};~is', ' ', $text );

        // 4. Unicode lowercase
        $text = mb_strtolower( $text, 'UTF-8' );

        // 5. Build pattern using Unicode-aware flags (u = UTF-8 mode)
        $charClass = self::filterByLang( $lang );
        $pattern   = '/' . $charClass . '{' . (int) $MinSymbol . ',}/isu';

        // 6. Extract all matching words
        preg_match_all( $pattern, $text, $matches );
        if ( empty( $matches[0] ) ) {
            return [];
        }

        // 7. Count occurrences, sort descending
        $counts = array_count_values( $matches[0] );
        arsort( $counts );

        // 8. Build result array (MaxWords limit)
        $result = [];
        $i      = 0;
        foreach ( $counts as $word => $freq ) {
            $result[] = $word;
            if ( ++$i >= $MaxWords ) {
                break;
            }
        }

        return $result;
    }

    /**
     * Like genKeywords() but additionally removes common stop-words.
     * Use this for the "suggested keywords" UI widget.
     * 
     * @param string $text Source text (HTML allowed — will be stripped).
     * @param int $MinSymbol Minimum characters per word.
     * @param int $MaxWords Maximum keywords to return.
     * @param string $lang Language hint: 'en' | 'ru' | 'uk' | '' (auto).
     * @return array Sorted array of keywords (most frequent first).
     */
    public static function genKeywordsFiltered(
        string $text,
        int $MinSymbol = 4,
        int $MaxWords  = 25,
        string $lang   = ''
    ): array {
        // Fetch more than needed so we have room after filtering
        $raw      = self::genKeywords( $text, $MinSymbol, $MaxWords * 4, $lang );
        $stopList = self::stopWords( $lang );

        $filtered = array_filter( $raw, static function ( $w ) use ( $stopList ) {
            return ! in_array( $w, $stopList, true );
        } );

        return array_slice( array_values( $filtered ), 0, $MaxWords );
    }

    /**
     * Auto-detect language by inspecting the first 500 chars of text.
     * 
     * @param string $text Source text (HTML allowed — will be stripped).
     * @return string Returns 'ru', 'uk', 'en' or '' (unknown).
     */
    public static function detectLang( string $text ): string {
        $sample = mb_substr( $text, 0, 500, 'UTF-8' );

        // Ukrainian-specific letters
        if ( preg_match( '/[іїєґ]/u', $sample ) ) {
            return 'uk';
        }
        // Cyrillic (Russian)
        if ( preg_match( '/\p{Cyrillic}/u', $sample ) ) {
            return 'ru';
        }
        // Latin
        if ( preg_match( '/[a-zA-Z]/', $sample ) ) {
            return 'en';
        }

        return '';
    }
    
    /**
     * Returns a PCRE character-class pattern for the requested language.
     * Works purely with Unicode code-points (no Win-1251 conversion needed).
     *
     * @param string $lang Language hint: 'en' | 'ru' | 'uk' | '' (auto).
     * @return string PCRE character-class pattern.
     */
    private static function filterByLang( string $lang ): string {
        switch ( strtolower( $lang ) ) {
            case 'ru':
            case 'uk':
                // Cyrillic + Ukrainian-specific letters
                return '[\p{Cyrillic}a-z0-9\-]';
            case 'en':
                return '[a-z0-9\-]';
            default:
                // All unicode letters + digits (safe universal fallback)
                return '[\p{L}\p{N}\-]';
        }
    }
    
    /**
     * Stop words.
     * 
     * @param string $lang Language hint: 'en' | 'ru' | 'uk' | '' (auto).
     * @return array 
     */
    private static function stopWords( string $lang ): array {
        $map = [
            'ru' => [
                'и','в','не','на','что','с','по','это','как','из','или','к',
                'его','но','для','от','а','то','все','она','так','её','бы',
                'он','был','за','со','при','без','через','вы','мы','они',
                'их','об','же','ещё','тот','та','те','это','эта',
            ],
            'uk' => [
                'і','в','не','на','що','з','по','це','як','або','до',
                'його','але','для','від','а','то','всі','вона','так','її',
                'він','був','за','при','без','через','ви','ми','вони',
                'їх','ще','той','та','ті',
            ],
            'en' => [
                'the','be','to','of','and','a','in','that','have','it',
                'for','not','on','with','he','as','you','do','at','this',
                'but','his','by','from','they','we','or','an','will','my',
                'one','all','would','there','their','what','so','up','out',
                'if','about','who','get','which','go','me','when','make',
                'can','like','time','no','just','him','know','take','people',
                'into','year','your','good','some','could','them','see',
                'other','than','then','now','look','only','come','its',
                'over','think','also','back','after','use','two','how',
                'our','work','first','well','way','even','new','want',
                'because','any','these','give','day','most','us',
            ],
        ];

        $words = [];
        if ( '' === $lang ) {
            foreach ( $map as $list ) {
                $words = array_merge( $words, $list );
            }
        } else {
            $words = $map[ $lang ] ?? [];
        }

        return array_unique( $words );
    }
}
