<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 *
 * 캘린더 및 일정과 관련된 모든 기능을 제어한다.
 * 
 * @file /modules/calendar/ModuleCalendar.class.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2018. 3. 18.
 */
class ModuleCalendar {
	/**
	 * iModule 및 Module 코어클래스
	 */
	private $IM;
	private $Module;
	
	/**
	 * DB 관련 변수정의
	 *
	 * @private object $DB DB접속객체
	 * @private string[] $table DB 테이블 별칭 및 원 테이블명을 정의하기 위한 변수
	 */
	private $DB;
	private $table;
	
	/**
	 * 언어셋을 정의한다.
	 * 
	 * @private object $lang 현재 사이트주소에서 설정된 언어셋
	 * @private object $oLang package.json 에 의해 정의된 기본 언어셋
	 */
	private $lang = null;
	private $oLang = null;
	
	/**
	 * DB접근을 줄이기 위해 DB에서 불러온 데이터를 저장할 변수를 정의한다.
	 *
	 * @private object[] $calendars 캘린더정보
	 */
	private $calendars = array();
	
	/**
	 * class 선언
	 *
	 * @param iModule $IM iModule 코어클래스
	 * @param Module $Module Module 코어클래스
	 * @see /classes/iModule.class.php
	 * @see /classes/Module.class.php
	 */
	function __construct($IM,$Module) {
		/**
		 * iModule 및 Module 코어 선언
		 */
		$this->IM = $IM;
		$this->Module = $Module;
		
		/**
		 * 모듈에서 사용하는 DB 테이블 별칭 정의
		 * @see 모듈폴더의 package.json 의 databases 참고
		 */
		$this->table = new stdClass();
		$this->table->calendar = 'calendar_table';
		$this->table->category = 'calendar_category_table';
		$this->table->event = 'calendar_event_table';
	}
	
	/**
	 * 모듈 코어 클래스를 반환한다.
	 * 현재 모듈의 각종 설정값이나 모듈의 package.json 설정값을 모듈 코어 클래스를 통해 확인할 수 있다.
	 *
	 * @return Module $Module
	 */
	function getModule() {
		return $this->Module;
	}
	
	/**
	 * 모듈 설치시 정의된 DB코드를 사용하여 모듈에서 사용할 전용 DB클래스를 반환한다.
	 *
	 * @return DB $DB
	 */
	function db() {
		if ($this->DB == null || $this->DB->ping() === false) $this->DB = $this->IM->db($this->getModule()->getInstalled()->database);
		return $this->DB;
	}
	
	/**
	 * 모듈에서 사용중인 DB테이블 별칭을 이용하여 실제 DB테이블 명을 반환한다.
	 *
	 * @param string $table DB테이블 별칭
	 * @return string $table 실제 DB테이블 명
	 */
	function getTable($table) {
		return empty($this->table->$table) == true ? null : $this->table->$table;
	}
	
	/**
	 * URL 을 가져온다.
	 *
	 * @param string $view
	 * @param string $idx
	 * @return string $url
	 */
	function getUrl($view=null,$idx=null) {
		$url = $this->IM->getUrl(null,null,false);
		if ($view == null || $view == false) return $url;
		$url.= '/'.$view;
		
		if ($idx == null || $idx == false) return $url;
		return $url.'/'.$idx;
	}
	
	/**
	 * view 값을 가져온다.
	 *
	 * @return string $view
	 */
	function getView() {
		return $this->IM->getView();
	}
	
	/**
	 * view 값을 변경한다.
	 *
	 * @param string $view
	 */
	function setView($view) {
		return $this->IM->setView($view);
	}
	
	/**
	 * idx 값을 가져온다.
	 *
	 * @return string $idx
	 */
	function getIdx() {
		return $this->IM->getIdx();
	}
	
	/**
	 * [코어] 사이트 외부에서 현재 모듈의 API를 호출하였을 경우, API 요청을 처리하기 위한 함수로 API 실행결과를 반환한다.
	 * 소스코드 관리를 편하게 하기 위해 각 요쳥별로 별도의 PHP 파일로 관리한다.
	 *
	 * @param string $protocol API 호출 프로토콜 (get, post, put, delete)
	 * @param string $api API명
	 * @param any $idx API 호출대상 고유값
	 * @param object $params API 호출시 전달된 파라메터
	 * @return object $datas API처리후 반환 데이터 (해당 데이터는 /api/index.php 를 통해 API호출자에게 전달된다.)
	 * @see /api/index.php
	 */
	function getApi($protocol,$api,$idx=null,$params=null) {
		$data = new stdClass();
		
		$values = (object)get_defined_vars();
		$this->IM->fireEvent('beforeGetApi',$this->getModule()->getName(),$api,$values);
		
		/**
		 * 모듈의 api 폴더에 $api 에 해당하는 파일이 있을 경우 불러온다.
		 */
		if (is_file($this->getModule()->getPath().'/api/'.$api.'.'.$protocol.'.php') == true) {
			INCLUDE $this->getModule()->getPath().'/api/'.$api.'.'.$protocol.'.php';
		}
		
		unset($values);
		$values = (object)get_defined_vars();
		$this->IM->fireEvent('afterGetApi',$this->getModule()->getName(),$api,$values,$data);
		
		return $data;
	}
	
