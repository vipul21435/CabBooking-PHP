<?php
require_once('../config.php');
Class Users extends DBConnection {
	private $settings;
	public function __construct(){
		global $_settings;
		$this->settings = $_settings;
		parent::__construct();
	}
	public function __destruct(){
		parent::__destruct();
	}
	/**
	 * Creates or updates a staff user.
	 *
	 * The version this replaces ran extract($_POST), then looped over $_POST
	 * building "col = 'value'" fragments and interpolated the lot into
	 * "INSERT INTO users set {$data}". Both the column names and the values
	 * came from the request, so any extra form field became part of the
	 * statement. Columns now come from a fixed whitelist and values are bound.
	 */
	public function save_users(){
		$id = (int) ($_POST['id'] ?? 0);
		$username = trim((string) ($_POST['username'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');

		if(!isset($_POST['status']) && $this->settings->userdata('login_type') == 1){
			$_POST['status'] = 1;
		}

		// Changing your own password requires the current one.
		if(isset($_POST['oldpassword'])){
			$stored = (string) $this->fetchValue('SELECT `password` FROM `users` WHERE `id` = ?', [$this->settings->userdata('id')]);
			if(!Password::verify((string) $_POST['oldpassword'], $stored)){
				return 4;
			}
		}

		$taken = $id > 0
			? $this->count('SELECT COUNT(*) FROM `users` WHERE `username` = ? AND `id` != ?', [$username, $id])
			: $this->count('SELECT COUNT(*) FROM `users` WHERE `username` = ?', [$username]);
		if($taken > 0){
			return 3;
		}

		[$set, $params] = self::buildSet($_POST, ['firstname','middlename','lastname','username','type']);

		if($password !== ''){
			$set .= ($set === '' ? '' : ', ') . '`password` = ?';
			$params[] = Password::hash($password);
		}

		if($set === ''){
			return 2;
		}

		$resp = [];
		if($id === 0){
			$ok = $this->execute("INSERT INTO `users` SET {$set}", $params) >= 0;
			if($ok){
				$id = $this->lastInsertId();
				$this->settings->set_flashdata('success','User Details successfully saved.');
				$resp['status'] = 1;
			}else{
				$resp['status'] = 2;
			}
		}else{
			$params[] = $id;
			$ok = $this->execute("UPDATE `users` SET {$set} WHERE `id` = ?", $params) >= 0;
			if($ok){
				$this->settings->set_flashdata('success','User Details successfully updated.');
				if($id == $this->settings->userdata('id')){
					foreach($_POST as $k => $v){
						if($k !== 'id' && $k !== 'password' && $k !== 'oldpassword'){
							$this->settings->set_userdata($k,$v);
						}
					}
				}
				$resp['status'] = 1;
			}else{
				$resp['status'] = 2;
			}
		}

		if(isset($_FILES['img']) && $_FILES['img']['tmp_name'] != ''){
			$fname = 'uploads/avatar-'.$id.'.png';
			$dir_path = BASE_APP . $fname;
			$upload = $_FILES['img']['tmp_name'];
			$type = mime_content_type($upload);
			$allowed = array('image/png','image/jpeg');
			if(!in_array($type,$allowed)){
				$resp['msg'] = ($resp['msg'] ?? '') . " But Image failed to upload due to invalid file type.";
			}else{
				$new_height = 200;
				$new_width = 200;

				list($width, $height) = getimagesize($upload);
				$t_image = imagecreatetruecolor($new_width, $new_height);
				imagealphablending( $t_image, false );
				imagesavealpha( $t_image, true );
				$gdImg = ($type == 'image/png')? imagecreatefrompng($upload) : imagecreatefromjpeg($upload);
				imagecopyresampled($t_image, $gdImg, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
				if($gdImg){
						if(is_file($dir_path))
						unlink($dir_path);
						$uploaded_img = imagepng($t_image,$dir_path);
						imagedestroy($gdImg);
						imagedestroy($t_image);
				}else{
					$resp['msg'] = ($resp['msg'] ?? '') . " But Image failed to upload due to unknown reason.";
				}
			}
			if(isset($uploaded_img)){
				$this->execute("UPDATE `users` SET `avatar` = CONCAT(?, '?v=', unix_timestamp(CURRENT_TIMESTAMP)) WHERE `id` = ?", [$fname, $id]);
				if($id == $this->settings->userdata('id')){
						$this->settings->set_userdata('avatar',$fname);
				}
			}
		}
		if(isset($resp['msg']))
		$this->settings->set_flashdata('success',$resp['msg']);
		return  $resp['status'];
	}
	public function delete_users(){
		$id = (int) ($_POST['id'] ?? 0);
		$avatar = (string) $this->fetchValue('SELECT `avatar` FROM `users` WHERE `id` = ?', [$id]);
		$qry = $this->execute('DELETE FROM `users` WHERE `id` = ?', [$id]) >= 0;
		if($qry){
			$this->settings->set_flashdata('success','User Details successfully deleted.');
			if(is_file(base_app.$avatar))
				unlink(base_app.$avatar);
			$resp['status'] = 'success';
		}else{
			$resp['status'] = 'failed';
		}
		return json_encode($resp);
	}
	public function save_client(){
		if(!empty($_POST['password']))
		$_POST['password'] = Password::hash($_POST['password']);
		else
		unset($_POST['password']);
		if(isset($_POST['oldpassword'])){
			if($this->settings->userdata('id') > 0 && $this->settings->userdata('login_type') == 2){
				$get = $this->run("SELECT * FROM `client_list` WHERE `id` = ?", [$this->settings->userdata('id')])->get_result();
				$res = $get->fetch_array();
				if(!Password::verify((string) $_POST['oldpassword'], (string) $res['password'])){
					return  json_encode([
						'status' =>'failed',
						'msg'=>' Current Password is incorrect.'
					]);
				}
			}
			unset($_POST['oldpassword']);
		}
		extract($_POST, EXTR_SKIP);
		$data = "";
		foreach($_POST as $k => $v){
			if(!in_array($k, array('id'))){
				if(!empty($data)) $data .= ", ";
				$data .= " `{$k}` = '{$v}' ";
			}
		}
		$check = (is_numeric($id) && $id > 0)
			? $this->count("SELECT COUNT(*) FROM `client_list` WHERE `email` = ? AND `delete_flag` = 0 AND `id` != ?", [$email, $id])
			: $this->count("SELECT COUNT(*) FROM `client_list` WHERE `email` = ? AND `delete_flag` = 0", [$email]);
		if($check > 0){
			$resp['status'] = 'failed';
			$resp['msg'] = ' Email already exists in the database.';
		}else{
			if(empty($id)){
				$sql = "INSERT INTO `client_list` set $data";
			}else{
				$sql = "UPDATE `client_list` set $data where id = '{$id}'";
			}
			$save = $this->conn->query($sql);
			if($save){
				$resp['status'] = 'success';
				$uid = empty($id) ? $this->conn->insert_id : $id;
				if(empty($id)){
					$resp['msg'] = " Account is successfully registered.";
				}else if($this->settings->userdata('id') == $id && $this->settings->userdata('login_type') == 2){
					$resp['msg'] = " Account Details has been updated successfully.";
					foreach($_POST as $k => $v){
						if(!in_array($k,['password'])){
							$this->settings->set_userdata($k,$v);
						}
					}
				}else{
					$resp['msg'] = " Client's Account Details has been updated successfully.";
				}
				if(isset($_FILES['img']) && $_FILES['img']['tmp_name'] != ''){
					if(!is_dir(base_app."uploads/clients/"))
						mkdir(base_app."uploads/clients/");
					$fname = 'uploads/clients/'.$uid.'.png';
					$dir_path =base_app. $fname;
					$upload = $_FILES['img']['tmp_name'];
					$type = mime_content_type($upload);
					$allowed = array('image/png','image/jpeg');
					if(!in_array($type,$allowed)){
						$resp['msg'].=" But Image failed to upload due to invalid file type.";
					}else{
						$new_height = 200; 
						$new_width = 200; 
				
						list($width, $height) = getimagesize($upload);
						$t_image = imagecreatetruecolor($new_width, $new_height);
						imagealphablending( $t_image, false );
						imagesavealpha( $t_image, true );
						$gdImg = ($type == 'image/png')? imagecreatefrompng($upload) : imagecreatefromjpeg($upload);
						imagecopyresampled($t_image, $gdImg, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
						if($gdImg){
								if(is_file($dir_path))
								unlink($dir_path);
								$uploaded_img = imagepng($t_image,$dir_path);
								imagedestroy($gdImg);
								imagedestroy($t_image);
						}else{
						$resp['msg'].=" But Image failed to upload due to unkown reason.";
						}
					}
					if(isset($uploaded_img)){
						$this->execute("UPDATE `client_list` SET `image_path` = CONCAT(?, '?v=', unix_timestamp(CURRENT_TIMESTAMP)) WHERE `id` = ?", [$fname, $uid]);
						if($id == $this->settings->userdata('id') && $this->settings->userdata('login_type') == 2){
								$this->settings->set_userdata('image_path',$fname);
						}
					}
				}
			}else{
				$resp['status'] = 'failed';
				if(empty($id)){
					$resp['msg'] = " Account has failed to register for some reason.";
				}else if($this->settings->userdata('id') == $id && $this->settings->userdata('login_type') == 2){
					$resp['msg'] = " Account Details has failed to update.";
				}else{
					$resp['msg'] = " Client's Account Details has failed to update.";
				}
			}
		}
		
		if($resp['status'] == 'success')
		$this->settings->set_flashdata('success',$resp['msg']);
		return json_encode($resp);

	} 
	function delete_client(){
		extract($_POST, EXTR_SKIP);
		$del = $this->execute("UPDATE `client_list` SET `delete_flag` = 1 WHERE `id` = ?", [(int) $id]) >= 0;
		if($del){
			$resp['status'] = 'success';
			$resp['msg'] = ' Client Account has been deleted successfully.';
		}else{
			$resp['status'] = 'failed';
			$resp['msg'] = " Client Account has failed to delete";
		}
		if($resp['status'] =='success')
		$this->settings->set_flashdata('success',$resp['msg']);
		return json_encode($resp);
	}
	
}

$users = new users();
$action = !isset($_GET['f']) ? 'none' : strtolower($_GET['f']);
switch ($action) {
	case 'save':
		echo $users->save_users();
	break;
	case 'delete':
		echo $users->delete_users();
	break;
	case 'save_client':
		echo $users->save_client();
	break;
	case 'delete_client':
		echo $users->delete_client();
	break;
	break;
	default:
		// echo $sysset->index();
		break;
}