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

//------------------------------------------------------------------------------
//    DESCRIPTIONS
//------------------------------------------------------------------------------
/**
 * File <b>uninstall.php</b> -- WP SEO Meta Manager — Admin JS. 
 * Dependencies: jQuery, wp.media (enqueued via wp_enqueue_media)
 *
 * @version 1.0.0-a.3
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov
 * @created 2026-05-14 15:06:23
 */

/* global ammData, wp */
(function ($) {
    'use strict';

    // ── Tabs ─────────────────────────────────────────────────────────────────

    $(document).on('click', '.amm-tab-link', function () {
        const $tab = $(this);
        const target = $tab.data('tab');

        $tab.closest('.amm-wrap').find('.amm-tab-link').removeClass('active');
        $tab.addClass('active');

        $tab.closest('.amm-wrap').find('.amm-tab-content').removeClass('active');
        $('#' + target).addClass('active');
    });

    // ── Char counter ─────────────────────────────────────────────────────────

    function updateCounter($field) {
        const $counter = $('.amm-counter[data-field="' + $field.attr('id') + '"]');
        if ($counter.length) {
            const max = parseInt($field.attr('maxlength'), 10) || 320;
            const len = $field.val().length;
            $counter.text(len + ' / ' + max);
            $counter.toggleClass('amm-counter-warn', len > max * 0.9);
        }
    }

    $(document).on('input', 'textarea[maxlength]', function () {
        updateCounter($(this));
    });

    $('textarea[maxlength]').each(function () {
        updateCounter($(this));
    });

    // ── Custom Meta Rows ─────────────────────────────────────────────────────

    let rowIdx = $('#amm-custom-meta-rows tr').length;

    $(document).on('click', '.amm-add-custom-meta', function () {
        const tpl = $('#amm-custom-meta-tpl').html();
        if (!tpl) return;
        const newRow = tpl.replace(/__IDX__/g, 'new_' + rowIdx++);
        $('#amm-custom-meta-rows').append(newRow);
    });

    $(document).on('click', '.amm-remove-row', function () {
        $(this).closest('tr').fadeOut(200, function () {
            $(this).remove();
        });
    });

    // ── Media / Image picker ──────────────────────────────────────────────────

    //  Pattern: one shared wp.media frame, but $mediaTarget is updated on
    //  every button click so the 'select' handler always writes to the
    //  correct hidden input / preview wrap — even when multiple pickers
    //  exist on the same page (meta box OG image + settings page default OG).

    let mediaFrame   = null;
    let $mediaTarget = null;

    $(document).on('click', '.amm-media-btn', function (e) {
        e.preventDefault();
        const $btn    = $(this);
        const targetId = $btn.data('target');
        
        // Update context BEFORE opening so the 'select' callback sees it
        $mediaTarget = {
            $input: $('#' + targetId),
            $btn:   $btn
        };
        
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        // i18n strings may be absent on settings page → safe fallbacks
        const i18n = (ammData && ammData.i18n) ? ammData.i18n : {};

        mediaFrame = wp.media({
            title:   i18n.selectImage || 'Select image',
            button:  { text: i18n.useImage || 'Use this image' },
            multiple: false,
            library:  { type: 'image' }
        });

        mediaFrame.on('select', function () {
            if (!$mediaTarget) return;

            const attachment = mediaFrame.state().get('selection').first().toJSON();
            
            // For the preview use the thumbnail size when available (faster load)
            const previewUrl = (attachment.sizes && attachment.sizes.thumbnail)
                ? attachment.sizes.thumbnail.url
                : attachment.url;

            const { $input, $btn: $activeBtn } = $mediaTarget;

            // 1. Save full-size URL to the hidden field
            $input.val(attachment.url).trigger('change');

            // 2. Show / update preview image
            const $wrap = $input.closest('.amm-og-image-wrap');
            let $preview = $wrap.find('.amm-og-preview');
            
            if ($preview.length) {
                $preview.attr('src', previewUrl);
            } else {
                $('<img>', {
                    src:   previewUrl,
                    alt:   attachment.alt || '',
                    class: 'amm-og-preview'
                }).prependTo($wrap);
            }

            // 3. Show "Remove" button once
            if (!$wrap.find('.amm-media-remove').length) {
                $('<button>', {
                    type:  'button',
                    class: 'button amm-media-remove',
                    text:  i18n.removeImage || 'Remove'
                }).insertAfter($activeBtn);
            }
        });

        mediaFrame.open();
    });

    $(document).on('click', '.amm-media-remove', function () {
        const $wrap  = $(this).closest('.amm-og-image-wrap');
        $wrap.find('.amm-og-preview').remove();
        $wrap.find('input[type="hidden"]').val('');
        $(this).remove();
        
         // Destroy frame so next open starts with empty selection
        if (mediaFrame) {
            mediaFrame.detach();
            mediaFrame = null;
        }
    });

    // ── Keyword Generation ────────────────────────────────────────────────────

    $('#amm-gen-keywords').on('click', function () {
        const $btn    = $(this);
        const $result = $('#amm-kw-result');
        const lang    = $('#amm-kw-lang').val();
        const postId  = ammData.postId || $('input#post_ID').val();

        $btn.prop('disabled', true).text(ammData.i18n.generating);
        $result.html('<span class="amm-spinner"></span>');

        $.post(ammData.ajaxUrl, {
            action:   'amm_generate_keywords',
            nonce:    ammData.nonce,
            post_id:  postId,
            lang:     lang,
        })
        .done(function (res) {
            if (res.success && res.data.keywords.length) {
                const chips = res.data.keywords.map(function (kw) {
                    return '<span class="amm-chip" data-kw="' + escAttr(kw) + '">' + escHtml(kw) + '</span>';
                }).join('');
                $result.html(chips);
            } else {
                $result.text(ammData.i18n.noKeywords);
            }
        })
        .fail(function () {
            $result.text('Error. Please try again.');
        })
        .always(function () {
            $btn.prop('disabled', false).text('⚡ Generate Suggestions');
        });
    });

    // Click chip → append to keywords field
    $(document).on('click', '.amm-chip', function () {
        const $chip = $(this);
        const kw    = $chip.data('kw');
        const $kwField = $('#amm_keywords');

        if (!$kwField.length) return;

        const current = $kwField.val().trim();
        const list    = current ? current.split(',').map(s => s.trim()) : [];

        if (!list.includes(kw)) {
            list.push(kw);
            $kwField.val(list.join(', '));
            $chip.addClass('amm-chip-added');
        }
    });

    // ── Utilities ─────────────────────────────────────────────────────────────

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return escHtml(str).replace(/'/g, '&#039;');
    }

}(jQuery));



