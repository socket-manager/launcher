(function(global)
{
    class CustomizeImport
    {
        constructor(p_ws, p_host, p_port, p_gui_root)
        {
            this.ws = p_ws;
            this.gui_root = p_gui_root;
            this.handlerMap = new Map();
            this.guiElements = new Map();

            this.ws.addEventListener('message', (e) =>
            {
                const payload = JSON.parse(e.data);
                $.each(payload.parts, (idx, val) =>
                {
                    const key = `${val.service}:${val.type}`;
                    const handler = this.handlerMap.get(key);
                    if(handler)
                    {
                        handler(val);
                    }
                });
            });

            this.#initHandlers(p_host, p_port);
        }

        getHandlers()
        {
            return this.handlerMap;
        }

        #initHandlers(p_host, p_port)
        {
            operator_config
                .filter(h => h.host == p_host && h.port == p_port)
                .forEach(({ service, type, label }) =>
                {
                    const key = `${service}:${type}`;
                    this.handlerMap.set(key, (part) =>
                    {
                        this.#updateGUI(service, label, part.data);
                    });
                });
        }

        #insertMonitoringItem(serviceLabel, partLabel, data)
        {
            const panel = document.querySelector(`.monitoring-panel[data-service="${serviceLabel}"]`);
            if(!panel)
            {
                return;
            }

            const container = panel.querySelector('.monitoring-items');
            if(!container)
            {
                return;
            }

            // 既存項目があれば更新、なければ追加
            let item = container.querySelector(`.monitoring-item[data-label="${partLabel}"]`);
            if(!item)
            {
                item = document.createElement('div');
                item.className = 'monitoring-item';
                item.setAttribute('data-label', partLabel);

                const title = document.createElement('h5');
                title.textContent = partLabel;

                const content = document.createElement('pre');
                content.className = 'monitoring-content';
                content.textContent = data;

                item.appendChild(title);
                item.appendChild(content);
                container.appendChild(item);
            }
            else
            {
                const content = item.querySelector('.monitoring-content');
                if(content)
                {
                    content.textContent = data;
                }
            }
        }

        #updateGUI(p_service, p_label, p_data)
        {
            this.#insertMonitoringItem(p_service, p_label, p_data);
        }
    }

    global.CustomizeImport = CustomizeImport;
})(window);
