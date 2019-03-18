<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 *
 * iCal 이벤트를 생성하거나, 가져온다.
 * 
 * @file /modules/calendar/classes/iCal.class.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 3. 16.
 */
class iCal {
	const DATE_TIME_FORMAT = 'Ymd\THis';
	const DATE_TIME_FORMAT_PRETTY = 'F Y H:i:s';
	const RECURRENCE_EVENT = 'Generated recurrence event';
	const ICAL_DATE_TIME_TEMPLATE = 'TZID=%s:';
	const TIME_ZONE_UTC = 'UTC';
	const UNIX_MIN_YEAR = 1970;
	const SECONDS_IN_A_WEEK = 604800;
	const UNIX_FORMAT = 'U';
	
	private $defaultSpan = 2;
	private $cal;
	private $lastKeyword;
	private $eventCount = 0;
	private $validTimeZones = array();
	private $defaultTimeZone;
	
	private $dayOrdinals = array(
		1 => 'first',
		2 => 'second',
		3 => 'third',
		4 => 'fourth',
		5 => 'fifth',
	);
	private $weekdays = array(
		'SU' => 'sunday',
		'MO' => 'monday',
		'TU' => 'tuesday',
		'WE' => 'wednesday',
		'TH' => 'thursday',
		'FR' => 'friday',
		'SA' => 'saturday',
	);
	private $weeks = array(
		'SA' => array('SA', 'SU', 'MO', 'TU', 'WE', 'TH', 'FR'),
		'SU' => array('SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'),
		'MO' => array('MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'),
	);
	private $monthNames = array(
		1  => 'January',
		2  => 'February',
		3  => 'March',
		4  => 'April',
		5  => 'May',
		6  => 'June',
		7  => 'July',
		8  => 'August',
		9  => 'September',
		10 => 'October',
		11 => 'November',
		12 => 'December',
	);
	
	function __construct($url=null,$username=null,$password=null) {
		$this->defaultTimeZone = date_default_timezone_get();
		
		if ($url != null) {
			$ch = curl_init($url);
			if ($username != null && $password != null) {
				curl_setopt($ch,CURLOPT_USERPWD,$username.':'.$password);
			}
			curl_setopt($ch,CURLOPT_TIMEOUT,30);
			curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
			curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
			$data = curl_exec($ch);
			curl_close($ch);
			
			$lines = array_filter(explode("\n",$data));
			$this->initLines($lines);
		}
	}
	
	/**
	 * 각 라인별 데이터를 파싱한다.
	 *
	 * @param string[] $lines iCal 데이터의 각 라인
	 */
	function initLines($lines) {
		$lines = $this->unfold($lines);
		if (stristr($lines[0],'BEGIN:VCALENDAR') !== false) {
			$component = '';
			foreach ($lines as $line) {
				$line = rtrim($line);
				$add = $this->keyValueFromString($line);

				$keyword = $add[0];
				$values  = $add[1]; // May be an array containing multiple values

				if (!is_array($values)) {
					if (!empty($values)) {
						$values = array($values); // Make an array as not already
						$blankArray = array(); // Empty placeholder array
						array_push($values, $blankArray);
					} else {
						$values = array(); // Use blank array to ignore this line
					}
				} elseif (empty($values[0])) {
					$values = array(); // Use blank array to ignore this line
				}

				// Reverse so that our array of properties is processed first
				$values = array_reverse($values);

				foreach ($values as $value) {
					switch ($line) {
						// https://www.kanzaki.com/docs/ical/vtodo.html
						case 'BEGIN:VTODO':
							if (!is_array($value)) {
								$this->todoCount++;
							}

							$component = 'VTODO';
						break;

						// https://www.kanzaki.com/docs/ical/vevent.html
						case 'BEGIN:VEVENT':
							if (!is_array($value)) {
								$this->eventCount++;
							}

							$component = 'VEVENT';
						break;

						// https://www.kanzaki.com/docs/ical/vfreebusy.html
						case 'BEGIN:VFREEBUSY':
							if (!is_array($value)) {
								$this->freeBusyIndex++;
							}

							$component = 'VFREEBUSY';
						break;

						case 'BEGIN:VALARM':
							if (!is_array($value)) {
								$this->alarmCount++;
							}

							$component = 'VALARM';
						break;

						case 'END:VALARM':
							$component = 'VEVENT';
						break;

						case 'BEGIN:DAYLIGHT':
						case 'BEGIN:STANDARD':
						case 'BEGIN:VCALENDAR':
						case 'BEGIN:VTIMEZONE':
							$component = $value;
						break;

						case 'END:DAYLIGHT':
						case 'END:STANDARD':
						case 'END:VCALENDAR':
						case 'END:VEVENT':
						case 'END:VFREEBUSY':
						case 'END:VTIMEZONE':
						case 'END:VTODO':
							$component = 'VCALENDAR';
						break;

						default:
							$this->addCalendarComponentWithKeyAndValue($component, $keyword, $value);
						break;
					}
				}
			}

			$this->processEvents();
			$this->processRecurrences();

			// Apply changes to altered recurrence instances
			if (!empty($this->alteredRecurrenceInstances)) {
				$events = $this->cal['VEVENT'];

				foreach ($this->alteredRecurrenceInstances as $alteredRecurrenceInstance) {
					if (isset($alteredRecurrenceInstance['altered-event'])) {
						$alteredEvent = $alteredRecurrenceInstance['altered-event'];
						$key		  = key($alteredEvent);
						$events[$key] = $alteredEvent[$key];
					}
				}

				$this->cal['VEVENT'] = $events;
			}

			$this->processDateConversions();
		}
	}
	
	/**
	 * Returns the calendar time zone
	 *
	 * @param  boolean $ignoreUtc
	 * @return string
	 */
	function calendarTimeZone($ignoreUtc = false) {
		if (isset($this->cal['VCALENDAR']['X-WR-TIMEZONE'])) {
			$timeZone = $this->cal['VCALENDAR']['X-WR-TIMEZONE'];
		} elseif (isset($this->cal['VTIMEZONE']['TZID'])) {
			$timeZone = $this->cal['VTIMEZONE']['TZID'];
		} else {
			$timeZone = $this->defaultTimeZone;
		}

		// Use default time zone if the calendar's is invalid
		if ($this->isValidIanaTimeZoneId($timeZone) === false) {
			// phpcs:ignore CustomPHPCS.ControlStructures.AssignmentInCondition.Warning
			if (($timeZone = $this->isValidCldrTimeZoneId($timeZone, true)) === false) {
				$timeZone = $this->defaultTimeZone;
			}
		}

		if ($ignoreUtc && strtoupper($timeZone) === self::TIME_ZONE_UTC) {
			return null;
		}

		return $timeZone;
	}
	
	/**
	 * Performs admin tasks on all events as read from the iCal file.
	 * Adds a Unix timestamp to all `{DTSTART|DTEND|RECURRENCE-ID}_array` arrays
	 * Tracks modified recurrence instances
	 *
	 * @return void
	 */
	protected function processEvents() {
		$events = (isset($this->cal['VEVENT'])) ? $this->cal['VEVENT'] : array();

		if (!empty($events)) {
			foreach ($events as $key => $anEvent) {
				foreach (array('DTSTART', 'DTEND', 'RECURRENCE-ID') as $type) {
					if (isset($anEvent[$type])) {
						$date = $anEvent[$type . '_array'][1];

						if (isset($anEvent[$type . '_array'][0]['TZID'])) {
							$date = sprintf(self::ICAL_DATE_TIME_TEMPLATE, $anEvent[$type . '_array'][0]['TZID']) . $date;
						}

						$anEvent[$type . '_array'][2] = $this->iCalDateToUnixTimestamp($date, true, true);
						$anEvent[$type . '_array'][3] = $date;
					}
				}

				if (isset($anEvent['RECURRENCE-ID'])) {
					$uid = $anEvent['UID'];

					if (!isset($this->alteredRecurrenceInstances[$uid])) {
						$this->alteredRecurrenceInstances[$uid] = array();
					}

					$recurrenceDateUtc = $this->iCalDateToUnixTimestamp($anEvent['RECURRENCE-ID_array'][3], true, true);
					$this->alteredRecurrenceInstances[$uid][$key] = $recurrenceDateUtc;
				}

				$events[$key] = $anEvent;
			}

			$eventKeysToRemove = array();

			foreach ($events as $key => $event) {
				$checks[] = !isset($event['RECURRENCE-ID']);
				$checks[] = isset($event['UID']);
				$checks[] = isset($event['UID']) && isset($this->alteredRecurrenceInstances[$event['UID']]);

				if ((bool) array_product($checks)) {
					$eventDtstartUnix = $this->iCalDateToUnixTimestamp($event['DTSTART_array'][3], true, true);

					if (false !== $alteredEventKey = array_search($eventDtstartUnix, $this->alteredRecurrenceInstances[$event['UID']])) {
						$eventKeysToRemove[] = $alteredEventKey;

						$alteredEvent = array_replace_recursive($events[$key], $events[$alteredEventKey]);
						$this->alteredRecurrenceInstances[$event['UID']]['altered-event'] = array($key => $alteredEvent);
					}
				}

				unset($checks);
			}

			if (!empty($eventKeysToRemove)) {
				foreach ($eventKeysToRemove as $eventKeyToRemove) {
					$events[$eventKeyToRemove] = null;
				}
			}

			$this->cal['VEVENT'] = $events;
		}
	}
	
