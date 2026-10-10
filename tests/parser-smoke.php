<?php
// Run: php tests/parser-smoke.php
error_reporting(E_ALL);
define('ABSPATH','/');
function sanitize_title($s){return strtolower(preg_replace('/[^a-zA-Z0-9-]+/','-',trim($s)));}
function wp_timezone(){return new DateTimeZone('Europe/Moscow');}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function wp_kses_post($s){return strip_tags($s,'<h1><h2><h3><h4><h5><h6><p><a><em><strong><ul><ol><li><pre><code><hr><img><figure><blockquote>');}
require __DIR__.'/../includes/parser.php';
require __DIR__.'/../includes/markdown.php';
$txt=file_get_contents(__DIR__.'/../examples/example-batch.md');
$batch=wbsmi_parse_batch($txt,new DateTimeZone('Europe/Moscow'),new DateTimeImmutable('2026-10-10 09:00',new DateTimeZone('Europe/Moscow')));
function must($test,$reason){if(!$test){fwrite(STDERR,"FAILED: $reason\n");exit(1);}}
must($batch['count']===3,'count');
must(!$batch['errors'],'no parser errors '.implode(' | ',$batch['errors']));
must(array_column($batch['posts'],'action')===array('draft','future','publish'),'actions');
must($batch['posts'][1]['scheduled']==='2030-01-15 10:30:00','future time');
must(count($batch['posts'][0]['fields']['categories'])===2,'categories');
$bad=str_replace('2030-01-15 10:30','2020-01-15 10:30',$txt);
$badbatch=wbsmi_parse_batch($bad);must(count($badbatch['errors'])>=1,'past scheduled date rejected');
$missing=str_replace('status: draft','status: draft'."\n".'publish_at: 2030-10-10 10:30',$txt);
$mis=wbsmi_parse_batch($missing);must(count($mis['errors'])>=1,'draft with date rejected');
$html=wbsmi_markdown_html("## Заголовок\n\n- Один\n- Два\n\n[Ссылка](https://example.org/)\n\n<script>alert('x')</script>");
must(strpos($html,'<h2>')!==false,'heading rendered');
must(strpos($html,'<ul>')!==false,'list rendered');
must(strpos($html,'<script>')===false,'raw html escaped');
echo "OK: examples, 3 posts, statuses, invalid dates, safe markdown\n";