	/**
	 * [사이트관리자] 모듈 관리자패널 구성한다.
	 *
	 * @return string $panel 관리자패널 HTML
	 */
	function getAdminPanel() {
		/**
		 * 설정패널 PHP에서 iModule 코어클래스와 모듈코어클래스에 접근하기 위한 변수 선언
		 */
		$IM = $this->IM;
		$Module = $this;
		
		ob_start();
		INCLUDE $this->getModule()->getPath().'/admin/index.php';
		$panel = ob_get_contents();
		ob_end_clean();
		
		return $panel;
	}
	
	/**
	 * [사이트관리자] 모듈의 전체 컨텍스트 목록을 반환한다.
	 *
	 * @return object $lists 전체 컨텍스트 목록
	 */
	function getContexts() {
		$lists = $this->db()->select($this->table->calendar,'cid,title')->get();
		
		for ($i=0,$loop=count($lists);$i<$loop;$i++) {
			$lists[$i] = array('context'=>$lists[$i]->cid,'title'=>$lists[$i]->title);
		}
		
		return $lists;
	}
	
	/**
	 * 특정 컨텍스트에 대한 제목을 반환한다.
	 *
	 * @param string $context 컨텍스트명
	 * @return string $title 컨텍스트 제목
	 */
	function getContextTitle($context) {
		$calendar = $this->getCalendar($context);
		if ($calendar == null) return '삭제된 캘린더';
		return $calendar->title.'('.$calendar->cid.')';
	}
	
	/**
	 * [사이트관리자] 모듈의 컨텍스트 환경설정을 구성한다.
	 *
	 * @param object $site 설정대상 사이트
	 * @param object $values 설정값
	 * @param string $context 설정대상 컨텍스트명
	 * @return object[] $configs 환경설정
	 */
	function getContextConfigs($site,$values,$context) {
		$configs = array();
		
		$templet = new stdClass();
		$templet->title = $this->IM->getText('text/templet');
		$templet->name = 'templet';
		$templet->type = 'templet';
		$templet->target = 'calendar';
		$templet->use_default = true;
		$templet->value = $values != null && isset($values->templet) == true ? $values->templet : '#';
		$configs[] = $templet;
		
		return $configs;
	}
	
	/**
	 * 사이트맵에 나타날 뱃지데이터를 생성한다.
	 *
	 * @param string $context 컨텍스트종류
	 * @param object $configs 사이트맵 관리를 통해 설정된 페이지 컨텍스트 설정
	 * @return object $badge 뱃지데이터 ($badge->count : 뱃지숫자, $badge->latest : 뱃지업데이트 시각(UNIXTIME), $badge->text : 뱃지텍스트)
	 * @todo check count information
	 */
	function getContextBadge($context,$config) {
		/**
		 * null 일 경우 뱃지를 표시하지 않는다.
		 */
		return null;
	}
	
	/**
	 * 언어셋파일에 정의된 코드를 이용하여 사이트에 설정된 언어별로 텍스트를 반환한다.
	 * 코드에 해당하는 문자열이 없을 경우 1차적으로 package.json 에 정의된 기본언어셋의 텍스트를 반환하고, 기본언어셋 텍스트도 없을 경우에는 코드를 그대로 반환한다.
	 *
	 * @param string $code 언어코드
	 * @param string $replacement 일치하는 언어코드가 없을 경우 반환될 메세지 (기본값 : null, $code 반환)
	 * @return string $language 실제 언어셋 텍스트
	 */
	function getText($code,$replacement=null) {
		if ($this->lang == null) {
			if (is_file($this->getModule()->getPath().'/languages/'.$this->IM->language.'.json') == true) {
				$this->lang = json_decode(file_get_contents($this->getModule()->getPath().'/languages/'.$this->IM->language.'.json'));
				if ($this->IM->language != $this->getModule()->getPackage()->language && is_file($this->getModule()->getPath().'/languages/'.$this->getModule()->getPackage()->language.'.json') == true) {
					$this->oLang = json_decode(file_get_contents($this->getModule()->getPath().'/languages/'.$this->getModule()->getPackage()->language.'.json'));
				}
			} elseif (is_file($this->getModule()->getPath().'/languages/'.$this->getModule()->getPackage()->language.'.json') == true) {
				$this->lang = json_decode(file_get_contents($this->getModule()->getPath().'/languages/'.$this->getModule()->getPackage()->language.'.json'));
				$this->oLang = null;
			}
		}
		
		$returnString = null;
		$temp = explode('/',$code);
		
		$string = $this->lang;
		for ($i=0, $loop=count($temp);$i<$loop;$i++) {
			if (isset($string->{$temp[$i]}) == true) {
				$string = $string->{$temp[$i]};
			} else {
				$string = null;
				break;
			}
		}
		
		if ($string != null) {
			$returnString = $string;
		} elseif ($this->oLang != null) {
			if ($string == null && $this->oLang != null) {
				$string = $this->oLang;
				for ($i=0, $loop=count($temp);$i<$loop;$i++) {
					if (isset($string->{$temp[$i]}) == true) {
						$string = $string->{$temp[$i]};
					} else {
						$string = null;
						break;
					}
				}
			}
			
			if ($string != null) $returnString = $string;
		}
		
		$this->IM->fireEvent('afterGetText',$this->getModule()->getName(),$code,$returnString);
		
		/**
		 * 언어셋 텍스트가 없는경우 iModule 코어에서 불러온다.
		 */
		if ($returnString != null) return $returnString;
		elseif (in_array(reset($temp),array('text','button','action')) == true) return $this->IM->getText($code,$replacement);
		else return $replacement == null ? $code : $replacement;
	}
	
