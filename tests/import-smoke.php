<?php
// Run: php tests/import-smoke.php
error_reporting(E_ALL); define('ABSPATH','/'); define('OBJECT','OBJECT');
function wp_timezone(){return new DateTimeZone('Europe/Moscow');}
function sanitize_title($s){return strtolower(preg_replace('/[^a-zA-Z0-9-]+/','-',trim($s)));}
function esc_url($s){return $s;}
function esc_attr($s){return $s;}
function wp_kses_post($s){return strip_tags($s,'<h2><p><ul><li><a>');}
function current_user_can($cap){return true;}
function get_current_user_id(){return 99;}
function get_page_by_path($slug,$return,$type){return false;}
function sanitize_text_field($s){return $s;}
function sanitize_textarea_field($s){return $s;}
function wp_slash($x){return $x;}
function is_wp_error($x){return false;}
function esc_url_raw($s){return $s;}
function term_exists($name,$taxonomy){return false;}
function wp_insert_term($name,$taxonomy){return array('term_id'=>strtolower($taxonomy)==='category'?13:14);}
function wp_set_object_terms($id,$ids,$taxonomy,$append){return $ids;}
function get_post_type($id){return 'attachment';}
function wp_attachment_is_image($id){return true;}
function set_post_thumbnail($id,$attachment){return true;}
$events=array();$metas=array();$statuses=array();$idSeq=100;
function wp_insert_post($args,$error){global $events,$statuses,$idSeq;$id=++$idSeq;$events[]='insert:'.$args['post_status'];$statuses[$id]=$args['post_status'];return $id;}
function update_post_meta($id,$k,$v){global $events,$metas;$metas[$id][$k]=$v;$events[]='meta:'.$k;return true;}
function wp_update_post($args,$error){global $events,$metas,$statuses;$id=$args['ID'];if(empty($metas[$id]['_yoast_wpseo_focuskw'])){throw new Exception('Published before meta fields!');}$events[]='update:'.$args['post_status'];$statuses[$id]=$args['post_status'];return $id;}
function get_post_status($id){global $statuses;return $statuses[$id];}
function get_edit_post_link($id,$context){return '/wp-admin/post.php?post='.$id;}
function get_gmt_from_date($d){return $d;}
require __DIR__.'/../includes/parser.php';require __DIR__.'/../includes/markdown.php';require __DIR__.'/../includes/import.php';
$batch=wbsmi_parse_batch(file_get_contents(__DIR__.'/../examples/example-batch.md'),new DateTimeZone('Europe/Moscow'),new DateTimeImmutable('2026-10-10 09:00',new DateTimeZone('Europe/Moscow')));
$report=wbsmi_do_import($batch);
if(count($report['created'])!==3 || count($report['errors'])!==0){throw new Exception('Wrong import result '.json_encode($report));}
if(array_column($report['created'],'status')!==array('draft','future','publish')){throw new Exception('Wrong statuses');}
if($metas[101]['_yoast_wpseo_focuskw']!=='оптимизация изображений wordpress'){throw new Exception('Yoast focus missing');}
if($metas[101]['_yoast_wpseo_is_cornerstone']!=='false'){throw new Exception('Yoast cornerstone invalid');}
echo "OK: draft/future/publish, SEO data before publication, taxonomy and Yoast meta\n";
