<?php
/**
 * Allon Comment plugin
 */
class AllonComment {
	public $db_options = 'alloncomment';
	private $comment_childs = [];
	private $options;

	public function getConfigOption() {
		$this->options = get_option($this->db_options);

	}
	/**
	 * 插件初始化
	 * @author Allon<xianlong300@sina.com>
	 * @dateTime 2016-06-17T16:43:11+0800
	 * @return   [type]                   [description]
	 */
	public function init() {
		$this->getConfigOption();
		$this->initHoos();

	}

	public function initHoos() {
		add_filter('comment_text', array(&$this, 'renderComment'), 20, 2);
		if ($this->options['thread_option']) {
			add_filter('comments_array', array(&$this, 'changeComment'), 9998);
		}

		add_action('wp_enqueue_scripts', array(&$this, 'setStatic'), 20, 1);

		// add the filter
		add_filter('comment_reply_link', array(&$this, 'filterCommentReplyLink'), 10, 4);
		if ($this->options['like_option']) {
			add_action('wp_ajax_nopriv_do_comment_rate', array(&$this, 'doCommentRate'));
			add_action('wp_ajax_do_comment_rate', array(&$this, 'doCommentRate'));
		}
	}

	/**
	 * 挂载静态资源
	 * @author Allon<xianlong300@sina.com>
	 * @dateTime 2016-06-06T15:25:44+0800
	 */
	public function setStatic() {
		wp_enqueue_style('allon_comment', self::setStaticUrl('css/style.css'), array(), AL_VERSION);
		wp_enqueue_script('jquery');
		wp_enqueue_script('allon_comment', self::setStaticUrl('js/index.js'), array(), AL_VERSION);
		wp_localize_script('allon_comment', 'allon_comment_ajax_url', ALLON_COMMENT_ADMIN_URL . "admin-ajax.php");

	}

	/**
	 * 设置静态资源
	 * @author Allon<xianlong300@sina.com>
	 * @dateTime 2016-06-06T15:24:51+0800
	 * @param    [type]                   $name [description]
	 */
	public static function setStaticUrl($name) {

		return ALLON_COMMENT_URL . "/static/$name";
	}

	public function renderComment($comment_text) {
		global $comment, $post;
		static $deep = 0;
		$comment_id = $comment->comment_ID;
		$actionBar = '';
		$text = $comment_text;
		$copyComment = $comment;
		$_comment_like = intval(get_comment_meta($comment_id, '_comment_like', true));
		$actionBar = '<div class="allon-box-btm"><div class="article-type pull-right">

                    <ul class="vote-comment-act ' . ($deep % 2 ? 'icon-chalt' : "") . '" data-commentid="' . $comment_id . '">
                        <li><i class="allon-icon allon-icon-edit" onclick="return addComment.moveForm( &quot;div-allon-comment-' . $copyComment->comment_ID . '&quot;, &quot;' . $copyComment->comment_ID . '&quot;, &quot;respond&quot;, &quot;' . $post->ID . '&quot; )"></i></li>';
		if ($this->options['like_option']) {
			$actionBar .= '<li class="js-icon-like" data-type="like" data-event="up"><i class="allon-icon allon-icon-like "></i><span class="like count">' . $_comment_like . '</span></li>';
		}
		$actionBar .= '</ul>
                </div>
            </div>';

		$text = '<div class="comment-content" id="div-allon-comment-' . $copyComment->comment_ID . '">' . $text . $actionBar . '</div>';

		if (isset($this->comment_childs) && array_key_exists($comment->comment_ID, $this->comment_childs)) {

			$deep++;

			$id_temp = $comment->comment_ID;

			$count = 0;
			foreach ($this->comment_childs[$id_temp] as $comment) {

				$text .= $this->commenttext($deep, $count);
				$count++;

			}
			unset($this->comment_childs[$id_temp]);

			$deep--;
		}

		return $text;
	}

	public function changeComment($comments) {
		foreach ($comments as $key => $comment) {
			if ($comment->comment_parent) {
				$this->comment_childs[$comment->comment_parent][] = $comment;
				unset($comments[$key]);
			}

		}

		return $comments;
	}