	/**
	 * 상황에 맞게 에러코드를 반환한다.
	 *
	 * @param string $code 에러코드
	 * @param object $value(옵션) 에러와 관련된 데이터
	 * @param boolean $isRawData(옵션) RAW 데이터 반환여부
	 * @return string $message 에러 메세지
	 */
	function getErrorText($code,$value=null,$isRawData=false) {
		$message = $this->getText('error/'.$code,$code);
		if ($message == $code) return $this->IM->getErrorText($code,$value,null,$isRawData);
		
		$description = null;
		switch ($code) {
			default :
				if (is_object($value) == false && $value) $description = $value;
		}
		
		$error = new stdClass();
		$error->message = $message;
		$error->description = $description;
		$error->type = 'BACK';
		
		if ($isRawData === true) return $error;
		else return $this->IM->getErrorText($error);
	}
	
	/**
	 * 템플릿 정보를 가져온다.
	 *
	 * @param string $this->getTemplet($configs) 템플릿명
	 * @return string $package 템플릿 정보
	 */
	function getTemplet($templet=null) {
		$templet = $templet == null ? '#' : $templet;
		
		/**
		 * 사이트맵 관리를 통해 설정된 페이지 컨텍스트 설정일 경우
		 */
		if (is_object($templet) == true) {
			$templet_configs = $templet !== null && isset($templet->templet_configs) == true ? $templet->templet_configs : null;
			$templet = $templet !== null && isset($templet->templet) == true ? $templet->templet : '#';
		} else {
			$templet_configs = null;
		}
		
		/**
		 * 템플릿명이 # 이면 모듈 기본설정에 설정된 템플릿을 사용한다.
		 */
		if ($templet == '#') {
			$templet = $this->getModule()->getConfig('templet');
			$templet_configs = $this->getModule()->getConfig('templet_configs');
		}
		
		return $this->getModule()->getTemplet($templet,$templet_configs);
	}
	
	/**
	 * 페이지 컨텍스트를 가져온다.
	 *
	 * @param string $cid 캘린더 아이디
	 * @param object $configs 사이트맵 관리를 통해 설정된 페이지 컨텍스트 설정
	 * @return string $html 컨텍스트 HTML
	 */
	function getContext($cid,$configs=null) {
		/**
		 * 모듈 기본 스타일 및 자바스크립트
		 */
		$this->IM->addHeadResource('style',$this->getModule()->getDir().'/styles/style.css');
		$this->IM->addHeadResource('script',$this->getModule()->getDir().'/scripts/script.js');
		$this->IM->addHeadResource('script',$this->getModule()->getDir().'/scripts/fullcalendar.min.js');
		
		if (is_file($this->getModule()->getPath().'/scripts/locale/'.$this->IM->getLanguage().'.js') == true) {
			$this->IM->addHeadResource('script',$this->getModule()->getDir().'/scripts/locale/'.$this->IM->getLanguage().'.js');
		}
		
		$calendar = $this->getCalendar($cid);
		if ($calendar == null) return $this->getError('NOT_FOUND_PAGE');
		
		if ($configs == null) $configs = new stdClass();
		if (isset($configs->templet) == false) $configs->templet = '#';
		if ($configs->templet == '#') {
			$configs->templet = $calendar->templet;
			$configs->templet_configs = $calendar->templet_configs;
		} else {
			$configs->templet_configs = isset($configs->templet_configs) == true ? $configs->templet_configs : null;
		}
		
		$html = PHP_EOL.'<!-- CALENDAR MODULE -->'.PHP_EOL.'<div data-role="context" data-type="module" data-module="calendar" data-cid="'.$cid.'">'.PHP_EOL;
		$html.= $this->getHeader($configs);
		$html.= $this->getCalendarContext($cid,$configs);
		$html.= $this->getFooter($configs);
		
		/**
		 * 컨텍스트 컨테이너를 설정한다.
		 */
		$html.= PHP_EOL.'</div>'.PHP_EOL.'<!--// CALENDAR MODULE -->'.PHP_EOL;
		
		return $html;
	}
	
	/**
	 * 모듈 외부컨테이너를 가져온다.
	 *
	 * @param string $cid 캘린더 아이디
	 * @return string $html 컨텍스트 HTML
	 */
	function getContainer($container) {
		$this->IM->addHeadResource('style',$this->getModule()->getDir().'/styles/container.css');
		
		$html = $this->getContext($cid);
		
		$this->IM->removeTemplet();
		$footer = $this->IM->getFooter();
		$header = $this->IM->getHeader();
		
		return $header.$html.$footer;
	}
	