	/**
	 * Processes recurrence rules
	 *
	 * @return void
	 */
	function processRecurrences() {
		$events = (isset($this->cal['VEVENT'])) ? $this->cal['VEVENT'] : array();

		$recurrenceEvents	= array();
		$allRecurrenceEvents = array();

		if (!empty($events)) {
			foreach ($events as $anEvent) {
				if (isset($anEvent['RRULE']) && $anEvent['RRULE'] !== '') {
					// Tag as generated by a recurrence rule
					$anEvent['RRULE_array'][2] = self::RECURRENCE_EVENT;

					$countNb = 0;

					$isAllDayEvent = (strlen($anEvent['DTSTART_array'][1]) === 8) ? true : false;

					$initialStart			 = new DateTime($anEvent['DTSTART_array'][1]);
					$initialStartTimeZoneName = $initialStart->getTimezone()->getName();

					if (isset($anEvent['DTEND'])) {
						$initialEnd			 = new DateTime($anEvent['DTEND_array'][1]);
						$initialEndTimeZoneName = $initialEnd->getTimezone()->getName();
					} else {
						$initialEndTimeZoneName = $initialStartTimeZoneName;
					}

					// Recurring event, parse RRULE and add appropriate duplicate events
					$rrules = array();
					$rruleStrings = explode(';', $anEvent['RRULE']);

					foreach ($rruleStrings as $s) {
						list($k, $v) = explode('=', $s);
						$rrules[$k] = $v;
					}

					// Get frequency
					$frequency = $rrules['FREQ'];
					// Get Start timestamp
					$startTimestamp = $initialStart->getTimestamp();

					if (isset($anEvent['DTEND'])) {
						$endTimestamp = $initialEnd->getTimestamp();
					} elseif (isset($anEvent['DURATION'])) {
						$duration = end($anEvent['DURATION_array']);
						$endTimestamp = $this->parseDuration($anEvent['DTSTART'], $duration);
					} else {
						$endTimestamp = $anEvent['DTSTART_array'][2];
					}

					$eventTimestampOffset = $endTimestamp - $startTimestamp;
					// Get Interval
					$interval = (isset($rrules['INTERVAL']) && $rrules['INTERVAL'] !== '') ? $rrules['INTERVAL'] : 1;

					$dayNumber = null;
					$weekday   = null;

					if (in_array($frequency, array('MONTHLY', 'YEARLY')) && isset($rrules['BYDAY']) && $rrules['BYDAY'] !== '') {
						// Deal with BYDAY
						$byDay	 = $rrules['BYDAY'];
						$dayNumber = intval($byDay);

						if (empty($dayNumber)) { // Returns 0 when no number defined in BYDAY
							if (!isset($rrules['BYSETPOS'])) {
								$dayNumber = 1; // Set first as default
							} elseif (is_numeric($rrules['BYSETPOS'])) {
								$dayNumber = $rrules['BYSETPOS'];
							}
						}

						$weekday = substr($byDay, -2);
					}

					if (is_int($this->defaultSpan)) {
						$untilDefault = date_create('now');
						$untilDefault->modify($this->defaultSpan . ' year');
						$untilDefault->setTime(23, 59, 59); // End of the day
					} else {
						trigger_error('ICal::defaultSpan: User defined value is not an integer', E_USER_NOTICE);
					}

					// Compute EXDATEs
					$exdates = $this->parseExdates($anEvent);

					$countOrig = null;

					if (isset($rrules['UNTIL'])) {
						// Get Until
						$until = strtotime($rrules['UNTIL']);
					} elseif (isset($rrules['COUNT'])) {
						$countOrig = (is_numeric($rrules['COUNT']) && $rrules['COUNT'] > 1) ? $rrules['COUNT'] : 0;

						// Increment count by the number of excluded dates
						$countOrig += sizeof($exdates);

						// Remove one to exclude the occurrence that initialises the rule
						$count = ($countOrig - 1);

						if ($interval >= 2) {
							$count += ($count > 0) ? ($count * $interval) : 0;
						}

						$countNb = 1;
						$offset  = "+{$count} " . $this->frequencyConversion[$frequency];
						$until   = strtotime($offset, $startTimestamp);

						if (in_array($frequency, array('MONTHLY', 'YEARLY'))
							&& isset($rrules['BYDAY']) && $rrules['BYDAY'] !== ''
						) {
							$dtstart = date_create($anEvent['DTSTART']);

							if (!$dtstart) {
								continue;
							}

							for ($i = 1; $i <= $count; $i++) {
								$dtstartClone = clone $dtstart;
								$dtstartClone->modify('next ' . $this->frequencyConversion[$frequency]);
								$offset = "{$this->convertDayOrdinalToPositive($dayNumber, $weekday, $dtstartClone)} {$this->weekdays[$weekday]} of " . $dtstartClone->format('F Y H:i:01');
								$dtstart->modify($offset);
							}

							// Jumping X months forwards doesn't mean
							// the end date will fall on the same day defined in BYDAY
							// Use the largest of these to ensure we are going far enough
							// in the future to capture our final end day
							$until = max($until, $dtstart->format(self::UNIX_FORMAT));
						}

						unset($offset);
					} elseif (isset($untilDefault)) {
						$until = $untilDefault->getTimestamp();
					}

					$until = intval($until);

					// Decide how often to add events and do so
					switch ($frequency) {
						case 'DAILY':
							// Simply add a new event each interval of days until UNTIL is reached
							$offset = "+{$interval} day";
							$recurringTimestamp = strtotime($offset, $startTimestamp);

							while ($recurringTimestamp <= $until) {
								$dayRecurringTimestamp = $recurringTimestamp;

								// Adjust time zone from initial event
								$dayRecurringOffset = 0;
								
								// Add event
								$anEvent['DTSTART'] = date(self::DATE_TIME_FORMAT, $dayRecurringTimestamp) . ($isAllDayEvent || ($initialStartTimeZoneName === 'Z') ? 'Z' : '');
								$anEvent['DTSTART_array'][1] = $anEvent['DTSTART'];
								$anEvent['DTSTART_array'][2] = $dayRecurringTimestamp;
								$anEvent['DTEND_array']	  = $anEvent['DTSTART_array'];
								$anEvent['DTEND_array'][2]  += $eventTimestampOffset;
								$anEvent['DTEND'] = date(
										self::DATE_TIME_FORMAT,
										$anEvent['DTEND_array'][2]
									) . ($isAllDayEvent || ($initialEndTimeZoneName === 'Z') ? 'Z' : '');
								$anEvent['DTEND_array'][1] = $anEvent['DTEND'];

								// Exclusions
								$isExcluded = array_filter($exdates, function ($exdate) use ($anEvent, $dayRecurringOffset) {
									return self::isExdateMatch($exdate, $anEvent, $dayRecurringOffset);
								});

								if (isset($anEvent['UID'])) {
									$searchDate = $anEvent['DTSTART'];
									if (isset($anEvent['DTSTART_array'][0]['TZID'])) {
										$searchDate = sprintf(self::ICAL_DATE_TIME_TEMPLATE, $anEvent['DTSTART_array'][0]['TZID']) . $searchDate;
									}

									if (isset($this->alteredRecurrenceInstances[$anEvent['UID']])) {
										$searchDateUtc = $this->iCalDateToUnixTimestamp($searchDate, true, true);
										if (in_array($searchDateUtc, $this->alteredRecurrenceInstances[$anEvent['UID']])) {
											$isExcluded = true;
										}
									}
								}

								if (!$isExcluded) {
									$anEvent			= $this->processEventIcalDateTime($anEvent);
									$recurrenceEvents[] = $anEvent;
									$this->eventCount++;

									// If RRULE[COUNT] is reached then break
									if (isset($rrules['COUNT'])) {
										$countNb++;

										if ($countNb >= $countOrig) {
											break;
										}
									}
								}

								// Move forwards
								$recurringTimestamp = strtotime($offset, $recurringTimestamp);
							}

							$recurrenceEvents	= $this->trimToRecurrenceCount($rrules, $recurrenceEvents);
							$allRecurrenceEvents = array_merge($allRecurrenceEvents, $recurrenceEvents);
							$recurrenceEvents	= array(); // Reset
						break;

						case 'WEEKLY':
							// Create offset
							$offset = "+{$interval} week";

							$wkst  = (isset($rrules['WKST']) && in_array($rrules['WKST'], array('SA', 'SU', 'MO'))) ? $rrules['WKST'] : 'SU';
							$aWeek = $this->weeks[$wkst];
							$days  = array('SA' => 'Saturday', 'SU' => 'Sunday', 'MO' => 'Monday');

							// Build list of days of week to add events
							$weekdays = $aWeek;

							if (isset($rrules['BYDAY']) && $rrules['BYDAY'] !== '') {
								$byDays = explode(',', $rrules['BYDAY']);
							} else {
								// A textual representation of a day, two letters (e.g. SU)
								$byDays = array(mb_substr(strtoupper($initialStart->format('D')), 0, 2));
							}

							// Get timestamp of first day of start week
							$weekRecurringTimestamp = (strcasecmp($initialStart->format('l'), $this->weekdays[$wkst]) === 0)
								? $startTimestamp
								: strtotime("last {$days[$wkst]} " . $initialStart->format('H:i:s'), $startTimestamp);

							// Step through weeks
							while ($weekRecurringTimestamp <= $until) {
								$dayRecurringTimestamp = $weekRecurringTimestamp;

								// Adjust time zone from initial event
								$dayRecurringOffset = 0;
								
								foreach ($weekdays as $day) {
									// Check if day should be added
									if (in_array($day, $byDays) && $dayRecurringTimestamp > $startTimestamp
										&& $dayRecurringTimestamp <= $until
									) {
										// Add event
										$anEvent['DTSTART'] = date(self::DATE_TIME_FORMAT, $dayRecurringTimestamp) . ($isAllDayEvent || ($initialStartTimeZoneName === 'Z') ? 'Z' : '');
										$anEvent['DTSTART_array'][1] = $anEvent['DTSTART'];
										$anEvent['DTSTART_array'][2] = $dayRecurringTimestamp;
										$anEvent['DTEND_array']	  = $anEvent['DTSTART_array'];
										$anEvent['DTEND_array'][2]  += $eventTimestampOffset;
										$anEvent['DTEND'] = date(
												self::DATE_TIME_FORMAT,
												$anEvent['DTEND_array'][2]
											) . ($isAllDayEvent || ($initialEndTimeZoneName === 'Z') ? 'Z' : '');
										$anEvent['DTEND_array'][1] = $anEvent['DTEND'];

										// Exclusions
										$isExcluded = array_filter($exdates, function ($exdate) use ($anEvent, $dayRecurringOffset) {
											return self::isExdateMatch($exdate, $anEvent, $dayRecurringOffset);
										});

										if (isset($anEvent['UID'])) {
											$searchDate = $anEvent['DTSTART'];
											if (isset($anEvent['DTSTART_array'][0]['TZID'])) {
												$searchDate = sprintf(self::ICAL_DATE_TIME_TEMPLATE, $anEvent['DTSTART_array'][0]['TZID']) . $searchDate;
											}

											if (isset($this->alteredRecurrenceInstances[$anEvent['UID']])) {
												$searchDateUtc = $this->iCalDateToUnixTimestamp($searchDate, true, true);
												if (in_array($searchDateUtc, $this->alteredRecurrenceInstances[$anEvent['UID']])) {
													$isExcluded = true;
												}
											}
										}

										if (!$isExcluded) {
											$anEvent			= $this->processEventIcalDateTime($anEvent);
											$recurrenceEvents[] = $anEvent;
											$this->eventCount++;

											// If RRULE[COUNT] is reached then break
											if (isset($rrules['COUNT'])) {
												$countNb++;

												if ($countNb >= $countOrig) {
													break 2;
												}
											}
										}
									}

									// Move forwards a day
									$dayRecurringTimestamp = strtotime('+1 day', $dayRecurringTimestamp);
								}

								// Move forwards $interval weeks
								$weekRecurringTimestamp = strtotime($offset, $weekRecurringTimestamp);
							}

							$recurrenceEvents	= $this->trimToRecurrenceCount($rrules, $recurrenceEvents);
							$allRecurrenceEvents = array_merge($allRecurrenceEvents, $recurrenceEvents);
							$recurrenceEvents	= array(); // Reset
						break;

						case 'MONTHLY':
							// Create offset
							$recurringTimestamp = $startTimestamp;
							$offset = "+{$interval} month";

							if (isset($rrules['BYMONTHDAY']) && $rrules['BYMONTHDAY'] !== '') {
								// Deal with BYMONTHDAY
								$monthdays = explode(',', $rrules['BYMONTHDAY']);

								while ($recurringTimestamp <= $until) {
									foreach ($monthdays as $key => $monthday) {
										$monthRecurringTimestamp = null;

										if ($key === 0) {
											// Ensure original event conforms to monthday rule
											$anEvent['DTSTART'] = gmdate(
													'Ym' . sprintf('%02d', $monthday) . '\T' . self::TIME_FORMAT,
													strtotime($anEvent['DTSTART'])
												) . ($isAllDayEvent || ($initialStartTimeZoneName === 'Z') ? 'Z' : '');

											$anEvent['DTEND'] = gmdate(
													'Ym' . sprintf('%02d', $monthday) . '\T' . self::TIME_FORMAT,
													isset($anEvent['DURATION'])
														? $this->parseDuration($anEvent['DTSTART'], end($anEvent['DURATION_array']))
														: strtotime($anEvent['DTEND'])
												) . ($isAllDayEvent || ($initialEndTimeZoneName === 'Z') ? 'Z' : '');

											$anEvent['DTSTART_array'][1] = $anEvent['DTSTART'];
											$anEvent['DTSTART_array'][2] = $this->iCalDateToUnixTimestamp($anEvent['DTSTART']);
											$anEvent['DTEND_array'][1]   = $anEvent['DTEND'];
											$anEvent['DTEND_array'][2]   = $this->iCalDateToUnixTimestamp($anEvent['DTEND']);

											// Ensure recurring timestamp confirms to BYMONTHDAY rule
											$monthRecurringTimestamp = $this->iCalDateToUnixTimestamp(
												gmdate(
													'Ym' . sprintf('%02d', $monthday) . '\T' . self::TIME_FORMAT,
													$recurringTimestamp
												) . ($isAllDayEvent || ($initialStartTimeZoneName === 'Z') ? 'Z' : '')
											);
										}

										// Adjust time zone from initial event
										$monthRecurringOffset = 0;
										
										// Add event
										$anEvent['DTSTART'] = date(
												'Ym' . sprintf('%02d', $monthday) . '\T' . self::TIME_FORMAT,
												$monthRecurringTimestamp
											) . ($isAllDayEvent || ($initialStartTimeZoneName === 'Z') ? 'Z' : '');
										$anEvent['DTSTART_array'][1] = $anEvent['DTSTART'];
										$anEvent['DTSTART_array'][2] = $monthRecurringTimestamp;
										$anEvent['DTEND_array']	  = $anEvent['DTSTART_array'];
										$anEvent['DTEND_array'][2]  += $eventTimestampOffset;
										$anEvent['DTEND'] = date(
												self::DATE_TIME_FORMAT,
												$anEvent['DTEND_array'][2]
											) . ($isAllDayEvent || ($initialEndTimeZoneName === 'Z') ? 'Z' : '');
										$anEvent['DTEND_array'][1] = $anEvent['DTEND'];

										// Exclusions
										$isExcluded = array_filter($exdates, function ($exdate) use ($anEvent, $monthRecurringOffset) {
											return self::isExdateMatch($exdate, $anEvent, $monthRecurringOffset);
										});

										if (isset($anEvent['UID'])) {
											$searchDate = $anEvent['DTSTART'];
											if (isset($anEvent['DTSTART_array'][0]['TZID'])) {
												$searchDate = sprintf(self::ICAL_DATE_TIME_TEMPLATE, $anEvent['DTSTART_array'][0]['TZID']) . $searchDate;
											}

											if (isset($this->alteredRecurrenceInstances[$anEvent['UID']])) {
												$searchDateUtc = $this->iCalDateToUnixTimestamp($searchDate, true, true);
												if (in_array($searchDateUtc, $this->alteredRecurrenceInstances[$anEvent['UID']])) {
													$isExcluded = true;
												}
											}
										}

										if (!$isExcluded) {
											$anEvent			= $this->processEventIcalDateTime($anEvent);
											$recurrenceEvents[] = $anEvent;
											$this->eventCount++;

											// If RRULE[COUNT] is reached then break
											if (isset($rrules['COUNT'])) {
												$countNb++;

												if ($countNb >= $countOrig) {
													break 2;
												}
											}
										}
									}

									// Move forwards
									$recurringTimestamp = strtotime($offset, $recurringTimestamp);
								}
							} elseif (isset($rrules['BYDAY']) && $rrules['BYDAY'] !== '') {
								while ($recurringTimestamp <= $until) {
									$monthRecurringTimestamp = $recurringTimestamp;

									// Adjust time zone from initial event
									$monthRecurringOffset = 0;

									$eventStartDesc = "{$this->convertDayOrdinalToPositive($dayNumber, $weekday, $monthRecurringTimestamp)} {$this->weekdays[$weekday]} of "
										. date(self::DATE_TIME_FORMAT_PRETTY, $monthRecurringTimestamp);
									$eventStartTimestamp = strtotime($eventStartDesc);

									if (intval($rrules['BYDAY']) === 0) {
										$lastDayDesc = "last {$this->weekdays[$weekday]} of "
											. date(self::DATE_TIME_FORMAT_PRETTY, $monthRecurringTimestamp);
									} else {
										$lastDayDesc = "{$this->convertDayOrdinalToPositive($dayNumber, $weekday, $monthRecurringTimestamp)} {$this->weekdays[$weekday]} of "
											. date(self::DATE_TIME_FORMAT_PRETTY, $monthRecurringTimestamp);
									}

									$lastDayTimestamp = strtotime($lastDayDesc);

									do {
										// Prevent 5th day of a month from showing up on the next month
										// If BYDAY and the event falls outside the current month, skip the event

										$compareCurrentMonth = date('F', $monthRecurringTimestamp);
										$compareEventMonth   = date('F', $eventStartTimestamp);

										if ($compareCurrentMonth !== $compareEventMonth) {
											$monthRecurringTimestamp = strtotime($offset, $monthRecurringTimestamp);
											continue;
										}

										if ($eventStartTimestamp > $startTimestamp && $eventStartTimestamp <= $until) {
											$anEvent['DTSTART'] = date(self::DATE_TIME_FORMAT, $eventStartTimestamp) . ($isAllDayEvent || ($initialStartTimeZoneName === 'Z') ? 'Z' : '');
											$anEvent['DTSTART_array'][1] = $anEvent['DTSTART'];
											$anEvent['DTSTART_array'][2] = $eventStartTimestamp;
											$anEvent['DTEND_array']	  = $anEvent['DTSTART_array'];
											$anEvent['DTEND_array'][2]  += $eventTimestampOffset;
											$anEvent['DTEND'] = date(
													self::DATE_TIME_FORMAT,
													$anEvent['DTEND_array'][2]
												) . ($isAllDayEvent || ($initialEndTimeZoneName === 'Z') ? 'Z' : '');
											$anEvent['DTEND_array'][1] = $anEvent['DTEND'];

											// Exclusions
											$isExcluded = array_filter($exdates, function ($exdate) use ($anEvent, $monthRecurringOffset) {
												return self::isExdateMatch($exdate, $anEvent, $monthRecurringOffset);
											});

											if (isset($anEvent['UID'])) {
												$searchDate = $anEvent['DTSTART'];
												if (isset($anEvent['DTSTART_array'][0]['TZID'])) {
													$searchDate = sprintf(self::ICAL_DATE_TIME_TEMPLATE, $anEvent['DTSTART_array'][0]['TZID']) . $searchDate;
												}

												if (isset($this->alteredRecurrenceInstances[$anEvent['UID']])) {
													$searchDateUtc = $this->iCalDateToUnixTimestamp($searchDate, true, true);
													if (in_array($searchDateUtc, $this->alteredRecurrenceInstances[$anEvent['UID']])) {
														$isExcluded = true;
													}
												}
											}

											if (!$isExcluded) {
												$anEvent			= $this->processEventIcalDateTime($anEvent);
												$recurrenceEvents[] = $anEvent;
												$this->eventCount++;

												// If RRULE[COUNT] is reached then break
												if (isset($rrules['COUNT'])) {
													$countNb++;

													if ($countNb >= $countOrig) {
														break 2;
													}
												}
											}
										}

										if (isset($rrules['BYSETPOS'])) {
											// BYSETPOS is defined so skip
											// looping through each week
											$lastDayTimestamp = $eventStartTimestamp;
										}

										$eventStartTimestamp += self::SECONDS_IN_A_WEEK;
									} while ($eventStartTimestamp <= $lastDayTimestamp);

									// Move forwards
									$recurringTimestamp = strtotime($offset, $recurringTimestamp);
								}
							}

							$recurrenceEvents	= $this->trimToRecurrenceCount($rrules, $recurrenceEvents);
							$allRecurrenceEvents = array_merge($allRecurrenceEvents, $recurrenceEvents);
							$recurrenceEvents	= array(); // Reset
						break;

						case 'YEARLY':
							// Create offset
							$recurringTimestamp = $startTimestamp;
							$offset = "+{$interval} year";

							// Deal with BYMONTH
							if (isset($rrules['BYMONTH']) && $rrules['BYMONTH'] !== '') {
								$bymonths = explode(',', $rrules['BYMONTH']);
							} else {
								$bymonths = array();
							}

							// Check if BYDAY rule exists
							if (isset($rrules['BYDAY']) && $rrules['BYDAY'] !== '') {
								while ($recurringTimestamp <= $until) {
									$yearRecurringTimestamp = $recurringTimestamp;

									// Adjust time zone from initial event
									$yearRecurringOffset = 0;

									foreach ($bymonths as $bymonth) {
										$eventStartDesc = "{$this->convertDayOrdinalToPositive($dayNumber, $weekday, $yearRecurringTimestamp)} {$this->weekdays[$weekday]}"
											. " of {$this->monthNames[$bymonth]} "
											. gmdate('Y H:i:s', $yearRecurringTimestamp);
										$eventStartTimestamp = strtotime($eventStartDesc);

										if (intval($rrules['BYDAY']) === 0) {
											$lastDayDesc = "last {$this->weekdays[$weekday]}"
												. " of {$this->monthNames[$bymonth]} "
												. gmdate('Y H:i:s', $yearRecurringTimestamp);
										} else {
											$lastDayDesc = "{$this->convertDayOrdinalToPositive($dayNumber, $weekday, $yearRecurringTimestamp)} {$this->weekdays[$weekday]}"
												. " of {$this->monthNames[$bymonth]} "
												. gmdate('Y H:i:s', $yearRecurringTimestamp);
										}

										$lastDayTimestamp = strtotime($lastDayDesc);

										do {
											if ($eventStartTimestamp > $startTimestamp && $eventStartTimestamp <= $until) {
												$anEvent['DTSTART'] = date(self::DATE_TIME_FORMAT, $eventStartTimestamp) . ($isAllDayEvent || ($initialStartTimeZoneName === 'Z') ? 'Z' : '');
												$anEvent['DTSTART_array'][1] = $anEvent['DTSTART'];
												$anEvent['DTSTART_array'][2] = $eventStartTimestamp;
												$anEvent['DTEND_array']	  = $anEvent['DTSTART_array'];
												$anEvent['DTEND_array'][2]  += $eventTimestampOffset;
												$anEvent['DTEND'] = date(
														self::DATE_TIME_FORMAT,
														$anEvent['DTEND_array'][2]
													) . ($isAllDayEvent || ($initialEndTimeZoneName === 'Z') ? 'Z' : '');
												$anEvent['DTEND_array'][1] = $anEvent['DTEND'];

												// Exclusions
												$isExcluded = array_filter($exdates, function ($exdate) use ($anEvent, $yearRecurringOffset) {
													return self::isExdateMatch($exdate, $anEvent, $yearRecurringOffset);
												});

												if (isset($anEvent['UID'])) {
													$searchDate = $anEvent['DTSTART'];
													if (isset($anEvent['DTSTART_array'][0]['TZID'])) {
														$searchDate = sprintf(self::ICAL_DATE_TIME_TEMPLATE, $anEvent['DTSTART_array'][0]['TZID']) . $searchDate;
													}

													if (isset($this->alteredRecurrenceInstances[$anEvent['UID']])) {
														$searchDateUtc = $this->iCalDateToUnixTimestamp($searchDate, true, true);
														if (in_array($searchDateUtc, $this->alteredRecurrenceInstances[$anEvent['UID']])) {
															$isExcluded = true;
														}
													}
												}

												if (!$isExcluded) {
													$anEvent			= $this->processEventIcalDateTime($anEvent);
													$recurrenceEvents[] = $anEvent;
													$this->eventCount++;

													// If RRULE[COUNT] is reached then break
													if (isset($rrules['COUNT'])) {
														$countNb++;

														if ($countNb >= $countOrig) {
															break 3;
														}
													}
												}
											}

											$eventStartTimestamp += self::SECONDS_IN_A_WEEK;
										} while ($eventStartTimestamp <= $lastDayTimestamp);
									}

									// Move forwards
									$recurringTimestamp = strtotime($offset, $recurringTimestamp);
								}
							} else {
								$day = $initialStart->format('d');

								// Step through years
								while ($recurringTimestamp <= $until) {
									$yearRecurringTimestamp = $recurringTimestamp;

									// Adjust time zone from initial event
									$yearRecurringOffset = 0;
									
									$eventStartDescs = array();
									if (isset($rrules['BYMONTH']) && $rrules['BYMONTH'] !== '') {
										foreach ($bymonths as $bymonth) {
											array_push($eventStartDescs, "{$day} {$this->monthNames[$bymonth]} " . gmdate('Y H:i:s', $yearRecurringTimestamp));
										}
									} else {
										array_push($eventStartDescs, $day . gmdate(self::DATE_TIME_FORMAT_PRETTY, $yearRecurringTimestamp));
									}

									foreach ($eventStartDescs as $eventStartDesc) {
										$eventStartTimestamp = strtotime($eventStartDesc);

										if ($eventStartTimestamp > $startTimestamp && $eventStartTimestamp <= $until) {
											$anEvent['DTSTART'] = date(self::DATE_TIME_FORMAT, $eventStartTimestamp) . ($isAllDayEvent || ($initialStartTimeZoneName === 'Z') ? 'Z' : '');
											$anEvent['DTSTART_array'][1] = $anEvent['DTSTART'];
											$anEvent['DTSTART_array'][2] = $eventStartTimestamp;
											$anEvent['DTEND_array']	  = $anEvent['DTSTART_array'];
											$anEvent['DTEND_array'][2]  += $eventTimestampOffset;
											$anEvent['DTEND'] = date(
													self::DATE_TIME_FORMAT,
													$anEvent['DTEND_array'][2]
												) . ($isAllDayEvent || ($initialEndTimeZoneName === 'Z') ? 'Z' : '');
											$anEvent['DTEND_array'][1] = $anEvent['DTEND'];

											// Exclusions
											$isExcluded = array_filter($exdates, function ($exdate) use ($anEvent, $yearRecurringOffset) {
												return self::isExdateMatch($exdate, $anEvent, $yearRecurringOffset);
											});

											if (isset($anEvent['UID'])) {
												$searchDate = $anEvent['DTSTART'];
												if (isset($anEvent['DTSTART_array'][0]['TZID'])) {
													$searchDate = sprintf(self::ICAL_DATE_TIME_TEMPLATE, $anEvent['DTSTART_array'][0]['TZID']) . $searchDate;
												}

												if (isset($this->alteredRecurrenceInstances[$anEvent['UID']])) {
													$searchDateUtc = $this->iCalDateToUnixTimestamp($searchDate, true, true);
													if (in_array($searchDateUtc, $this->alteredRecurrenceInstances[$anEvent['UID']])) {
														$isExcluded = true;
													}
												}
											}

											if (!$isExcluded) {
												$anEvent			= $this->processEventIcalDateTime($anEvent);
												$recurrenceEvents[] = $anEvent;
												$this->eventCount++;

												// If RRULE[COUNT] is reached then break
												if (isset($rrules['COUNT'])) {
													$countNb++;

													if ($countNb >= $countOrig) {
														break 2;
													}
												}
											}
										}
									}

									// Move forwards
									$recurringTimestamp = strtotime($offset, $recurringTimestamp);
								}
							}

							$recurrenceEvents	= $this->trimToRecurrenceCount($rrules, $recurrenceEvents);
							$allRecurrenceEvents = array_merge($allRecurrenceEvents, $recurrenceEvents);
							$recurrenceEvents	= array(); // Reset
						break;
					}
				}
			}

			$events = array_merge($events, $allRecurrenceEvents);

			$this->cal['VEVENT'] = $events;
		}
	}
	
