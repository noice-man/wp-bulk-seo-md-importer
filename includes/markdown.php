<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Deliberately limited, dependency-free Markdown->HTML for WordPress posts. Raw HTML is escaped. */
function wbsmi_inline( $input ) {
    $text = htmlspecialchars( (string) $input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    // Protect inline code before applying other styling.
    $codes = array();
    $text = preg_replace_callback('/`([^`\n]+)`/',function($m) use (&$codes){
        $key='WBSMICODETOKEN'.count($codes).'END'; $codes[$key]='<code>'.$m[1].'</code>'; return $key;
    },$text);
    // URL is already HTML escaped; restrict scheme and escape attribute again.
    $text = preg_replace_callback('/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+|\/[a-zA-Z0-9_\/#?&=%+.,-]*)\)/u',function($m){
        $href=html_entity_decode($m[2],ENT_QUOTES | ENT_HTML5,'UTF-8');
        return '<a href="'.esc_url($href).'">'.$m[1].'</a>';
    },$text);
    $text = preg_replace('/\*\*(.+?)\*\*/us','<strong>$1</strong>',$text);
    $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u','<em>$1</em>',$text);
    $text = strtr($text,$codes);
    return $text;
}
function wbsmi_markdown_html( $markdown ) {
    $lines=preg_split('/\r\n|\r|\n/',(string)$markdown);
    $out=array(); $paragraph=array(); $list=''; $quote=array(); $code=false; $codebuf=array(); $code_lang='';
    $flush_p=function() use (&$paragraph,&$out){if($paragraph){$out[]='<p>'.wbsmi_inline(implode(' ',$paragraph)).'</p>';$paragraph=array();}};
    $flush_list=function() use (&$list,&$out){if($list!==''){$out[]='</'.$list.'>';$list='';}};
    $flush_quote=function() use (&$quote,&$out){if($quote){$out[]='<blockquote><p>'.wbsmi_inline(implode(' ',$quote)).'</p></blockquote>';$quote=array();}};
    foreach($lines as $line){
        $trim=trim($line);
        if($code){
            if(preg_match('/^\s*```\s*$/',$line)){
                $out[]='<pre><code'.($code_lang!==''?' class="language-'.esc_attr($code_lang).'"':'').'>'.htmlspecialchars(implode("\n",$codebuf),ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8').'</code></pre>';
                $code=false;$codebuf=array();$code_lang='';
            }else{$codebuf[]=$line;} continue;
        }
        if(preg_match('/^\s*```([a-zA-Z0-9_-]*)\s*$/',$line,$m)){
            $flush_p();$flush_quote();$flush_list();$code=true;$code_lang=$m[1];continue;
        }
        if($trim===''){$flush_p();$flush_quote();$flush_list();continue;}
        if(preg_match('/^\s{0,3}(#{1,6})\s+(.+?)\s*$/u',$line,$m)){
            $flush_p();$flush_quote();$flush_list();$n=strlen($m[1]);$out[]='<h'.$n.'>'.wbsmi_inline($m[2]).'</h'.$n.'>';continue;
        }
        if(preg_match('/^\s{0,3}(?:---+|\*\*\*+)\s*$/',$line)){
            $flush_p();$flush_quote();$flush_list();$out[]='<hr />';continue;
        }
        if(preg_match('/^\s*>\s?(.*)$/u',$line,$m)){
            $flush_p();$flush_list();$quote[]=$m[1];continue;
        }
        if(preg_match('/^\s{0,3}(?:[-*+]\s+)(.+)$/u',$line,$m)){
            $flush_p();$flush_quote();if($list!=='ul'){$flush_list();$out[]='<ul>';$list='ul';}
            $out[]='<li>'.wbsmi_inline($m[1]).'</li>';continue;
        }
        if(preg_match('/^\s{0,3}(?:\d+[.)]\s+)(.+)$/u',$line,$m)){
            $flush_p();$flush_quote();if($list!=='ol'){$flush_list();$out[]='<ol>';$list='ol';}
            $out[]='<li>'.wbsmi_inline($m[1]).'</li>';continue;
        }
        // Images with HTTPS URLs; no remote downloading.
        if(preg_match('/^!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)$/u',$trim,$m)){
            $flush_p();$flush_quote();$flush_list();$out[]='<figure><img src="'.esc_url($m[2]).'" alt="'.esc_attr($m[1]).'" loading="lazy" /></figure>';continue;
        }
        $flush_quote();$flush_list();$paragraph[]=$trim;
    }
    $flush_p();$flush_quote();$flush_list();
    if($code){$out[]='<pre><code>'.htmlspecialchars(implode("\n",$codebuf),ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8').'</code></pre>';}
    return wp_kses_post(implode("\n",$out));
}
