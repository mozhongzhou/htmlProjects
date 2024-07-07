<?php


class WB_IMGSPY_Ajax extends IMGSPY_Base
{

  public static function init()
  {
    add_action('wp_ajax_wb_scrapy_image', array(__CLASS__, 'wp_ajax_settings'));
    add_action('wp_ajax_wb_scrapy_image', array(__CLASS__, 'wp_ajax_wb_scrapy_image'));
  }

  public static function wp_ajax_settings()
  {
      $op = self::param('op');
      if(!$op){
          return;
      }
      $spec_op = ['chk_ver', 'chk_ver_ce', 'get_ce_cont'];
      $allow = [
          'history', 'scan', 'down', 'watermark_preview', 'recover', 'remove',
          'verify', 'set_setting', 'get_setting', 'chk_ver', 'chk_ver_ce', 'get_ce_cont',
      ];
      if(!in_array($op, $allow)){
          return ;
      }
      if(!current_user_can('manage_options')){
          if(in_array($op, $spec_op)){
              exit();
          }
          self::ajax_resp(['code' => 1, 'desc' => 'deny']);
          return;
      }
      $nonce = self::param('_ajax_nonce');
      if(!$nonce){
          $nonce = self::param('_ajax_nonce','', 'g');
      }
      if (!wp_verify_nonce(sanitize_text_field($nonce), 'wp_ajax_wb_imgspider')) {
          if(in_array($op, $spec_op)){
              exit();
          }
          self::ajax_resp(['code'=>1,'desc'=>'illegal']);
          return;
      }


      switch ($op)
      {

          case 'history':

              $data = get_option('wb_imgspy_history', []);
              if (!empty($data['domain']) && is_array($data['domain'])) {
                  $data['domain'] = trim(implode("\n", $data['domain']));
              }
              $post_id = $data['post_id'] ?? [];
              $domain = trim($data['domain'] ?? '');
              $images = [];
              $history_post_id = [];
              if (!empty($post_id) && is_array($post_id)) {
                  foreach ($post_id as $ID) {
                      $post = get_post($ID);
                      $img = self::parse_images_url($post, explode("\n", $domain));
                      if (empty($img)) {
                          continue;
                      }
                      $images = array_merge($images, $img);
                      $history_post_id[] = $post->ID;
                  }
                  $data['post_id'] = $history_post_id;
                  update_option('wb_imgspy_history', $data, false);
              }
              //$data['img_data'] = $images;
              $data['images'] = $images;
              if ($images) {
                  $data['finnish'] = 1;
              }
              $ret = ['code'=>0, 'desc'=>'success', 'data'=>$data];

              self::ajax_resp($ret);
              break;
          case 'scan':

              $domain = sanitize_textarea_field(self::param('domain'));
              $scan_type = self::array_sanitize_text_field(self::param('scan_type', []));
              $scan_status = self::array_sanitize_text_field(self::param('scan_status', []));
              $cat = absint(self::param('cat', 0 ));
              $start_id = absint(self::param('start_id', 0));
              $finnish_id = absint(self::param('finnish_id', 0));
              $sort = absint(self::param('sort',0));
              $start_post_date = sanitize_text_field(self::param('start_post_date'));
              $finnish_post_date = sanitize_text_field(self::param('finnish_post_date'));
              $domain = trim(str_replace("\r\n", "\n", $domain));
              $history = [
                  'domain' => $domain,
                  'scan_type' => $scan_type,
                  'scan_status' => $scan_status,
                  'cat' => $cat,
                  'start_id' => $start_id,
                  'finnish_id' => $finnish_id,
                  'start_post_date' => $start_post_date,
                  'finnish_post_date' => $finnish_post_date,
                  'sort' => $sort,
                  'post_id' => [],
              ];

              update_option('wb_imgspy_history', $history, false);


              if (empty($scan_type)) {
                  $ret = array('code' => 1, 'desc' => '扫描类型为空');
                  self::ajax_resp($ret);
                  return;
              }

              $history_post_id = get_option('wb_imgspy_history_post_id', []);
              if (!$history_post_id) {
                  $history_post_id = [];
              }

              $db = self::db();

              //
              $job = get_option('wb_imgspy_scan', false);

              if (!$job) {
                  $history_post_id = [];
                  $job = array('scan_num' => 0, 'offset' => 0, 'total' => 0, 'num' => 10, 'finnish' => 0, 'type' => array('post'));
              }

              $offset = $job['offset'];
              $num = $job['num'];

              $find_total = '';
              if ($job['total'] < 1) {
                  $find_total = 'SQL_CALC_FOUND_ROWS';
              }
              $where = [];
              $post_type_in = implode("','", $scan_type);
              $where[] = "post_type IN('$post_type_in')";
              if ($scan_status) {
                  $where[] = "post_status IN('" . implode("','", $scan_status) . "')";
              }

              if ($start_id > 0) {
                  $where[] = "ID>=" . $start_id;
              }
              if ($finnish_id > 0) {
                  $where[] = "ID<=" . $finnish_id;
              }

              if ($start_post_date) {
                  $where[] = $db->prepare("post_date>=%s", $start_post_date . ' 00:00:00');
              }

              if ($finnish_post_date) {
                  $where[] = $db->prepare("post_date<=%s", $finnish_post_date . ' 23:56:56');
              }

              if ($cat) {
                  $child = get_term_children($cat, 'category');
                  $child[] = $cat;
                  $term_id = implode(',', $child);
                  $where[] = "EXISTS(SELECT tr.object_id FROM $db->term_taxonomy tt,$db->term_relationships tr  
                                    WHERE tt.term_id IN($term_id) AND tt.term_taxonomy_id=tr.term_taxonomy_id AND tr.object_id=$db->posts.ID )";
              }

              $sql = "SELECT $find_total * FROM $db->posts WHERE ";
              $sql .= implode(' AND ', $where);
              if ($sort == 2) {
                  $sql .= " ORDER BY ID ASC";
              } else {
                  $sql .= " ORDER BY ID DESC";
              }

              $sql .= " LIMIT $offset,$num";


              $list = $db->get_results($sql);
              $job['total'] = $db->get_var("SELECT FOUND_ROWS()");
              $images = array();
              $idx = 0;
              if ($list) foreach ($list as $r) {
                  $idx++;
                  //$html = array();
                  //$img_list = WB_IMGSPY_Post::find_img_src($r,$html);
                  $img = self::parse_images_url($r, explode("\n", $domain));
                  if (empty($img)) {
                      continue;
                  }
                  $images = array_merge($images, $img);
                  $history_post_id[] = $r->ID;
              }

              //$images = array_unique($images);




              $job['scan_num'] += $idx;

              $job['offset'] = $job['offset'] + $job['num'];

              if ($job['offset'] > $job['total']) {
                  $job['finnish'] = 1;
              }

              if ($job['finnish']) {
                  $history['post_id'] = $history_post_id;
                  update_option('wb_imgspy_history', $history, false);
                  delete_option('wb_imgspy_scan');
                  delete_option('wb_imgspy_history_post_id');
              } else {
                  update_option('wb_imgspy_history_post_id', $history_post_id, false);
                  update_option('wb_imgspy_scan', $job, false);
              }


              $ret = array('code' => 0, 'desc' => 'success', 'job' => $job, 'images' => $images);

              self::ajax_resp($ret);
              break;
          case 'down':
              $ret = ['code'=>1, 'desc' => 'fail'];
              do{
                  set_time_limit(600);
                  $id = sanitize_text_field(self::param('id'));
                  $image = sanitize_text_field(self::param('image'));
                  $img_key = sanitize_text_field(self::param('key'));

                  if (empty($id)) {
                      $ret['code'] = 1;
                      $ret['desc'] = 'ID不能为空';
                      break;
                  }

                  if (empty($image)) {
                      $ret['code'] = 1;
                      $ret['desc'] = '图片不能为空';
                      break;
                  }
                  if (empty($img_key)) {
                      $ret['code'] = 1;
                      $ret['desc'] = 'Key不能为空';
                      break;
                  }

                  if (!preg_match('#^\d+-\d+$#', $id)) {
                      $ret['code'] = 1;
                      $ret['desc'] = 'ID参数符';
                      break;
                  }

                  $db = self::db();

                  $ids = explode('-', $id);


                  $post_id = intval($ids[0]);
                  $idx = intval($ids[1]);

                  //$post = get_post($post_id);
                  $sql = "SELECT ID,post_title,post_date,post_status,post_type,post_content,post_author FROM $db->posts 
                        WHERE ID=%d";
                  $post = $db->get_row($db->prepare($sql, $post_id));

                  if (!$post || !$post->ID) { //|| $post->post_status != 'publish'
                      $ret['code'] = 1;
                      $ret['desc'] = '无法找到对应文章';
                      break;
                  }

                  if (!preg_match_all('#<img([^>]+)>#is', $post->post_content, $match)) {
                      $ret['code'] = 1;
                      $ret['desc'] = '未匹配到图片';
                      break;
                  }


                  $find_it = false;
                  foreach ($match[0] as $k => $img_html) {
                      $src_html = $match[1][$k];
                      if (preg_match('#data-src=([^\s]+)#is', $src_html, $img_match)) {
                      } else if (preg_match('#src=([^\s]+)#is', $src_html, $img_match)) {
                      } else {
                          continue;
                      }

                      $img_src = trim(preg_replace('#/?>$#', '', $img_match[1]), '\'"');
                      if (!preg_match('#^https?://#is', $img_src)) {
                          continue;
                      }
                      $img_src = str_replace('&amp;', '&', $img_src);
                      $key = md5($img_src);
                      if ($img_key != $key) {
                          continue;
                      }
                      $find_it = true;
                      break;
                  }
                  if (!$find_it) {
                      $ret['code'] = 1;
                      $ret['desc'] = '图片不匹配';
                      break;
                  }

                  $config = WB_IMGSPY_Conf::opt();
                  $proxy = sanitize_text_field(self::param('proxy'));
                  $base64_img = self::param('base64_img', null);
                  if ($proxy && $proxy != 'none') {
                      //set proxy
                      WB_IMGSPY_Down::set_proxy($proxy);
                  }

                  $upload_ret = false;
                  $new_html = '';
                  if (isset($_FILES['img_file'])) {
                      $error = '';
                      $upload_ret = WB_IMGSPY_Post::upload_img_file('img_file', $post_id, $post->post_date, $error);
                  } else if ($base64_img) {
                      $upload_ret = WB_IMGSPY_Post::upload_img_base64($base64_img, $post_id, $post->post_date);
                  } else if ($image) {
                      $img =  rawurldecode($image);
                      $img = str_replace('&amp;', '&', $img);
                      $upload_ret = WB_IMGSPY_Post::upload($img, $post_id, $post->post_date);
                  }

                  if ($upload_ret) {
                      $new_html = WB_IMGSPY_Post::img_html($upload_ret, $idx, $post->post_title, $config);
                  }

                  if (!$new_html) {
                      $ret['code'] = 1;
                      $ret['desc'] = '采集失败';
                      break;
                  }

                  $post = $db->get_row($db->prepare($sql, $post_id));
                  $content = $post->post_content;
                  if ($config['del_src_url']) {
                      $content = self::replaceImageLink($content);
                  }

                  $has_change = 0;

                  foreach ($match[0] as $k => $img_html) {
                      $src_html = $match[1][$k];
                      if (preg_match('#data-src=([^\s]+)#is', $src_html, $img_match)) {
                      } else if (preg_match('#src=([^\s]+)#is', $src_html, $img_match)) {
                      } else {
                          continue;
                      }
                      $img_src = trim(preg_replace('#/?>$#', '', $img_match[1]), '\'"');
                      if (!preg_match('#^https?://#is', $img_src)) {
                          continue;
                      }
                      $img_src = str_replace('&amp;', '&', $img_src);
                      $key = md5($img_src);
                      if ($img_key != $key) {
                          continue;
                      }
                      $content = str_replace($img_html, $new_html, $content);
                      $has_change = 1;
                  }
                  if ($has_change) {
                      $db->update($db->posts, ['post_content' => $content], ['ID' => $post->ID]);
                  }

                  $ret['code'] = 0;
                  $ret['desc'] = 'success';
              }while(0);
              self::ajax_resp($ret);

              break;
          case 'watermark_preview':
              $img = IMGSPY_PATH . '/assets/img/demo.jpeg';
              $img2 = IMGSPY_PATH . '/assets/img/demo-water.jpeg';
              if (copy($img, $img2)) {
                  WB_IMGSPY_Image::watermark_preview($img2);
              }
              exit();
              break;
          case 'recover':
              $ret = ['code'=>0, 'desc' => 'success'];
              $state = WB_IMGSPY_Image::recover_backup();
              $ret['state'] = $state;
              if (!$state) {
                  $ret['code'] = 1;
                  $ret['desc'] = WB_IMGSPY_Image::$error;
              }
              self::ajax_resp($ret);
              break;
          case 'remove':
              $ret = ['code'=>0, 'desc' => 'success'];
              $state = WB_IMGSPY_Image::remove_backup();
              $ret['state'] = $state;
              if (!$state) {
                  $ret['code'] = 1;
                  $ret['desc'] = WB_IMGSPY_Image::$error;
              }
              self::ajax_resp($ret);
              break;
          case 'verify':

              $ret = ['code' => 1, 'desc'=>'fail'];
              $param = array(
                  'code' => sanitize_text_field(self::param('key')),
                  'host' => sanitize_text_field(self::param('host')),
                  'ver' => 'imgspider-pro',
              );
              $err = '';
              do {
                  if(empty($param['code']) || empty($param['host'])){
                      $err = '不合法请求，参数无效';
                      break;
                  }
                  $http = wp_remote_post('https://www.wbolt.com/wb-api/v1/verify', array('sslverify' => false, 'body' => $param, 'headers' => array('referer' => home_url()),));
                  if (is_wp_error($http)) {
                      $err = '校验失败，请稍后再试（错误代码001[' . $http->get_error_message() . '])';
                      break;
                  }

                  if ($http['response']['code'] != 200) {
                      $err = '校验失败，请稍后再试（错误代码001[' . $http['response']['code'] . '])';
                      break;
                  }

                  $body = $http['body'];

                  if (empty($body)) {
                      $err = '发生异常错误，联系<a href="https://www.wbolt.com/?wb=member#/contact" target="_blank">技术支持</a>（错误代码 010）';
                      break;
                  }

                  $data = json_decode($body, true);

                  if (empty($data)) {
                      $err = '发生异常错误，联系<a href="https://www.wbolt.com/?wb=member#/contact" target="_blank">技术支持</a>（错误代码011）';
                      break;
                  }
                  if (empty($data['data'])) {
                      $err = '校验失败，请稍后再试（错误代码004)';
                      break;
                  }
                  if ($data['code']) {
                      $err_code = $data['data'];
                      switch ($err_code) {
                          case 100:
                          case 101:
                          case 102:
                          case 103:
                              $err = '插件配置参数错误，联系<a href="https://www.wbolt.com/?wb=member#/contact" target="_blank">技术支持</a>（错误代码' . $err_code . '）';
                              break;
                          case 200:
                              $err = '输入key无效，请输入正确key（错误代码200）';
                              break;
                          case 201:
                              $err = 'key使用次数超出限制范围（错误代码201）';
                              break;
                          case 202:
                          case 203:
                          case 204:
                              $err = '校验服务器异常，联系<a href="https://www.wbolt.com/?wb=member#/contact" target="_blank">技术支持</a>（错误代码' . $err_code . '）';
                              break;
                          default:
                              $err = '发生异常错误，联系<a href="https://www.wbolt.com/?wb=member#/contact" target="_blank">技术支持</a>（错误代码' . $err_code . '）';
                      }

                      break;
                  }

                  update_option('wb_imgspider_ver', $data['v'], false);
                  update_option('wb_imgspider_cnf_' . $data['v'], $data['data'], false);


                  $ret = ['code'=>0, 'desc' => 'success'];

              } while (false);
              if($err){
                  $ret['desc'] = $err;
              }
              self::ajax_resp($ret);

              break;

          case 'set_setting':
              $ret = array('code' => 0, 'desc' => 'success');
              do {
                  $key = sanitize_text_field( self::param('key'));
                  $key2 = implode('',['re','set']);
                  if($key2 === $key){
                      $w_key = implode('_',['wb','imgs'.'pider','']);
                      $u_uid = get_option($w_key.'ver', 0);
                      if($u_uid){
                          update_option($w_key.'ver',0);
                          update_option($w_key.'cnf_' . $u_uid, '');
                      }
                      break;
                  }
                  $opt = self::param('opt', []);
                  if($opt && is_array($opt)){
                      $opt_data = self::array_sanitize_text_field($opt, ['domain', 'custom_name', 'custom_title']);
                      WB_IMGSPY_Conf::update_cnf($opt_data);
                  }

              } while (0);
              self::ajax_resp($ret);
              break;

          case 'get_setting':
              $ret = ['code' => 1, 'desc' => 'success', 'data'=>[]];
              $opt = WB_IMGSPY_Conf::opt();
              if (is_array($opt['filter']['domain'])) {
                  $opt['filter']['domain'] = implode("\n", $opt['filter']['domain']);
              }
              if (is_array($opt['filter']['type'])) {
                  $filter_type = [];
                  foreach ($opt['filter']['type'] as $k => $v) {
                      if ($v) {
                          $filter_type[] = $k;
                      }
                  }
                  $opt['filter']['type'] = implode(',', $filter_type);
              }
              $ret['data']['opt'] = $opt;
              $ret['data']['cnf'] = WB_IMGSPY_Conf::$cnf_fields;
              self::ajax_resp($ret);
              break;

          case 'chk_ver':
              $http = wp_remote_get('https://www.wbolt.com/wb-api/v1/themes/checkver?code=' . IMGSPY_CODE . '&ver=' . IMGSPY_VERSION . '&chk=1', array('sslverify' => false, 'headers' => array('referer' => home_url()),));

              if (wp_remote_retrieve_response_code($http) == 200) {
                  echo esc_html(wp_remote_retrieve_body($http));
              }

              exit();
              break;

          case 'chk_ver_ce':
              $http = wp_remote_get('https://www.wbolt.com/wb-api/v1/extension/ver?code=' . IMGSPY_CODE . '&ver=', array('sslverify' => false, 'headers' => array('referer' => home_url()),));
              if (wp_remote_retrieve_response_code($http) == 200) {
                  echo esc_html(wp_remote_retrieve_body($http));
              }

              exit();
              break;

          case 'get_ce_cont':
              $http = wp_remote_get('https://www.wbolt.com/plugins/wbolt-assistant-chrome-extension/', array('sslverify' => false, 'headers' => array('referer' => home_url()),));
              if (wp_remote_retrieve_response_code($http) == 200) {
                  echo $http['body'];
              }
              exit();
              break;
      }
  }

  public static function wp_ajax_wb_scrapy_image()
  {
    // global $wpdb;
    $ret = array('code' => 0, 'desc' => 'success');
    $op = sanitize_text_field($_REQUEST['op'] ?? '');
    $op = (!$op && isset($_REQUEST['do'])) ? sanitize_text_field($_REQUEST['do']) : $op;
    if(!$op){
        return;
    }
    $allow = [
        'save_img', 'save_paste_image', 'scrapy', 'options'
    ];
    if(!in_array($op, $allow)){
        return;
    }
      if (!current_user_can('edit_posts')) {
          self::ajax_resp([]);
          return;
      }
      $nonce = self::param('_ajax_nonce');
      if(!$nonce){
          $nonce = self::param('_ajax_nonce','', 'g');
      }
      if (!wp_verify_nonce(sanitize_text_field($nonce), 'wp_ajax_wb_imgspider')) {
          self::ajax_resp([]);
          return;
      }

    switch ($op) {



      case 'save_img':
          $ret = [];
        do{
            $post_title = sanitize_text_field(self::param('post_title'));
            $post_id = absint(self::param('post_id', 0));
            if (!$post_id || !current_user_can('edit_post', $post_id)) {
                $ret = [];
                break;
            }
            $ret_list = array();
            $error = '';
            $ret = WB_IMGSPY_Post::upload_img_file('img_file', $post_id, false, $error);
            if ($ret) {
                $config = WB_IMGSPY_Conf::opt();
                $img_html = WB_IMGSPY_Post::img_html($ret, 0, $post_title, $config);
                $ret_list[] = $img_html;
            }
            $ret = $ret_list;
        }while(0);
        self::ajax_resp($ret);
        break;

      case 'save_paste_image':
          $ret = [];
          do{
              $post_title = sanitize_text_field(self::param('post_title'));
              $post_id = absint(self::param('post_id', 0));
              $img = sanitize_text_field(self::param('image'));
              if (empty($img) || empty($post_id)) {
                  $ret = [];
                  break;
              }
              if (!current_user_can('edit_post', $post_id)) {
                  $ret = []; //array('code'=>1,'desc'=>'没有权限');
                  break;
              }
              $ret_list = array();
              $ret = WB_IMGSPY_Post::upload_img_base64($img, $post_id, false);
              if ($ret) {
                  $config = WB_IMGSPY_Conf::opt();
                  $img_html = WB_IMGSPY_Post::img_html($ret, 0, $post_title, $config);

                  $ret_list[] = $img_html;
              }
              $ret = $ret_list;
          }while(0);
        self::ajax_resp($ret);
        break;

      case 'scrapy':

          $ret = [];
          do{
              set_time_limit(600);
              $idx = absint(self::param('idx', 0));
              $post_title = sanitize_text_field(self::param('title'));
              $img = sanitize_text_field(self::param('image'));
              $proxy = sanitize_text_field(self::param('proxy'));
              $post_id = absint(self::param('post_id', 0));

              $config = WB_IMGSPY_Conf::opt();

              if (!current_user_can('edit_post', $post_id)) {
                  $ret = [];
                  break;
              }

              //set proxy
              if (strlen($proxy) > 0 && $proxy != 'none') {
                  WB_IMGSPY_Down::set_proxy($proxy);
              }
              $ret_list = array();
              if ($img) {

                  $img =  rawurldecode($img);
                  $img = str_replace('&amp;', '&', $img);

                  $ret = WB_IMGSPY_Post::upload($img, $post_id, false);
                  if ($ret) {
                      $gtb = self::param('gtb', null);
                      if (null !== $gtb) {
                          $img_html = $ret;
                      } else {
                          $img_html = WB_IMGSPY_Post::img_html($ret, $idx, $post_title, $config);
                      }

                      $ret_list[] = $img_html;
                  }
              }

              $ret = $ret_list;
          }while(0);
          self::ajax_resp($ret);
        break;
        case 'options':
            $ver = get_option('wb_imgspider_ver', 0);
            $cnf = '';
            if ($ver) {
                $cnf = get_option('wb_imgspider_cnf_' . $ver, '');
            }
            self::ajax_resp(['o' => $cnf]);
            break;

    }

  }


  private static function parse_images_url($post, $allow_domain = array())
  {
    $ret = array();
    $host_name = wp_parse_url(home_url(), PHP_URL_HOST);
    $host_name = str_replace('www.', '', $host_name);
    $allow_domain[] = $host_name;

    if (preg_match_all('#<img[^>]+>#is', $post->post_content, $match)) {

      foreach ($match[0] as $img_html) {
        $img_src = '';
        if (preg_match('#data-src=(\'|")(.+?)\1#is', $img_html, $img_match)) {
          $img_src = $img_match[2];
        } else if (preg_match('#src=(\'|")(.+?)\1#is', $img_html, $img_match)) {
          $img_src = $img_match[2];
        } else if (preg_match('#src=([^\s>]+)#is', $img_html, $img_match)) {
          $img_src = trim($img_match[1], '\'"/');
        } else {
          continue;
        }

        if (!preg_match('#^https?://#is', $img_src)) {

          continue;
        }

        $find_id = false;

        foreach ($allow_domain as $domain) {
          if (strpos($img_src, $domain)) {
            $find_id = true;
            break;
          }
        }
        if (!$find_id) {
          $img_src = str_replace('&amp;', '&', $img_src);
          $key = md5($img_src);
          $ret[$key] = $img_src;
        }
      }
    }
    if ($ret) {
      //$ret = array_values($ret);
      $images = array();
      $post_url = get_permalink($post);
      $idx = -1;
      foreach ($ret as $k => $img) {
        $idx++;
        $images[] = array('post_id' => $post->ID, 'status' => 0, 'key' => $k, 'id' => $post->ID . '-' . $idx, 'url' => $post_url, 'src' => $img);
      }

      return $images;
    }

    return $ret;
  }

  public static function  array_sanitize_text_field($value, $skip = [])
  {
      if (is_array($value)) {
          foreach ($value as $k => $v) {
              if ($skip && in_array($k, $skip)) continue;
              $value[$k] = self::array_sanitize_text_field($v, $skip);
          }
          return $value;
      } else {
          return sanitize_text_field($value);
      }
  }


  public static function replaceImageLink($content)
  {
    //error_log('replace'."\n",3,__DIR__.'/log.txt');
    if (!preg_match_all('#(<a[^>]+>)\s*(<img[^>]+>)\s*</a>#is', $content, $match)) {
      //error_log('empty match'."\n",3,__DIR__.'/log.txt');
      return $content;
    }
    //error_log(''.(print_r($match[1],1))."\n",3,__DIR__.'/log.txt');
    foreach ($match[1] as $k => $a_html) {
      if (!preg_match('#href=([^\s]+)#', $a_html, $m)) {
        //error_log('empty href'."\n",3,__DIR__.'/log.txt');
        continue;
      }
        $content = str_replace($match[0][$k], $match[2][$k], $content);
      /*
      $link = preg_replace('#/?>$#', '', $m[1]);
      $link = trim($link, "\"'");

      //error_log($link."\n",3,__DIR__.'/log.txt');
      if (!preg_match('#\.(png|jpg|jpeg|gif)$#i', $link)) {
        //error_log('href not image'."\n",3,__DIR__.'/log.txt');
        continue;
      }
      //error_log('replace image link'."\n",3,__DIR__.'/log.txt');
      $content = str_replace($match[0][$k], $match[2][$k], $content);*/
    }

    return $content;
  }
}
