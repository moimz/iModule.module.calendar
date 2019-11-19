<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 카테고리를 저장한다.
 *
 * @file /modules/calendar/process/@saveCategory.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 11. 15.
 */
if (defined('__IM__') == false) exit;

$cid = Param('cid');
$idx = Request('idx');

$errors = array();
$insert = array();
$insert['cid'] = $cid;
$insert['title'] = Request('title') ? Request('title') : $errors['title'] = $this->getErrorText('REQUIRED');
$insert['color'] = Request('color') ? Request('color') : $errors['color'] = $this->getErrorText('REQUIRED');

$permission = new stdClass();
if (Request('use_ical')) {
	$insert['ical'] = Request('ical') ? Request('ical') : $errors['ical'] = $this->getErrorText('REQUIRED');
	$permission->write = false;
	$permission->edit = false;
} else {
	$insert['ical'] = '';
	$permission->write = $this->IM->checkPermissionString(Request('permission_write')) == true ? Request('permission_write') : $errors['permission_write'] = $this->IM->checkPermissionString(Request('permission_write'));
	$permission->edit = $this->IM->checkPermissionString(Request('permission_edit')) == true ? Request('permission_edit') : $errors['permission_edit'] = $this->IM->checkPermissionString(Request('permission_edit'));
}
$permission->view = $this->IM->checkPermissionString(Request('permission_view')) == true ? Request('permission_view') : $errors['permission_view'] = $this->IM->checkPermissionString(Request('permission_view'));

$insert['permission'] = json_encode($permission,JSON_UNESCAPED_UNICODE);

if (count($errors) == 0) {
	if ($idx) {
		$this->db()->update($this->table->category,$insert)->where('idx',$idx)->execute();
	} else {
		$sort = $this->db()->select($this->table->category)->where('cid',$cid)->count();
		$insert['sort'] = $sort;
		$this->db()->insert($this->table->category,$insert)->execute();
	}
	
	$results->success = true;
} else {
	$results->success = false;
	$results->errors = $errors;
}
?>