	/**
	 * Extends the `{DTSTART|DTEND|RECURRENCE-ID}_array`
	 * array to include an iCal date time for each event
	 * (`TZID=Timezone:YYYYMMDD[T]HHMMSS`)
	 *
	 * @param  array   $event
	 * @param  integer $index
	 * @return array
	 */
	function processEventIcalDateTime(array $event, $index = 3) {
		$calendarTimeZone = $this->calendarTimeZone(true);

		foreach (array('DTSTART', 'DTEND', 'RECURRENCE-ID') as $type) {
			if (isset($event["{$type}_array"])) {
				$timeZone = (isset($event["{$type}_array"][0]['TZID'])) ? $event["{$type}_array"][0]['TZID'] : $calendarTimeZone;
				$event["{$type}_array"][$index] = ((is_null($timeZone)) ? '' : sprintf(self::ICAL_DATE_TIME_TEMPLATE, $timeZone)) . $event["{$type}_array"][1];
			}
		}

		return $event;
	}
	
	/**
	 * Processes date conversions using the time zone
	 *
	 * Add keys `DTSTART_tz` and `DTEND_tz` to each Event
	 * These keys contain dates adapted to the calendar
	 * time zone depending on the event `TZID`.
	 *
	 * @return void
	 */
	protected function processDateConversions() {
		$events = (isset($this->cal['VEVENT'])) ? $this->cal['VEVENT'] : array();

		if (!empty($events)) {
			foreach ($events as $key => $anEvent) {
				if (!$this->isValidDate($anEvent['DTSTART'])) {
					unset($events[$key]);
					$this->eventCount--;

					continue;
				}
				
				$events[$key]['DTSTART_tz'] = $this->iCalDateWithTimeZone($anEvent, 'DTSTART');

				if ($this->iCalDateWithTimeZone($anEvent, 'DTEND')) {
					$events[$key]['DTEND_tz'] = $this->iCalDateWithTimeZone($anEvent, 'DTEND');
				} elseif ($this->iCalDateWithTimeZone($anEvent, 'DURATION')) {
					$events[$key]['DTEND_tz'] = $this->iCalDateWithTimeZone($anEvent, 'DURATION');
				} elseif ($this->iCalDateWithTimeZone($anEvent, 'DTSTART')) {
					$events[$key]['DTEND_tz'] = $this->iCalDateWithTimeZone($anEvent, 'DTSTART');
				}
			}

			$this->cal['VEVENT'] = $events;
		}
	}
	