	/**
	 * 컨텍스트 헤더를 가져온다.
	 *
	 * @param object $configs 사이트맵 관리를 통해 설정된 페이지 컨텍스트 설정
	 * @return string $html 컨텍스트 HTML
	 */
	function getHeader($configs=null) {
		/**
		 * 템플릿파일을 호출한다.
		 */
		return $this->getTemplet($configs)->getHeader(get_defined_vars());
	}
	
	/**
	 * 컨텍스트 푸터를 가져온다.
	 *
	 * @param object $configs 사이트맵 관리를 통해 설정된 페이지 컨텍스트 설정
	 * @return string $html 컨텍스트 HTML
	 */
	function getFooter($configs=null) {
		/**
		 * 템플릿파일을 호출한다.
		 */
		return $this->getTemplet($configs)->getFooter(get_defined_vars());
	}
	
	/**
	 * 에러메세지를 반환한다.
	 *
	 * @param string $code 에러코드 (에러코드는 iModule 코어에 의해 해석된다.)
	 * @param object $value 에러코드에 따른 에러값
	 * @return $html 에러메세지 HTML
	 */
	function getError($code,$value=null) {
		/**
		 * iModule 코어를 통해 에러메세지를 구성한다.
		 */
		$error = $this->getErrorText($code,$value,true);
		return $this->IM->getError($error);
	}
	
	/**
	 * 캘린더 컨텍스트를 가져온다.
	 *
	 * @param string $cid 캘린더아이디
	 * @param object $configs 사이트맵 관리를 통해 설정된 페이지 컨텍스트 설정
	 * @return string $html 컨텍스트 HTML
	 */
	function getCalendarContext($cid,$configs=null) {
		$calendar = $this->getCalendar($cid);
		if ($calendar == null) return $this->getError('NOT_FOUND_PAGE');
		
		$categories = $this->db()->select($this->table->category)->where('cid',$cid)->orderBy('sort','asc')->get();
		
		$view = $this->getView() ? $this->getView() : 'month';
		$idxes = $this->getIdx() ? explode('/',$this->getIdx()) : array();
		$year = count($idxes) > 0 ? $idxes[0] : date('Y');
		
		if ($view == 'month') {
			$month = count($idxes) > 1 ? $idxes[1] : date('m');
			$idx = $year.'/'.$month;
		} elseif ($view == 'week') {
			$idx = count($idxes) > 1 ? $year.'/'.$idxes[1] : date('o/W',strtotime($year.'-'.date('m-d')));
		} elseif ($view == 'day') {
			$month = count($idxes) > 1 ? $idxes[1] : date('m');
			$day = count($idxes) > 2 ? $idxes[2] : date('d');
			$idx = $year.'/'.$month.'/'.$day;
		}
		
		$context = PHP_EOL.'<div data-role="calendar" data-writable="'.($this->checkPermission($cid,0,'write') == true ? "TRUE" : "FALSE").'" data-editable="'.($this->checkPermission($cid,0,'edit') == true ? "TRUE" : "FALSE").'" data-selectable="TRUE" data-view="'.$view.'" data-idx="'.$idx.'"></div>'.PHP_EOL;
		
		$permission = new stdClass();
		$permission->write = $this->checkPermission($cid,null,'write');
		
		$header = PHP_EOL.'<div id="ModuleCalendarContext" data-cid="'.$cid.'">'.PHP_EOL;
		$footer = PHP_EOL.'</div>'.PHP_EOL.'<script>Calendar.init("ModuleCalendarContext");</script>';
		
		/**
		 * 템플릿파일을 호출한다.
		 */
		return $this->getTemplet($configs)->getContext('calendar',get_defined_vars(),$header,$footer);
	}
	
