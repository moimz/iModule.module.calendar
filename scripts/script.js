/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 *
 * 캘린더 UI 를 구성한다.
 * 
 * @file /modules/calendar/scripts/script.js
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2018. 3. 18.
 */
var Calendar = {
	getUrl:function(view,idx) {
		return ENV.getUrl(null,null,view,idx);
	},
	init:function(id) {
		var $context = $("#"+id);
		
		if (id == "ModuleCalendarContext") {
			var $calendar = $("div[data-role=calendar]",$context);
			var views = {"month":"month","week":"agendaWeek","day":"agendaDay"};
			var view = $calendar.attr("data-view");
			var idxes = $calendar.attr("data-idx").split("/");
			
			if (view == "month") {
				if (moment().format("YYYY/MM") == $calendar.attr("data-idx")) {
					var defaultDate = moment();
				} else {
					var defaultDate = moment(idxes[0]+"-"+idxes[1]+"-01");
				}
			} else if (view == "week") {
				if (moment().format("gggg/ww") == $calendar.attr("data-idx")) {
					var defaultDate = moment();
				} else {
					var defaultDate = moment().year(idxes[0]).weeks(idxes[1]);
				}
			} else if (view == "day") {
				if (moment().format("YYYY/MM/DD") == $calendar.attr("data-idx")) {
					var defaultDate = moment();
				} else {
					var defaultDate = moment(moment(idxes[0]+"-"+idxes[1]+"-"+idxes[2]));
				}
			}
			
			$calendar.fullCalendar({
				customButtons:{
					write:{
						text:"일정추가",
						click:function() {
							Calendar.write(moment().format("YYYY-MM-DD"),moment().add(1,"day").format("YYYY-MM-DD"));
						}
					},
					share:{
						text:"구독",
						click:function() {
							Calendar.share();
						}
					}
				},
				header:{
					left:"prev,next today",
					center:"prev title next",
					right:$calendar.attr("data-writable") == "TRUE" ? "month,agendaWeek,agendaDay write share" : "month,agendaWeek,agendaDay share"
				},
				timezone:"local",
				defaultView:views[view],
				defaultDate:defaultDate,
				editable:$calendar.attr("data-writable") == "TRUE" || $calendar.attr("data-editable") == "TRUE",
				selectable:$calendar.attr("data-selectable") == "TRUE",
				displayEventEnd:false,
				handleWindowResize:false,
				contentHeight:"auto",
				eventSources:[{
					events:function(start,end,timezone,callback) {
						$.send(ENV.getProcessUrl("calendar","getEvents"),{cid:$context.attr("data-cid"),start:start.format("YYYY-MM-DD"),end:end.format("YYYY-MM-DD")},function(result) {
							var events = [];
							if (result.success == true) {
								events = result.events;
							}
							
							callback(events);
						})
					}
				}],
				eventClick:function(event,e,view) {
					$(this).addClass("opened");
					Calendar.view(event.data);
					e.stopPropagation();
				},
				eventDrop:function(event,delta,revert) {
					Calendar.updateDuration(event,revert);
				},
				eventResize:function(event,delta,revert) {
					Calendar.updateDuration(event,revert);
				},
				select:function(start,end) {
					Calendar.write(start.format("YYYY-MM-DD HH:mm:ss"),end.format("YYYY-MM-DD HH:mm:ss"));
				},
				viewRender:function(calendar) {
					var views = {"month":"month","agendaWeek":"week","agendaDay":"day"};
					var view = views[calendar.name];
					
					if (view == "month") {
						var idx = calendar.intervalStart.format("YYYY/MM");
					} else if (view == "week") {
						var idx = calendar.intervalStart.format("gggg/ww");
					} else if (view == "day") {
						var idx = calendar.intervalStart.format("YYYY/MM/DD");
					}
					
					if (view != $calendar.attr("data-view") || idx != $calendar.attr("data-idx")) {
						$calendar.attr("data-view",view);
						$calendar.attr("data-idx",idx);
						
						if (history.pushState) {
							history.pushState({view:$("div[data-role=calendar]").fullCalendar("getView").name,date:$("div[data-role=calendar]").fullCalendar("getDate").format("YYYY-MM-DD"),dataView:view,dataIdx:idx},document.title,Calendar.getUrl(view,idx));
						}
					}
				}
			});
			
			$(document).on("click",function() {
				var $calendar = $("div[data-role=calendar]",$context);
				$("a.fc-event.opened",$calendar).removeClass("opened");
			});
			
			$(window).on("popstate",function(e) {
				if (e.originalEvent.state != null && e.originalEvent.state.view && e.originalEvent.state.date) {
					var $calendar = $("div[data-role=calendar]",$("#ModuleCalendarContext"));
					$calendar.attr("data-view",e.originalEvent.state.dataView);
					$calendar.attr("data-idx",e.originalEvent.state.dataIdx);
					$calendar.fullCalendar("changeView",e.originalEvent.state.view,e.originalEvent.state.date);
				} else {
					location.replace(location.href);
				}
			});
		}
	},
	write:function(start,end) {
		var $context = $("#ModuleCalendarContext");
		var $calendar = $("div[data-role=calendar]",$context);
		var cid = $context.attr("data-cid");
		
		iModule.modal.get(ENV.getProcessUrl("calendar","getModal"),{modal:"write",cid:cid,start:start,end:end},function($modal,$form) {
			var $category = $("select[name=category]",$form);
			$category.on("change",function() {
				var $selected = $("option:selected",$(this));
				$("div[data-role=input][data-name=category] > button > span",$form).css("background",$selected.attr("data-color"));
			});
			$category.triggerHandler("change");
			
			var $is_allday = $("input[name=is_allday]",$form);
			$is_allday.on("change",function() {
				if ($(this).checked() == true) {
					$("input[type=time]",$form).disable();
				} else {
					$("input[type=time]",$form).enable();
				}
			});
			$is_allday.triggerHandler("change");
			
			var $repeat = $("select[name=repeat]",$form);
			$repeat.on("change",function() {
				$("div[data-role=repeat_rule]",$form).hide();
				if ($(this).val() == "NONE") {
					$("div[data-role=inputset][data-name=repeat_interval]",$form).hide();
				} else {
					$("div[data-role=repeat_rule][data-rule="+$(this).val()+"]",$form).show();
					var $text = $("div[data-role=inputset][data-name=repeat_interval] > div[data-role=text]",$form);
					$text.html($text.attr("data-" + $(this).val().toLowerCase()));
					$("div[data-role=inputset][data-name=repeat_interval]",$form).show();
					
					if ($(this).val() == "WEEKLY") {
						if ($("div[data-role=repeat_rule][data-rule=WEEKLY] > ul > li.on",$form).length == 0) {
							var start = moment($("input[name=start_date]",$form).val());
							$("div[data-role=repeat_rule][data-rule=WEEKLY] > ul > li",$form).eq(start.day()).addClass("on");
						}
					}
					
					if ($(this).val() == "MONTHLY") {
						$("select[name=repeat_rule_type]",$form).triggerHandler("change");
					}
					
					if ($(this).val() == "YEARLY") {
						if ($("div[data-role=repeat_rule][data-rule=YEARLY] > ul > li.on",$form).length == 0) {
							var start = moment($("input[name=start_date]",$form).val());
							$("div[data-role=repeat_rule][data-rule=YEARLY] > ul > li > button[data-value="+(start.month() + 1)+"]",$form).parent().addClass("on");
						}
						$("input[name=repeat_rule_apply]",$form).triggerHandler("change");
					}
				}
				iModule.modal.set();
			});
			$repeat.triggerHandler("change");
			
			$("select[name=repeat_rule_type]",$form).on("change",function() {
				if ($(this).val() == "date") {
					$("div[data-role=repeat_rule][data-rule=MONTHLY] > ul",$form).show();
					$("div[data-role=repeat_rule][data-rule=MONTHLY] > div[data-role=inputset]",$form).hide();
					
					if ($("div[data-role=repeat_rule][data-rule=MONTHLY] > ul > li.on",$form).length == 0) {
						var start = moment($("input[name=start_date]",$form).val());
						$("div[data-role=repeat_rule][data-rule=MONTHLY] > ul > li > button[data-value="+start.date()+"]").parent().addClass("on");
					}
				} else {
					$("div[data-role=repeat_rule][data-rule=MONTHLY] > ul",$form).hide();
					$("div[data-role=repeat_rule][data-rule=MONTHLY] > div[data-role=inputset]",$form).show();
				}
			});
			
			$("input[name=repeat_rule_apply]",$form).on("change",function() {
				if ($(this).checked() == true) {
					$("div[data-role=repeat_rule][data-rule=YEARLY] > div[data-role=inputset] > div[data-role=input] > select").enable();
				} else {
					$("div[data-role=repeat_rule][data-rule=YEARLY] > div[data-role=inputset] > div[data-role=input] > select").disable();
				}
			});
			
			var $repeat_rule = $("div[data-role=repeat_rule]",$form);
			$("button",$repeat_rule).on("click",function() {
				$(this).parent().toggleClass("on");
			});
			
			$form.on("submit",function() {
				$form.send(ENV.getProcessUrl("calendar","saveEvent"),function(result) {
					if (result.success == true) {
						$calendar.fullCalendar("refetchEvents");
						iModule.modal.close();
					}
				});
				return false;
			});
			
			return false;
		},function(result) {
			$calendar.fullCalendar("unselect");
		});
	},
	share:function(start,end) {
		var $context = $("#ModuleCalendarContext");
		var $calendar = $("div[data-role=calendar]",$context);
		var cid = $context.attr("data-cid");
		
		iModule.modal.get(ENV.getProcessUrl("calendar","getModal"),{modal:"share",cid:cid},function($modal,$form) {
			var clipboard = new ClipboardJS(document.querySelectorAll("button[data-action=clipboard]"),{
				container:document.getElementById('iModuleModalForm')
			});
			clipboard.on("success",function() {
				iModule.alert.show("success","iCal 주소가 클립보드에 복사되었습니다.");
			});
			$("input",$form).on("focus",function(e) {
				setTimeout(function($input) { $input.select(); },100,$(this));
				e.stopImmediatePropagation();
			});
		});
	},
	view:function(event) {
		var $context = $("#ModuleCalendarContext");
		var $calendar = $("div[data-role=calendar]",$context);
		
		iModule.modal.get(ENV.getProcessUrl("calendar","getModal"),{modal:"view",event:JSON.stringify(event)},function($modal,$form) {
			$modal.on("close",function() {
				var $context = $("#ModuleCalendarContext");
				var $calendar = $("div[data-role=calendar]",$context);
				$("a.fc-event.opened",$calendar).removeClass("opened");
			});
			
			$("button[data-action]",$form).on("click",function(e) {
				var action = $(this).attr("data-action");
				
				if (action == "modify") {
					Calendar.modify(event);
				}
				
				if (action == "delete") {
					Calendar.delete(event);
				}
				
				e.stopPropagation();
			});
		});
	},
	modify:function(idx) {
		var $context = $("#ModuleCalendarContext");
		var $calendar = $("div[data-role=calendar]",$context);
		
		iModule.modal.get(ENV.getProcessUrl("calendar","getModal"),{modal:"modify",idx:idx},function($modal,$form) {
			$modal.on("close",function() {
				var $context = $("#ModuleCalendarContext");
				var $calendar = $("div[data-role=calendar]",$context);
				$("a.fc-event.opened",$calendar).removeClass("opened");
			});
			
			var $category = $("select[name=category]",$form);
			$category.on("change",function() {
				var $selected = $("option:selected",$(this));
				$("i[data-role=color]",$form).css("background",$selected.attr("data-color"));
			});
			$category.triggerHandler("change");
			
			var $is_allday = $("input[name=is_allday]",$form);
			$is_allday.on("change",function() {
				if ($(this).checked() == true) {
					$("input[type=time]",$form).disable();
				} else {
					$("input[type=time]",$form).enable();
				}
			});
			$is_allday.triggerHandler("change");
			
			var $repeat = $("select[name=repeat]",$form);
			$repeat.on("change",function() {
				if ($(this).val() == "NONE") {
					$("input[name=repeat_end_date]",$form).disable();
				} else {
					$("input[name=repeat_end_date]",$form).enable();
					if ($("input[name=repeat_end_date]",$form).val()) return;
					
					var start_date = moment($("input[name=start_date]",$form).val() ? $("input[name=start_date]",$form).val() : moment().format("YYYY-MM-DD"));
					if (start_date == "ANNUALLY") {
						$("input[name=repeat_end_date]",$form).val(start_date.add(10,"year").format("YYYY-MM-DD"));
					} else {
						$("input[name=repeat_end_date]",$form).val(start_date.add(1,"year").format("YYYY-MM-DD"));
					}
				}
			});
			$repeat.triggerHandler("change");
			
			var $repeat_modify = $("select[name=repeat_modify]",$form);
			$repeat_modify.on("change",function() {
				if ($(this).val() == "ONCE") {
					$("select[name=repeat]",$form).disable();
					$("input[name=repeat_end_date]",$form).disable();
				} else {
					$("select[name=repeat]",$form).enable();
					$("input[name=repeat_end_date]",$form).enable();
				}
			});
			
			$form.on("submit",function() {
				$form.send(ENV.getProcessUrl("calendar","saveEvent"),function(result) {
					if (result.success == true) {
						$calendar.fullCalendar("refetchEvents");
						iModule.modal.close();
					}
				});
				return false;
			});
			
			return false;
		});
	},
	delete:function(event) {
		var $context = $("#ModuleCalendarContext");
		var $calendar = $("div[data-role=calendar]",$context);
		
		iModule.modal.get(ENV.getProcessUrl("calendar","getModal"),{modal:"delete",uid:event.uid,rid:event.rid},function($modal,$form) {
			$modal.on("close",function() {
				var $context = $("#ModuleCalendarContext");
				var $calendar = $("div[data-role=calendar]",$context);
				$("a.fc-event.opened",$calendar).removeClass("opened");
			});
			
			$form.on("submit",function() {
				$form.send(ENV.getProcessUrl("calendar","deleteEvent"),function(result) {
					if (result.success == true) {
						$calendar.fullCalendar("refetchEvents");
						iModule.modal.close();
					}
				});
				return false;
			});
			
			return false;
		});
	},
	updateDuration:function(event,revert) {
		if (event.is_recurrence == true) {
			iModule.modal.get(ENV.getProcessUrl("calendar","getModal"),{modal:"duration",uid:event.uid,rid:event.rid,start:event.start.format("YYYY-MM-DD HH:mm:ss"),end:event.end.format("YYYY-MM-DD HH:mm:ss")},function($modal,$form) {
				$modal.on("close",function() {
					var $context = $("#ModuleCalendarContext");
					var $calendar = $("div[data-role=calendar]",$context);
					
					$calendar.fullCalendar("refetchEvents");
				});
				
				$form.on("submit",function() {
					$form.send(ENV.getProcessUrl("calendar","saveDuration"),function(result) {
						if (result.success == true) {
							iModule.modal.close();
						} else {
							iModule.modal.close();
						}
						
						return false;
					});
					
					return false;
				});
				
				return false;
			},function(result) {
				if (result.success == false) revert();
			});
		} else {
			$.send(ENV.getProcessUrl("calendar","saveDuration"),{uid:event.data.uid,rid:event.data.rid,start:event.start.format("YYYY-MM-DD HH:mm:ss"),end:event.end.format("YYYY-MM-DD HH:mm:ss")},function(result) {
				if (result.success == false) {
					revert();
				}
			});
		}
	}
}