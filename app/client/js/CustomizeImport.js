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

            this.ws.addEventListener("message", (e) =>
            {
                const msg = JSON.parse(e.data);
                const key = `${msg.service}:${msg.type}`;
                const handler = this.handlerMap.get(key);
                if(handler)
                {
                    handler(msg);
                }
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
                    this.handlerMap.set(key, (msg) =>
                    {
                        this.#updateGUI(label, msg.payload);
                    });
                    this.#createGUIItem(label);
                });
        }

        #createGUIItem(p_label)
        {
            const container = document.createElement("div");
            container.className = "gui-item";

            const title = document.createElement("h4");
            title.textContent = p_label;

            const content = document.createElement("pre");
            content.className = "gui-content";
            content.textContent = "待機中...";

            container.appendChild(title);
            container.appendChild(content);
            this.gui_root.appendChild(container);

            this.guiElements.set(p_label, content);
        }

        #updateGUI(p_label, p_payload)
        {
            const el = this.guiElements.get(p_label);
            if(el)
            {
                el.textContent = JSON.stringify(p_payload, null, 2);
            }
        }
    }

    global.CustomizeImport = CustomizeImport;
})(window);