	/**
	 * 일정을 추가하는 모달을 가져온다.
	 *
	 * @param string $cid 캘린더아이디
	 * @param int $start 일정 시작시각
	 * @param int $end 일정 종료시각
	 * @return string $html 모달 HTML
	 */
	function getEventWriteModal($cid,$start,$end) {
		$is_allday = date('H',$start) == '00' && date('H',$end) == '00';
		
		$start_date = date('Y-m-d',$start);
		$start_time = $is_allday == true ? '' : date('H:i',$start);
		
		$end_date = date('H',$end) == '00' ? date('Y-m-d',$end - 1) : date('Y-m-d',$end);
		$end_time = $is_allday == true ? '' : (date('H',$end) == '00' ? '24:00' : date('H:i',$end));
		
		$title = '일정추가';
		
		$content = '<input type="hidden" name="cid" value="'.$cid.'">';
		
		$content.= '<div data-module="calendar" data-role="write">';
		$content.= '<div data-role="inputset">';
		$content.= '<div data-role="input"><input type="text" name="title" placeholder="일정명"></div>';
		$content.= '<div data-role="input"><select name="category">';
		$categories = $this->db()->select($this->table->category)->where('cid',$cid)->orderBy('sort','asc')->get();
		foreach ($categories as $category) {
			if ($this->checkPermission($cid,$category->idx,'write') == false) continue;
			$content.= '<option value="'.$category->idx.'" data-color="'.$category->color.'">'.$category->title.'</option>';
		}
		$content.= '</select></div>';
		$content.= '</div>';
		
		$content.= '<div class="line"><span>일정</span></div>';
		
		$content.= '<div data-role="inputset" class="label">';
		$content.= '<div data-role="text" class="label">하루종일</div>';
		$content.= '<div data-role="input"><label><input type="checkbox" name="is_allday" value="TRUE"'.($is_allday == true ? ' checked="checked"' : '').'></label></div>';
		$content.= '</div>';
		
		$content.= '<div data-role="inputset" class="label">';
		$content.= '<div data-role="text" class="label">시작</div>';
		$content.= '<div data-role="input"><input type="date" name="start_date" data-format="YYYY-MM-DD" value="'.$start_date.'"></div>';
		$content.= '<div data-role="input"><input type="time" name="start_time" data-format="HH:mm" value="'.$start_time.'"></div>';
		$content.= '</div>';
		
		$content.= '<div data-role="inputset" class="label">';
		$content.= '<div data-role="text" class="label">종료</div>';
		$content.= '<div data-role="input"><input type="date" name="end_date" data-format="YYYY-MM-DD" value="'.$end_date.'"></div>';
		$content.= '<div data-role="input"><input type="time" name="end_time" data-format="HH:mm" value="'.$end_time.'"></div>';
		$content.= '</div>';
		
		$content.= '<div data-role="inputset" class="label">';
		$content.= '<div data-role="text" class="label">반복주기</div>';
		$content.= '<div data-role="input"><select name="repeat">';
		$content.= '<option value="NONE">반복없음</option>';
		foreach ($this->getText('repeat_type') as $value=>$display) {
			$content.= '<option value="'.$value.'">'.$display.'</option>';
		}
		$content.= '</select></div></div>';
		
		$content.= '<div data-role="inputset" data-name="repeat_interval">';
		$content.= '<div data-role="input"><input type="number" name="repeat_interval" value="1"></div>';
		$content.= '<div data-role="text" data-daily="일 마다" data-weekly="주 마다" data-monthly="개월 마다" data-yearly="년 마다"></div>';
		$content.= '</div>';
		
		$content.= '<div data-role="repeat_rule" data-rule="WEEKLY">';
		$content.= '<ul>';
		foreach ($this->getText('week') as $value=>$name) {
			$content.= '<li><button type="button" data-value="'.$value.'">'.$name.'</button></li>';
		}
		$content.= '</ul>';
		$content.= '</div>';
		
		$content.= '<div data-role="repeat_rule" data-rule="MONTHLY">';
		$content.= '<div data-role="input"><select name="repeat_rule_type">';
		foreach ($this->getText('repeat_rule_type') as $value=>$name) {
			$content.= '<option value="'.$value.'">'.$name.'</option>';
		}
		$content.= '</select></div>';
		$content.= '<ul>';
		for ($i=1;$i<=31;$i++) {
			$content.= '<li><button type="button" data-value="'.$i.'">'.$i.'</button></li>';
		}
		$content.= '</ul>';
		
		$content.= '<div data-role="inputset">';
		$content.= '<div data-role="input"><select name="repeat_rule_monthly_1">';
		foreach ($this->getText('repeat_rule1') as $value=>$name) {
			$content.= '<option value="'.$value.'">'.$name.'</option>';
		}
		$content.= '</select></div>';
		$content.= '<div data-role="input"><select name="repeat_rule_monthly_2">';
		foreach ($this->getText('repeat_rule2') as $value=>$name) {
			$content.= '<option value="'.$value.'">'.$name.'</option>';
		}
		$content.= '</select></div>';
		$content.= '</div>';
		
		$content.= '</div>';
		
		$content.= '<div data-role="repeat_rule" data-rule="YEARLY">';
		$content.= '<ul>';
		for ($i=1;$i<=12;$i++) {
			$content.= '<li><button type="button" data-value="'.$i.'">'.$this->getText('month/'.$i).'</button></li>';
		}
		$content.= '</ul>';
		$content.= '<div data-role="input"><label><input type="checkbox" name="repeat_rule_apply" value="TRUE">조건지정</label></div>';
		$content.= '<div data-role="inputset">';
		$content.= '<div data-role="input"><select name="repeat_rule_yearly_1">';
		foreach ($this->getText('repeat_rule1') as $value=>$name) {
			$content.= '<option value="'.$value.'">'.$name.'</option>';
		}
		$content.= '</select></div>';
		$content.= '<div data-role="input"><select name="repeat_rule_yearly_2">';
		foreach ($this->getText('repeat_rule2') as $value=>$name) {
			$content.= '<option value="'.$value.'">'.$name.'</option>';
		}
		$content.= '</select></div>';
		$content.= '</div>';
		
//		$content.= '<div data-role="text" class="label repeat_end_date">종료</div>';
//		$content.= '<div data-role="input"><input type="date" name="repeat_end_date" data-format="YYYY-MM-DD"></div>';
//		$content.= '</div>';
		
		
		
		$content.= '</div>';
		
		$buttons = array();
		
		$button = new stdClass();
		$button->type = 'close';
		$button->text = '취소';
		$buttons[] = $button;
		
		$button = new stdClass();
		$button->type = 'submit';
		$button->text = '추가';
		$buttons[] = $button;
		
		return $this->getTemplet()->getModal($title,$content,true,array('width'=>400),$buttons);
	}
	
