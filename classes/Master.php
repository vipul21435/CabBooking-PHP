<?php
require_once('../config.php');
Class Master extends DBConnection {
	private $settings;
	public function __construct(){
		global $_settings;
		$this->settings = $_settings;
		parent::__construct();
	}
	public function __destruct(){
		parent::__destruct();
	}
	function capture_err(){
		if(!$this->conn->error)
			return false;
		else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
			return json_encode($resp);
			exit;
		}
	}
	function save_category(){
		extract($_POST, EXTR_SKIP);
		$data = "";
		foreach($_POST as $k =>$v){
			if(!in_array($k,array('id','description'))){
				if(!empty($data)) $data .=",";
				$data .= " `{$k}`='{$v}' ";
			}
		}
		if(isset($_POST['description'])){
			if(!empty($data)) $data .=",";
				$data .= " `description`='".addslashes(htmlentities($description))."' ";
		}
		$check = !empty($id)
			? $this->count("SELECT COUNT(*) FROM `category_list` WHERE `name` = ? AND `delete_flag` = 0 AND `id` != ?", [$name, (int) $id])
			: $this->count("SELECT COUNT(*) FROM `category_list` WHERE `name` = ? AND `delete_flag` = 0", [$name]);
		if($this->capture_err())
			return $this->capture_err();
		if($check > 0){
			$resp['status'] = 'failed';
			$resp['msg'] = " Category already exist.";
			return json_encode($resp);
			exit;
		}
		if(empty($id)){
			$sql = "INSERT INTO `category_list` set {$data} ";
			$save = $this->conn->query($sql);
		}else{
			$sql = "UPDATE `category_list` set {$data} where id = '{$id}' ";
			$save = $this->conn->query($sql);
		}
		if($save){
			$resp['status'] = 'success';
			if(empty($id))
				$this->settings->set_flashdata('success'," New Category successfully saved.");
			else
				$this->settings->set_flashdata('success'," Category successfully updated.");
		}else{
			$resp['status'] = 'failed';
			$resp['err'] = $this->conn->error."[{$sql}]";
		}
		return json_encode($resp);
	}
	function delete_category(){
		extract($_POST, EXTR_SKIP);
		$del = $this->execute("UPDATE `category_list` SET `delete_flag` = 1 WHERE `id` = ?", [(int) $id]) >= 0;
		if($del){
			$resp['status'] = 'success';
			$this->settings->set_flashdata('success'," Category successfully deleted.");
		}else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
		}
		return json_encode($resp);

	}
	function save_cab(){
		if(!empty($_POST['password']))
			$_POST['password'] = Password::hash($_POST['password']);
		else
			unset($_POST['password']);
		if(empty($_POST['id'])){
			$prefix = date('Ym-');
			$code = sprintf("%'.05d",1);
			while(true){
				$check = $this->count("SELECT COUNT(*) FROM `cab_list` WHERE `reg_code` = ?", [$prefix . $code]);
				if($check > 0){
					$code = sprintf("%'.05d",ceil($code) + 1);
				}else{
					break;
				}
			}
			$_POST['reg_code'] = $prefix.$code;
		}


		extract($_POST, EXTR_SKIP);
		$data = "";
		foreach($_POST as $k =>$v){
			if(!in_array($k,array('id','oldpassword'))){
				$v = $this->conn->real_escape_string($v);
				if(!empty($data)) $data .=",";
				$data .= " `{$k}`='{$v}' ";
			}
		}
		if(isset($cab_reg_no)){
			$check = !empty($id)
				? $this->count("SELECT COUNT(*) FROM `cab_list` WHERE `cab_reg_no` = ? AND `id` != ?", [$cab_reg_no, (int) $id])
				: $this->count("SELECT COUNT(*) FROM `cab_list` WHERE `cab_reg_no` = ?", [$cab_reg_no]);
			if($this->capture_err())
				return $this->capture_err();
			if($check > 0){
				$resp['status'] = 'failed';
				$resp['msg'] = " Cab already exist.";
				return json_encode($resp);
				exit;
			}
		}
		if(isset($body_no)){
			$check = !empty($id)
				? $this->count("SELECT COUNT(*) FROM `cab_list` WHERE `body_no` = ? AND `id` != ?", [$body_no, (int) $id])
				: $this->count("SELECT COUNT(*) FROM `cab_list` WHERE `body_no` = ?", [$body_no]);
			if($this->capture_err())
				return $this->capture_err();
			if($check > 0){
				$resp['status'] = 'failed';
				$resp['msg'] = " Cab Body # already exist.";
				return json_encode($resp);
				exit;
			}
		}
		if(isset($oldpassword)){
			$cur_pass = $this->fetchValue("SELECT `password` FROM `cab_list` WHERE `id` = ?", [$this->settings->userdata('id')]);
			if(!Password::verify((string) $oldpassword, (string) $cur_pass)){
				$resp['status'] = 'failed';
				$resp['msg'] = " Current Password is Incorrect.";
				return json_encode($resp);
				exit;
			}
		}
		if(empty($id)){
			$sql = "INSERT INTO `cab_list` set {$data} ";
			$save = $this->conn->query($sql);
		}else{
			$sql = "UPDATE `cab_list` set {$data} where id = '{$id}' ";
			$save = $this->conn->query($sql);
		}
		if($save){
			$resp['status'] = 'success';
			$cid = empty($id) ? $this->conn->insert_id : $id;
			$resp['id'] = $cid ;
			if(empty($id))
				$resp['msg'] = " New Cab successfully saved.";
			else
				$resp['msg'] = " Cab successfully updated.";
				if($this->settings->userdata('id')  == $cid && $this->settings->userdata('login_type') == 3){
					foreach($_POST as $k => $v){
						if(!in_array($k,['password']))
						$this->settings->set_userdata($k,$v);
					}
					$resp['msg'] = " Account successfully updated.";
				}
				if(isset($_FILES['img']) && $_FILES['img']['tmp_name'] != ''){
					if(!is_dir(base_app."uploads/dirvers/"))
						mkdir(base_app."uploads/dirvers/");
					$fname = 'uploads/dirvers/'.$cid.'.png';
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
						$this->execute("UPDATE `cab_list` SET `image_path` = CONCAT(?, '?v=', unix_timestamp(CURRENT_TIMESTAMP)) WHERE `id` = ?", [$fname, $cid]);
						if($id == $this->settings->userdata('id')){
								$this->settings->set_userdata('avatar',$fname);
						}
					}
				}
		}else{
			$resp['status'] = 'failed';
			$resp['err'] = $this->conn->error."[{$sql}]";
		}
		if(isset($resp['msg']) && $resp['status'] == 'success'){
			$this->settings->set_flashdata('success',$resp['msg']);
		}
		return json_encode($resp);
	}
	function delete_cab(){
		extract($_POST, EXTR_SKIP);
		$del = $this->execute("UPDATE `cab_list` SET `delete_flag` = 1 WHERE `id` = ?", [(int) $id]) >= 0;
		if($del){
			$resp['status'] = 'success';
			$this->settings->set_flashdata('success'," Cab successfully deleted.");
		}else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
		}
		return json_encode($resp);

	}
	function save_booking(){
		if(empty($_POST['id'])){
			$prefix = date('Ym-');
			$code = sprintf("%'.05d",1);
			while(true){
				$check = $this->count("SELECT COUNT(*) FROM `cab_list` WHERE `reg_code` = ?", [$prefix . $code]);
				if($check > 0){
					$code = sprintf("%'.05d",ceil($code) + 1);
				}else{
					break;
				}
			}
			$_POST['client_id'] = $this->settings->userdata('id');
			$_POST['ref_code'] = $prefix.$code;
		}
		extract($_POST, EXTR_SKIP);
		$data = "";
		foreach($_POST as $k =>$v){
			if(!in_array($k,array('id'))){
				if(!empty($data)) $data .=",";
				$data .= " `{$k}`='{$v}' ";
			}
		}
		if(empty($id)){
			$sql = "INSERT INTO `booking_list` set {$data} ";
			$save = $this->conn->query($sql);
		}else{
			$sql = "UPDATE `booking_list` set {$data} where id = '{$id}' ";
			$save = $this->conn->query($sql);
		}
		if($save){
			$resp['status'] = 'success';
			if(empty($id))
				$this->settings->set_flashdata('success'," Cab has been booked successfully.");
			else
				$this->settings->set_flashdata('success'," Booking successfully updated.");
		}else{
			$resp['status'] = 'failed';
			$resp['err'] = $this->conn->error."[{$sql}]";
		}
		return json_encode($resp);
	}
	function delete_booking(){
		extract($_POST, EXTR_SKIP);
		$del = $this->execute("DELETE FROM `booking_list` WHERE `id` = ?", [(int) $id]) >= 0;
		if($del){
			$resp['status'] = 'success';
			$this->settings->set_flashdata('success'," Booking successfully deleted.");
		}else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
		}
		return json_encode($resp);

	}
	function update_booking_status(){
		extract($_POST, EXTR_SKIP);
		$update = $this->execute("UPDATE `booking_list` SET `status` = ? WHERE `id` = ?", [(int) $status, (int) $id]) >= 0;
		if($update){
			$resp['status'] = 'success';
			$this->settings->set_flashdata('success'," Booking status successfully updated.");
		}else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
		}
		return json_encode($resp);
	}
}

$Master = new Master();
$action = !isset($_GET['f']) ? 'none' : strtolower($_GET['f']);
$sysset = new SystemSettings();
switch ($action) {
	case 'save_category':
		echo $Master->save_category();
	break;
	case 'delete_category':
		echo $Master->delete_category();
	break;
	case 'save_cab':
		echo $Master->save_cab();
	break;
	case 'delete_cab':
		echo $Master->delete_cab();
	break;
	case 'save_booking':
		echo $Master->save_booking();
	break;
	case 'delete_booking':
		echo $Master->delete_booking();
	break;
	case 'update_booking_status':
		echo $Master->update_booking_status();
	break;
	default:
		// echo $sysset->index();
		break;
}