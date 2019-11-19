<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 캘린더 정보를 불러온다.
 *
 * @file /modules/calendar/process/@getCalendar.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 11. 19.
 */
if (defined('__IM__') == false) exit;

$cid = Param('cid');
$data = $this->db()->select($this->table->calendar)->where('cid',$cid)->getOne();

if ($data == null) {
	$results->success = false;
	$results->message = $this->getErrorText('NOT_FOUND');
} else {
	unset($data->templet_configs);
	$results->success = true;
	$results->data = $data;
}
?>