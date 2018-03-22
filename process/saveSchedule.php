<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodule.kr)
 * 
 * 일정을 저장한다.
 *
 * @file /modules/calendar/process/saveSchedule.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2018. 3. 20.
 */
if (defined('__IM__') == false) exit;

$errors = array();
$cid = Request('cid');
$idx = Request('idx');

if ($idx) {
	if ($this->checkPermission($cid,'modify') == false) {
		$results->success = false;
		$results->message = $this->getErrorText('FORBIDDEN');
		return;
	}
	
	$schedule = $this->db()->select($this->table->schedule)->where('idx',$idx)->getOne();
	if ($schedule == null) {
		$results->success = false;
		$results->message = $this->getErrorText('NOT_FOUND');
		return;
	}
} else {
	if ($this->checkPermission($cid,'add') == false) {
		$results->success = false;
		$results->message = $this->getErrorText('FORBIDDEN');
		return;
	}
}

$title = Request('title') ? Request('title') : $errors['title'] = $this->getErrorText('REQUIRED');
$category = Request('category') ? Request('category') : $errors['category'] = $this->getErrorText('REQUIRED');

if ($this->db()->select($this->table->category)->where('idx',$category)->has() == false) {
	$results->success = false;
	$results->message = $this->getErrorText('NOT_FOUND');
	return;
}

$is_allday = Request('is_allday') == 'TRUE';
$start_date = Request('start_date') && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',Request('start_date')) == true ? Request('start_date') : $errors['start_date'] = $this->getErrorText('REQUIRED');
$end_date = Request('end_date') && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',Request('end_date')) == true ? Request('end_date') : $errors['end_date'] = $this->getErrorText('REQUIRED');

if ($is_allday == true) {
	$start_time = strtotime($start_date);
	$end_time = strtotime($end_date.' 24:00:00');
} else {
	$start_time = Request('start_time') && preg_match('/^[0-9]{2}:[0-9]{2}$/',Request('start_time')) == true ? Request('start_time') : $errors['start_time'] = $this->getErrorText('REQUIRED');
	$end_time = Request('end_time') && preg_match('/^[0-9]{2}:[0-9]{2}$/',Request('end_time')) == true ? Request('end_time') : $errors['end_time'] = $this->getErrorText('REQUIRED');
	
	if (count($errors) == 0) {
		$start_time = strtotime($start_date.' '.$start_time.':00');
		$end_time = strtotime($end_date.' '.$end_time.':00');
	}
}

$repeat = Request('repeat');
$repeat_modify = Request('repeat_modify');
if ($repeat == 'NONE' || $repeat_modify == 'ONCE') {
	$repeat_end_date = 0;
} else {
	$repeat_end_date = Request('repeat_end_date') && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',Request('repeat_end_date')) == true ? strtotime(Request('repeat_end_date').' 24:00:00') : $errors['repeat_end_date'] = $this->getErrorText('REQUIRED');
	
	if ($repeat == 'MONTHLY_END' && date('d',$start_time) != date('t',$start_time)) {
		$errors['start_date'] = '일정의 시작일이 달의 마지막날이 아닙니다.';
	}
}

