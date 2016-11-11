/**
 * @app jsroll.app.js
 * @category RIA (Rich Internet Application) / SPA (Single-page Application)
 *
 * Классы RIA / SPA application
 * @author Андрей Новиков <andrey@novikov.be>
 * @status beta
 * @version 0.1.0
 * @revision $Id: jsroll.app.js 0004 2016-06-01 9:00:01Z $
 */

(function ( g, undefined ) {
    'use strict';

    g.model = {};

    var acl = {
        _user: null,
        route: '/',
        get user(){
            try {
                this._user = JSON.parse(storage.getItem('user'));
                this.route = this._user.path || '/';
                return this._user;
            } catch (e) { return null; }
        },
        set user(u){
            this._user = u;
            storage.setItem('user', u ? JSON.stringify(u) : '');
            this.route = u ? u.path : '/';
        }
    }; g.acl = acl;

    var filter = {
        box:{},
        validators:{},
        formatters:{},
        field:{},
        pg: {
            location: function(el) {
                this.el = el;
                this.url = el.spa.attr('data-tab-url');
                this.limit = parseInt(el.spa.attr('data-list-limit')) || 10;
                var p = location.params(this.url);
                if (this.page) this.page = p.page = parseInt(this.page) - 1;
                else this.page = p.page = 0;

                p.limit = this.limit;
                var u = []; for (var at in p) u.push(at+'='+p[at]);
                el.spa.attr('data-tab-url',this.url.split('?')[0]+'?'+u.join('&'));
                return this.url.split('?')[0]+'?'+u.join('&');
            }
        },
        clear:function (el) {
            for (var i in this.field) {
                el.spa.attr('data-tab-url', el.spa.attr('data-tab-url').replace(css.re(i+'=.*?(&|$)'),''));
                this.field[i].el.value = '';
            }
        },
        update:function(el) {
            var validator = '';
            var res = true;
            var p = location.params(el.spa.attr('data-tab-url'));
            for (var i in this.field) {
                validator = this.field[i].el.spa.attr('validator');
                if (validator && filter.validators[validator]) res = filter.validators[validator].call(this.field[i].el,i) & res;
                p[i]=this.field[i].el.value;
            }
            this.pg.page = p.page = 0;
            var u = []; for (var at in p) u.push(encodeURIComponent(at)+'='+encodeURIComponent(p[at]));
            if (this.pg.el) this.pg.el.spa.attr('data-tab-url',this.pg.url.split('?')[0]+'?'+u.join('&'));
            return res;
        },
        paginator: function(pg){
            this.pg.page = pg.page;
            this.pg.count = pg.count;
            this.pg.pages = Math.ceil(this.pg.count / this.pg.limit);
        }

    }; g.filter = filter;

    g.spa.on("keyup", function (e) {
        if (e.keyCode == 27 && popup.visible) popup.hide(function(param){
            if (!acl.user) login();
        });
    }, false);

    function login() {
        var fn =  function (e) {
            spinner = true;
            JSON.form(spa.el('#login'), function(res){
                var res = true;
                if (!res) {
                    spinner = false;
                    spa.el('.modal-content.login-form').css.add('has-error');
                    msg.show({message: 'неверный <пользователь>:<пароль>!'});
                }
                return res;
            }).release({
                done: function (responce) {
                    spinner = false;
                    acl.user = responce.data;
                    popup.hide();
                    workspace(acl);
                },
                fail: function (responce) {
                    spinner = false;
                    spa.el('.modal-content.login-form').css.add('has-error');
                    msg.show(responce);
                }
            });
        };
        tmpl('/tmpl/login.tmpl', null, function (content) {
            popup.show({
                width: 500,
                height: 300,
                content: content,
                event: [
                    function () {
                        spa.el('[role="login-submit"]').spa.on('click', fn);
                        spa.el('[name="login[email]"]').spa.on('keydown', function(e){ if (e.keyCode == 13) fn.call(this)});
                        spa.el('[name="login[passwd]"]').spa.on('keydown', function(e){ if (e.keyCode == 13) fn.call(this)});
                    }
                ]
            });
        });
    }

    function workspace(acl){
        spinner = true; 
        tmpl('/tmpl/app.tmpl', acl, function (content) {

            spa.el(config.app.container).innerHTML = content || '';

            var errors = function(res){
                msg.show(res);
                if (res.error && Object.keys(filter.field).length) for (var i in res.error) if (i in filter.field) filter.field[i].el.status = 'error';
            };

            var tabContent = function (res, index, element, error) {
                model['tmpl-tab' + index].rows = (res && res.data.rows) ? res.data.rows : [];
                tmpl('tmpl-tab' + index, model['tmpl-tab' + index], function (content) {
                    element.innerHTML = content;
                    if (model['tmpl-tab' + index] && typeof model['tmpl-tab' + index].bootstrap === 'function') model['tmpl-tab' + index].bootstrap.call(element, res);
                    if (res && res.paginator) {
                        filter.paginator(res.paginator);
                        tmpl("paginator-box", filter.pg, function (data) {
                            var container = spa.el('.row.paginator' + index);
                            container.innerHTML = data;
                            spa.els('.row.paginator' + index + ' a', function (item) {
                                item.spa.on('click', function (e) {
                                    filter.pg.page = parseInt(this.getAttribute('page-num'));
                                    chooseTab(index, element);
                                    return false;
                                })
                            })
                        });
                    }
                });

                spa.els('[filter]', function(item, i){
                    var name = item.getAttribute('name');
                    var validator = item.getAttribute('validator');
                    filter.field[name] = {
                        tabindex: i,
                        item: item,name:name,
                        type: (item.getAttribute('type') ? item.getAttribute('type') : 'text'),
                        value: (res.filter && res.filter[name]) ? ((filter.formatters[validator]) ? filter.formatters[validator](res.filter[name]) : res.filter[name]) : '',
                        ph: item.getAttribute('placeholder'),
                        validator: validator
                    };
                    tmpl('filter-box',filter.field[name], function(cn){
                        item.innerHTML = cn;
                        if (filter.field[name].type == 'text') {
                            filter.field[name].el = item.spa.el("input");
                            filter.field[name].el.spa.on('keydown', function (e) {
                                if (e.keyCode == 13) {
                                    if (filter.update(element)) chooseTab(index, element);
                                    else msg.show({message: 'поля фильтра заполнены неверно!'});
                                    return false;
                                }
                            });
                            item.spa.el("button").spa.on('click', function (e) {
                                if (filter.update(element)) chooseTab(index, element);
                                else msg.show({message: 'поля фильтра заполнены неверно!'});
                                return false;
                            });
                        } else  if (filter.field[name].type == 'list') {
                            filter.field[name].el = item.spa.el('select');
                            filter.field[name].el.spa.on('change', function (e) {
                                if (filter.update(element)) chooseTab(index, element);
                                else msg.show({message: 'поля фильтра заполнены неверно!'});
                                return false;
                            });
                        }

                        if (validator && filter.validators[validator]) filter.validators[validator].call(filter.field[name].el, name);
                    });
                });

                if (error) error.call(this, res);

                if (element.spa.el('[role="popup-box"]', 'popup_box')) popup_box.spa.on('click', function (e) {
                    var wnd = popup_box.spa.attr('data-form');
                    tmpl('tmpl-add-tab' + index, model['tmpl-tab' + index].row, function (content) {
                        popup.show({
                            width: wnd && wnd.width || null,
                            height: wnd && wnd.height || null,
                            content: content,
                            event: [
                                function () {
                                    if (model['tmpl-add-tab'+index] && typeof model['tmpl-add-tab'+index].bootstrap === 'function') model['tmpl-add-tab' + index].bootstrap.call(popup.container, model['tmpl-tab' + index].row);
                                    spa.el('[role="popup-save"]').spa.on('click', function (e) {
                                        spinner = true;
                                        JSON.form(spa.el('[role="form popup"]'), model['tmpl-tab' + index].validator).release({
                                            done: function (responce) {
                                                if (model['tmpl-add-tab'+index]) {
                                                    if (typeof model['tmpl-add-tab' + index].submited === 'function')
                                                        model['tmpl-add-tab' + index].submited.call(this, model['tmpl-tab' + index].row);
                                                    if (model['tmpl-add-tab' + index].hasOwnProperty('fclear') && model['tmpl-add-tab' + index].fclear === true || !model['tmpl-add-tab' + index].hasOwnProperty('fclear'))
                                                        filter.clear(element);
                                                }
                                                popup.hide();filter.pg.page += 1;
                                                chooseTab(index, element);
                                                spinner = false;
                                            },
                                            fail: function (responce) {
                                                spinner = false;
                                                if (responce.error) for (var i =0; i < this.elements.length; i++) {
                                                    if (responce.error.hasOwnProperty(this.elements[i].name)) this.elements[i].status = 'error';
                                                    else if (this.elements[i].status == 'nene') this.elements[i].status = 'nene';
                                                }
                                                 msg.show(responce);
                                            }, rs:{'Hash': g.acl.user.hash}
                                        });
                                    })
                                }
                            ]
                        });
                    });
                    return false;
                });

                if (spa.el('#tmpl-add-tab' + index)) {
                    element.spa.els('.btn.btn-danger.btn-xs', function (btn) {
                        btn.spa.on('click', function (e) {
                            var info = spa.src(e).attr('row-info') || spa.src(e).parent.attr('row-info');
                            var data = spa.src(e).attr('row-data') || spa.src(e).parent.attr('row-data');
                            if (confirm('Подтвердите удаление записи [' + info.fio + '] ?')) {
                                var d = []; for (var i in data) d.push(encodeURIComponent(i)+'='+encodeURIComponent(data[i]));
                                deleting(info.url || g.location.search, d.join('&'), index, element);
                            }
                            return false;
                        });
                    });

                    element.spa.els('.is_correct', function (btn) {
                        btn.spa.on('click', function (e) {
                            var info = spa.src(e).attr('row-info') || spa.src(e).parent.attr('row-info');
                            var data = spa.src(e).attr('row-data') || spa.src(e).parent.attr('row-data');
                            data.is_correct = this.checked === true ? 1 : 0;
                            var d = []; for (var i in data) d.push(encodeURIComponent(i)+'='+encodeURIComponent(data[i]));
                            return checking(info.url || g.location.search, d.join('&'), index, element);
                        });
                    });

                    element.spa.els('.btn.btn-success.btn-xs', function (btn) {
                        btn.spa.on('click', function (e) {
                            var info = spa.src(e).attr('row-info') || spa.src(e).parent.attr('row-info');
                            var data = spa.src(e).attr('row-data') || spa.src(e).parent.attr('row-data');
                            data.kind = data.kind == 1 ? 0 : 1;
                            var d = []; for (var i in data) d.push(encodeURIComponent(i)+'='+encodeURIComponent(data[i]));
                            return voting(info.url || g.location.search, d.join('&'), index, element);
                        });
                    });

                    element.spa.els('.btn.btn-info.btn-xs', function (btn) {
                        btn.spa.on('click', function (e) {
                            var info = spa.src(e).attr('row-info') || spa.src(e).parent.attr('row-info');
                            var data = spa.src(e).attr('row-data') || spa.src(e).parent.attr('row-data');
                            data.kind = data.kind == 2 ? 0 : 2;
                            var d = []; for (var i in data) d.push(encodeURIComponent(i)+'='+encodeURIComponent(data[i]));
                            return voting(info.url || g.location.search, d.join('&'), index, element);
                        });
                    });

                    element.spa.els('.btn.btn-warning.btn-xs', function (btn) {
                        btn.spa.on('click', function (e) {
                            var info = spa.src(e).attr('row-info') || spa.src(e).parent.attr('row-info');
                            var data = spa.src(e).attr('row-data') || spa.src(e).parent.attr('row-data');
                            data.kind = data.kind == 3 ? 0 : 3;
                            var d = []; for (var i in data) d.push(encodeURIComponent(i)+'='+encodeURIComponent(data[i]));
                            return voting(info.url || g.location.search, d.join('&'), index, element);
                        });
                    });

                    element.spa.els('tbody tr', function (row, i) {
                        row.spa.on('click', function (e) {
                            var x = spa.src(e), info = x.parent.attr('row-data');
                            if (x.instance.tagName == 'TD' && info) {
                                tmpl('tmpl-add-tab' + index, model['tmpl-tab' + index].rows[i], function (content) {
                                    var wnd = popup_box.spa.attr('data-form');
                                    popup.show({
                                        width: wnd && wnd.width || null,
                                        height: wnd && wnd.height || null,
                                        content: content,
                                        event: [
                                            function () {
                                                if (model['tmpl-add-tab'+index] && typeof model['tmpl-add-tab'+index].bootstrap === 'function') model['tmpl-add-tab' + index].bootstrap.call(popup.container, model['tmpl-tab' + index].row);
                                                var btn = spa.el('[role="popup-save"]');
                                                if (btn) btn.spa.on('click', function (e) {
                                                    spinner = true;
                                                    JSON.form(spa.el('[role="form popup"]'), model['tmpl-tab' + index].validator).release({
                                                        done: function (responce) {
                                                            if (model['tmpl-add-tab'+index]) {
                                                                if (typeof model['tmpl-add-tab' + index].submited === 'function')
                                                                    model['tmpl-add-tab' + index].submited.call(this, model['tmpl-tab' + index].row);
                                                                if (model['tmpl-add-tab' + index].hasOwnProperty('fclear') && model['tmpl-add-tab' + index].fclear === true || !model['tmpl-add-tab' + index].hasOwnProperty('fclear'))
                                                                    filter.clear(element);
                                                            }
                                                            popup.hide(); filter.pg.page += 1;
                                                            chooseTab(index, element);
                                                            spinner = false;
                                                        },
                                                        fail: function (responce) {
                                                            spinner = false;
                                                            if (responce.error) for (var i =0; i < this.elements.length; i++) {
                                                                if (responce.error.hasOwnProperty(this.elements[i].name)) this.elements[i].status = 'error';
                                                                else if (this.elements[i].status == 'nene') this.elements[i].status = 'nene';
                                                            }
                                                            msg.show(responce);
                                                        },
                                                        method: info.method || 'put',
                                                        rs: {'Hash': g.acl.user.hash}
                                                    });
                                                    return false;
                                                })
                                            }
                                        ]
                                    });
                                });
                            }
                            return false;
                        }, true);
                    });
                }
            };

            var checking = function(url, data, index, element ) {
                xhr.request({method:'POST', url: url, data: data, rs:{'Hash':acl.user.hash}})
                    .result(function(d) {
                        if ([200, 206].indexOf(this.status) < 0) {
                            msg.show({error:'ОШИБКА', message: this.status + ': ' + this.statusText });
                        } else {
                            try { var res = JSON.parse(this.responseText);
                                if (res.result == 'error'){
                                    msg.show(res);
                                } else
                                    filter.pg.page +=1 ;
                                chooseTab(index, element);
                            } catch(e) {
                                msg.show({message:'сервер вернул не коректные данные'});
                            }
                        }
                        return this;
                    });
                return false;
            };

            var voting = function(url, data, index, element ) {
                xhr.request({method:'PUT', url: url, data: data, rs:{'Hash':acl.user.hash}})
                    .result(function(d) {
                        if ([200, 206].indexOf(this.status) < 0) {
                            msg.show({error:'ОШИБКА', message: this.status + ': ' + this.statusText });
                        } else {
                            try { var res = JSON.parse(this.responseText);
                                if (res.result == 'error'){
                                    msg.show(res);
                                } else
                                    filter.pg.page +=1 ;
                                    chooseTab(index, element);
                            } catch(e) {
                                msg.show({message:'сервер вернул не коректные данные'});
                            }
                        }
                        return this;
                    });
                return false;
            };

            var deleting = function(url, data, index, element ) {
                xhr.request({method:'DELETE', url: url, data: data, rs:{'Hash':acl.user.hash}})
                    .result(function(d) {
                        if ([200, 206].indexOf(this.status) < 0) {
                            msg.show({error:'ОШИБКА', message: this.status + ': ' + this.statusText });
                        } else {
                            try { var res = JSON.parse(this.responseText);
                                if (res.result == 'error'){
                                    msg.show(res);
                                } else
                                    chooseTab(index, element);
                            } catch(e) {
                                msg.show({message:'сервер вернул не коректные данные'});
                            }
                        }
                        return this;
                    });
            };

            var chooseTab = function(index, element) {
                spinner = true;
                // if (filter.update(element))
                xhr.request({url:  filter.pg.location(element), rs:{'Hash':acl.user.hash}}).result(function(e){
                    spinner = false;
                    if ([200, 206].indexOf(this.status) < 0) {
                        errors({result:'error',message:this.status + ': ' + this.statusText +' (URL: '+filter.pg.location(element)+')'});
                    } else {
                        try {
                            var res = JSON.parse(this.responseText);
                            tabContent(res, index, element, res.result == 'error' ? errors : null);
                        } catch (e) {
                            msg.show({message: 'сервер вернул не коректные данные', e: e});
                        }
                    }
                });
                //else
                //tabContent({data: {rows: model['tmpl-tab' + index].rows || []}}, index, element);

            };

            spa.el('.dropdown-toggle').spa.on('click', function (e) {css.el(this.parentElement).tgl('open') })
                .spa.on('blur', function (e) { css.el(this.parentElement).del('open') })
                .setAttribute('tabindex', '0');
            spa.el('[role="logout"]').spa.on('mousedown', function (e) {
                if (confirm('Вы действительно хотите завершить работу?')) {
                    css.el(spa.el('.dropdown-toggle').parentElement).del('open');
                    spa.el('[role="workspace"]').innerHTML = '';
                    acl.user = null;
                    tmpl.cache = {};
                    login();
                }
            });
            spa.els('[data-tab]', function(t, i){
                if (i == 0) {
                    g.css.el(t.parentElement).add('active');
                    var f = t.getAttribute('data-tab');
                    var e = spa.el('[data-tab-container="' + f + '"]');
                    e.style.display = 'inherit';
                    tmpl('tmpl-tab' + f, model['tmpl-tab' + f], function (c) {
                        e.innerHTML = c;
                        filter.pg.page = null;
                        chooseTab(f, e);
                        spinner = false;
                    });
                }
                t.spa.on('click', function (e) {
                    var active = spa.el('.nav.nav-tabs li.active'),
                        el = spa.src(e),
                        nextIndex = el.instance.getAttribute('data-tab');
                    if ( !el.parent.css().has('active')) {
                        spinner = true;
                        var activeIndex = spa.el('.nav.nav-tabs li.active a').getAttribute('data-tab');
                        fadeOut(spa.el('[data-tab-container="' + activeIndex + '"]'), function () {
                            active.css.del('active');
                            el.parent.css().add('active');
                            var next = spa.el('[data-tab-container="' + nextIndex + '"]');
                            tmpl('tmpl-tab' + nextIndex, model['tmpl-tab' + nextIndex], function (c) {
                                next.innerHTML = c;
                                filter.pg.page = null;
                                chooseTab(nextIndex, next);
                                spinner = false;
                                fadeIn(next);
                            });
                        });
                    }
                    return false;
                }, true);
            }, 'tabs');
            
        }, {rs:{'Hash': g.acl.user.hash}});
    }

    filter.validators.named = function(p){
        var re = /^[а-яА-ЯёЁ\-\s]+$/i;
        var self = inputer(this.spa).instance;
        if (self.value) {
            var res = re.test(self.value.trim());
            if (!res) self.status = 'error';
            else self.status = 'success';
            return res;
        }
        self.status = 'none';
        return true;
    };

    filter.validators.digit = function(p){
        var re = /^[0-9]+$/i;
        var self = inputer(this.spa).instance;
        if (self.value) {
            var res = re.test(self.value.trim());
            if (!res) self.status = 'error';
            else self.status = 'success';
            return res;
        }
        self.status = 'none';
        return true;
    };

    filter.validators.digits = function(p){
        var re = /^\d{4}|[VIXCM]+-[А-Я]|(80|81|82|83|90)$/i;
        var self = inputer(this.spa).instance;
        if (self.value) {
            var res = re.test(self.value.trim());
            if (!res) self.status = 'error';
            else self.status = 'success';
            return res;
        }
        self.status = 'none';
        return true;
    };

    filter.validators.pgdate = function(p){
        var re = /^(0|1|2|3)\d\.(0|1)\d\.(19|20)\d{2}$/i;
        var self = inputer(this.spa).instance;
        if (self.value) {
            var res = re.test(self.value.trim());
            if (!res) self.status = 'error';
            else self.status = 'success';
            return res;
        }
        self.status = 'none';
        return true;
    };

    filter.formatter = {};
    filter.formatter.digits = function(source, pattern, stub){
        if (!pattern) return source;
        for (var i in source) if (/\d/.test(source[i])) pattern = pattern.replace('_', source[i]);
        return !source ? stub || '' : pattern.replace(/\_/g, '');
    };

    g.formvalidator = function(res){
        var test = function(element){
            if (element) {
                var res = true;
                if ((element.getAttribute('required') !== null) && !element.value) res = false;
                else if ((element.getAttribute('required') === null) && !element.value) res = true;
                else if (element.getAttribute('pattern') === null) res = true;
                else { try {
                    var pattern = /[?\/]([^\/]+)\/([^\/]*)/g.exec(element.getAttribute('pattern')) || [];
                    var re = new RegExp(pattern[1], pattern[2]);
                    res = re.test(element.value.trim());
                } catch(e) { res = false }
                }

                var el = inputer(element.hasOwnProperty('spa') ? element.spa : spa.create(element));
                if (!res) el.instance.status = 'error';
                else if (!el.instance.hasAttribute('disabled'))
                    if (element.value.length) el.instance.status = 'success'; else el.instance.status = null;
                return res;
            }
            return false;
        };

        var result = true;
        for (var i =0; i < this.elements.length; i++) result = result & test(this.elements[i]);

        if (!result) {
            spinner = false;
            msg.show({message: 'неверно заполнены поля формы!'});
        }
        return result;
    };

    var typeahead = function (element, opt) {
        if (element) {
            var instance = element.hasOwnProperty('spa') ? element : spa.create(element).instance;
            var th = {
                tmpl:function(data){
                    var self = this.owner;
                    this.index = 0; this.key = self.value.toLowerCase() || 'null';
                    if (self.pannel) {
                        var n = spa.xml(tmpl(this.opt.tmpl, {data:data})).firstChild;
                        if (n) self.pannel.innerHTML = n.innerHTML;
                    } else {
                        self.spa.parent.instance.insertAdjacentHTML('beforeend', tmpl(this.opt.tmpl, {data: data}));
                        self.spa.parent.css().add('dropdown');
                        self.pannel = self.spa.parent.el('.dropdown-menu.list');
                    }
                    self.spa.parent.els('.dropdown-menu.list li', function (i) {
                        i.spa.on('mousedown', function (e) {
                            self.value = this.innerHTML;
                            if (self.typeahead.opt.key) self.typeahead.opt.key.value = this.spa.attr('value');
                            return false;
                        });
                    });
                },
                xhr:function(){
                    var self = this.owner, params = {};
                    params[self.name] = self.value;
                    var index = self.value ? self.value.toLowerCase() : 'null';
                    if (!this.cache.hasOwnProperty(index) || index == 'null'){
                        self.status = 'spinner';
                        xhr.request({url: location.update(self.spa.attr('url'), params), rs: {'Hash': acl.user.hash}})
                            .result(function (d) {
                                if ([200, 206].indexOf(this.status) < 0) {
                                    msg.show({error: 'ОШИБКА', message: this.status + ': ' + this.statusText});
                                } else {
                                    try {
                                        var res = JSON.parse(this.responseText);
                                        if (res.result == 'error') {
                                            msg.show(res);
                                        } else {
                                            self.typeahead.cache[index] = res.data;
                                            self.typeahead.show(res.data);
                                        }
                                    } catch (e) {
                                        msg.show({message: 'сервер вернул не коректные данные'});
                                    }
                                }
                                self.status = 'none';
                                return this;
                            });
                    } else {
                        self.typeahead.show(this.cache[index]);
                    }
                },
                show:function(data){
                    var self = this.owner;
                    if (self === g.document.activeElement) if (Object.keys(data).length) {
                        this.tmpl(data);
                        return fadeIn(self.pannel);
                    } else {
                        if (self.pannel) {
                            self.pannel.innerHTML = null;
                            fadeOut(self.pannel);
                        }
                    }
                    return false;
                },
                onKeydown:function (e) {
                    var key = (e.charCode && e.charCode > 0) ? e.charCode : e.keyCode;
                    var th = this.typeahead, cashe = th.cache[th.key],cnt = Object.keys(cashe || {}).length - 1,y = 0;
                    switch (key) {
                        case 38:
                            for (var x in cashe) {
                                if (y == th.index) {
                                    this.value = cashe[x];
                                    if (th.opt.key) th.opt.key.value = x;
                                    this.selectionStart = this.selectionEnd = this.value.length;
                                    if (th.index > 0) th.index--; else th.index = cnt;
                                    e.preventDefault();
                                    e.stopPropagation();
                                    return false;
                                }
                                y++;
                            }
                            return false;
                        case 40:
                            for (var x in cashe) {
                                if (y == th.index) {
                                    this.value = cashe[x];
                                    if (th.opt.key) th.opt.key.value = x;
                                    this.selectionStart = this.selectionEnd = this.value.length;
                                    if (th.index < cnt) th.index++; else th.index = 0;
                                    e.preventDefault();
                                    e.stopPropagation();
                                    return false;
                                }
                                y++;
                            }
                            return false;
                        case 13:
                            this.status = 'none';
                            fadeOut(this.pannel);
                            e.preventDefault();
                            return e.stopPropagation();
                        default: return false;
                    }
                },
                onChange: function (e) {
                    var th = this.typeahead;
                    if (th.opt.key) {
                        th.opt.key.value = '';
                        if (this.value && th.cache.hasOwnProperty(this.value.toLowerCase())) {
                            var ds = this.typeahead.cache[this.value.toLowerCase()];
                            for (var x in ds) if (ds[x].toLowerCase() === this.value.toLowerCase()) th.opt.key.value = x;
                        }
                        return th.opt.key.value;
                    }
                    return false;
                },
                onFocus:function(e){
                    this.typeahead.xhr();
                    return false;
                },
                onInput:function(e){
                    this.typeahead.xhr();
                    return false;
                },
                onBlur:function(e){
                    fadeOut(this.pannel);
                    return false;
                }
            };
            th.index = 0; th.key = null; th.cache = {}; th.opt = {master:[], slave:[], tmpl:'typeahead-tmpl'};
            instance.typeahead = th;
            th.opt = Object.assign(th.opt, opt);
            instance.typeahead.owner = element;
            inputer(instance.spa).on('focus',th.onFocus).spa.on('input',th.onInput)
                .spa.on('blur',th.onBlur).spa.on('keydown', th.onKeydown).spa.on('change',th.onChange);
            if (!instance.spa.attr('tabindex')) instance.spa.attr('tabindex', '0');
            return instance;
        }
    }; g.typeahead = typeahead;

    var inputer = function(el){
        if (el && !el.instance.hasOwnProperty('status')) {
            var parent = el.parent;
            el.instance.chk = parent.el('span');
            Object.defineProperty(el.instance, 'status', {
                set: function status(stat) {
                    parent.css().add('has-feedback').del('has-error').del('has-warning').del('has-success');
                    if (this.chk)  this.chk.css.del( 'glyphicon-ok').del('glyphicon-warning-sign').del('glyphicon-remove').del('spinner');
                    switch (stat) {
                        case 'error':
                            this._status = 'error';
                            if (this.chk) this.chk.css.add('glyphicon-remove');
                            parent.css().add('has-error');
                            break;
                        case 'warning':
                            this._status = 'warning';
                            if (this.chk) this.chk.css.add('glyphicon-warning-sign');
                            parent.css().add('has-warning');
                            break;
                        case 'success':
                            this._status = 'success';
                            if (this.chk) this.chk.css.add('glyphicon-ok');
                            parent.css().add('has-success');
                            break;
                        case 'spinner':
                            this._status = 'spinner';
                            if (this.chk) this.chk.css.add('spinner');
                            break;
                        case 'none':
                        default:
                            this._status = 'none';
                    }
                },
                get: function status() {
                    return this._status;
                }
            });
        }
        return el;
    }; g.inputer = inputer;

    var maskedigits = function(elemetn, pattern){
        var el = inputer(elemetn);
        if (el.instance.tagName === 'INPUT') {
            if (pattern) el.instance.maxLength = el.attr('placeholder', pattern || '').attr('placeholder').length;
            if (!el.attr('tabindex')) el.attr('tabindex', '0');
            if (el && !el.instance.hasOwnProperty('insertDigit')) {
                el.instance.insertDigit = function(dg, selected) {
                    if (selected) {
                        var pos = this.value.indexOf(selected);
                        var digitOffset = /\d/.test(dg) ? 1 : 0;
                        var shift = this.spa.attr('placeholder').substr(pos, selected.length).indexOf('_');
                        if (shift > 0) pos += shift;
                        this.value = this.value.substr(0,pos)+(/\d/.test(dg)?dg:'')+this.spa.attr('placeholder').substr(pos+digitOffset,
                                selected.length-digitOffset)+this.value.substr(pos+selected.length, this.value.length);
                        this.selectionStart = this.e1 = this.selectionEnd = this.s1 = pos +1;
                    } else if (/\d/.test(dg) && (this.value || this.spa.attr('placeholder')).indexOf('_') > -1) {
                        var text = this.value || this.spa.attr('placeholder');
                        var pos = (text).indexOf('_');
                        var next = text.match(/\d/) ? (text.indexOf(text.match(/\d/))) : -1;
                        if (pos <= this.selectionStart || next < 0 || next > pos) {
                            this.value = (this.value || this.spa.attr('placeholder')).replace('_', dg);
                            pos = (this.value || this.spa.attr('placeholder')).indexOf('_');
                            this.e1 = this.selectionEnd = this.selectionStart = this.s1 =  pos > -1 ? pos : this.value.length;
                        } else if (pos > this.selectionStart) {
                            this.s1 = pos = this.selectionStart;
                            var text = dg + (this.value.substr(pos, this.value.length).match(/\d+/g) || []).join('')+'_';
                            for (var i= 0; i < text.length -1; i++) {
                                pos = this.value.indexOf(text.charAt(i+1), pos);
                                if (pos > -1) this.value = this.value.substr(0, pos) + text.charAt(i) + this.value.substr(pos+1, this.value.length);
                            }
                            this.selectionStart = this.e1 = this.selectionEnd = ++this.s1;
                        }
                    }
                    return this.selectionStart;
                };
                el.instance.init = function (clear) {
                    var text = this.value;
                    var pos = 0;
                    if (text) {
                        this.value = this.spa.attr('placeholder');
                        pos = this.value.indexOf('_');
                        for (var i in text) if (/\d/.test(text[i])) {
                            this.value = this.value.replace('_', text[i]);
                            pos = this.value.indexOf('_');
                        }
                    } else {
                        if (!clear) this.value = this.spa.attr('placeholder');
                        pos = this.spa.attr('placeholder').indexOf('_');
                    }
                    if (clear) this.value = this.value.replace(/\_/g, '');
                    return this.e1 = this.selectionEnd = this.selectionStart = this.s1 = (pos > -1 ? pos : this.value.length);
                };
            };
            el.instance.init(true);
            el.on('keydown', function (e) {
                if (this.spa.attr('placeholder').length && !this.value) {
                    this.value = this.spa.attr('placeholder');
                    this.e1 = this.selectionEnd = this.selectionStart = this.s1 = 0;
                }

                var selected = window.getSelection().toString();
                var key = (e.charCode && e.charCode > 0) ? e.charCode : e.keyCode;
                var dg = ((key >= 96 && key <= 105)) ? (key-96).toString() : String.fromCharCode(key);
                switch (key) {
                    case 8:
                        if (selected ) {
                            var pos = this.value.indexOf(selected);
                            this.value = this.value.substr(0,pos)+this.spa.attr('placeholder').substr(pos, selected.length)+
                                this.value.substr(pos+selected.length, this.value.length);
                            var shift = this.spa.attr('placeholder').substr(pos, selected.length).indexOf('_');
                            if (shift > 0) pos += shift;
                            this.selectionStart = this.e1 = this.selectionEnd = this.s1 = pos;
                        } else {
                            this.e1 = this.s1 = --this.selectionStart;  --this.selectionEnd;
                            while ((this.s1 >= 0) && !/\d/.test(this.value.charAt(this.s1))) { this.s1 = --this.selectionStart; --this.selectionEnd;}
                            if (this.s1 >= 0 && /\d/.test(this.value.charAt(this.s1))) this.value = this.value.substr(0, this.s1) + '_' + this.value.substr((this.s1+1), this.value.length);
                            else this.s1 = this.e1 + 1;
                            this.selectionStart = this.selectionEnd = this.s1;
                        }
                        break;
                    case 9:
                        var el = false; var way = e.shiftKey ? -1 : 1;
                        var index = parseInt(this.spa.attr('tabindex'));
                        if (index > 0) while (el = spa.el('[tabindex="'+index+'"]'))
                            if (el.spa.attr('disabled')) index += way; else { el.focus(); break; }
                        if (index <= 1 && way < 0) return e.preventDefault();
                        e.stopPropagation();
                        return false;
                    case 37:
                        this.s1 = --this.selectionStart; this.e1 = --this.selectionEnd;
                        break
                    case 39:
                        this.s1 = ++this.selectionStart;
                        break
                    default:
                        this.insertDigit(dg, selected);
                }
                e.preventDefault(); e.stopPropagation();
                return /d/.test(dg);
            }, false).spa.on('focus', function (e) {
                this.init(false); e.preventDefault(); e.stopPropagation();
                return false;
            }, false).spa.on('blur',function(e){
                if (this.value.match(/[\d]+/g)) this.value = this.value.replace(/\_/g, '');
                else this.value = '';
                e.preventDefault(); e.stopPropagation();
                return false;
            }, false).spa.on('paste',function(e){
                var dgs = e.clipboardData.getData('Text').match(/\d+/g) ? e.clipboardData.getData('Text').match(/\d+/g).join('') : ''
                //TODO pate afte cursor position & past selected pice
                var selected = window.getSelection().toString();
                for (var i in dgs) this.insertDigit(dgs[i], selected);
                e.preventDefault(); e.stopPropagation();
                return false;
            }, false);
        }
        return el;
    }; g.maskedigits = maskedigits;

    if (!acl.user) login(); else workspace(acl);

}( window ));