<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
function wbsmi_admin_menu() {
    add_menu_page('Массовый SEO-импорт MD','SEO MD Импорт','manage_options','wbsmi-import','wbsmi_admin_page','dashicons-media-document',82);
}
function wbsmi_session_key() { return 'wbsmi_session_' . get_current_user_id(); }
function wbsmi_notice_key() { return 'wbsmi_notice_' . get_current_user_id(); }
function wbsmi_session( $token ) {
    $session=get_transient(wbsmi_session_key());
    if(!is_array($session) || !isset($session['token'],$session['path'],$session['sha']) || !is_string($token) || !hash_equals($session['token'],$token)){return false;}
    if(!is_file($session['path']) || !is_readable($session['path']) || filesize($session['path'])>10*1024*1024 || hash_file('sha256',$session['path'])!==$session['sha']){return false;}
    return $session;
}
function wbsmi_batch_from_session( $session ) {
    $text=file_get_contents($session['path']);
    if($text===false){return array('posts'=>array(),'errors'=>array('Не удалось прочитать загруженный файл.'),'count'=>0);}
    $batch=wbsmi_parse_batch($text);
    foreach($batch['posts'] as $idx=>&$p){
        $aid=$p['fields']['featured_image_id']??'';
        if($aid!=='' && ctype_digit((string)$aid) && (!get_post_type((int)$aid) || get_post_type((int)$aid)!=='attachment' || !wp_attachment_is_image((int)$aid))){
            $message='featured_image_id '.(int)$aid.' не является изображением из медиатеки.';
            $p['errors'][]=$message;
            $batch['errors'][]='Запись №'.($idx+1).': '.$message;
        }
    }
    unset($p);
    return $batch;
}
function wbsmi_redirect( $token = '' ) {
    $url=admin_url('admin.php?page=wbsmi-import');
    if($token!==''){$url=add_query_arg('batch',$token,$url);}
    wp_safe_redirect($url);exit;
}
function wbsmi_handle_post() {
    if(!current_user_can('manage_options') || !current_user_can('publish_posts')){wp_die('Недостаточно прав.');}
    $action=isset($_POST['wbsmi_action'])?sanitize_key(wp_unslash($_POST['wbsmi_action'])):'';
    if($action==='upload'){
        check_admin_referer('wbsmi_upload');
        $errors=array();
        if(!isset($_FILES['wbsmi_file']) || !is_array($_FILES['wbsmi_file'])){$errors[]='Файл не передан.';}
        else{
            $file=$_FILES['wbsmi_file'];
            if(isset($file['error']) && $file['error']!==UPLOAD_ERR_OK){$errors[]='Ошибка загрузки (код '.$file['error'].').';}
            if(!isset($file['size']) || $file['size']<=0 || $file['size']>10*1024*1024){$errors[]='Файл должен быть от 1 байта до 10 МБ.';}
            if(!isset($file['name']) || !preg_match('/\.(md|markdown|txt)$/i',(string)$file['name'])){$errors[]='Допустимы только .md, .markdown и .txt.';}
            if(!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])){$errors[]='Некорректный загруженный файл.';}
        }
        if(!$errors){
            $old=get_transient(wbsmi_session_key());
            if(is_array($old) && !empty($old['path']) && is_file($old['path'])){@unlink($old['path']);}
            $tmpdir=realpath(get_temp_dir());
            $root=realpath(ABSPATH);
            $docroot=!empty($_SERVER['DOCUMENT_ROOT'])?realpath($_SERVER['DOCUMENT_ROOT']):false;
            $is_public=false;
            foreach(array($root,$docroot) as $web){
                if($web && $tmpdir && ($tmpdir===$web || strpos($tmpdir,$web.DIRECTORY_SEPARATOR)===0)){$is_public=true;}
            }
            if(!$tmpdir || $is_public || !is_writable($tmpdir)){
                $errors[]='Не найдена безопасная временная папка вне публичного корня сайта. Настройте системный PHP tmp_dir.';
                $path=false;
            } else {
                $path=$tmpdir.DIRECTORY_SEPARATOR.'wbsmi-'.bin2hex(random_bytes(16));
            }
            if($path && !move_uploaded_file($file['tmp_name'],$path)){$errors[]='Не удалось сохранить временный файл на сервере.';}
            if($path && !$errors){
                @chmod($path,0600);
                // Structural errors will be shown on the validation screen.
                $token=bin2hex(random_bytes(16));
                set_transient(wbsmi_session_key(),array('token'=>$token,'path'=>$path,'sha'=>hash_file('sha256',$path),'filename'=>sanitize_file_name($file['name'])),45*MINUTE_IN_SECONDS);
                wbsmi_redirect($token);
            }
        }
        set_transient(wbsmi_notice_key(),array('errors'=>$errors),3*MINUTE_IN_SECONDS);
        wbsmi_redirect();
    }
    if($action==='import'){
        $token=isset($_POST['wbsmi_token'])?sanitize_text_field(wp_unslash($_POST['wbsmi_token'])):'';
        check_admin_referer('wbsmi_import_'.$token);
        $session=wbsmi_session($token);
        if(!$session){wp_die('Временный файл недоступен или изменён. Загрузите его ещё раз.');}
        if(empty($_POST['wbsmi_confirm']) || $_POST['wbsmi_confirm']!=='1'){
            set_transient(wbsmi_notice_key(),array('errors'=>array('Подтвердите выполнение публикаций и создание черновиков.')),3*MINUTE_IN_SECONDS);
            wbsmi_redirect($token);
        }
        $batch=wbsmi_batch_from_session($session);
        $result=wbsmi_do_import($batch);
        set_transient(wbsmi_notice_key(),$result,20*MINUTE_IN_SECONDS);
        @unlink($session['path']);delete_transient(wbsmi_session_key());
        wbsmi_redirect();
    }
    wp_die('Неизвестная операция.');
}
function wbsmi_ajax_chunk_import() {
    if(!current_user_can('manage_options') || !current_user_can('publish_posts')){
        wp_send_json_error(array('message'=>'Недостаточно прав.'),403);
    }
    $token=isset($_POST['token'])?sanitize_text_field(wp_unslash($_POST['token'])):'';
    check_ajax_referer('wbsmi_import_'.$token,'nonce');
    $session=wbsmi_session($token);
    if(!$session){wp_send_json_error(array('message'=>'Временный файл больше недоступен. Загрузите Markdown ещё раз.'),410);}
    $batch=wbsmi_batch_from_session($session);
    if($batch['errors']){wp_send_json_error(array('message'=>'Найдены ошибки в файле. Импорт отменён.'),422);}
    $offset=isset($_POST['offset'])?(int)$_POST['offset']:-1;
    $count=count($batch['posts']);
    if($offset<0 || $offset>$count || $offset%10!==0){wp_send_json_error(array('message'=>'Некорректная позиция импорта.'),400);}
    $slice=array_slice($batch['posts'],$offset,10);
    $result=wbsmi_do_import(array('posts'=>$slice,'errors'=>array()),$offset);
    $next=$offset+count($slice);
    $done=$next >= $count;
    if($done){@unlink($session['path']);delete_transient(wbsmi_session_key());}
    wp_send_json_success(array('next'=>$next,'total'=>$count,'done'=>$done,'report'=>$result));
}
function wbsmi_admin_page() {
    if(!current_user_can('manage_options')){wp_die('Недостаточно прав.');}
    if($_SERVER['REQUEST_METHOD']==='POST'){wbsmi_handle_post();}
    $notice=get_transient(wbsmi_notice_key());
    if($notice){delete_transient(wbsmi_notice_key());}
    $token=isset($_GET['batch'])?sanitize_text_field(wp_unslash($_GET['batch'])):'';
    $session=$token?wbsmi_session($token):false;
    $batch=$session?wbsmi_batch_from_session($session):null;
    $idx=isset($_GET['item'])?max(0,(int)$_GET['item']):0;
    $base=admin_url('admin.php?page=wbsmi-import');
    $yoast_active=defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
    ?>
    <div class="wrap wbsmi-page">
        <h1>Массовый SEO-импорт из Markdown</h1>
        <p>Один файл — несколько записей WordPress, поля Yoast SEO, черновики, немедленная и отложенная публикация. <strong>Статус и дата задаются только внутри Markdown.</strong></p>
        <style>
        .wbsmi-page{max-width:1280px}.wbsmi-panel{background:#fff;border:1px solid #dcdcde;border-radius:8px;margin:16px 0;padding:20px}
        .wbsmi-stats{display:flex;gap:12px;flex-wrap:wrap}.wbsmi-stats div{padding:12px 20px;background:#f6f7f7;border-radius:7px}
        .wbsmi-stats strong{display:block;font-size:24px}.wbsmi-meta{width:100%;border-collapse:collapse}
        .wbsmi-meta th,.wbsmi-meta td{padding:8px 11px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
        .wbsmi-meta th{width:245px;background:#fafafa}.wbsmi-meta td{overflow-wrap:anywhere;white-space:pre-wrap}
        .wbsmi-body{padding:18px;background:#fff;border:1px solid #e0e0e0;max-height:700px;overflow:auto}
        .wbsmi-navigation{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.wbsmi-errors{color:#a00}
        .wbsmi-list{width:100%;border-collapse:collapse}.wbsmi-list td,.wbsmi-list th{padding:7px 10px;text-align:left;border-bottom:1px solid #ddd}
        .wbsmi-list tr:nth-child(odd){background:#fafafa}.wbsmi-muted{color:#646970}
        </style>
        <?php if($notice): ?>
        <?php if(!empty($notice['errors'])): ?><div class="notice notice-error"><p><strong>Ошибки:</strong></p><ul><?php foreach($notice['errors'] as $e): ?><li><?php echo esc_html($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if(!empty($notice['created']) || !empty($notice['skipped'])): ?><div class="notice notice-success"><p>Создано: <?php echo count($notice['created']??array()); ?>; пропущено: <?php echo count($notice['skipped']??array()); ?>.</p>
        <ul><?php foreach($notice['created']??array() as $p): ?><li><a href="<?php echo esc_url($p['url']); ?>"><?php echo esc_html($p['title']); ?></a> — <?php echo esc_html($p['status']); ?></li><?php endforeach; ?>
        <?php foreach($notice['skipped']??array() as $msg): ?><li><?php echo esc_html($msg); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php endif; ?>
        <div class="wbsmi-panel">
            <h2>1. Загрузить Markdown</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('wbsmi_upload'); ?><input type="hidden" name="wbsmi_action" value="upload" />
                <input type="file" name="wbsmi_file" accept=".md,.markdown,.txt,text/markdown,text/plain" required />
                <?php submit_button('Проверить и показать предпросмотр','primary','submit',false); ?>
            </form>
            <p class="description">UTF-8, до 10 МБ, до 300 записей. Разделитель: <code>&lt;!-- WPSEO:POST --&gt;</code>. Формат описан в <code>examples/example-batch.md</code> внутри плагина.</p>
            <?php if(!$yoast_active): ?><p class="notice notice-warning inline" style="padding:10px">Yoast SEO сейчас не обнаружен: метаполя будут сохранены, но для их использования потребуется активировать Yoast SEO.</p><?php endif; ?>
        </div>
        <?php if($token && !$session): ?><div class="notice notice-error"><p>Предпросмотр устарел (45 минут) либо файл изменился. Загрузите его заново.</p></div><?php endif; ?>
        <?php if($batch && $batch['count']):
            $cnt=$batch['count'];$idx=min($idx,$cnt-1);$bad=0;$future=0;$draft=0;$publish=0;$skipped=0;
            foreach($batch['posts'] as $p){if($p['errors']){$bad++;}elseif($p['action']==='future'){$future++;}elseif($p['action']==='draft'){$draft++;}elseif($p['action']==='publish'){$publish++;}}
            ?>
            <div class="wbsmi-panel">
                <h2>2. Результат валидации: <?php echo esc_html($session['filename']); ?></h2>
                <div class="wbsmi-stats"><div><strong><?php echo $cnt; ?></strong>Найдено</div><div><strong><?php echo $bad; ?></strong>С ошибками</div><div><strong><?php echo $draft; ?></strong>Черновиков</div><div><strong><?php echo $publish; ?></strong>Сразу</div><div><strong><?php echo $future; ?></strong>Запланировано</div></div>
                <p class="description">Часовой пояс WordPress: <strong><?php echo esc_html(wp_timezone_string()); ?></strong>. Существующие записи с тем же slug не обновляются.</p>
                <?php if($batch['errors']): ?><div class="notice notice-error inline"><p><strong>Импорт заблокирован, пока не исправлены все ошибки:</strong></p><ul><?php foreach($batch['errors'] as $e): ?><li><?php echo esc_html($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <table class="wbsmi-list"><thead><tr><th>№</th><th>Заголовок</th><th>Действие</th><th>Ошибки</th></tr></thead><tbody>
                <?php foreach($batch['posts'] as $i=>$p):$f=$p['fields'];$existing=!empty($f['slug'])?wbsmi_existing_post($f['slug']):0; ?>
                <tr><td><a href="<?php echo esc_url(add_query_arg(array('batch'=>$token,'item'=>$i),$base)); ?>"><?php echo $i+1; ?></a></td>
                <td><?php echo esc_html($f['title']??'—'); ?></td><td><?php echo $p['errors']?'Ошибка':($existing?'Пропуск (slug существует)':wbsmi_action_label($p)); ?></td><td><?php echo esc_html(implode('; ',$p['errors'])); ?></td></tr>
                <?php endforeach; ?></tbody></table>
            </div>
            <?php $p=$batch['posts'][$idx];$f=$p['fields']; ?>
            <div class="wbsmi-panel">
                <div class="wbsmi-navigation">
                    <?php if($idx>0): ?><a class="button" href="<?php echo esc_url(add_query_arg(array('batch'=>$token,'item'=>$idx-1),$base)); ?>">← Предыдущая</a><?php endif; ?>
                    <strong>Запись <?php echo $idx+1; ?> из <?php echo $cnt; ?></strong>
                    <?php if($idx+1<$cnt): ?><a class="button" href="<?php echo esc_url(add_query_arg(array('batch'=>$token,'item'=>$idx+1),$base)); ?>">Следующая →</a><?php endif; ?>
                </div>
                <h2><?php echo esc_html($f['title']??'Запись с ошибкой'); ?></h2>
                <p><strong>Действие:</strong> <?php echo esc_html(wbsmi_action_label($p)); ?> <?php if(!empty($f['slug']) && wbsmi_existing_post($f['slug'])):?><span style="color:#a00">— slug уже существует, будет пропущена</span><?php endif; ?></p>
                <?php foreach($p['warnings'] as $warn): ?><p style="color:#8a5a00">⚠ <?php echo esc_html($warn); ?></p><?php endforeach; ?>
                <?php foreach($p['errors'] as $err): ?><p class="wbsmi-errors">✖ <?php echo esc_html($err); ?></p><?php endforeach; ?>
                <h3>Все поля записи</h3><table class="wbsmi-meta"><tbody>
                    <?php foreach($f as $key=>$value): ?><tr><th><?php echo esc_html($key); ?></th><td><?php echo esc_html(is_array($value)?implode(', ',$value):$value); ?></td></tr><?php endforeach; ?>
                </tbody></table>
                <h3>Предпросмотр содержимого</h3><div class="wbsmi-body"><?php echo wbsmi_markdown_html($p['body']); // Safe Markdown HTML from wp_kses_post. ?></div>
                <details style="margin-top:12px"><summary>Исходный Markdown этой записи</summary><pre style="overflow:auto;background:#f6f7f7;padding:12px"><?php echo esc_html($p['body']); ?></pre></details>
            </div>
            <div class="wbsmi-panel"><h2>3. Импортировать записи</h2>
                <?php if(!$batch['errors']): ?>
                <form method="post" id="wbsmi-import-form">
                    <?php wp_nonce_field('wbsmi_import_'.$token); ?>
                    <input type="hidden" name="wbsmi_action" value="import" /><input type="hidden" name="wbsmi_token" value="<?php echo esc_attr($token); ?>" />
                    <label><input type="checkbox" name="wbsmi_confirm" value="1" required /> Я проверил записи и понимаю, что публикация или постановка в расписание произойдёт по значениям Markdown-файла.</label>
                    <?php submit_button('Выполнить импорт по файлу','primary'); ?>
                </form>
                <div id="wbsmi-import-progress" aria-live="polite" style="display:none;padding:12px;background:#f6f7f7">
                    <progress id="wbsmi-bar" value="0" max="<?php echo esc_attr($cnt); ?>" style="width:100%;height:20px"></progress>
                    <p id="wbsmi-progress-label">Ожидание…</p><ul id="wbsmi-import-results"></ul>
                </div>
                <script>
                (function(){
                    const form=document.getElementById('wbsmi-import-form');
                    const panel=document.getElementById('wbsmi-import-progress');
                    if(!form||!window.fetch||!window.FormData){return;}
                    form.addEventListener('submit',async function(e){
                        e.preventDefault();
                        const confirm=form.querySelector('[name="wbsmi_confirm"]');
                        if(!confirm.checked){confirm.focus();return;}
                        form.querySelector('[type="submit"]').disabled=true;
                        panel.style.display='block';
                        const bar=document.getElementById('wbsmi-bar');
                        const label=document.getElementById('wbsmi-progress-label');
                        const log=document.getElementById('wbsmi-import-results');
                        let offset=0, created=0, skipped=0, problems=0;
                        const token=<?php echo wp_json_encode($token,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
                        const nonce=<?php echo wp_json_encode(wp_create_nonce('wbsmi_import_'.$token),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
                        while(true){
                            const request=new FormData();
                            request.append('action','wbsmi_chunk_import');request.append('token',token);
                            request.append('nonce',nonce);request.append('offset',String(offset));
                            try {
                                const response=await fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',body:request,credentials:'same-origin'});
                                const data=await response.json();
                                if(!response.ok||!data.success){throw new Error(data.data?.message||'Ошибка сервера при импорте');}
                                const rpt=data.data.report;
                                created+=rpt.created.length;skipped+=rpt.skipped.length;problems+=rpt.errors.length;
                                for(const msg of rpt.errors){const li=document.createElement('li');li.textContent='Ошибка: '+msg;li.style.color='#a00';log.appendChild(li);}
                                offset=data.data.next;bar.value=offset;
                                label.textContent='Обработано '+offset+' / '+data.data.total+'. Создано: '+created+', пропущено: '+skipped+', ошибок: '+problems+'.';
                                if(data.data.done){label.textContent+=' Импорт завершён. Обновите страницу для нового импорта.';break;}
                            } catch(error){
                                label.textContent='Импорт прерван: '+error.message+'. Уже созданные записи сохраняются. После обновления можно повторить импорт: существующие slug будут пропущены.';
                                form.querySelector('[type="submit"]').disabled=false;
                                break;
                            }
                        }
                    });
                })();
                </script>
                <?php else: ?><p>Исправьте ошибки и загрузите файл ещё раз. Импорт целиком заблокирован.</p><?php endif; ?>
            </div>
        <?php elseif($batch): ?><div class="notice notice-error"><p><?php echo esc_html(implode('; ',$batch['errors'])); ?></p></div><?php endif; ?>
    </div>
    <?php
}
function wbsmi_action_label($post){
    if(!empty($post['errors'])){return 'Ошибка';}
    if($post['action']==='future'){return 'Публикация по расписанию: '.$post['scheduled'];}
    if($post['action']==='publish'){return 'Немедленная публикация';}
    if($post['action']==='draft'){return 'Сохранить черновиком';}
    return 'Не определено';
}
