<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 캘린더를 저장한다.
 *
 * @file /modules/calendar/process/@saveCalendar.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 11. 19.
 */
if (defined('__IM__') == false) exit;

$errors = array();
$mode = Param('mode');
$cid = Request('cid') ? Request('cid') : $errors['cid'] = $this->getErrorText('REQUIRED');
$title = Request('title') ? Request('title') : $errors['title'] = $this->getErrorText('REQUIRED');
$templet = Request('templet') ? Request('templet') : $errors['templet'] = $this->getErrorText('REQUIRED');
$templetConfigs = new stdClass();
foreach ($_POST as $key=>$value) {
	if (preg_match('/^templet_configs_/',$key) == true) {
		$templetConfigs->{str_replace('templet_configs_','',$key)} = $value;
	}
}

if ($mode == 'add') {
	$cid = Request('cid');
	if ($this->db()->select($this->table->calendar)->where('cid',$cid)->has() == true) $errors['cid'] = $this->getErrorText('DUPLICATED');
	else $insert['cid'] = $cid;
}

if (count($errors) == 0) {
	$insert = array();
	$insert['cid'] = $cid;
	$insert['title'] = $title;
	$insert['templet'] = $templet;
	$insert['templet_configs'] = json_encode($templetConfigs,JSON_UNESCAPED_UNICODE);
	
	if ($mode == 'add') {
		$this->db()->insert($this->table->calendar,$insert)->execute();
	} else {
		$cid = Request('cid');
		$this->db()->update($this->table->calendar,$insert)->where('cid',$cid)->execute();
	}
	
	$results->success = true;
} else {
	$results->success = false;
	$results->errors = $errors;
}
?>