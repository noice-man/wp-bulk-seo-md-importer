<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
function wbsmi_existing_post( $slug ) {
    if ( ! $slug ) { return 0; }
    $post=get_page_by_path($slug,OBJECT,'post');
    return $post ? (int)$post->ID : 0;
}
function wbsmi_tax_terms( $post_id, $taxonomy, $terms ) {
    $ids=array();
    foreach($terms as $name){
        $term=term_exists($name,$taxonomy);
        if(!$term){$term=wp_insert_term($name,$taxonomy);}
        if(is_wp_error($term)){return $term;}
        $ids[]=is_array($term)?(int)$term['term_id']:(int)$term;
    }
    if($ids){$res=wp_set_object_terms($post_id,$ids,$taxonomy,false);if(is_wp_error($res)){return $res;}}
    return $ids;
}
function wbsmi_save_yoast( $id, $f, $cat_ids ) {
    $map=array(
        'yoast_title'=>'_yoast_wpseo_title',
        'yoast_meta_description'=>'_yoast_wpseo_metadesc',
        'yoast_focus_keyphrase'=>'_yoast_wpseo_focuskw',
        'yoast_canonical'=>'_yoast_wpseo_canonical',
        'yoast_breadcrumb_title'=>'_yoast_wpseo_bctitle',
        'yoast_opengraph_title'=>'_yoast_wpseo_opengraph-title',
        'yoast_opengraph_description'=>'_yoast_wpseo_opengraph-description',
        'yoast_opengraph_image'=>'_yoast_wpseo_opengraph-image',
        'yoast_twitter_title'=>'_yoast_wpseo_twitter-title',
        'yoast_twitter_description'=>'_yoast_wpseo_twitter-description',
        'yoast_twitter_image'=>'_yoast_wpseo_twitter-image',
        'yoast_schema_page_type'=>'_yoast_wpseo_schema_page_type',
        'yoast_schema_article_type'=>'_yoast_wpseo_schema_article_type',
        'yoast_robots_advanced'=>'_yoast_wpseo_meta-robots-adv'
    );
    foreach($map as $src=>$dest){
        if(!isset($f[$src]) || $f[$src]===''){continue;}
        $value=(string)$f[$src];
        if($src==='yoast_robots_advanced' && $value==='none'){$value='';}
        if(strpos($src,'_image')!==false || $src==='yoast_canonical'){$value=esc_url_raw($value);}
        else {$value=sanitize_textarea_field($value);}
        update_post_meta($id,$dest,wp_slash($value));
    }
    if(isset($f['yoast_cornerstone'])){update_post_meta($id,'_yoast_wpseo_is_cornerstone',$f['yoast_cornerstone']==='true'?'true':'false');}
    if(isset($f['yoast_robots_index']) && $f['yoast_robots_index']!=='default'){
        update_post_meta($id,'_yoast_wpseo_meta-robots-noindex',$f['yoast_robots_index']==='noindex'?'1':'2');
    }
    if(isset($f['yoast_robots_follow']) && $f['yoast_robots_follow']!=='default'){
        update_post_meta($id,'_yoast_wpseo_meta-robots-nofollow',$f['yoast_robots_follow']==='nofollow'?'1':'0');
    }
    if(!empty($f['yoast_primary_category'])){
        $name=trim($f['yoast_primary_category']);$term=term_exists($name,'category');
        $term_id=$term?(is_array($term)?(int)$term['term_id']:(int)$term):0;
        if($term_id && in_array($term_id,$cat_ids,true)){update_post_meta($id,'_yoast_wpseo_primary_category',(string)$term_id);}
    }
}
function wbsmi_do_import( $batch, $start_index = 0 ) {
    $results=array();
    if(!empty($batch['errors'])){return array('errors'=>array('Нельзя импортировать файл с ошибками валидации.'),'created'=>array(),'skipped'=>array());}
    if(!current_user_can('publish_posts')){return array('errors'=>array('Недостаточно прав на публикацию записей.'),'created'=>array(),'skipped'=>array());}
    $report=array('errors'=>array(),'created'=>array(),'skipped'=>array());
    foreach($batch['posts'] as $n=>$p){
        $f=$p['fields'];$slug=$f['slug'];
        if(wbsmi_existing_post($slug)){$report['skipped'][]='№'.($n+$start_index+1).' «'.$f['title'].'»: slug уже существует.';continue;}
        $args=array(
            'post_type'=>'post','post_title'=>sanitize_text_field($f['title']),
            'post_name'=>sanitize_title($slug),
            'post_content'=>wbsmi_markdown_html($p['body']),
            'post_excerpt'=>isset($f['excerpt'])?sanitize_textarea_field($f['excerpt']):'',
            'post_author'=>get_current_user_id(),
            'post_status'=>'draft' // Populate SEO metadata and taxonomy before publication.
        );
        $id=wp_insert_post(wp_slash($args),true);
        if(is_wp_error($id) || !$id){$report['errors'][]='№'.($n+$start_index+1).': '.(is_wp_error($id)?$id->get_error_message():'не удалось создать запись');continue;}
        update_post_meta($id,'_wbsmi_import_id',isset($f['id'])?sanitize_text_field($f['id']):$slug);
        $cat_ids=array();$record_has_errors=false;
        if(!empty($f['categories'])){
            $res=wbsmi_tax_terms($id,'category',wbsmi_terms($f['categories']));
            if(is_wp_error($res)){$report['errors'][]='№'.($n+$start_index+1).': категории: '.$res->get_error_message();$record_has_errors=true;}else{$cat_ids=$res;}
        }
        if(!empty($f['tags'])){$res=wbsmi_tax_terms($id,'post_tag',wbsmi_terms($f['tags']));if(is_wp_error($res)){$report['errors'][]='№'.($n+$start_index+1).': метки: '.$res->get_error_message();$record_has_errors=true;}}
        if(!empty($f['featured_image_id'])){
            $aid=(int)$f['featured_image_id'];
            if(get_post_type($aid)==='attachment' && wp_attachment_is_image($aid)){set_post_thumbnail($id,$aid);}else{$report['errors'][]='№'.($n+$start_index+1).': изображение с ID '.$aid.' не найдено.';$record_has_errors=true;}
        }
        wbsmi_save_yoast($id,$f,$cat_ids);
        if(!$record_has_errors && $p['action']!=='draft'){
            $update=array('ID'=>$id,'post_status'=>$p['action']==='future'?'future':'publish');
            if($p['action']==='future'){
                $update['post_date']=$p['scheduled'];
                $update['post_date_gmt']=get_gmt_from_date($p['scheduled']);
            }
            $updated=wp_update_post(wp_slash($update),true);
            if(is_wp_error($updated) || !$updated){
                $report['errors'][]='№'.($n+$start_index+1).': создан черновик, но публикация не удалась: '.(is_wp_error($updated)?$updated->get_error_message():'ошибка WordPress');
            }
        }
        if($record_has_errors){$report['errors'][]='№'.($n+$start_index+1).': запись оставлена черновиком из-за ошибок полей.';}
        $report['created'][]=array('id'=>$id,'title'=>$f['title'],'status'=>get_post_status($id),'url'=>get_edit_post_link($id,'raw'));
    }
    return $report;
}
