<?php
	class Post extends MY_Controller {
		function __construct() {
			parent::__construct();
			$this->load->model('Post_model');
		}
		// 默认调用_view 方法
		function index($post_id = '') {
			if(is_numeric($post_id)) {
				$this->_view($post_id);
			} else {
				static_view('抱歉，你访问的页面不存在');
			}
		}
		
		function _view($post_id) {
			$post = $this->Post_model->get_info($post_id);
			if($post['type_id'] == $this->config->item('entity_type_activity')) {
				$join = array(
					'corporation' => array('owner_id', 'id'),
					'comment' => array('id', 'post_id'),
					'comment.user' => array('user_id', 'id')
				);
			} else {
				$join = array(
					'user' => array('owner_id', 'id'),
					'comment' => array('id', 'post_id'),
					'comment.user' => array('user_id', 'id')
				);
			}
			$post = $this->Post_model->get_info($post_id, $join);
			// 暂时注释，社团post需要权限验证
			//$this->_auth('view', 'post', $post['owner_id'], FALSE, $post['id']);
			if($post) {
				$data['js'] = 'post.js';
				$data['css'] = 'home.css';
				$data['title'] = mb_substr($post['content'], 0, 5);
				$data['main_content'] = 'post_single_view';
				// 活动post
				if($post['type_id'] == $this->config->item('entity_type_activity')) {
					$data['posts']['activity'][0] = $post;
				} else {
					$data['posts']['personal'][0] = $post;
				}
				$this->load->view('includes/template_view', $data);
			} else {
				static_view('抱歉，你访问的页面不存在');
			}
		}
		
		function add() {
			$this->_require_ajax();
			$this->_require_login();
			if(!$this->_auth('add', 'post', $this->session->userdata('id'), TRUE)) {
				echo 0;exit;
			}
			$content = $this->input->post('content');
			if(trim($content)) {
				$post = array(
				'owner_id' => $this->session->userdata('id'),
				'type' => 'personal',
				'content' => trim($this->input->post('content')),
				'time' => time()
			);
				$post_id = $this->Post_model->insert($post);
				if($post_id) {
					$post = $this->Post_model->get_info($post_id, array('user' => array('owner_id', 'id')));
					$this->load->view('post/ajax_post_view', array('post'=>$post));
				} else {
					echo 0; exit;
				}
			} else {
				echo 0; exit;
			}
		}
		
		function edit($id = 1) {
			$post = $this->db->where('id', $id)->get('posts')->result_array();
			$this->_auth(array('owner'), $post);
			echo '可以编辑';
		}
		
		function comment() {
			$this->_require_login();
			$this->_require_ajax();
			$this->load->model('Notify_model');
			//$owner_id = $this->input->post('owner_id');
			$type = $this->input->post('type');
			$post_id = $this->input->post('post_id');
			if(!$this->_auth('add', 'comment', $this->session->userdata('id'), TRUE, $post_id)) {
				echo 0;exit;
			}
			$post = $this->Post_model->get_info($post_id);
			$owner_id = $post['owner_id'];
			$time = time();
			if($this->input->post('content') != '') {
				$comment = array(
					'post_id' => $this->input->post('post_id'),
					'user_id' => $this->session->userdata('id'),
					'content' => $this->input->post('content'),
					'time' => $time
				);
				$comment_id = $this->Post_model->insert_comment($comment);
				if($type == 'personal' && $owner_id != $this->session->userdata('id')) {
					// 插入一条通知
					$notify = array(
						'user_id' => $this->session->userdata('id'),
						'receiver_id' => $owner_id,
						'content' => '评论了你的' . anchor('post/' . $post_id, '动态'),
						'type' => 'message',
						'time' => $time
					);
					$this->Notify_model->insert($notify);
				}
				if($comment_id) {
					$comment = $this->Post_model->fetch_comment(array('id' => $comment_id));
					?>
					<li>
						<div class="img_block"><?=anchor('personal/profile/' . $comment['user'][0]['id'], '<img src="'. avatar_url($comment['user'][0]['avatar']) .'" >','class="head_pic"') ?></div>
						<div class="comment_main">
							<div class="f_info">
							<?=anchor('personal/profile/' . $comment['user'][0]['id'], $comment['user'][0]['name']) ?>：
							<span class="f_do"><?=$comment['content']?></span>
						</div>
						<p class="f_pm">
							<span><?=jdate($comment['time']) ?></span>
						</p>
						</div>
					</li>
				<?
				} else {
					echo 0;
				}
			}
		}

		function emoji() {
			$this->config->load('emoji');
			$json_array = $this->CI->config->item('emoji');
			$json_array['emoji_path'] = $this->CI->config->item('emoji_path');
			return json_encode($json_array);
		}
	} 