	/**
	 * 나뉘어진 라인을 파싱을 위해 합친다.
	 * (https://icalendar.org/iCalendar-RFC-5545/3-1-content-lines.html)
	 *
	 * @param  string[] $lines
	 * @return string[] $lines
	 */
	function unfold($lines) {
		$string = implode("\n",$lines);
		$string = preg_replace('/(\r\n)[ \t]/','',$string);
		$lines  = explode("\r\n",$string);

		return $lines;
	}
	
	/**
	 * iCal 문자열에서 키값과 배열값을 분리한다.
	 *
	 * @param  string $text
	 * @return string[]|boolean $data
	 */
	function keyValueFromString($text) {
		$text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

		$colon = strpos($text, ':');
		$quote = strpos($text, '"');
		if ($colon === false) {
			$matches = array();
		} elseif ($quote === false || $colon < $quote) {
			list($before, $after) = explode(':', $text, 2);
			$matches			  = array($text, $before, $after);
		} else {
			list($before, $text) = explode('"', $text, 2);
			$text				= '"' . $text;
			$matches			 = str_getcsv($text, ':');
			$combinedValue	   = '';

			foreach ($matches as $key => $match) {
				if ($key === 0) {
					if (!empty($before)) {
						$matches[$key] = $before . '"' . $matches[$key] . '"';
					}
				} else {
					if ($key > 1) {
						$combinedValue .= ':';
					}

					$combinedValue .= $matches[$key];
				}
			}

			$matches	= array_slice($matches, 0, 2);
			$matches[1] = $combinedValue;
			array_unshift($matches, $before . $text);
		}

		if (count($matches) === 0) {
			return false;
		}

		if (preg_match('/^([A-Z-]+)([;][\w\W]*)?$/', $matches[1])) {
			$matches = array_splice($matches, 1, 2); // Remove first match and re-align ordering

			// Process properties
			if (preg_match('/([A-Z-]+)[;]([\w\W]*)/', $matches[0], $properties)) {
				// Remove first match
				array_shift($properties);
				// Fix to ignore everything in keyword after a ; (e.g. Language, TZID, etc.)
				$matches[0] = $properties[0];
				array_shift($properties); // Repeat removing first match

				$formatted = array();
				foreach ($properties as $property) {
					// Match semicolon separator outside of quoted substrings
					preg_match_all('~[^' . PHP_EOL . '";]+(?:"[^"\\\]*(?:\\\.[^"\\\]*)*"[^' . PHP_EOL . '";]*)*~', $property, $attributes);
					// Remove multi-dimensional array and use the first key
					$attributes = (sizeof($attributes) === 0) ? array($property) : reset($attributes);

					if (is_array($attributes)) {
						foreach ($attributes as $attribute) {
							// Match equals sign separator outside of quoted substrings
							preg_match_all(
								'~[^' . PHP_EOL . '"=]+(?:"[^"\\\]*(?:\\\.[^"\\\]*)*"[^' . PHP_EOL . '"=]*)*~',
								$attribute,
								$values
							);
							// Remove multi-dimensional array and use the first key
							$value = (sizeof($values) === 0) ? null : reset($values);

							if (is_array($value) && isset($value[1])) {
								// Remove double quotes from beginning and end only
								$formatted[$value[0]] = trim($value[1], '"');
							}
						}
					}
				}

				// Assign the keyword property information
				$properties[0] = $formatted;

				// Add match to beginning of array
				array_unshift($properties, $matches[1]);
				$matches[1] = $properties;
			}

			return $matches;
		} else {
			return false; // Ignore this match
		}
	}
	