	/**
	 * 일정을 수정하는 모달을 가져온다.
	 *
	 * @param string $idx 일정고유번호
	 * @return string $html 모달 HTML
	 */
	function getModifyModal($idx) {
		$schedule = $this->db()->select($this->table->schedule)->where('idx',$idx)->getOne();
		if ($schedule == null) return;
		
		$is_allday = $schedule->is_allday == 'TRUE';
		
		$start_date = date('Y-m-d',$schedule->start_time);
		$start_time = $is_allday == true ? '' : date('H:i',$schedule->start_time);
		
		$end_date = date('H',$schedule->end_time) == '00' ? date('Y-m-d',$schedule->end_time - 1) : date('Y-m-d',$schedule->end_time);
		$end_time = $is_allday == true ? '' : (date('H',$schedule->end_time) == '00' ? '24:00' : date('H:i',$schedule->end_time));
		
		$title = '일정수정';
		
		$content = '<input type="hidden" name="cid" value="'.$schedule->cid.'">';
		$content.= '<input type="hidden" name="idx" value="'.$schedule->idx.'">';
		
		$content.= '<div data-module="calendar" data-role="schedule">';
		$content.= '<div data-role="inputset">';
		$content.= '<div data-role="input"><input type="text" name="title" placeholder="일정명" value="'.GetString($schedule->title,'input').'"></div>';
		$content.= '<div data-role="text" class="color"><i data-role="color"></i></div>';
		$content.= '<div data-role="input"><select name="category">';
		$categories = $this->db()->select($this->table->category)->where('cid',$schedule->cid)->orderBy('sort','asc')->get();
		foreach ($categories as $category) {
			$content.= '<option value="'.$category->idx.'" data-color="'.$category->color.'"'.($category->idx == $schedule->category ? ' selected="selected"' : '').'>'.$category->title.'</option>';
		}
		$content.= '</select></div>';
		$content.= '</div>';
		
		$content.= '<div class="line"><span>일정</span></div>';
		
		$content.= '<div data-role="inputset" class="label">';
		$content.= '<div data-role="text" class="label">하루종일</div>';
		$content.= '<div data-role="input"><label><input type="checkbox" name="is_allday" value="TRUE"'.($is_allday == true ? ' checked="checked"' : '').'></label></div>';
		$content.= '</div>';
		
		$content.= '<div data-role="inputset" class="label">';
		$content.= '<div data-role="text" class="label">시작</div>';
		$content.= '<div data-role="input"><input type="date" name="start_date" data-format="YYYY-MM-DD" value="'.$start_date.'"></div>';
		$content.= '<div data-role="input"><input type="time" name="start_time" data-format="HH:mm" value="'.$start_time.'"></div>';
		$content.= '</div>';
		
		$content.= '<div data-role="inputset" class="label">';
		$content.= '<div data-role="text" class="label">종료</div>';
		$content.= '<div data-role="input"><input type="date" name="end_date" data-format="YYYY-MM-DD" value="'.$end_date.'"></div>';
		$content.= '<div data-role="input"><input type="time" name="end_time" data-format="HH:mm" value="'.$end_time.'"></div>';
		$content.= '</div>';
		
		if ($schedule->repeat != 'NONE') {
			$content.= '<div class="line"><span>반복되고 있는 일정 수정</span></div>';
			$content.= '<div data-role="inputset" class="label">';
			$content.= '<div data-role="text" class="label">향후일정</div>';
			$content.= '<div data-role="input"><select name="repeat_modify"><option value="NEXT">현재 일정 이후 반복되는 일정도 함께 수정합니다.</option><option value="ONCE">현재 일정만 수정합니다.</option></select></div>';
			$content.= '</div>';
		}
		
		$content.= '<div data-role="inputset" class="label">';
		$content.= '<div data-role="text" class="label">반복</div>';
		$content.= '<div data-role="input"><select name="repeat">';
		$content.= '<option value="NONE">반복없음</option>';
		foreach ($this->getText('repeat_type') as $value=>$display) {
			$content.= '<option value="'.$value.'"'.($value == $schedule->repeat ? ' selected="selected"' : '').'>'.$display.'</option>';
		}
		$content.= '</select></div>';
		$content.= '<div data-role="text" class="label repeat_end_date">종료</div>';
		$content.= '<div data-role="input"><input type="date" name="repeat_end_date" data-format="YYYY-MM-DD" value="'.date('Y-m-d',$schedule->repeat_end_date - 1).'"></div>';
		$content.= '</div>';
		
		$buttons = array();
		
		$button = new stdClass();
		$button->type = 'close';
		$button->text = '취소';
		$buttons[] = $button;
		
		$button = new stdClass();
		$button->type = 'submit';
		$button->text = '수정';
		$buttons[] = $button;
		
		return $this->getTemplet()->getModal($title,$content,true,array('width'=>500),$buttons);
	}
	