	function commenttext($deep = 0, $count = 0) {
		global $comment;
		$id = $comment->comment_ID;

		$p = '<div class="comment-childs<?php echo $deep%2 ? \' chalt\' : ""; ?>"><footer class="comment-meta"><?php if(function_exists("get_avatar")):?><div class="comment-author vcard"><?php echo get_avatar( $comment, 32 ); ?></div><?php endif;?><b class="fn">[author]</b><span class="says">Reply:[moderation]</span><div class="comment-metadata"><a><time>[date]  [time]</time></a></div></footer>[content]</div>';

		ob_start();

		ob_clean();
		//$p = str_replace('<'.'?php','<'.'?',$p);
		eval('?' . '>' . $p);

		$p = ob_get_contents();

		ob_end_clean();

		$p = str_replace('[ID]', $id, $p);

		$p = str_replace('[author]', get_comment_author_link($comment->comment_ID), $p);
		$p = str_replace('[date]', get_comment_date('F jS, Y', $comment), $p);
		$p = str_replace('[time]', get_comment_time(), $p);
		$p = str_replace('[moderation]', $comment->comment_approved == '0' ? '<em>Your comment is awaiting moderation.</em>' : '', $p);

		if (strpos($p, '[content]')) {

			$text = $this->renderComment($comment->comment_content, $comment);

			$p = str_replace('[content]', $text, $p);

			unset($text);
		}

		return $p;
	}

	function filterCommentReplyLink($args_before__link_args_after, $args, $comment, $post) {

		return '';
	}

/**
 * ajax点赞操作
 * @author Allon<xianlong300@sina.com>
 * @dateTime 2016-06-06T15:29:01+0800
 * @return   [type]                   [description]
 */
	public function doCommentRate() {
		if (!isset($_POST["comment_id"]) || !isset($_POST["event"])) {

			$data = array("status" => 500, "data" => '?');

			echo json_encode($data);

		} else {

			$comment_id = $_POST["comment_id"];
			$current_user = wp_get_current_user();
			if (self::getLoveLog($comment_id, $current_user->ID)) {
				$data = array("status" => 500, "data" => array('msg' => 'is act', 'code' => 1001));
				echo json_encode($data);
			} else {

				$event = $_POST["event"];
				$expire = time() + 99999999;
				$domain = ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false; // make cookies work with localhost
				//setcookie('comment_rated_'.$comment_id,$comment_id,$expire,'/',$domain,false);
				$_comment_up = get_comment_meta($comment_id, '_comment_like', true);
				$_comment_down = get_comment_meta($comment_id, '_comment_nolike', true);
				if ($event == "up") {

					if (!$_comment_up) {

						update_comment_meta($comment_id, '_comment_like', 1);

					} else {

						update_comment_meta($comment_id, '_comment_like', ($_comment_up + 1));
					}
					$this->addLoveLog($comment_id);
				} else {
					if (!$_comment_down || $_comment_down == '' || !is_numeric($_comment_down)) {

						update_comment_meta($comment_id, '_comment_nolike', 1);

					} else {

						update_comment_meta($comment_id, '_comment_nolike', ($_comment_down + 1));

					}

				}

				$data = array();
				$_comment_up = get_comment_meta($comment_id, '_comment_like', true);
				$_comment_down = get_comment_meta($comment_id, '_comment_nolike', true);
				$data = array("status" => 200, "data" => array("event" => $event, "_comment_up" => $_comment_up, "_comment_down" => $_comment_down));
				echo json_encode($data);
			}
		}
		die;
	}

	/**
	 * 获取点赞记录
	 * @author Allon<xianlong300@sina.com>
	 * @dateTime 2016-06-06T15:28:27+0800
	 * @param    integer                  $commentId [description]
	 * @param    integer                  $userId    [description]
	 * @return   [type]                              [description]
	 */
	public function getLoveLog($commentId = 0, $userId = 0) {
		global $wpdb, $table_prefix;
		if ($commentId || $userId) {
			$where = ($commentId ? "comment_id='" . $commentId . "'" : "");
			if ($where && $userId) {
				$where .= " and ";
			}
			$where .= ($userId ? "user_id='" . $userId . "'" : "");
			$sql = "SELECT * FROM " . $table_prefix . "allon_comment where " . $where;

			return $wpdb->query($sql);
		}
		return [];
	}

