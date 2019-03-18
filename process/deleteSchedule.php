<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 일정을 삭제한다.
 *
 * @file /modules/calendar/process/saveSchedule.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2018. 3. 20.
 */
if (defined('__IM__') == false) exit;

$idx = Request('idx');
$schedule = $this->db()->select($this->table->schedule)->where('idx',$idx)->getOne();
if ($schedule == null) {
	$results->success = false;
	$results->message = $this->getErrorText('NOT_FOUND');
	return;
}

if ($schedule->midx != $this->IM->getModule('member')->getLogged() && $this->checkPermission($schedule->cid,'delete') == false) {
	$results->success = false;
	$results->message = $this->getErrorText('FORBIDDEN');
	return;
}

$this->db()->delete($this->table->schedule)->where('idx',$idx)->execute();

if ($schedule->repeat != 'NONE') {
	$repeat_delete = Request('repeat_delete');
	
	if ($repeat_delete == 'NEXT') {
		$this->db()->delete($this->table->schedule)->where('idx',$idx,'>')->where('repeat_idx',$schedule->repeat_idx)->execute();
	}
	
	if ($this->db()->select($this->table->schedule)->where('repeat_idx',$schedule->repeat_idx)->count() <= 1) {
		$this->db()->update($this->table->schedule,array('repeat'=>'NONE','repeat_end_date'=>0,'repeat_idx'=>0))->where('repeat_idx',$schedule->repeat_idx)->execute();
	} else {
		if ($schedule->repeat_idx == $idx) {
			$first = $this->db()->select($this->table->schedule)->where('repeat_idx',$schedule->repeat_idx)->orderBy('idx','asc')->getOne();
			$this->db()->update($this->table->schedule,array('repeat_idx'=>$first->idx))->where('repeat_idx',$schedule->repeat_idx)->execute();
		}
	}
}

$this->updateCalendar($schedule->cid);

$results->success = true;
?>