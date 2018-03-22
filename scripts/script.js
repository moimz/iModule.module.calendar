/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodule.kr)
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
					add:{
						text:"일정추가",
						click:function() {
							Calendar.add(moment().format("YYYY-MM-DD"),moment().add(1,"day").format("YYYY-MM-DD"));
						}
					}
				},
				header:{
					left:"prev,next today",
					center:"prev title next",
					right:$calendar.attr("data-editable") == "TRUE" ? "month,agendaWeek,agendaDay add" : "month,agendaWeek,agendaDay"
				},
				timezone:"local",
				defaultView:views[view],
				defaultDate:defaultDate,
				editable:$calendar.attr("data-editable") == "TRUE",
				selectable:$calendar.attr("data-selectable") == "TRUE",
				displayEventEnd:false,
				handleWindowResize:false,
				contentHeight:"auto",
				eventSources:[{
					events:function(start,end,timezone,callback) {
						$.send(ENV.getProcessUrl("calendar","getSchedules"),{cid:$context.attr("data-cid"),start:start.format("YYYY-MM-DD"),end:end.format("YYYY-MM-DD")},function(result) {
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
					Calendar.view(event.id);
					e.stopPropagation();
				},
				eventDrop:function(event,delta,revert) {
					Calendar.updateDuration(event,revert);
				},
				eventResize:function(event,delta,revert) {
					Calendar.updateDuration(event,revert);
				},
				select:function(start,end) {
					Calendar.add(start.format("YYYY-MM-DD HH:mm:ss"),end.format("YYYY-MM-DD HH:mm:ss"));
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
	add:function(start,end) {
		var $context = $("#ModuleCalendarContext");
		var $calendar = $("div[data-role=calendar]",$context);
		var cid = $context.attr("data-cid");
		
		iModule.modal.get(ENV.getProcessUrl("calendar","getModal"),{modal:"add",cid:cid,start:start,end:end},function($modal,$form) {
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
			
			$form.on("submit",function() {
				$form.send(ENV.getProcessUrl("calendar","saveSchedule"),function(result) {
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
	view:function(idx) {
		var $context = $("#ModuleCalendarContext");
		var $calendar = $("div[data-role=calendar]",$context);
		
		iModule.modal.get(ENV.getProcessUrl("calendar","getModal"),{modal:"view",idx:idx},function($modal,$form) {
			$modal.on("close",function() {
				var $context = $("#ModuleCalendarContext");
				var $calendar = $("div[data-role=calendar]",$context);
				$("a.fc-event.opened",$calendar).removeClass("opened");
			});
			
			$("button[data-action]",$form).on("click",function(e) {
				var action = $(this).attr("data-action");
				
				if (action == "modify") {
					Calendar.modify(idx);
				}
				
				if (action == "delete") {
					Calendar.delete(idx);
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
				$form.send(ENV.getProcessUrl("calendar","saveSchedule"),function(result) {
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
	delete:function(idx) {
		var $context = $("#ModuleCalendarContext");
		var $calendar = $("div[data-role=calendar]",$context);
		
		iModule.modal.get(ENV.getProcessUrl("calendar","getModal"),{modal:"delete",idx:idx},function($modal,$form) {
			$modal.on("close",function() {
				var $context = $("#ModuleCalendarContext");
				var $calendar = $("div[data-role=calendar]",$context);
				$("a.fc-event.opened",$calendar).removeClass("opened");
			});
			
			$form.on("submit",function() {
				$form.send(ENV.getProcessUrl("calendar","deleteSchedule"),function(result) {
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
		if (!event.is_module) return revert();
		
		if (event.is_repeat == true) {
			iModule.modal.get(ENV.getProcessUrl("calendar","getModal"),{modal:"duration",idx:event.id,start:event.start.format("YYYY-MM-DD HH:mm:ss"),end:event.end.format("YYYY-MM-DD HH:mm:ss")},function($modal,$form) {
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
			$.send(ENV.getProcessUrl("calendar","saveDuration"),{idx:event.id,start:event.start.format("YYYY-MM-DD HH:mm:ss"),end:event.end.format("YYYY-MM-DD HH:mm:ss")},function(result) {
				if (result.success == false) {
					revert();
				}
			});
		}
	}
}