if (count($errors) == 0) {
	$insert = array();
	$insert['cid'] = $cid;
	$insert['category'] = $category;
	$insert['midx'] = $this->IM->getModule('member')->getLogged();
	$insert['title'] = $title;
	$insert['start_time'] = $start_time;
	$insert['end_time'] = $end_time;
	$insert['is_allday'] = $is_allday == true ? 'TRUE' : 'FALSE';
	
	if ($idx) {
		if ($schedule->repeat == 'NONE') {
			$insert['repeat'] = $repeat;
			if ($repeat != 'NONE') $insert['repeat_idx'] = $idx;
			$insert['repeat_end_date'] = $repeat_end_date;
			
			$this->db()->update($this->table->schedule,$insert)->where('idx',$idx)->execute();
			
			if ($repeat != 'NONE') {
				$next_time = $start_time;
				$duration = $end_time - $start_time;
				
				while (true) {
					if ($repeat == 'DAILY') {
						$next_time = $next_time + 60 * 60 * 24;
					} elseif ($repeat == 'WEEKLY') {
						$next_time = $next_time + 60 * 60 * 24 * 7;
					} elseif ($repeat == 'MONTHLY') {
						$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time) + 1,date('d',$next_time),date('Y',$next_time));
					} elseif ($repeat == 'MONTHLY_END') {
						$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time) + 1,1,date('Y',$next_time));
						$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time),date('t',$next_time),date('Y',$next_time));
					} elseif ($repeat == 'ANNUALLY') {
						$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time),date('d',$next_time),date('Y',$next_time) + 1);
					}
					
					if ($next_time > $repeat_end_date) break;
					
					$insert['start_time'] = $next_time;
					$insert['end_time'] = $next_time + $duration;
					$insert['repeat_idx'] = $idx;
					
					$this->db()->insert($this->table->schedule,$insert)->execute();
				}
				
				if ($this->db()->select($this->table->schedule)->where('repeat_idx',$idx)->count() <= 1) {
					$this->db()->update($this->table->schedule,array('repeat'=>'NONE','repeat_end_date'=>0,'repeat_idx'=>0))->where('repeat_idx',$idx)->execute();
				}
			}
		
			$results->success = true;
		} else {
			if ($repeat_modify == 'ONCE') {
				$insert['repeat'] = 'NONE';
				$insert['repeat_end_date'] = 0;
				$insert['repeat_idx'] = 0;
				
				$this->db()->update($this->table->schedule,$insert)->where('idx',$idx)->execute();
				
				if ($this->db()->select($this->table->schedule)->where('repeat_idx',$schedule->repeat_idx)->count() <= 1) {
					$this->db()->update($this->table->schedule,array('repeat'=>'NONE','repeat_end_date'=>0,'repeat_idx'=>0))->where('repeat_idx',$schedule->repeat_idx)->execute();
				}
				
				$results->success = true;
			} else {
				$insert['repeat'] = $repeat;
				$insert['repeat_idx'] = $idx;
				$insert['repeat_end_date'] = $repeat_end_date;
				
				$this->db()->update($this->table->schedule,$insert)->where('idx',$idx)->execute();
				
				if ($repeat != $schedule->repeat) {
					$this->db()->delete($this->table->schedule)->where('idx',$idx,'>')->where('repeat_idx',$schedule->repeat_idx)->execute();
				}
				
				if ($repeat != 'NONE') {
					$start_invertal = $start_time - $schedule->start_time;
					$end_interval = $end_time - $schedule->end_time;
					
					$nexts = $this->db()->select($this->table->schedule)->where('repeat_idx',$schedule->repeat_idx)->where('idx',$idx,'>')->orderBy('idx','asc')->get();
					
					foreach ($nexts as $next) {
						$insert['start_time'] = $next->start_time + $start_invertal;
						$insert['end_time'] = $next->end_time + $end_interval;
						$insert['repeat'] = $repeat;
						$insert['repeat_idx'] = $idx;
						$insert['repeat_end_date'] = $repeat_end_date;
						
						$this->db()->update($this->table->schedule,$insert)->where('idx',$next->idx)->execute();
					}
					
					$max = $this->db()->select($this->table->schedule)->where('repeat_idx',$idx)->orderBy('idx','desc')->limit(1)->getOne();
					$next_time = $max == null ? $start_time : $max->start_time;
					$duration = $end_time - $start_time;
					
					while (true) {
						if ($repeat == 'DAILY') {
							$next_time = $next_time + 60 * 60 * 24;
						} elseif ($repeat == 'WEEKLY') {
							$next_time = $next_time + 60 * 60 * 24 * 7;
						} elseif ($repeat == 'MONTHLY') {
							$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time) + 1,date('d',$next_time),date('Y',$next_time));
						} elseif ($repeat == 'MONTHLY_END') {
							$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time) + 1,1,date('Y',$next_time));
							$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time),date('t',$next_time),date('Y',$next_time));
						} elseif ($repeat == 'ANNUALLY') {
							$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time),date('d',$next_time),date('Y',$next_time) + 1);
						}
						
						if ($next_time > $repeat_end_date) break;
						
						$insert['start_time'] = $next_time;
						$insert['end_time'] = $next_time + $duration;
						$insert['repeat_idx'] = $idx;
						
						$this->db()->insert($this->table->schedule,$insert)->execute();
					}
				}
				
				if ($this->db()->select($this->table->schedule)->where('repeat_idx',$schedule->repeat_idx)->count() <= 1) {
					$this->db()->update($this->table->schedule,array('repeat'=>'NONE','repeat_end_date'=>0,'repeat_idx'=>0))->where('repeat_idx',$schedule->repeat_idx)->execute();
				}
				
				if ($this->db()->select($this->table->schedule)->where('repeat_idx',$idx)->count() <= 1) {
					$this->db()->update($this->table->schedule,array('repeat'=>'NONE','repeat_end_date'=>0,'repeat_idx'=>0))->where('repeat_idx',$idx)->execute();
				}
				
				$results->success = true;
			}
		}
	} else {
		$insert['repeat'] = $repeat;
		$insert['repeat_end_date'] = $repeat_end_date;
		
		$idx = $this->db()->insert($this->table->schedule,$insert)->execute();
		
		if ($idx === false) {
			$results->success = false;
			$results->message = $this->getErrorText('DATABASE_INSERT_ERROR');
			return;
		}
		
		if ($repeat != 'NONE') {
			$this->db()->update($this->table->schedule,array('repeat_idx'=>$idx))->where('idx',$idx)->execute();
			$next_time = $start_time;
			$duration = $end_time - $start_time;
			
			while (true) {
				if ($repeat == 'DAILY') {
					$next_time = $next_time + 60 * 60 * 24;
				} elseif ($repeat == 'WEEKLY') {
					$next_time = $next_time + 60 * 60 * 24 * 7;
				} elseif ($repeat == 'MONTHLY') {
					$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time) + 1,date('d',$next_time),date('Y',$next_time));
				} elseif ($repeat == 'MONTHLY_END') {
					$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time) + 1,1,date('Y',$next_time));
					$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time),date('t',$next_time),date('Y',$next_time));
				} elseif ($repeat == 'ANNUALLY') {
					$next_time = mktime(date('H',$next_time),date('i',$next_time),date('s',$next_time),date('m',$next_time),date('d',$next_time),date('Y',$next_time) + 1);
				}
				
				if ($next_time > $repeat_end_date) break;
				
				$insert['start_time'] = $next_time;
				$insert['end_time'] = $next_time + $duration;
				$insert['repeat_idx'] = $idx;
				
				$this->db()->insert($this->table->schedule,$insert)->execute();
			}
			
			if ($this->db()->select($this->table->schedule)->where('repeat_idx',$idx)->count() <= 1) {
				$this->db()->update($this->table->schedule,array('repeat'=>'NONE','repeat_end_date'=>0,'repeat_idx'=>0))->where('repeat_idx',$idx)->execute();
			}
		}
		
		$results->success = true;
	}
	
	$this->updateCalendar($cid);
} else {
	$results->success = false;
	$results->errors = $errors;
}
?>