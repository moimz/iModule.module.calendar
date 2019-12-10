/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 *
 * 캘린더모듈 관리자 UI를 제어한다.
 * 
 * @file /modules/calendar/admin/scripts/script.js
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 12. 11.
 */
var Calendar = {
	list:{
		/**
		 * 캘린더 추가/삭제
		 *
		 * @param string cid 캘린더아이디 (없을 경우 추가)
		 */
		add:function(cid) {
			new Ext.Window({
				id:"ModuleCalendarAddCalendarWindow",
				title:(cid ? Calendar.getText("admin/list/window/modify") : Calendar.getText("admin/list/window/add")),
				modal:true,
				width:600,
				border:false,
				autoScroll:true,
				items:[
					new Ext.form.Panel({
						id:"ModuleCalendarAddCalendarForm",
						border:false,
						bodyPadding:"10 10 0 10",
						fieldDefaults:{labelAlign:"right",labelWidth:100,anchor:"100%",allowBlank:false},
						items:[
							new Ext.form.Hidden({
								name:"mode",
								value:(cid ? "modify" : "add")
							}),
							new Ext.form.FieldSet({
								title:Calendar.getText("admin/list/form/default_setting"),
								items:[
									new Ext.form.TextField({
										fieldLabel:Calendar.getText("admin/list/form/cid"),
										name:"cid",
										maxLength:20,
										readOnly:cid ? true : false
									}),
									new Ext.form.TextField({
										fieldLabel:Calendar.getText("admin/list/form/title"),
										name:"title",
										maxLength:50
									})
								]
							}),
							new Ext.form.FieldSet({
								title:Calendar.getText("admin/list/form/design_setting"),
								items:[
									Admin.templetField(Calendar.getText("admin/list/form/templet"),"templet","module","calendar",false,ENV.getProcessUrl("calendar","@getTempletConfigs"),{},["cid"])
								]
							})
						]
					})
				],
				buttons:[
					new Ext.Button({
						text:Calendar.getText("button/confirm"),
						handler:function() {
							Ext.getCmp("ModuleCalendarAddCalendarForm").getForm().submit({
								url:ENV.getProcessUrl("calendar","@saveCalendar"),
								submitEmptyText:false,
								waitTitle:Admin.getText("action/wait"),
								waitMsg:Admin.getText("action/saving"),
								success:function(form,action) {
									Ext.Msg.show({title:Admin.getText("alert/info"),msg:Admin.getText("action/saved"),buttons:Ext.Msg.OK,icon:Ext.Msg.INFO,fn:function(button) {
										Ext.getCmp("ModuleCalendarAddCalendarWindow").close();
										Ext.getCmp("ModuleCalendarList").getStore().reload();
									}});
								},
								failure:function(form,action) {
									if (action.result) {
										if (action.result.message) {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:action.result.message,buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										} else {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_SAVE_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										}
									} else {
										Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("INVALID_FORM_DATA"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
									}
								}
							});
						}
					}),
					new Ext.Button({
						text:Calendar.getText("button/cancel"),
						handler:function() {
							Ext.getCmp("ModuleCalendarAddCalendarWindow").close();
						}
					})
				],
				listeners:{
					show:function() {
						if (cid !== undefined) {
							Ext.getCmp("ModuleCalendarAddCalendarForm").getForm().load({
								url:ENV.getProcessUrl("calendar","@getCalendar"),
								params:{cid:cid},
								waitTitle:Admin.getText("action/wait"),
								waitMsg:Admin.getText("action/loading"),
								success:function(form,action) {
								},
								failure:function(form,action) {
									if (action.result && action.result.message) {
										Ext.Msg.show({title:Admin.getText("alert/error"),msg:action.result.message,buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
									} else {
										Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getText("error/load"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
									}
									Ext.getCmp("ModuleCalendarAddCalendarWindow").close();
								}
							});
						}
					}
				}
			}).show();
		},
		view:function(cid,title) {
			new Ext.Window({
				id:"ModuleCalendarViewCalendarWindow",
				title:title,
				modal:true,
				width:950,
				height:600,
				border:false,
				layout:"fit",
				maximizable:true,
				items:[
					new Ext.Panel({
						border:false,
						html:'<iframe src="'+ENV.getModuleUrl("calendar",cid,false)+'" style="width:100%; height:100%; border:0px;" frameborder="0" scrolling="1"></iframe>'
					})
				]
			}).show();
		},
		delete:function() {
			var selected = Ext.getCmp("ModuleCalendarList").getSelectionModel().getSelection();
			if (selected.length == 0) {
				Ext.Msg.show({title:Admin.getText("alert/error"),msg:"삭제할 캘린더을 선택하여 주십시오.",buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
				return;
			}
			
			var cids = [];
			for (var i=0, loop=selected.length;i<loop;i++) {
				cids.push(selected[i].get("cid"));
			}
			
			Ext.Msg.show({title:Admin.getText("alert/info"),msg:"선택하신 캘린더을 정말 삭제하시겠습니까?<br>캘린더에 포함된 모든 카테고리/일정이 함께 삭제됩니다.",buttons:Ext.Msg.OKCANCEL,icon:Ext.Msg.QUESTION,fn:function(button) {
				if (button == "ok") {
					Ext.Msg.wait(Admin.getText("action/working"),Admin.getText("action/wait"));
					$.send(ENV.getProcessUrl("calendar","@deleteCalendar"),{cid:cids.join(",")},function(result) {
						if (result.success == true) {
							Ext.Msg.show({title:Admin.getText("alert/info"),msg:Admin.getText("action/worked"),buttons:Ext.Msg.OK,icon:Ext.Msg.INFO,fn:function() {
								Ext.getCmp("ModuleCalendarList").getStore().reload();
							}});
						}
					});
				}
			}});
		}
	},
	category:{
		list:function(cid) {
			new Ext.Window({
				id:"ModuleCalendarCategoryWindow",
				title:"카테고리 관리",
				modal:true,
				width:750,
				height:500,
				border:false,
				autoScroll:true,
				layout:"fit",
				items:[
					new Ext.grid.Panel({
						id:"ModuleCalendarCategoryList",
						border:true,
						tbar:[
							new Ext.Button({
								iconCls:"mi mi-plus",
								text:"카테고리추가",
								handler:function() {
									Calendar.category.add(cid);
								}
							}),
							new Ext.Button({
								iconCls:"mi mi-trash",
								text:"선택 카테고리 삭제",
								handler:function() {
									Calendar.category.delete();
								}
							})
						],
						store:new Ext.data.JsonStore({
							proxy:{
								type:"ajax",
								simpleSortMode:true,
								url:ENV.getProcessUrl("calendar","@getCategories"),
								extraParams:{cid:cid},
								reader:{type:"json"}
							},
							remoteSort:false,
							sorters:[{property:"sort",direction:"ASC"}],
							autoLoad:true,
							pageSize:50,
							fields:["cid","title",{name:"sort",type:"int"}],
							listeners:{
								load:function(store,records,success,e) {
									if (success == false) {
										if (e.getError()) {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										} else {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getText("error/load"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										}
									}
								}
							}
						}),
						columns:[{
							text:"카테고리명",
							dataIndex:"title",
							flex:1,
							renderer:function(value,p,record) {
								return '<div style="display:inline-block; vertical-align:middle; margin-right:5px; width:16px; height:16px; border:1px solid #ccc; background:'+record.data.color+';"></div>'+value;
							}
						},{
							text:"카테고리속성",
							dataIndex:"detail",
							width:250
						}],
						bbar:[
							new Ext.Button({
								iconCls:"fa fa-caret-up",
								handler:function() {
									Admin.gridSort(Ext.getCmp("ModuleCalendarCategoryList"),"sort","up");
									Admin.gridSave(Ext.getCmp("ModuleCalendarCategoryList"),ENV.getProcessUrl("calendar","@saveCategorySort"),500);
								}
							}),
							new Ext.Button({
								iconCls:"fa fa-caret-down",
								handler:function() {
									Admin.gridSort(Ext.getCmp("ModuleCalendarCategoryList"),"sort","down");
									Admin.gridSave(Ext.getCmp("ModuleCalendarCategoryList"),ENV.getProcessUrl("calendar","@saveCategorySort"),500);
								}
							}),
							"->",
							{xtype:"tbtext",text:"더블클릭 : 카테고리수정 / 마우스우클릭 : 상세메뉴"}
						],
						selModel:new Ext.selection.CheckboxModel(),
						listeners:{
							itemdblclick:function(grid,record,td,index) {
								Calendar.category.add(cid,record.data.idx);
							},
							itemcontextmenu:function(grid,record,item,index,e) {
								var menu = new Ext.menu.Menu();
								
								menu.addTitle(record.data.title);
								
								menu.add({
									iconCls:"xi xi-form",
									text:"카테고리 수정",
									handler:function() {
										Calendar.category.add(cid,record.data.idx);
									}
								});
								
								menu.add({
									iconCls:"mi mi-trash",
									text:"카테고리 삭제",
									handler:function() {
										Calendar.category.delete();
									}
								});
								
								e.stopEvent();
								menu.showAt(e.getXY());
							}
						}
					})
				]
			}).show();
		},
		add:function(cid,idx) {
			new Ext.Window({
				id:"ModuleCalendarAddCategoryWindow",
				title:(idx == null ? "카테고리추가" : "카테고리수정"),
				modal:true,
				width:600,
				border:false,
				autoScroll:true,
				items:[
					new Ext.form.Panel({
						id:"ModuleCalendarAddCategoryForm",
						border:false,
						bodyPadding:"10 10 0 10",
						fieldDefaults:{labelAlign:"right",labelWidth:100,anchor:"100%",allowBlank:false},
						items:[
							new Ext.form.Hidden({
								name:"cid",
								value:cid
							}),
							new Ext.form.Hidden({
								name:"idx",
								value:idx ? idx : ""
							}),
							new Ext.form.FieldContainer({
								layout:"hbox",
								items:[
									new Ext.form.TextField({
										name:"title",
										flex:1,
										emptyText:"카테고리명",
										style:{marginRight:"5px"}
									}),
									new Ext.ux.ColorField({
										name:"color",
										emptyText:"구분색상",
										maxLength:7,
										width:110,
										preview:false,
										style:{marginRight:"5px"},
										validator:function(value) {
											return value.search(/^#[0-9a-fA-F]{6}/) === 0;
										},
										listeners:{
											change:function(form,value) {
												if (form.isValid() == true) {
													var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(value);
													$("#ModuleCalendarAddCategoryColor-inputEl").css("backgroundColor","rgba("+parseInt(result[1],16)+","+parseInt(result[2],16)+","+parseInt(result[3],16)+",0.2)");
													$("#ModuleCalendarAddCategoryColor-inputEl").css("color",value);
												}
											}
										}
									}),
									new Ext.form.DisplayField({
										id:"ModuleCalendarAddCategoryColor",
										fieldStyle:{border:"1px solid #ccc",textAlign:"center"},
										width:30,
										value:"T"
									})
								],
								afterBodyEl:'<div class="x-form-help">일정을 구분할 고유한 색상값(예 : #000000)과 대상명을 입력하여 주십시오.</div>'
							}),
							new Ext.form.FieldSet({
								title:"외부 캘린더 연동 (일정을 직접입력하지 않고 외부 공유캘린더의 일정을 연동합니다.)",
								checkboxName:"use_ical",
								checkboxToggle:true,
								collapsed:true,
								items:[
									new Ext.form.TextField({
										name:"ical",
										margin:"0 0 0 0",
										emptyText:"iCal 주소를 입력하여 주십시오.",
										disabled:true,
										afterBodyEl:'<div class="x-form-help">http(s):// 를 포함한 전체 iCal 경로를 입력하여 주십시오.</div>'
									})
								],
								listeners:{
									expand:function(form,value) {
										Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().findField("ical").enable();
										Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().findField("permission_write").disable();
										Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().findField("permission_edit").disable();
									},
									collapse:function() {
										Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().findField("ical").disable();
										Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().findField("permission_write").enable();
										Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().findField("permission_edit").enable();
									}
								}
							}),
							new Ext.form.FieldSet({
								title:"카테고리 권한설정",
								items:[
									Admin.permissionField("일정보기","permission_view","true"),
									Admin.permissionField("일정작성","permission_write","true"),
									Admin.permissionField("일정수정","permission_edit","{$member.type} == 'ADMINISTRATOR'")
								]
							})
						]
					})
				],
				buttons:[
					new Ext.Button({
						text:"확인",
						handler:function() {
							Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().submit({
								url:ENV.getProcessUrl("calendar","@saveCategory"),
								submitEmptyText:false,
								waitTitle:Admin.getText("action/wait"),
								waitMsg:Admin.getText("action/saving"),
								success:function(form,action) {
									Ext.Msg.show({title:Admin.getText("alert/info"),msg:Admin.getText("action/saved"),buttons:Ext.Msg.OK,icon:Ext.Msg.INFO,fn:function(button) {
										Ext.getCmp("ModuleCalendarAddCategoryWindow").close();
										Ext.getCmp("ModuleCalendarCategoryList").getStore().reload();
										Ext.getCmp("ModuleCalendarList").getStore().reload();
									}});
								},
								failure:function(form,action) {
									if (action.result) {
										if (action.result.message) {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:action.result.message,buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										} else {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_SAVE_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										}
									} else {
										Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("INVALID_FORM_DATA"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
									}
								}
							});
						}
					}),
					new Ext.Button({
						text:"취소",
						handler:function() {
							Ext.getCmp("ModuleCalendarAddCategoryWindow").close();
						}
					})
				],
				listeners:{
					show:function() {
						if (idx) {
							Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().load({
								url:ENV.getProcessUrl("calendar","@getCategory"),
								params:{idx:idx},
								waitTitle:Admin.getText("action/wait"),
								waitMsg:Admin.getText("action/loading"),
								success:function(form,action) {
									
								},
								failure:function(form,action) {
									if (action.result && action.result.message) {
										Ext.Msg.show({title:Admin.getText("alert/error"),msg:action.result.message,buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
									} else {
										Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getText("error/load"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
									}
									Ext.getCmp("ModuleCalendarAddCategoryWindow").close();
								}
							});
						}
					}
				}
			}).show();
		},
		delete:function() {
			var selected = Ext.getCmp("ModuleCalendarCategoryList").getSelectionModel().getSelection();
			if (selected.length == 0) {
				Ext.Msg.show({title:Admin.getText("alert/error"),msg:"삭제할 카테고리를 선택하여 주십시오.",buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
				return;
			}
			
			var idxes = [];
			for (var i=0, loop=selected.length;i<loop;i++) {
				idxes.push(selected[i].get("idx"));
			}
			
			Ext.Msg.show({title:Admin.getText("alert/info"),msg:"선택하신 카테고리를 삭제하시겠습니까?<br>삭제되는 카테고리의 일정이 함께 삭제됩니다.",buttons:Ext.Msg.OKCANCEL,icon:Ext.Msg.QUESTION,fn:function(button) {
				if (button == "ok") {
					Ext.Msg.wait(Admin.getText("action/working"),Admin.getText("action/wait"));
					$.send(ENV.getProcessUrl("calendar","@deleteCategory"),{idx:idxes.join(",")},function(result) {
						if (result.success == true) {
							Ext.Msg.show({title:Admin.getText("alert/info"),msg:Admin.getText("action/worked"),buttons:Ext.Msg.OK,icon:Ext.Msg.INFO,fn:function() {
								Ext.getCmp("ModuleCalendarCategoryList").getStore().reload();
							}});
						}
					});
				}
			}});
		}
	},
	/**
	 * 관리자관리
	 */
	admin:{
		add:function(midx) {
			var midx = midx ? midx : 0;
			
			new Ext.Window({
				id:"ModuleCalendarAdminAddWindow",
				title:(midx ? Calendar.getText("admin/admin/modify_admin") : Calendar.getText("admin/admin/add_admin")),
				width:600,
				height:500,
				modal:true,
				border:false,
				layout:"fit",
				items:[
					new Ext.Panel({
						border:false,
						layout:"fit",
						tbar:[
							new Ext.form.Hidden({
								id:"ModuleCalendarAdminAddMidx",
								name:"midx",
								value:midx,
								disabled:midx
							}),
							new Ext.form.TextField({
								id:"ModuleCalendarAdminAddText",
								name:"text",
								emptyText:"검색버튼을 클릭하여 관리자로 지정할 회원을 검색하세요.",
								readOnly:true,
								flex:1,
								listeners:{
									focus:function() {
										Member.search(function(member) {
											var text = member.name + "(" + member.nickname + ") / " + member.email;
											Ext.getCmp("ModuleCalendarAdminAddText").setValue(text);
											Ext.getCmp("ModuleCalendarAdminAddMidx").setValue(member.idx);
										});
									}
								}
							}),
							new Ext.Button({
								iconCls:"mi mi-search",
								text:"검색",
								disabled:midx,
								handler:function() {
									Member.search(function(member) {
										var text = member.name + "(" + member.nickname + ") / " + member.email;
										Ext.getCmp("ModuleCalendarAdminAddText").setValue(text);
										Ext.getCmp("ModuleCalendarAdminAddMidx").setValue(member.idx);
									});
								}
							}),
							"-",
							new Ext.form.Checkbox({
								id:"ModuleCalendarAdminAddAll",
								boxLabel:Calendar.getText("admin/admin/admin_all"),
								listeners:{
									change:function(form,value) {
										Ext.getCmp("ModuleCalendarAdminAddList").setDisabled(value);
									}
								}
							})
						],
						items:[
							new Ext.grid.Panel({
								id:"ModuleCalendarAdminAddList",
								border:false,
								selected:[],
								layout:"fit",
								autoScroll:true,
								store:new Ext.data.JsonStore({
									proxy:{
										type:"ajax",
										simpleSortMode:true,
										url:ENV.getProcessUrl("calendar","@getCalendars"),
										extraParams:{depth:"group",parent:"NONE"},
										reader:{type:"json"}
									},
									remoteSort:false,
									sorters:[{property:"title",direction:"ASC"}],
									autoLoad:false,
									pageSize:0,
									fields:["idx","title"],
									listeners:{
										load:function(store,records,success,e) {
											if (success == true) {
												Ext.getCmp("ModuleCalendarAdminAddList").getSelectionModel().deselectAll(true);
												var selected = Ext.getCmp("ModuleCalendarAdminAddList").selected;
												for (var i=0, loop=store.getCount();i<loop;i++) {
													if ($.inArray(store.getAt(i).get("cid"),selected) > -1) {
														Ext.getCmp("ModuleCalendarAdminAddList").getSelectionModel().select(i,true);
													}
												}
											} else {
												if (e.getError()) {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												} else {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("LOAD_DATA_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												}
											}
										}
									}
								}),
								columns:[{
									text:Calendar.getText("admin/list/columns/cid"),
									width:180,
									dataIndex:"cid",
								},{
									text:Calendar.getText("admin/list/columns/title"),
									flex:1,
									dataIndex:"title"
								}],
								selModel:new Ext.selection.CheckboxModel({mode:"SIMPLE"})
							})
						]
					})
				],
				buttons:[
					new Ext.Button({
						text:Admin.getText("button/confirm"),
						handler:function() {
							var midx = Ext.getCmp("ModuleCalendarAdminAddMidx").getValue();
							if (Ext.getCmp("ModuleCalendarAdminAddAll").getValue() == true) {
								var cid = "*";
							} else {
								var cids = Ext.getCmp("ModuleCalendarAdminAddList").getSelectionModel().getSelection();
								for (var i=0, loop=cids.length;i<loop;i++) {
									cids[i] = cids[i].get("cid");
								}
								var cid = cids.join(",");
							}
							
							if (!midx) {
								Ext.Msg.show({title:Admin.getText("alert/error"),msg:"관리자로 추가할 회원을 검색하여 주십시오.",buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
							} else {
								Ext.Msg.wait(Admin.getText("action/working"),Admin.getText("action/saving"));
								$.send(ENV.getProcessUrl("calendar","@saveAdmin"),{midx:midx,cid:cid},function(result) {
									if (result.success == true) {
										Ext.Msg.show({title:Admin.getText("alert/info"),msg:Admin.getText("action/saved"),buttons:Ext.Msg.OK,icon:Ext.Msg.INFO,fn:function() {
											Ext.getCmp("ModuleCalendarAdminList").getStore().reload();
											Ext.getCmp("ModuleCalendarAdminAddWindow").close();
										}});
									}
								});
							}
						}
					}),
					new Ext.Button({
						text:Admin.getText("button/close"),
						handler:function() {
							Ext.getCmp("ModuleCalendarAdminAddWindow").close();
						}
					})
				],
				listeners:{
					show:function() {
						if (midx == 0) {
							Ext.getCmp("ModuleCalendarAdminAddList").getStore().load();
						} else {
							Ext.Msg.wait(Admin.getText("action/working"),Admin.getText("action/loading"));
							$.send(ENV.getProcessUrl("calendar","@getAdmin"),{midx:midx},function(result) {
								if (result.success == true) {
									Ext.Msg.hide();
									Ext.getCmp("ModuleCalendarAdminAddText").setValue(result.member.name+"("+result.member.nickname+") / "+result.member.email);
									if (result.cid == "*") {
										Ext.getCmp("ModuleCalendarAdminAddAll").setValue(true);
									} else {
										Ext.getCmp("ModuleCalendarAdminAddAll").setValue(false);
										Ext.getCmp("ModuleCalendarAdminAddList").selected = result.cid;
									}
									Ext.getCmp("ModuleCalendarAdminAddList").getStore().load();
								}
							});
						}
					}
				}
			}).show();
		},
		/**
		 * 관리자 삭제
		 */
		delete:function() {
			var selected = Ext.getCmp("ModuleCalendarAdminList").getSelectionModel().getSelection();
			if (selected.length == 0) {
				Ext.Msg.show({title:Admin.getText("alert/error"),msg:"삭제할 관리자를 선택하여 주십시오.",buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
				return;
			}
			
			var midxes = [];
			for (var i=0, loop=selected.length;i<loop;i++) {
				midxes[i] = selected[i].data.midx;
			}
			
			Ext.Msg.show({title:Admin.getText("alert/info"),msg:"선택된 관리자를 삭제하시겠습니까?",buttons:Ext.Msg.OKCANCEL,icon:Ext.Msg.QUESTION,fn:function(button) {
				if (button == "ok") {
					Ext.Msg.wait(Admin.getText("action/working"),Admin.getText("action/loading"));
					$.send(ENV.getProcessUrl("calendar","@deleteAdmin"),{midx:midxes.join(",")},function(result) {
						if (result.success == true) {
							Ext.Msg.show({title:Admin.getText("alert/info"),msg:Admin.getText("action/worked"),buttons:Ext.Msg.OK,icon:Ext.Msg.INFO,fn:function() {
								Ext.getCmp("ModuleCalendarAdminList").getStore().reload();
							}});
						}
					});
				}
			}});
		}
	}
};