	/**
	 * iCal 의 키-값 배열을 추가한다.
	 *
	 * @param  string $component
	 * @param  string|boolean $keyword
	 * @param  string $value
	 * @return void
	 */
	function addCalendarComponentWithKeyAndValue($component, $keyword, $value) {
		if ($keyword == false) {
			$keyword = $this->lastKeyword;
		}

		switch ($component) {
			case 'VALARM':
				$key1 = 'VEVENT';
				$key2 = ($this->eventCount - 1);
				$key3 = $component;

				if (!isset($this->cal[$key1][$key2][$key3]["{$keyword}_array"])) {
					$this->cal[$key1][$key2][$key3]["{$keyword}_array"] = array();
				}

				if (is_array($value)) {
					// Add array of properties to the end
					array_push($this->cal[$key1][$key2][$key3]["{$keyword}_array"], $value);
				} else {
					if (!isset($this->cal[$key1][$key2][$key3][$keyword])) {
						$this->cal[$key1][$key2][$key3][$keyword] = $value;
					}

					if ($this->cal[$key1][$key2][$key3][$keyword] !== $value) {
						$this->cal[$key1][$key2][$key3][$keyword] .= ',' . $value;
					}
				}
			break;

			case 'VEVENT':
				$key1 = $component;
				$key2 = ($this->eventCount - 1);

				if (!isset($this->cal[$key1][$key2]["{$keyword}_array"])) {
					$this->cal[$key1][$key2]["{$keyword}_array"] = array();
				}

				if (is_array($value)) {
					// Add array of properties to the end
					array_push($this->cal[$key1][$key2]["{$keyword}_array"], $value);
				} else {
					if (!isset($this->cal[$key1][$key2][$keyword])) {
						$this->cal[$key1][$key2][$keyword] = $value;
					}

					if ($keyword === 'EXDATE') {
						if (trim($value) === $value) {
							$array = array_filter(explode(',', $value));
							$this->cal[$key1][$key2]["{$keyword}_array"][] = $array;
						} else {
							$value = explode(',', implode(',', $this->cal[$key1][$key2]["{$keyword}_array"][1]) . trim($value));
							$this->cal[$key1][$key2]["{$keyword}_array"][1] = $value;
						}
					} else {
						$this->cal[$key1][$key2]["{$keyword}_array"][] = $value;

						if ($keyword === 'DURATION') {
							$duration = new DateInterval($value);
							array_push($this->cal[$key1][$key2]["{$keyword}_array"], $duration);
						}
					}

					if ($this->cal[$key1][$key2][$keyword] !== $value) {
						$this->cal[$key1][$key2][$keyword] .= ',' . $value;
					}
				}
			break;

			case 'VFREEBUSY':
				$key1 = $component;
				$key2 = ($this->freeBusyIndex - 1);
				$key3 = $keyword;

				if ($keyword === 'FREEBUSY') {
					if (is_array($value)) {
						$this->cal[$key1][$key2][$key3][][] = $value;
					} else {
						$this->freeBusyCount++;

						end($this->cal[$key1][$key2][$key3]);
						$key = key($this->cal[$key1][$key2][$key3]);

						$value = explode('/', $value);
						$this->cal[$key1][$key2][$key3][$key][] = $value;
					}
				} else {
					$this->cal[$key1][$key2][$key3][] = $value;
				}
			break;

			case 'VTODO':
				$this->cal[$component][$this->todoCount - 1][$keyword] = $value;
			break;

			default:
				$this->cal[$component][$keyword] = $value;
			break;
		}

		$this->lastKeyword = $keyword;
	}
	
