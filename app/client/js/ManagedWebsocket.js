(function(global)
{
    /**
     * クライアント起点の切断
     */
    const CHAT_SELF_CLOSE_CODE = 10;

    /**
     * サーバーからの切断
     */
    const CHAT_SERVER_CLOSE_CODE = 1006;

    /**
     * オプションメッセージ
     */
    const OPTION_ADMIN_USER = 'システム';
    const OPTION_SERVER_CLOSE = 'サーバーから切断されました。';
    const OPTION_UNEXPECTED_ERROR = '予期しないエラーが発生しました。';

    class ManagedWebsocket
    {
        constructor(p_server_id, p_protocol, p_host, p_port, p_operator, p_params, p_gui_root)
        {
            this.flg_error = false;
            this.self_user_id = null;
            this.self_user_name = p_operator;
            this.option_message = {admin_user: OPTION_ADMIN_USER, server_close: OPTION_SERVER_CLOSE, unexpected_error: OPTION_UNEXPECTED_ERROR};
            this.user_list = null;
            this.server_id = p_server_id;
            this.uri = `${p_protocol}://${p_host}:${p_port}/${p_params.join('/')}`;
            this.ws = new WebSocket(this.uri);

            this.customize_import = new CustomizeImport(this.ws, p_host, p_port, p_gui_root);
            this.#registerHandlers();

            this.ws.onopen = this.#handleOpen.bind(this);
            this.ws.onmessage = this.#handleMessage.bind(this);
            this.ws.onclose = this.#handleClose.bind(this);
            this.ws.onerror = this.#handleError.bind(this);
        }

        #registerHandlers()
        {
            for(const [event_type, handler] of this.customize_import.getHandlers())
            {
                this.ws.addEventListener(event_type, handler.bind(this.customize_import));
            }
        }

        #handleOpen(p_event)
        {
            const user = this.getSelfUserName();
            this.ws.send(JSON.stringify({ cmd: 'entering', user: user }));
            SocketManager.preparingServer(this.server_id);
        }

        #handleMessage(p_event)
        {
            const data = JSON.parse(p_event.data);

            if(data.cmd === 'entering')
            {
                this.user_list.set(data.uid, data.user);
                SocketManager.entryUserList(this.server_id, this.getSelfUserId(), this.user_list);
                SocketManager.entryChatLog({p_server_id: this.server_id, p_datetime: data.datetime, p_message: data.message, p_user_id: data.uid, p_user_name: data.user});
            }
            else
            if(data.cmd === 'entering_response')
            {
                this.option_message = data.option_message;
                if(data.result)
                {
                    this.self_user_id = data.uid;
                    this.user_list = new Map(Object.entries(data.user_list));
                    SocketManager.entryUserList(this.server_id, this.getSelfUserId(), this.user_list);
                    SocketManager.connected(this.server_id);
                    SocketManager.entryChatLog({p_server_id: this.server_id, p_datetime: data.datetime, p_message: data.message, p_user_id: data.uid, p_user_name: data.user});
                    SocketManager.renderServiceManager(this.server_id, data.service_list, data.enable_save_flg);
                }
                else
                {
                    SocketManager.connectionFailure(this.server_id);
                    SocketManager.entryChatLog({p_server_id: this.server_id, p_datetime: data.datetime, p_message: data.message, p_user_id: data.uid, p_user_name: data.user});
                }
            }
            else
            if(data.cmd === 'leaving')
            {
                this.user_list.delete(data.uid);
                SocketManager.entryUserList(this.server_id, this.getSelfUserId(), this.user_list);
                SocketManager.entryChatLog({p_server_id: this.server_id, p_datetime: data.datetime, p_message: data.message, p_user_id: data.uid, p_user_name: data.user});
            }
            else
            if(data.cmd === 'core_count')
            {
                SocketManager.renderCpuCheckboxes(this.server_id, data.count);
            }
            else
            if(data.cmd === 'launcher_log')
            {
                SocketManager.appendLauncherLog(this.server_id, data.log);
            }
            else
            if(data.cmd === 'resource_cpu')
            {
                SocketManager.renderCpuResource(this.server_id, data.usage_rate, data.usage_color, data.logical_cpus, data.service_cpus);
            }
            else
            if(data.cmd === 'resource_memory')
            {
                SocketManager.renderMemoryBar(this.server_id, data.types, data.services);
            }
            else
            if(data.cmd === 'resource_disk')
            {
                SocketManager.renderDiskBar(this.server_id, data.disks);
            }
            else
            if(data.cmd === 'service_list')
            {
                SocketManager.renderServiceManager(this.server_id, data.list, data.enable_save_flg);
            }
            else
            if(data.cmd === 'action_response')
            {
                if(data.result === false)
                {
                    SocketManager.updateServiceOverlay(this.server_id, data.message);
                    setTimeout(() => {
                        SocketManager.hideServiceOverlay(this.server_id);
                    }, 3000);
                }
            }
            else
            if(data.cmd === 'setting_action_response')
            {
                if(data.result === false)
                {
                    SocketManager.updateServiceOverlay(this.server_id, data.message);
                    setTimeout(() => {
                        SocketManager.hideServiceOverlay(this.server_id);
                    }, 3000);
                }
                else
                {
                    SocketManager.showServiceOverlay(this.server_id, '反映しました'/* data.message */);
                    setTimeout(() => {
                        SocketManager.hideServiceOverlay(this.server_id);
                    }, 3000);
                    if(data.type === 'edit' || data.type === 'append')
                    {
                        SocketManager.editorSlideUp(this.server_id);
                    }
                }
            }
            else
            if(data.cmd === 'action_notice')
            {
                if(data.is_guard === true)
                {
                    SocketManager.showServiceOverlay(this.server_id, data.message);
                }
                else
                {
                    SocketManager.hideServiceOverlay(this.server_id);
                }
            }
            else
            if(data.cmd === 'message')
            {
                SocketManager.entryChatLog({p_server_id: this.server_id, p_datetime: data.datetime, p_message: data.message, p_user_id: data.uid, p_user_name: data.user});
            }
            else
            if(data.cmd === 'private_message')
            {
                SocketManager.entryChatLog({p_server_id: this.server_id, p_datetime: data.datetime, p_message: data.message, p_user_id: data.uid, p_user_name: data.user, p_is_private: true});
            }
            else
            if(data.cmd === 'private_message_response')
            {
                SocketManager.entryChatLog({p_server_id: this.server_id, p_datetime: data.datetime, p_message: data.message, p_user_id: this.getSelfUserId(), p_user_name: this.getSelfUserName(), p_is_private: true, p_is_success: data.result});
            }
        }

        #handleClose(p_event)
        {
            console.log(`[${this.server_id}] WebSocket切断: code=${p_event.code}, reason=${p_event.reason}`);

            if(this.flg_error === true)
            {
                return;
            }

            if(p_event.code === CHAT_SELF_CLOSE_CODE)
            {
                const payload = JSON.parse(p_event.reason);
                SocketManager.entryChatLog({p_server_id: this.server_id, p_datetime: payload.datetime, p_message: this.option_message.leaving, p_user_id: this.getSelfUserId(), p_user_name: this.getSelfUserName()});
            }
            else
            if(p_event.code === CHAT_SERVER_CLOSE_CODE)
            {
                SocketManager.entryChatLog({p_server_id: this.server_id, p_datetime: this.getDatetimeString(), p_message: this.option_message.server_close, p_user_id: '', p_user_name: this.option_message.admin_user});
            }
            else
            {
                SocketManager.entryChatLog({p_server_id: this.server_id, p_datetime: this.getDatetimeString(), p_message: this.option_message.unexpected_close, p_user_id: '', p_user_name: this.option_message.admin_user});
            }

            SocketManager.disconnected(this.server_id);
        }

        #handleError(p_error)
        {
            this.flg_error = true;

            let error_message = this.option_message.unexpected_error;
            if(typeof(p_error.message) !== 'undefined')
            {
                error_message = p_error.message;
            }
            SocketManager.error(this.server_id);
            console.log(`エラー発生[${this.server_id}]${error_message}`);

            if(this.ws.readyState === this.ws.OPEN)
            {
                this.ws.close();
            }
        }

        getSelfUserId()
        {
            return this.self_user_id;
        }

        getSelfUserName()
        {
            return this.self_user_name;
        }

        getUserName(p_user_id)
        {
            const return_user_name = null;
            for(const [user_id, user_name] of this.user_list)
            {
                if(user_id === p_user_id)
                {
                    return_user_name = user_name;
                }
            }
            return return_user_name;
        }

        sendMessage({p_message, p_recipient = null})
        {
            if($.type(p_recipient) === 'string' && p_recipient !== '')
            {
                this.ws.send(JSON.stringify({cmd:'private_message', message: p_message, uid: p_recipient}));
            }
            else
            {
                this.ws.send(JSON.stringify({cmd:'message', message: p_message}));
            }
            SocketManager.clearMessage(this.server_id);
        }

        disconnect()
        {
            // 切断要求コマンドを送信
            let data =
            {
                'cmd': 'leaving'
            };
            this.ws.send(JSON.stringify(data));
        }

        /**
         * 現在の日時文字列を取得
         * 
         * @returns {string} 日時文字列（"Y/m/d H:i:s"形式）
         */
        getDatetimeString()
        {
            let ins = new Date();
            let y = ins.getFullYear();
            y = y.toString().padStart(4, '0');
            let m = ins.getMonth() + 1;
            m = m.toString().padStart(2, '0');
            let d = ins.getDate();
            d = d.toString().padStart(2, '0');
            let h = ins.getHours();
            h = h.toString().padStart(2, '0');
            let i = ins.getMinutes();
            i = i.toString().padStart(2, '0');
            let s = ins.getSeconds();
            s = s.toString().padStart(2, '0');

            return `${y}-${m}-${d} ${h}:${i}:${s}`;
        }

        action({p_action, p_service, p_group = false})
        {
            // アクションコマンドを送信
            let data =
            {
                'cmd': 'action',
                'action': p_action,
                'service': p_service,
                'group': p_group
            };
            this.ws.send(JSON.stringify(data));
        }

        settingAction({p_type, p_service = null, p_items = null})
        {
            // アクションコマンドを送信
            let data =
            {
                'cmd': 'setting_action',
                'type': p_type,
                'service': p_service,
                'items': p_items
            };
            this.ws.send(JSON.stringify(data));
        }
    }

    global.ManagedWebsocket = ManagedWebsocket;
})(window);