	/**
	 * 일정을 수정하는 모달을 가져온다.
	 *
	 * @param string $idx 일정고유번호
	 * @return string $html 모달 HTML
	 */
	function getDeleteModal($idx) {
		$schedule = $this->db()->select($this->table->schedule)->where('idx',$idx)->getOne();
		if ($schedule == null) return;
		
		$title = '일정삭제';
		
		$content = '<input type="hidden" name="idx" value="'.$schedule->idx.'">';
		$content.= '<div data-module="calendar" data-role="schedule">';
		$content.= '<div data-ro;e="messeage">현재 일정을 삭제하시겠습니까?</div>';
		
		if ($schedule->repeat != 'NONE') {
			$content.= '<div class="line"><span>반복되고 있는 일정 삭제</span></div>';
			$content.= '<div data-role="input"><select name="repeat_delete"><option value="NEXT">현재 일정 이후 반복되는 일정도 함께 삭제합니다.</option><option value="ONCE">현재 일정만 삭제합니다.</option></select></div>';
		}
		$content.= '</div>';
		
		$buttons = array();
		
		$button = new stdClass();
		$button->type = 'close';
		$button->text = '취소';
		$buttons[] = $button;
		
		$button = new stdClass();
		$button->type = 'submit';
		$button->text = '삭제';
		$button->class = 'danger';
		$buttons[] = $button;
		
		return $this->getTemplet()->getModal($title,$content,true,array('width'=>400),$buttons);
	}
	
	/**
	 * 반복되는 일정에 대한 기간수정 확인 모달을 가져온다.
	 *
	 * @param string $idx 일정고유번호
	 * @param int $start 수정된 일정 시작시각
	 * @param int $end 수정된 일정 종료시각
	 * @return string $html 모달 HTML
	 */
	function getDurationConfirmModal($idx,$start,$end) {
		$title = '반복일정 확인';
		
		$content = '<input type="hidden" name="idx" value="'.$idx.'">';
		$content.= '<input type="hidden" name="start" value="'.date('Y-m-d H:i:s',$start).'">';
		$content.= '<input type="hidden" name="end" value="'.date('Y-m-d H:i:s',$end).'">';
		
		$content.= '<div data-role="message">현재 일정은 반복되고 있는 있는 일정의 일부입니다.<br>반복되고 있는 일정에 대한 수정여부를 선택하여 주십시오.</div>';
		$content.= '<div data-role="input"><select name="repeat"><option value="NEXT">현재 일정 이후 반복되는 일정도 함께 수정합니다.</option><option value="ONCE">현재 일정만 수정합니다.</option></select></div>';
		
		$buttons = array();
		
		$button = new stdClass();
		$button->type = 'close';
		$button->text = '취소';
		$buttons[] = $button;
		
		$button = new stdClass();
		$button->type = 'submit';
		$button->text = '확인';
		$buttons[] = $button;
		
		return $this->getTemplet()->getModal($title,$content,true,array('width'=>500),$buttons);
	}
	
	/**
	 * 일정 상세보기 모달을 가져온다.
	 *
	 * @param string $idx 일정고유번호
	 * @return string $html 모달 HTML
	 */
	function getViewModal($idx) {
		$schedule = $this->db()->select($this->table->schedule)->where('idx',$idx)->getOne();
		if ($schedule == null) return '';
		
		$category = $this->db()->select($this->table->category)->where('idx',$schedule->category)->getOne();
		$title = GetString($schedule->title,'replace');
		
		$content = '<input type="hidden" name="idx" value="'.$idx.'">';
		
		$content.= '<div data-module="calendar" data-role="view">';
		$content.= '<div class="category"><i style="background:'.$category->color.'"></i>'.$category->title.'</div>';
		$content.= '<div class="date">';
		if ($schedule->is_allday == 'TRUE') {
			$content.= GetTime('Y.m.d(D)',$schedule->start_time);
			if (date('Ymd',$schedule->start_time) != date('Ymd',$schedule->end_time - 1)) {
				$content.= ' ~ '.GetTime('Y.m.d(D)',$schedule->end_time - 1);
			}
		} else {
			$content.= GetTime('Y.m.d(D) H:i',$schedule->start_time);
			if (date('Ymd',$schedule->start_time) != date('Ymd',$schedule->end_time - 1)) {
				$content.= ' ~ '.GetTime('Y.m.d(D) H:i',$schedule->end_time);
			} else {
				$content.= ' ~ '.GetTime('H:i',$schedule->end_time);
			}
		}
		$content.= '</div>';
		
		$member = $this->IM->getModule('member')->getMember($schedule->midx);
		$content.= '<div class="author">'.$this->IM->getModule('member')->getMemberPhoto($schedule->midx).$this->IM->getModule('member')->getMemberNickname($schedule->midx,true,'Unknown').'</div>';
		
		if ($schedule->repeat != 'NONE') {
			$repeat = $this->db()->select($this->table->schedule)->where('idx',$schedule->repeat_idx)->getOne();
			
			if ($repeat->repeat == 'DAILY') {
				$repeat_start_type = $repeat->is_allday == 'TRUE' ? '' : date('H:i',$repeat->start_time);
			} elseif ($repeat->repeat == 'WEEKLY') {
				$repeat_start_type = GetTime('l',$repeat->start_time);
			} elseif ($repeat->repeat == 'MONTHLY') {
				$repeat_start_type = str_replace('{DATE}',date('d',$repeat->start_time),$this->getText('text/date'));
			} elseif ($repeat->repeat == 'MONTHLY_END') {
				$repeat_start_type = '';
			} elseif ($repeat->repeat == 'ANNUALLY') {
				$repeat_start_type = str_replace('{MONTH}',date('m',$repeat->start_time),$this->getText('text/month')).' '.str_replace('{DATE}',date('d',$repeat->start_time),$this->getText('text/date'));
			}
			
			$content.= '<div class="repeat">';
			$content.= str_replace(array('{REPEAT_START_DATE}','{REPEAT_END_DATE}','{REPEAT_TYPE}','{REPEAT_START_TYPE}'),array(GetTime('Y.m.d(D)',$repeat->start_time),GetTime('Y.m.d(D)',$repeat->repeat_end_date - 1),$this->getText('repeat_type/'.$repeat->repeat),$repeat_start_type),$this->getText('text/repeat_text'));
			$content.= '</div>';
		}
		$content.= '</div>';
		
		$buttons = array();
		
		if ($schedule->midx == $this->IM->getModule('member')->getLogged() || $this->checkPermission($schedule->cid,'modify') == true) {
			$button = new stdClass();
			$button->type = 'modify';
			$button->text = '수정';
			$buttons[] = $button;
			
			$button = new stdClass();
			$button->type = 'delete';
			$button->text = '삭제';
			$button->class = 'danger';
			$buttons[] = $button;
		}
		
		$button = new stdClass();
		$button->type = 'submit';
		$button->text = '확인';
		$button->class = 'submit';
		$buttons[] = $button;
		
		return $this->getTemplet()->getModal($title,$content,true,array('width'=>400),$buttons);
	}
	