	/**
	 * Returns a `DateTime` object from an iCal date time format
	 *
	 * @param  string  $icalDate
	 * @param  boolean $forceTimeZone
	 * @param  boolean $forceUtc
	 * @return \DateTime
	 * @throws Exception
	 */
	function iCalDateToDateTime($icalDate, $forceTimeZone = false, $forceUtc = false) {
		/**
		 * iCal times may be in 3 formats, (https://www.kanzaki.com/docs/ical/dateTime.html)
		 *
		 * UTC:	  Has a trailing 'Z'
		 * Floating: No time zone reference specified, no trailing 'Z', use local time
		 * TZID:	 Set time zone as specified
		 *
		 * Use DateTime class objects to get around limitations with `mktime` and `gmmktime`.
		 * Must have a local time zone set to process floating times.
		 */
		$pattern  = '/\AT?Z?I?D?=?(.*):?'; // [1]: Time zone
		$pattern .= '([0-9]{4})';		  // [2]: YYYY
		$pattern .= '([0-9]{2})';		  // [3]: MM
		$pattern .= '([0-9]{2})';		  // [4]: DD
		$pattern .= 'T?';				  //	  Time delimiter
		$pattern .= '([0-9]{0,2})';		// [5]: HH
		$pattern .= '([0-9]{0,2})';		// [6]: MM
		$pattern .= '([0-9]{0,2})';		// [7]: SS
		$pattern .= '(Z?)/';			   // [8]: UTC flag

		preg_match($pattern, $icalDate, $date);

		if (empty($date)) {
			throw new Exception('Invalid iCal date format.');
		}

		// A Unix timestamp cannot represent a date prior to 1 Jan 1970
		$year  = $date[2];
		$isUtc = false;

		if ($year <= self::UNIX_MIN_YEAR) {
			$eventTimeZone = ltrim(strstr($icalDate, ':', true), 'TZID=');

			if (empty($eventTimeZone)) {
				$dateTime = new DateTime($icalDate, new DateTimeZone($this->defaultTimeZone));
			} else {
				$icalDate = ltrim(strstr($icalDate, ':'), ':');
				$dateTime = new DateTime($icalDate, new DateTimeZone($eventTimeZone));
			}
		} else {
			if ($forceTimeZone) {
				// TZID={Time Zone}:
				if (isset($date[1])) {
					$eventTimeZone = rtrim($date[1], ':');
				}

				if ($date[8] === 'Z') {
					$isUtc	= true;
					$dateTime = new DateTime('now', new DateTimeZone(self::TIME_ZONE_UTC));
				} elseif (isset($eventTimeZone) && $this->isValidIanaTimeZoneId($eventTimeZone)) {
					$dateTime = new DateTime('now', new DateTimeZone($eventTimeZone));
				} elseif (isset($eventTimeZone) && $this->isValidCldrTimeZoneId($eventTimeZone)) {
					$dateTime = new DateTime('now', new DateTimeZone($this->isValidCldrTimeZoneId($eventTimeZone, true)));
				} else {
					$dateTime = new DateTime('now', new DateTimeZone($this->defaultTimeZone));
				}
			} else {
				if ($forceUtc) {
					$dateTime = new DateTime('now', new DateTimeZone(self::TIME_ZONE_UTC));
				} else {
					$dateTime = new DateTime('now');
				}
			}

			$dateTime->setDate((int) $date[2], (int) $date[3], (int) $date[4]);
			$dateTime->setTime((int) $date[5], (int) $date[6], (int) $date[7]);
		}

		if ($forceTimeZone && $isUtc) {
			$dateTime->setTimezone(new DateTimeZone($this->defaultTimeZone));
		} elseif ($forceUtc) {
			$dateTime->setTimezone(new DateTimeZone(self::TIME_ZONE_UTC));
		}

		return $dateTime;
	}
	
	/**
	 * Returns a Unix timestamp from an iCal date time format
	 *
	 * @param  string  $icalDate
	 * @param  boolean $forceTimeZone
	 * @param  boolean $forceUtc
	 * @return integer
	 */
	function iCalDateToUnixTimestamp($icalDate, $forceTimeZone = false, $forceUtc = false) {
		$dateTime = $this->iCalDateToDateTime($icalDate, $forceTimeZone, $forceUtc);
		$offset   = 0;

		if ($forceTimeZone) {
			$offset = $dateTime->getOffset();
		}

		return $dateTime->getTimestamp() + $offset;
	}
	
	/**
	 * Returns an array of Events.
	 * Every event is a class with the event
	 * details being properties within it.
	 *
	 * @return array
	 */
	function events() {
		$array = $this->cal;
		$array = isset($array['VEVENT']) ? $array['VEVENT'] : array();
		$events = array();

		if (!empty($array)) {
			foreach ($array as $event) {
				$events[] = new iCalEvent($event);
			}
		}

		return $events;
	}
	
	/**
	 * Returns a sorted array of the events in a given range,
	 * or an empty array if no events exist in the range.
	 *
	 * Events will be returned if the start or end date is contained within the
	 * range (inclusive), or if the event starts before and end after the range.
	 *
	 * If a start date is not specified or of a valid format, then the start
	 * of the range will default to the current time and date of the server.
	 *
	 * If an end date is not specified or of a valid format, then the end of
	 * the range will default to the current time and date of the server,
	 * plus 20 years.
	 *
	 * Note that this function makes use of Unix timestamps. This might be a
	 * problem for events on, during, or after 29 Jan 2038.
	 * See https://en.wikipedia.org/wiki/Unix_time#Representing_the_number
	 *
	 * @param  string|null $rangeStart
	 * @param  string|null $rangeEnd
	 * @return array
	 * @throws \Exception
	 */
	function getEvents($rangeStart,$rangeEnd) {
		// Sort events before processing range
		$events = $this->sortEventsWithOrder($this->events(), SORT_ASC);

		if (empty($events)) {
			return array();
		}

		$extendedEvents = array();

		if (!is_null($rangeStart)) {
			try {
				$rangeStart = new DateTime(date('Y-m-d H:i:s',$rangeStart), new DateTimeZone($this->defaultTimeZone));
			} catch (\Exception $e) {
				error_log("ICal::eventsFromRange: Invalid date passed ({$rangeStart})");
				$rangeStart = false;
			}
		} else {
			$rangeStart = new DateTime('now', new DateTimeZone($this->defaultTimeZone));
		}

		if (!is_null($rangeEnd)) {
			try {
				$rangeEnd = new DateTime(date('Y-m-d H:i:s',$rangeEnd), new DateTimeZone($this->defaultTimeZone));
			} catch (\Exception $e) {
				error_log("ICal::eventsFromRange: Invalid date passed ({$rangeEnd})");
				$rangeEnd = false;
			}
		} else {
			$rangeEnd = new DateTime('now', new DateTimeZone($this->defaultTimeZone));
			$rangeEnd->modify('+20 years');
		}

		// If start and end are identical and are dates with no times...
		if ($rangeEnd->format('His') == 0 && $rangeStart->getTimestamp() == $rangeEnd->getTimestamp()) {
			$rangeEnd->modify('+1 day');
		}

		$rangeStart = $rangeStart->getTimestamp();
		$rangeEnd   = $rangeEnd->getTimestamp();

		foreach ($events as $anEvent) {
			$eventStart = $anEvent->dtstart_array[2];
			$eventEnd   = (isset($anEvent->dtend_array[2])) ? $anEvent->dtend_array[2] : null;

			if (($eventStart >= $rangeStart && $eventStart < $rangeEnd)		 // Event start date contained in the range
				|| ($eventEnd !== null
					&& (
						($eventEnd > $rangeStart && $eventEnd <= $rangeEnd)	 // Event end date contained in the range
						|| ($eventStart < $rangeStart && $eventEnd > $rangeEnd) // Event starts before and finishes after range
					)
				)
			) {
				$extendedEvents[] = $anEvent;
			}
		}

		if (empty($extendedEvents)) {
			return array();
		}

		return $extendedEvents;
	}
	
