<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodule.kr)
 * 
 * 일정의 시작 및 종료시각을 저장한다.
 *
 * @file /modules/calendar/process/saveDuration.php
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

if ($schedule->midx != $this->IM->getModule('member')->getLogged() && $this->checkPermission($schedule->cid,'modify') == false) {
	$results->success = false;
	$results->message = $this->getErrorText('FORBIDDEN');
	return;
}

$start_time = preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/',Request('start')) == true ? strtotime(Request('start')) : null;
$end_time = preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/',Request('end')) == true ? strtotime(Request('end')) : null;

if ($start_time == null || $end_time == null) {
	$results->success = false;
	$results->message = '날짜 또는 시간이 잘못 입력되었습니다.';
	return;
}

if ($schedule->repeat == 'NONE') {
	$update = array();
	$update['start_time'] = $start_time;
	$update['end_time'] = $end_time;
	
	$this->db()->update($this->table->schedule,$update)->where('idx',$idx)->execute();
	$results->success = true;
} else {
	$repeat = Request('repeat');
	if ($repeat != 'NEXT' && $repeat != 'ONCE') {
		$results->success = false;
		return;
	}
	
	$update = array();
	$update['start_time'] = $start_time;
	$update['end_time'] = $end_time;
	
	if ($repeat == 'ONCE') {
		$update['repeat'] = 'NONE';
		$update['repeat_end_date'] = 0;
		$update['repeat_idx'] = 0;
		
		$this->db()->update($this->table->schedule,$update)->where('idx',$idx)->execute();
		if ($this->db()->select($this->table->schedule)->where('repeat_idx',$schedule->repeat_idx)->count() <= 1) {
			$this->db()->update($this->table->schedule,array('repeat'=>'NONE','repeat_end_date'=>0,'repeat_idx'=>0))->where('repeat_idx',$schedule->repeat_idx)->execute();
		}
		
		$results->success = true;
	} else {
		$update['repeat_idx'] = $idx;
		$this->db()->update($this->table->schedule,$update)->where('idx',$idx)->execute();
		
		$start_invertal = $start_time - $schedule->start_time;
		$end_interval = $end_time - $schedule->end_time;
		
		$nexts = $this->db()->select($this->table->schedule)->where('repeat_idx',$schedule->repeat_idx)->where('idx',$idx,'>')->orderBy('idx','asc')->get();
		
		foreach ($nexts as $next) {
			$update['start_time'] = $next->start_time + $start_invertal;
			$update['end_time'] = $next->end_time + $end_interval;
			$update['repeat_idx'] = $idx;
			
			$this->db()->update($this->table->schedule,$update)->where('idx',$next->idx)->execute();
		}
		
		if ($this->db()->select($this->table->schedule)->where('repeat_idx',$schedule->repeat_idx)->count() <= 1) {
			$this->db()->update($this->table->schedule,array('repeat'=>'NONE','repeat_end_date'=>0,'repeat_idx'=>0))->where('repeat_idx',$schedule->repeat_idx)->execute();
		}
		
		if ($this->db()->select($this->table->schedule)->where('repeat_idx',$idx)->count() <= 1) {
			$this->db()->update($this->table->schedule,array('repeat'=>'NONE','repeat_end_date'=>0,'repeat_idx'=>0))->where('repeat_idx',$idx)->execute();
		} else {
			$last = $this->db()->select($this->table->schedule)->where('repeat_idx',$idx)->orderBy('start_time','desc')->limit(1)->getOne();
			$this->db()->update($this->table->schedule,array('repeat_end_date'=>strtotime(date('Y-m-d',$last->start_time).' 24:00:00')))->where('repeat_idx',$idx)->execute();
		}
		
		$results->success = true;
	}
}
?>