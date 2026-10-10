<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Restricted front matter parser: scalars, | blocks and dash-list fields only; no YAML tags/objects. */
function wbsmi_allowed_fields() {
    return array(
        'id', 'title', 'slug', 'status', 'publish_at', 'excerpt', 'categories', 'tags', 'featured_image_id',
        'yoast_title', 'yoast_meta_description', 'yoast_focus_keyphrase', 'yoast_canonical',
        'yoast_breadcrumb_title', 'yoast_opengraph_title', 'yoast_opengraph_description', 'yoast_opengraph_image',
        'yoast_twitter_title', 'yoast_twitter_description', 'yoast_twitter_image',
        'yoast_schema_page_type', 'yoast_schema_article_type', 'yoast_cornerstone',
        'yoast_robots_index', 'yoast_robots_follow', 'yoast_robots_advanced', 'yoast_primary_category'
    );
}
function wbsmi_value( $raw ) {
    $raw = trim( $raw );
    if ( strlen( $raw ) >= 2 && ( ( $raw[0] === '"' && substr( $raw, -1 ) === '"' ) || ( $raw[0] === "'" && substr( $raw, -1 ) === "'" ) ) ) {
        $raw = substr( $raw, 1, -1 );
        if ( strpos( $raw, '\\"' ) !== false ) { $raw = str_replace( '\\"', '"', $raw ); }
    }
    return $raw;
}
function wbsmi_parse_frontmatter( $text ) {
    $rows = preg_split( '/\n/', $text );
    $fields = array(); $errors = array();
    $allowed = wbsmi_allowed_fields();
    $count = count( $rows );
    for ( $i = 0; $i < $count; $i++ ) {
        $line = $rows[$i];
        if ( trim( $line ) === '' || preg_match( '/^\s*#/', $line ) ) { continue; }
        if ( ! preg_match( '/^([a-z][a-z0-9_]*)\s*:\s*(.*)$/', $line, $m ) ) {
            $errors[] = 'Строка ' . ( $i + 1 ) . ': ожидается поле key: value без вложенных объектов.'; continue;
        }
        $key = $m[1]; $value = trim( $m[2] );
        if ( ! in_array( $key, $allowed, true ) ) { $errors[] = 'Неизвестное поле: ' . $key; continue; }
        if ( array_key_exists( $key, $fields ) ) { $errors[] = 'Повторяется поле: ' . $key; continue; }
        if ( $value === '|' || $value === '|-' || $value === '>' || $value === '>-' ) {
            $parts = array();
            while ( $i + 1 < $count && ( preg_match( '/^ {2,}/', $rows[$i+1] ) || trim( $rows[$i+1] ) === '' ) ) {
                $i++; $parts[] = preg_replace( '/^ {2}/', '', $rows[$i] );
            }
            $fields[$key] = rtrim( implode( $value[0] === '>' ? ' ' : "\n", $parts ) );
        } elseif ( $value === '' && in_array( $key, array('categories','tags'), true ) ) {
            $parts = array();
            while ( $i + 1 < $count && preg_match( '/^\s{2,}-\s+(.+)$/', $rows[$i+1], $lm ) ) {
                $parts[] = wbsmi_value( $lm[1] ); $i++;
            }
            $fields[$key] = $parts;
        } else {
            $fields[$key] = wbsmi_value( $value );
        }
    }
    return array( $fields, $errors );
}
function wbsmi_terms( $val ) {
    $items = is_array( $val ) ? $val : preg_split( '/\s*,\s*/u', (string) $val );
    $out = array();
    foreach ( $items as $item ) {
        $item = trim( (string) $item );
        if ( $item !== '' && ! in_array( $item, $out, true ) ) { $out[] = $item; }
    }
    return $out;
}
function wbsmi_parse_datetime( $val, $timezone ) {
    if ( ! preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d$/D', $val ) ) { return false; }
    $date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $val, $timezone );
    if ( ! $date || $date->format( 'Y-m-d H:i' ) !== $val ) { return false; }
    return $date;
}
/** Returns valid parsed posts plus per-item errors. Never executes Markdown content. */
function wbsmi_parse_batch( $text, $timezone = null, $now = null ) {
    if ( ! is_string( $text ) || $text === '' ) { return array('posts'=>array(), 'errors'=>array('Файл пуст.'), 'count'=>0); }
    if ( substr( $text, 0, 3 ) === "\xef\xbb\xbf" ) { $text = substr( $text, 3 ); }
    if ( strpos( $text, "\0" ) !== false || ! preg_match( '//u', $text ) ) { return array('posts'=>array(), 'errors'=>array('Некорректная кодировка: требуется UTF-8.'), 'count'=>0); }
    $text = str_replace( array("\r\n", "\r"), "\n", $text );
    $timezone = $timezone ?: wp_timezone();
    $now = $now ?: new DateTimeImmutable('now', $timezone);
    $chunks = preg_split( '/^<!-- WPSEO:POST -->\s*$/m', $text );
    if ( count( $chunks ) < 2 || trim( $chunks[0] ) !== '' ) {
        return array('posts'=>array(), 'errors'=>array('Каждая запись должна начинаться с <!-- WPSEO:POST --> на отдельной строке.'), 'count'=>0);
    }
    array_shift( $chunks );
    if ( count( $chunks ) > 300 ) { return array('posts'=>array(), 'errors'=>array('Максимум 300 записей на один файл.'), 'count'=>count($chunks)); }
    $posts = array(); $all_errors = array(); $seen = array();
    foreach ( $chunks as $index => $chunk ) {
        $errors = array(); $warnings = array();
        $chunk = ltrim($chunk, "\n");
        if ( ! preg_match( '/\A---\n([\s\S]*?)\n---\s*\n([\s\S]*)\z/', $chunk, $m ) ) {
            $posts[] = array('fields'=>array(), 'body'=>'', 'errors'=>array('Не найден корректный front matter между --- и ---.'), 'warnings'=>array(), 'action'=>'invalid'); continue;
        }
        list($fields,$parse_errors) = wbsmi_parse_frontmatter($m[1]);
        $errors = array_merge($errors,$parse_errors);
        $body = trim($m[2]);
        foreach ( array('title','slug','yoast_title','yoast_meta_description','yoast_focus_keyphrase') as $req ) {
            if ( ! isset($fields[$req]) || ! is_string($fields[$req]) || trim($fields[$req]) === '' ) { $errors[] = 'Обязательное поле отсутствует: ' . $req; }
        }
        if ( $body === '' ) { $errors[] = 'Тело статьи пустое.'; }
        $title = isset($fields['title']) && is_string($fields['title']) ? trim($fields['title']) : '';
        if ( (function_exists('mb_strlen')?mb_strlen($title, 'UTF-8'):strlen($title)) > 190 ) { $errors[] = 'Title превышает 190 символов.'; }
        $slug = isset($fields['slug']) && is_string($fields['slug']) ? trim($fields['slug']) : '';
        if ( $slug !== '' && ( $slug !== sanitize_title($slug) || ! preg_match('/^[a-z0-9-]+$/D', $slug) ) ) { $errors[] = 'Slug: используйте латиницу, цифры и дефис.'; }
        if ( $slug !== '' ) {
            if ( isset($seen[$slug]) ) { $errors[] = 'Повтор slug в файле (первая запись №' . $seen[$slug] . ').'; }
            $seen[$slug] = $index + 1;
        }
        foreach ( array('yoast_title','yoast_meta_description','yoast_focus_keyphrase','excerpt','yoast_canonical') as $field ) {
            if ( isset($fields[$field]) && ! is_string($fields[$field]) ) { $errors[] = 'Поле должно быть строкой: ' . $field; }
        }
        if ( isset($fields['yoast_meta_description']) && is_string($fields['yoast_meta_description']) && (function_exists('mb_strlen')?mb_strlen($fields['yoast_meta_description'],'UTF-8'):strlen($fields['yoast_meta_description'])) > 190 ) { $warnings[]='Meta Description длиннее 190 символов.'; }
        $state = isset($fields['status']) ? strtolower(trim((string)$fields['status'])) : '';
        $raw_date = isset($fields['publish_at']) ? trim((string)$fields['publish_at']) : '';
        $action = 'invalid'; $scheduled = null;
        if ( $state !== '' && ! in_array($state,array('draft','publish'),true) ) { $errors[]='status допустим только draft или publish.'; }
        if ($raw_date !== '') {
            if ($state === 'draft') { $errors[]='Нельзя одновременно указать status: draft и publish_at.'; }
            $scheduled = wbsmi_parse_datetime($raw_date,$timezone);
            if ( !$scheduled ) { $errors[]='publish_at: нужен формат YYYY-MM-DD HH:MM (в часовом поясе WordPress).'; }
            elseif ($scheduled->getTimestamp() < $now->getTimestamp() + 60) { $errors[]='publish_at должен быть в будущем минимум на 1 минуту.'; }
            else { $action = 'future'; }
        } elseif ($state === 'draft') { $action = 'draft'; }
        elseif ($state === 'publish') { $action = 'publish'; }
        else { $errors[]='Укажите status: draft, status: publish или будущий publish_at.'; }
        if ( preg_match('/^#\s+\S/m', $body) ) { $warnings[]='В теле обнаружен H1. Для записи WordPress рекомендуется начинать с H2.'; }
        if (isset($fields['featured_image_id']) && ($fields['featured_image_id'] !== '' && (!ctype_digit((string)$fields['featured_image_id']) || (int)$fields['featured_image_id']<=0))) {$errors[]='featured_image_id должен быть положительным ID.';}
        foreach (array('yoast_canonical','yoast_opengraph_image','yoast_twitter_image') as $urlkey) {
            if (!empty($fields[$urlkey]) && (!is_string($fields[$urlkey]) || !preg_match('~^https?://~i',$fields[$urlkey]) || !preg_match('~^https?://[^\s/]+(?:/[^\s]*)?$~u',$fields[$urlkey]))) {$errors[]=$urlkey.': нужен абсолютный http(s) URL.';}
        }
        foreach (array('yoast_robots_index'=>array('default','index','noindex'),'yoast_robots_follow'=>array('default','follow','nofollow'),'yoast_cornerstone'=>array('true','false')) as $key=>$options) {
            if (isset($fields[$key]) && !in_array(strtolower((string)$fields[$key]),$options,true)) {$errors[]=$key.': недопустимое значение.';}
        }
        if (isset($fields['yoast_robots_advanced'])) {
            $allowed_adv=array('none','noarchive','noimageindex','nosnippet');
            foreach (wbsmi_terms($fields['yoast_robots_advanced']) as $adv) {if(!in_array($adv,$allowed_adv,true)){$errors[]='Недопустимое yoast_robots_advanced: '.$adv;}}
        }
        $posts[] = array('fields'=>$fields,'body'=>$body,'errors'=>array_values(array_unique($errors)),'warnings'=>$warnings,'action'=>$action, 'scheduled'=>$scheduled?$scheduled->format('Y-m-d H:i:s'):null);
    }
    foreach($posts as $idx=>$p) {foreach($p['errors'] as $e){$all_errors[]='Запись №'.($idx+1).': '.$e;}}
    return array('posts'=>$posts,'errors'=>$all_errors,'count'=>count($posts));
}