	/**
	 * Checks if a time zone is valid (IANA or CLDR)
	 *
	 * @param  string $timeZone
	 * @return boolean
	 */
	function isValidTimeZoneId($timeZone) {
		return ($this->isValidIanaTimeZoneId($timeZone) !== false || $this->isValidCldrTimeZoneId($timeZone) !== false);
	}

	/**
	 * Checks if a time zone is a valid IANA time zone
	 *
	 * @param  string $timeZone
	 * @return boolean
	 */
	function isValidIanaTimeZoneId($timeZone) {
		if (in_array($timeZone, $this->validTimeZones)) {
			return true;
		}

		$valid = array();
		$tza   = timezone_abbreviations_list();

		foreach ($tza as $zone) {
			foreach ($zone as $item) {
				$valid[$item['timezone_id']] = true;
			}
		}

		unset($valid['']);

		if (isset($valid[$timeZone]) || in_array($timeZone, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC))) {
			$this->validTimeZones[] = $timeZone;

			return true;
		}

		return false;
	}
	
	/**
	 * Checks if a time zone is a valid CLDR time zone
	 *
	 * @param  string  $timeZone
	 * @param  boolean $doConversion
	 * @return boolean|string
	 */
	function isValidCldrTimeZoneId($timeZone, $doConversion = false) {
		$timeZone = html_entity_decode($timeZone);

		$cldrTimeZones = array(
			'(UTC-12:00) International Date Line West'					  => 'Etc/GMT+12',
			'(UTC-11:00) Coordinated Universal Time-11'					 => 'Etc/GMT+11',
			'(UTC-10:00) Hawaii'											=> 'Pacific/Honolulu',
			'(UTC-09:00) Alaska'											=> 'America/Anchorage',
			'(UTC-08:00) Pacific Time (US & Canada)'						=> 'America/Los_Angeles',
			'(UTC-07:00) Arizona'										   => 'America/Phoenix',
			'(UTC-07:00) Chihuahua, La Paz, Mazatlan'					   => 'America/Chihuahua',
			'(UTC-07:00) Mountain Time (US & Canada)'					   => 'America/Denver',
			'(UTC-06:00) Central America'								   => 'America/Guatemala',
			'(UTC-06:00) Central Time (US & Canada)'						=> 'America/Chicago',
			'(UTC-06:00) Guadalajara, Mexico City, Monterrey'			   => 'America/Mexico_City',
			'(UTC-06:00) Saskatchewan'									  => 'America/Regina',
			'(UTC-05:00) Bogota, Lima, Quito, Rio Branco'				   => 'America/Bogota',
			'(UTC-05:00) Chetumal'										  => 'America/Cancun',
			'(UTC-05:00) Eastern Time (US & Canada)'						=> 'America/New_York',
			'(UTC-05:00) Indiana (East)'									=> 'America/Indianapolis',
			'(UTC-04:00) Asuncion'										  => 'America/Asuncion',
			'(UTC-04:00) Atlantic Time (Canada)'							=> 'America/Halifax',
			'(UTC-04:00) Caracas'										   => 'America/Caracas',
			'(UTC-04:00) Cuiaba'											=> 'America/Cuiaba',
			'(UTC-04:00) Georgetown, La Paz, Manaus, San Juan'			  => 'America/La_Paz',
			'(UTC-04:00) Santiago'										  => 'America/Santiago',
			'(UTC-03:30) Newfoundland'									  => 'America/St_Johns',
			'(UTC-03:00) Brasilia'										  => 'America/Sao_Paulo',
			'(UTC-03:00) Cayenne, Fortaleza'								=> 'America/Cayenne',
			'(UTC-03:00) City of Buenos Aires'							  => 'America/Buenos_Aires',
			'(UTC-03:00) Greenland'										 => 'America/Godthab',
			'(UTC-03:00) Montevideo'										=> 'America/Montevideo',
			'(UTC-03:00) Salvador'										  => 'America/Bahia',
			'(UTC-02:00) Coordinated Universal Time-02'					 => 'Etc/GMT+2',
			'(UTC-01:00) Azores'											=> 'Atlantic/Azores',
			'(UTC-01:00) Cabo Verde Is.'									=> 'Atlantic/Cape_Verde',
			'(UTC) Coordinated Universal Time'							  => 'Etc/GMT',
			'(UTC+00:00) Casablanca'										=> 'Africa/Casablanca',
			'(UTC+00:00) Dublin, Edinburgh, Lisbon, London'				 => 'Europe/London',
			'(UTC+00:00) Monrovia, Reykjavik'							   => 'Atlantic/Reykjavik',
			'(UTC+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna'  => 'Europe/Berlin',
			'(UTC+01:00) Belgrade, Bratislava, Budapest, Ljubljana, Prague' => 'Europe/Budapest',
			'(UTC+01:00) Brussels, Copenhagen, Madrid, Paris'			   => 'Europe/Paris',
			'(UTC+01:00) Sarajevo, Skopje, Warsaw, Zagreb'				  => 'Europe/Warsaw',
			'(UTC+01:00) West Central Africa'							   => 'Africa/Lagos',
			'(UTC+02:00) Amman'											 => 'Asia/Amman',
			'(UTC+02:00) Athens, Bucharest'								 => 'Europe/Bucharest',
			'(UTC+02:00) Beirut'											=> 'Asia/Beirut',
			'(UTC+02:00) Cairo'											 => 'Africa/Cairo',
			'(UTC+02:00) Chisinau'										  => 'Europe/Chisinau',
			'(UTC+02:00) Damascus'										  => 'Asia/Damascus',
			'(UTC+02:00) Harare, Pretoria'								  => 'Africa/Johannesburg',
			'(UTC+02:00) Helsinki, Kyiv, Riga, Sofia, Tallinn, Vilnius'	 => 'Europe/Kiev',
			'(UTC+02:00) Jerusalem'										 => 'Asia/Jerusalem',
			'(UTC+02:00) Kaliningrad'									   => 'Europe/Kaliningrad',
			'(UTC+02:00) Tripoli'										   => 'Africa/Tripoli',
			'(UTC+02:00) Windhoek'										  => 'Africa/Windhoek',
			'(UTC+03:00) Baghdad'										   => 'Asia/Baghdad',
			'(UTC+03:00) Istanbul'										  => 'Europe/Istanbul',
			'(UTC+03:00) Kuwait, Riyadh'									=> 'Asia/Riyadh',
			'(UTC+03:00) Minsk'											 => 'Europe/Minsk',
			'(UTC+03:00) Moscow, St. Petersburg, Volgograd'				 => 'Europe/Moscow',
			'(UTC+03:00) Nairobi'										   => 'Africa/Nairobi',
			'(UTC+03:30) Tehran'											=> 'Asia/Tehran',
			'(UTC+04:00) Abu Dhabi, Muscat'								 => 'Asia/Dubai',
			'(UTC+04:00) Baku'											  => 'Asia/Baku',
			'(UTC+04:00) Izhevsk, Samara'								   => 'Europe/Samara',
			'(UTC+04:00) Port Louis'										=> 'Indian/Mauritius',
			'(UTC+04:00) Tbilisi'										   => 'Asia/Tbilisi',
			'(UTC+04:00) Yerevan'										   => 'Asia/Yerevan',
			'(UTC+04:30) Kabul'											 => 'Asia/Kabul',
			'(UTC+05:00) Ashgabat, Tashkent'								=> 'Asia/Tashkent',
			'(UTC+05:00) Ekaterinburg'									  => 'Asia/Yekaterinburg',
			'(UTC+05:00) Islamabad, Karachi'								=> 'Asia/Karachi',
			'(UTC+05:30) Chennai, Kolkata, Mumbai, New Delhi'			   => 'Asia/Calcutta',
			'(UTC+05:30) Sri Jayawardenepura'							   => 'Asia/Colombo',
			'(UTC+05:45) Kathmandu'										 => 'Asia/Katmandu',
			'(UTC+06:00) Astana'											=> 'Asia/Almaty',
			'(UTC+06:00) Dhaka'											 => 'Asia/Dhaka',
			'(UTC+06:30) Yangon (Rangoon)'								  => 'Asia/Rangoon',
			'(UTC+07:00) Bangkok, Hanoi, Jakarta'						   => 'Asia/Bangkok',
			'(UTC+07:00) Krasnoyarsk'									   => 'Asia/Krasnoyarsk',
			'(UTC+07:00) Novosibirsk'									   => 'Asia/Novosibirsk',
			'(UTC+08:00) Beijing, Chongqing, Hong Kong, Urumqi'			 => 'Asia/Shanghai',
			'(UTC+08:00) Irkutsk'										   => 'Asia/Irkutsk',
			'(UTC+08:00) Kuala Lumpur, Singapore'						   => 'Asia/Singapore',
			'(UTC+08:00) Perth'											 => 'Australia/Perth',
			'(UTC+08:00) Taipei'											=> 'Asia/Taipei',
			'(UTC+08:00) Ulaanbaatar'									   => 'Asia/Ulaanbaatar',
			'(UTC+09:00) Osaka, Sapporo, Tokyo'							 => 'Asia/Tokyo',
			'(UTC+09:00) Pyongyang'										 => 'Asia/Pyongyang',
			'(UTC+09:00) Seoul'											 => 'Asia/Seoul',
			'(UTC+09:00) Yakutsk'										   => 'Asia/Yakutsk',
			'(UTC+09:30) Adelaide'										  => 'Australia/Adelaide',
			'(UTC+09:30) Darwin'											=> 'Australia/Darwin',
			'(UTC+10:00) Brisbane'										  => 'Australia/Brisbane',
			'(UTC+10:00) Canberra, Melbourne, Sydney'					   => 'Australia/Sydney',
			'(UTC+10:00) Guam, Port Moresby'								=> 'Pacific/Port_Moresby',
			'(UTC+10:00) Hobart'											=> 'Australia/Hobart',
			'(UTC+10:00) Vladivostok'									   => 'Asia/Vladivostok',
			'(UTC+11:00) Chokurdakh'										=> 'Asia/Srednekolymsk',
			'(UTC+11:00) Magadan'										   => 'Asia/Magadan',
			'(UTC+11:00) Solomon Is., New Caledonia'						=> 'Pacific/Guadalcanal',
			'(UTC+12:00) Anadyr, Petropavlovsk-Kamchatsky'				  => 'Asia/Kamchatka',
			'(UTC+12:00) Auckland, Wellington'							  => 'Pacific/Auckland',
			'(UTC+12:00) Coordinated Universal Time+12'					 => 'Etc/GMT-12',
			'(UTC+12:00) Fiji'											  => 'Pacific/Fiji',
			"(UTC+13:00) Nuku'alofa"										=> 'Pacific/Tongatapu',
			'(UTC+13:00) Samoa'											 => 'Pacific/Apia',
			'(UTC+14:00) Kiritimati Island'								 => 'Pacific/Kiritimati',
		);

		if (array_key_exists($timeZone, $cldrTimeZones)) {
			if ($doConversion) {
				return $cldrTimeZones[$timeZone];
			} else {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if a date string is a valid date
	 *
	 * @param  string $value
	 * @return boolean
	 * @throws Exception
	 */
	function isValidDate($value) {
		if (!$value) {
			return false;
		}

		try {
			new DateTime($value);

			return true;
		} catch (Exception $e) {
			return false;
		}
	}
	
	/**
	 * Parses a list of excluded dates
	 * to be applied to an Event
	 *
	 * @param  array $event
	 * @return array
	 */
	function parseExdates(array $event) {
		if (empty($event['EXDATE_array'])) {
			return array();
		} else {
			$exdates = $event['EXDATE_array'];
		}

		$output		  = array();
		$currentTimeZone = $this->defaultTimeZone;

		foreach ($exdates as $subArray) {
			end($subArray);
			$finalKey = key($subArray);

			foreach ($subArray as $key => $value) {
				if ($key === 'TZID') {
					$checkTimeZone = $subArray[$key];

					if ($this->isValidIanaTimeZoneId($checkTimeZone)) {
						$currentTimeZone = $checkTimeZone;
					} elseif ($this->isValidCldrTimeZoneId($checkTimeZone)) {
						$currentTimeZone = $this->isValidCldrTimeZoneId($checkTimeZone, true);
					} else {
						$currentTimeZone = $this->defaultTimeZone;
					}
				}
			}
		}

		return $output;
	}
	
	/**
	 * Ensures the recurrence count is enforced against generated recurrence events.
	 *
	 * @param  array $rrules
	 * @param  array $recurrenceEvents
	 * @return array
	 */
	function trimToRecurrenceCount(array $rrules, array $recurrenceEvents) {
		if (isset($rrules['COUNT'])) {
			$recurrenceCount = (intval($rrules['COUNT']) - 1);
			$surplusCount	= (sizeof($recurrenceEvents) - $recurrenceCount);

			if ($surplusCount > 0) {
				$recurrenceEvents  = array_slice($recurrenceEvents, 0, $recurrenceCount);
				$this->eventCount -= $surplusCount;
			}
		}

		return $recurrenceEvents;
	}
	
	/**
	 * Returns a date adapted to the calendar time zone depending on the event `TZID`
	 *
	 * @param  array  $event
	 * @param  string $key
	 * @param  string $format
	 * @return string|boolean
	 */
	function iCalDateWithTimeZone(array $event, $key, $format = self::DATE_TIME_FORMAT) {
		if (!isset($event[$key . '_array']) || !isset($event[$key])) {
			return false;
		}

		$dateArray = $event[$key . '_array'];

		if ($key === 'DURATION') {
			$duration = end($dateArray);
			$dateTime = $this->parseDuration($event['DTSTART'], $duration, null);
		} else {
			$dateTime = new DateTime($dateArray[1], new DateTimeZone(self::TIME_ZONE_UTC));
			$dateTime->setTimezone(new DateTimeZone($this->calendarTimeZone()));
		}

		// Force time zone
		if (isset($dateArray[0]['TZID'])) {
			if ($this->isValidIanaTimeZoneId($dateArray[0]['TZID'])) {
				$dateTime->setTimezone(new DateTimeZone($dateArray[0]['TZID']));
			} elseif ($this->isValidCldrTimeZoneId($dateArray[0]['TZID'])) {
				$dateTime->setTimezone(new DateTimeZone($this->isValidCldrTimeZoneId($dateArray[0]['TZID'], true)));
			} else {
				$dateTime->setTimezone(new DateTimeZone($this->defaultTimeZone));
			}
		}

		if (is_null($format)) {
			$output = $dateTime;
		} else {
			if ($format === self::UNIX_FORMAT) {
				$output = $dateTime->getTimestamp();
			} else {
				$output = $dateTime->format($format);
			}
		}

		return $output;
	}
	
	/**
	 * Gets the number of days between a start and end date
	 *
	 * @param  integer $days
	 * @param  integer $start
	 * @param  integer $end
	 * @return integer
	 */
	function numberOfDays($days, $start, $end) {
		$w	= array(date('w', $start), date('w', $end));
		$base = floor(($end - $start) / self::SECONDS_IN_A_WEEK);
		$sum  = 0;

		for ($day = 0; $day < 7; ++$day) {
			if ($days & pow(2, $day)) {
				$sum += $base + (($w[0] > $w[1]) ? $w[0] <= $day || $day <= $w[1] : $w[0] <= $day && $day <= $w[1]);
			}
		}

		return $sum;
	}
	
	/**
	 * Converts a negative day ordinal to
	 * its equivalent positive form
	 *
	 * @param  integer $dayNumber
	 * @param  integer $weekday
	 * @param  integer|\DateTime $timestamp
	 * @return string
	 */
	function convertDayOrdinalToPositive($dayNumber, $weekday, $timestamp) {
		$dayNumber = empty($dayNumber) ? 1 : $dayNumber; // Returns 0 when no number defined in BYDAY

		$dayOrdinals = $this->dayOrdinals;

		// We only care about negative BYDAY values
		if ($dayNumber >= 1) {
			return $dayOrdinals[$dayNumber];
		}

		$timestamp = (is_object($timestamp)) ? $timestamp : \DateTime::createFromFormat(self::UNIX_FORMAT, $timestamp);
		$start = strtotime('first day of ' . $timestamp->format(self::DATE_TIME_FORMAT_PRETTY));
		$end = strtotime('last day of ' . $timestamp->format(self::DATE_TIME_FORMAT_PRETTY));

		// Used with pow(2, X) so pow(2, 4) is THURSDAY
		$weekdays = array_flip(array_keys($this->weekdays));

		$numberOfDays = $this->numberOfDays(pow(2, $weekdays[$weekday]), $start, $end);

		// Create subset
		$dayOrdinals = array_slice($dayOrdinals, 0, $numberOfDays, true);

		// Reverse only the values
		$dayOrdinals = array_combine(array_keys($dayOrdinals), array_reverse(array_values($dayOrdinals)));

		return $dayOrdinals[$dayNumber * -1];
	}

	/**
	 * Sorts events based on a given sort order
	 *
	 * @param  array   $events
	 * @param  integer $sortOrder Either SORT_ASC, SORT_DESC, SORT_REGULAR, SORT_NUMERIC, SORT_STRING
	 * @return array
	 */
	function sortEventsWithOrder(array $events, $sortOrder = SORT_ASC) {
		$extendedEvents = array();
		$timestamp	  = array();

		foreach ($events as $key => $anEvent) {
			$extendedEvents[] = $anEvent;
			$timestamp[$key]  = $anEvent->dtstart_array[2];
		}

		array_multisort($timestamp, $sortOrder, $extendedEvents);

		return $extendedEvents;
	}
}

class iCalEvent {
	function __construct(array $data = array()) {
		if (!empty($data)) {
			foreach ($data as $key => $value) {
				$variable = self::snakeCase($key);
				$this->{$variable} = self::prepareData($value);
			}
		}
	}
	
	/**
	 * Prepares the data for output
	 *
	 * @param  mixed $value
	 * @return mixed
	 */
	protected function prepareData($value) {
		if (is_string($value)) {
			return stripslashes(trim(str_replace('\n', "\n", $value)));
		} elseif (is_array($value)) {
			return array_map('self::prepareData', $value);
		}

		return $value;
	}
	
	/**
	 * Converts the given input to snake_case
	 *
	 * @param  string $input
	 * @param  string $glue
	 * @param  string $separator
	 * @return string
	 */
	protected static function snakeCase($input, $glue = '_', $separator = '-') {
		$input = preg_split('/(?<=[a-z])(?=[A-Z])/x', $input);
		$input = join($input, $glue);
		$input = str_replace($separator, $glue, $input);

		return strtolower($input);
	}
}
?>