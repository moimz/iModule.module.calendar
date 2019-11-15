<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 카테고리를 가져온다.
 *
 * @file /modules/calendar/process/@getCategory.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 11. 15.
 */
if (defined('__IM__') == false) exit;

$idx = Param('idx');
$data = $this->db()->select($this->table->category)->where('idx',$idx)->getOne();
if ($data == null) {
	$results->success = false;
	$results->message = $this->getErrorText('NOT_FOUND');
	return;
}

if ($data->ical) {
	$data->use_ical = true;
} else {
	$data->use_ical = false;
}

$permission = json_decode($data->permission);
unset($data->permission);

if ($permission != null) {
	foreach ($permission as $key=>$value) {
		$data->{'permission_'.$key} = $value;
	}
}

$results->success = true;
$results->data = $data;
?>