	/**
	 * 캘린더정보를 가져온다.
	 *
	 * @param string $cid 캘린더아이디
	 * @return object $calendar
	 */
	function getCalendar($cid) {
		if (isset($this->calendars[$cid]) == true) return $this->calendars[$cid];
		$calendar = $this->db()->select($this->table->calendar)->where('cid',$cid)->getOne();
		if ($calendar == null) {
			$this->calendars[$cid] = null;
		} else {
			$calendar->templet_configs = json_decode($calendar->templet_configs);
			
			$this->calendars[$cid] = $calendar;
		}
		
		return $this->calendars[$cid];
	}
	
	/**
	 * 권한을 확인한다.
	 *
	 * @param string $cid 캘린더아이디
	 * @param int $category 카테고리고유값
	 * @param string $type 확인할 권한코드
	 * @return boolean $hasPermssion
	 */
	function checkPermission($cid,$category,$type) {
		$categories = $this->db()->select($this->table->category)->where('cid',$cid);
		if ($category) $categories->where('idx',$category);
		$categories = $categories->get();
		
		foreach ($categories as $category) {
			$permission = json_decode($category->permission);
			if (isset($permission->{$type}) == true && $this->IM->parsePermissionString($permission->{$type}) == true) return true;
		}
		
		return false;
	}
	
	/**
	 * 캘린더 정보를 업데이트한다.
	 *
	 * @param string $cid 캘린더아이디
	 */
	function updateCalendar($cid) {
		$total = $this->db()->select($this->table->schedule)->where('cid',$cid)->count();
		$this->db()->update($this->table->calendar,array('schedule'=>$total))->where('cid',$cid)->execute();
	}
	
	/**
	 * iCal 이벤트를 가져온다.
	 *
	 * @param string $url iCal 주소
	 * @return object[] $events
	 */
	function getICal($url,$username=null,$password=null) {
		REQUIRE_ONCE $this->getModule()->getPath().'/classes/iCal.class.php';
		return new iCal($url,$username,$password);
	}
	
	/**
	 * 현재 모듈에서 처리해야하는 요청이 들어왔을 경우 처리하여 결과를 반환한다.
	 * 소스코드 관리를 편하게 하기 위해 각 요쳥별로 별도의 PHP 파일로 관리한다.
	 * 작업코드가 '@' 로 시작할 경우 사이트관리자를 위한 작업으로 최고관리자 권한이 필요하다.
	 *
	 * @param string $action 작업코드
	 * @return object $results 수행결과
	 * @see /process/index.php
	 */
	function doProcess($action) {
		$results = new stdClass();
		
		$values = (object)get_defined_vars();
		$this->IM->fireEvent('beforeDoProcess',$this->getModule()->getName(),$action,$values);
		
		/**
		 * 모듈의 process 폴더에 $action 에 해당하는 파일이 있을 경우 불러온다.
		 */
		if (is_file($this->getModule()->getPath().'/process/'.$action.'.php') == true) {
			INCLUDE $this->getModule()->getPath().'/process/'.$action.'.php';
		}
		
		unset($values);
		$values = (object)get_defined_vars();
		$this->IM->fireEvent('afterDoProcess',$this->getModule()->getName(),$action,$values,$results);
		
		return $results;
	}
}
?>