	/**
	 * 添加点赞记录
	 * @author Allon<xianlong300@sina.com>
	 * @dateTime 2016-06-06T15:28:46+0800
	 * @param    [type]                   $commentId [description]
	 */
	public function addLoveLog($commentId) {
		global $wpdb, $table_prefix;
		if (!is_user_logged_in()) {
			return;
		}
		$current_user = wp_get_current_user();
		$sql = "INSERT INTO " . $table_prefix . "allon_comment (`comment_id`,`user_id`) values ('" . $commentId . "','" . $current_user->ID . "')";
		return $wpdb->query($sql);
	}

	//////////////////////////// 插件启用停用处理////////////////////////
	/**
	 * 插件启用
	 *
	 */
	public function plugin_activation() {
		$this->initDB();
		$this->initOption();

	}

	/**
	 * Removes all connection options
	 *
	 */
	public function plugin_deactivation() {
		$this->removeOption();
		// return self::deactivate_key( self::get_api_key() );
	}

	/**
	 * 初始化数据库
	 * @author Allon<xianlong300@sina.com>
	 * @dateTime 2016-06-04T16:30:58+0800
	 * @return   [type]                   [description]
	 */
	public function initDB() {
		global $wpdb, $table_prefix;
		$sql = "show table likes '" . $table_prefix . "allon_comment'";

		if (!$wpdb->get_row($sql)) {
			$sql = "CREATE TABLE IF NOT EXISTS `" . $table_prefix . "allon_comment` (
              `comment_id` int(11) NOT NULL,
              `user_id` int(11) DEFAULT '0',
              index(`comment_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

			$wpdb->query($sql);
		}

	}

	////////////////////////////后台//////////////////////
	public function initAdmin() {
		$this->getConfigOption();

		add_action('admin_menu', array(&$this, 'displaySettingMenu'));
	}

	function displaySettingMenu() {
		/* add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);  */
		/* 页名称，菜单名称，访问级别，菜单别名，点击该菜单时的回调函数（用以显示设置页面） */
		add_options_page('Set AllonComment', 'Set AllonComment', 'administrator', 'allon_comment', array(&$this, 'displayAllonCommentSetPage'));
	}

	function displayMessage($message, $status) {

		if ($message) {
			?>
				<div id="message" class="<?php echo ($status != '') ? $status : 'updated ';?> fade">
					<p><strong><?php echo $message;?></strong></p>
				</div>
	<?php
}
		unset($message, $status);
	}
	function displayAllonCommentSetPage() {
		if (isset($_POST['updateoptions'])) {

			update_option($this->db_options, $_POST);
			$message = __('Options saved', 'wp-thread-comment');
			$status = 'updated';
			$this->displayMessage($message, $status);
			$this->getConfigOption();
		}

		?>

    <div>
        <h2>Set AllonComment</h2>
        <form class="plugin_options" action="" method="post" id="plugin-options-panel">
	<form method="post" action="">
		<fieldset name="wp_basic_options"  class="options">
		<p>

			<label>Thread Comment Switch</label>
			<select name="thread_option"><option value="0" <?php if ($this->options['thread_option'] == 0): ?>selected="selected" <?php endif;?>>off</option><option value="1" <?php if ($this->options['thread_option'] == 1): ?>selected="selected" <?php endif;?>>on</option></select>
			<br />
			<label>Like Comment Switch</label>
			<select name="like_option"><option value="0" <?php if ($this->options['like_option'] == 0): ?>selected="selected" <?php endif;?>>off</option><option value="1" <?php if ($this->options['like_option'] == 1): ?>selected="selected" <?php endif;?>>on</option></select>
		</p>
		<div class="clearing"></div>
		<p class="submit">
			<input type="submit" name="updateoptions" value="Update Options" />

		</p>
		</fieldset>
    </div>
<?php
}

	public function initOption() {
		$this->options = array([
			'thread_option' => 1,
			'like_option' => 1,
		]);
		update_option($this->db_options, $this->options);
	}

	public function removeOption() {
		delete_option($this->db_options);
	}
}
