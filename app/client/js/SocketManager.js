(function(global)
{
    let server_id_counter = 0;
    const sockets = new Map();
    let service_list = [];
    let editing_index = null;
    let is_add_mode = false;
    const max_log_entries = 300;
    const logical_cpu_datas = new Map();
    const graph_point_max = 50;

    class CpuData
    {
        constructor(p_cpu_no, p_initial_value, p_color)
        {
            this.cpu_no = p_cpu_no;
            this.history = Array(graph_point_max).fill(0);
            this.history[graph_point_max - 1] = p_initial_value;
            this.label = `CPU ${p_cpu_no}`;
            this.status_color = p_color;
        }

        getCpuId()
        {
            return `cpu${this.cpu_no}`;
        }

        update(p_value, p_color)
        {
            this.status_color = p_color;
            this.history.push(p_value);
            this.history.shift();
        }
    }

    function generateServerId()
    {
        server_id_counter++;
        return `server-${String(server_id_counter).padStart(3, '0')}`;
    };

    function getServerSection(p_server_id)
    {
        let $server_section = null;
        $('#server-container .server-section').each(function()
        {
            if($(this).data('server-id') == p_server_id)
            {
                $server_section = $(this);
                return false;
            }
        });
        return $server_section;
    }

    function updateConnectionStatus(p_header_elem, p_status_text, p_status_class)
    {
        // 削除ボタンの表示制御
        const section_cnt = $('#server-container .server-section').length;
        if(section_cnt > 1)
        {
            if(p_status_text === 'オフライン')
            {
                $(p_header_elem).find('.remove-server-btn').prop('disabled', false);
            }
            else
            {
                $(p_header_elem).find('.remove-server-btn').prop('disabled', true);
            }
        }

        const $status = $(p_header_elem).find('.connection-status');
        $status.text(p_status_text);

        // 既存の状態クラスを削除
        $status.removeClass('status-disconnected status-error status-online status-pending');

        // 新しい状態クラスを追加
        $status.addClass(p_status_class);

        const $section = $(p_header_elem).closest('.server-section');
        const $accordion_body = $section.find('.accordion-body');
        updateAccordionOverlay($accordion_body, p_status_text);
    }

    function validateInputs(p_header_elem)
    {
        const $header = $(p_header_elem);
        const $host_input = $header.find('.host-input');
        const $port_input = $header.find('.port-input');
        const $operator_input = $header.find('.operator-input');

        let has_empty = false;

        [$host_input, $port_input, $operator_input].forEach(($input) =>
        {
            if($input.val().trim() === '')
            {
                $input.addClass('input-warning');
                has_empty = true;
            }
            else
            {
                $input.removeClass('input-warning');
            }
        });

        if(has_empty)
        {
            updateConnectionStatus($header, '未入力あり', 'status-error');
        }

        return !has_empty;
    }

    function updateAccordionOverlay($p_accordion_body, p_status_text)
    {
        const $overlay = $p_accordion_body.find('.overlay-blocker');

        if(p_status_text !== 'オンライン')
        {
            $overlay.show();
        }
        else
        {
            $overlay.hide();
        }
    }

    function insertAddButton()
    {
        // 既存の＋ボタンを削除
        $('.add-server-btn').remove();

        // 最後の .server-section に追加
        const $last_section = $('#server-container .server-section').last();
        if($last_section.length)
        {
            $last_section.append('<button class="add-server-btn">＋ サーバー追加</button>');
        }
    }

    function renderServiceList(p_server_id, p_enable_save_flg)
    {
        const $section = getServerSection(p_server_id);
        const $body = $section.find('.service-list-body');
        $body.empty();

        if(typeof(p_enable_save_flg) !== 'undefined')
        {
            $section.find('.save-config-btn').prop('disabled', !p_enable_save_flg);
        }

        let stop_cnt = 0;
        service_list.forEach((svc, index) =>
        {
            let cpu = 'ー';
            let cpu_color = '';
            if(typeof(svc.cpu) !== 'undefined' && svc.cpu !== 'ー')
            {
                cpu = svc.cpu;
                cpu_color = svc.cpu_color;
            }
            let memory = 'ー';
            let memory_color = '';
            if(typeof(svc.memory) !== 'undefined' && svc.memory !== 'ー')
            {
                memory = svc.memory;
                memory_color = svc.memory_color;
            }

            const $row = $(`
                <tr data-index="${index}">
                    <td>
                        <button class="start-btn">▶</button>
                        <button class="toggle-detail-btn" style="padding-right: 2px;">🧩</button>
                        <button class="edit-btn">✏</button>
                        <button class="delete-btn">🗑</button>
                    </td>
                    <td>${svc.name}</td>
                    <td>${svc.group || 'ー'}</td>
                    <td>${svc.status}</td>
                    <td class="service-list-cpu ${cpu_color}">${cpu}</td>
                    <td class="service-list-memory ${memory_color}">${memory}</td>
                    <td>${svc.timestamp}</td>
                </tr>
                <tr class="service-detail-row" style="display: none;">
                    <td colspan="7">
                        <div class="monitoring-panel" data-service="${svc.name}">
                            <h4 class="monitoring-label">${svc.name} カスタムモニタリング</h4>
                            <div class="monitoring-items">
                            </div>
                        </div>
                    </td>
                </tr>
            `);

            const $status_cell = $row.find('td').eq(3); // 状態列
            const $start_btn = $row.find('button').eq(0); // 起動／停止ボタン
            const $edit_btn = $row.find('button').eq(2); // 編集ボタン
            const $delete_btn = $row.find('button').eq(3); // 削除ボタン
            if(svc.status === '起動中')
            {
                $status_cell.addClass('status-running');
                $start_btn.text('⏹');
                $edit_btn.prop('disabled', true);
                $delete_btn.prop('disabled', true);
            }
            else
            if(svc.status === '停止中')
            {
                $status_cell.addClass('status-stopped');
                $start_btn.text('▶');
                $edit_btn.prop('disabled', false);
                $delete_btn.prop('disabled', false);
                stop_cnt++;
            }
            else
            if(svc.status === '操作中')
            {
                $status_cell.addClass('status-operation');
                $start_btn.prop('disabled', true);
                $edit_btn.prop('disabled', true);
                $delete_btn.prop('disabled', true);
            }
            else
            if(svc.status === '未検知')
            {
                $status_cell.addClass('status-abnormality');
                $start_btn.text('▶');
                $start_btn.prop('disabled', false);
                $edit_btn.prop('disabled', true);
                $delete_btn.prop('disabled', true);
            }
            $body.append($row);
        });

        const max = service_list.length;
        if(stop_cnt >= max)
        {
            $section.find('.load-config-btn').prop('disabled', false);
        }
        else
        {
            $section.find('.load-config-btn').prop('disabled', true);
        }
    }

    function updateGroupSelector(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        const groups = [...new Set(service_list.map(svc => svc.group).filter(Boolean))];
        const $selector = $section.find('.group-selector');
        $selector.empty().append('<option value="">グループ選択</option>');
        groups.forEach(group =>
        {
            $selector.append(`<option value="${group}">${group}</option>`);
        });
    }

    function drawLineGraph(p_server_id, p_cpu_id)
    {
        const svg_canvas = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg_canvas.setAttribute('width', '100');
        svg_canvas.setAttribute('height', '100');

        const width = parseFloat(svg_canvas.getAttribute('width'));
        const height = parseFloat(svg_canvas.getAttribute('height'));
        const max = 100;

        const cpu_data = logical_cpu_datas.get(p_cpu_id);
        const cpu_datas = cpu_data.history;
        const points = cpu_datas.map((val, i) =>
        {
            const x = (i / (cpu_datas.length - 1)) * width;
            const y = height - (val / max) * height;
            return `${x},${y}`;
        }).join(' ');

        const title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
        const usage = cpu_data.history[graph_point_max - 1];
        title.textContent = cpu_data.label + `(${usage}%)`;
        svg_canvas.appendChild(title);

        // 背景
        const bg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        bg.setAttribute('x', '0');
        bg.setAttribute('y', '0');
        bg.setAttribute('width', '100');
        bg.setAttribute('height', '100');
        bg.setAttribute('class', 'cpu-' + cpu_data.status_color);
        svg_canvas.appendChild(bg);

        // グリッド線
        const grid_spacing = 20;
        const grid_color = '#555';

        for(let x = grid_spacing; x < width; x += grid_spacing)
        {
            const v_line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            v_line.setAttribute('x1', x);
            v_line.setAttribute('y1', 0);
            v_line.setAttribute('x2', x);
            v_line.setAttribute('y2', height);
            v_line.setAttribute('stroke', grid_color);
            v_line.setAttribute('stroke-width', '1');
            v_line.setAttribute('stroke-opacity', '0.5');
            svg_canvas.appendChild(v_line);
        }

        for(let y = grid_spacing; y < height; y += grid_spacing)
        {
            const h_line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            h_line.setAttribute('x1', 0);
            h_line.setAttribute('y1', y);
            h_line.setAttribute('x2', width);
            h_line.setAttribute('y2', y);
            h_line.setAttribute('stroke', grid_color);
            h_line.setAttribute('stroke-width', '1');
            h_line.setAttribute('stroke-opacity', '0.5');
            svg_canvas.appendChild(h_line);
        }

        // 折れ線
        const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
        polyline.setAttribute('points', points);
        polyline.setAttribute('stroke', '#2196f3');
        polyline.setAttribute('stroke-width', '2');
        polyline.setAttribute('fill', 'none');
        svg_canvas.appendChild(polyline);

        // ラベリング
        const $label = $('<div>').addClass('cpu-label').text(cpu_data.label); // 例：'CPU 0'
        const $wrapper = $('<div>').addClass('cpu-graph-wrapper');
        $wrapper[0].appendChild(svg_canvas);
        $wrapper.append($label);

        // DOMに追加
        const $section = getServerSection(p_server_id);
        const $container = $section.find('.cpu-graph-container');
        $container.append($wrapper);
    }

    function updateMemoryBar(p_server_id, p_type, p_used_mb, p_total_mb, p_color)
    {
        const $section = getServerSection(p_server_id);
        const $wrapper = $section.find(`.memory-bar-wrapper[data-type="${p_type}"]`);
        const percent = Math.round((p_used_mb / p_total_mb) * 100);

        $wrapper.find('.memory-bar-fill').css('width', percent + '%');
        $wrapper.find('.memory-bar-value').text(percent + '%');

        // 使用量を data-* 属性に保存（合計計算用）
        $wrapper.data('used-mb', p_used_mb);
        $wrapper.data('total-mb', p_total_mb);

        const $fill = $wrapper.find('.memory-bar-fill');
        $fill.removeClass('memory-warn memory-alert memory-critical');
        if(p_color !== '')
        {
            $fill.addClass('memory-' + p_color);
        }
    }

    function updateMemorySummary(p_server_id, p_used, p_total, p_color)
    {
        const $section = getServerSection(p_server_id);

        const $summary = $section.find('.memory-usage-summary');
        $summary.removeClass('memory-normal memory-warn memory-alert memory-critical');

        // 色分けロジック（閾値は調整可能）
        if(p_color === '')
        {
            p_color = 'normal';
        }
        $summary.addClass(`memory-summary-${p_color}`);

        // 表示テキスト更新
        let summary_text;
        if(p_total >= 1024)
        {
            const used_gb = (p_used / 1024).toFixed(1);
            const total_gb = (p_total / 1024).toFixed(1);
            summary_text = `${used_gb} GB / ${total_gb} GB`;
        }
        else
        {
            summary_text = `${p_used} MB / ${p_total} MB`;
        }

        $summary.text(summary_text);
    }

    function updateDiskBar($p_section, p_path, p_label, p_used_mb, p_total_mb, p_active, p_color)
    {
        // 一意な識別子として data-path を使用
        let $wrapper = $p_section.find(`.disk-bar-wrapper[data-path="${p_path}"]`);

        // 存在しない場合は新規生成
        if ($wrapper.length === 0) {
            $wrapper = $(`
                <div class="disk-bar-wrapper" data-path="${p_path}">
                    <div class="disk-bar-label"></div>
                    <div class="disk-bar-bg">
                        <div class="disk-bar-fill"></div>
                    </div>
                    <div class="disk-bar-value">--%</div>
                    <div class="disk-bar-usage">-- GB / -- GB</div>
                </div>
            `);
            $p_section.find('.disk-bar-container').append($wrapper);
        }

        // ラベル更新
        $wrapper.find('.disk-bar-label').text(p_label);

        // 使用率・使用量の計算
        let percent = 0;
        let usage_text = '未取得';
        if (p_active && p_total_mb > 0) {
            percent = Math.round((p_used_mb / p_total_mb) * 100);
            const used_gb = (p_used_mb / 1024).toFixed(1);
            const total_gb = (p_total_mb / 1024).toFixed(1);
            usage_text = `${used_gb} GB / ${total_gb} GB`;
        }

        // 表示更新
        $wrapper.find('.disk-bar-fill').css('width', percent + '%');
        $wrapper.find('.disk-bar-value').text(p_active ? `${percent}%` : '--%');
        $wrapper.find('.disk-bar-usage').text(usage_text);

        // クラスの状態更新
        $wrapper.find('.disk-bar-fill')
            .removeClass('disk-warn disk-alert disk-critical')
            .addClass(`disk-${p_color}`);
        $wrapper.removeClass('disk-inactive');
        if(p_active !== true)
        {
            $wrapper.addClass('disk-inactive');
        }

        $wrapper.find('.disk-bar-usage')
            .removeClass('disk-usage-warn disk-usage-alert disk-usage-critical')
            .addClass(`disk-usage-${p_color}`);
    }

    function updateOperatorDropdown(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        const operators = new Set();

        $section.find('.log-entry').each(function()
        {
            const metaText = $(this).find('.log-meta').text();
            const parts = metaText.split('/');
            if(parts.length >= 2)
            {
                const operator = parts[1].trim();
                if(operator && operator !== 'ー')
                {
                    operators.add(operator);
                }
            }
        });

        const $dropdown = $section.find('.filter-operator');
        $dropdown.empty().append('<option value="all">オペレーター（すべて）</option>');

        Array.from(operators).sort().forEach(op =>
        {
            $dropdown.append(`<option value="${op}">${op}</option>`);
        });
    }

    const SocketManager = {};

    SocketManager.createServer = function()
    {
        const template = $('#server-template').html();
        const server_id = generateServerId();

        const $section = $(template).attr('data-server-id', server_id);
        $('#server-container').append($section);

        // 削除ボタンの表示制御
        const $section_first = $('#server-container .server-section').first();
        const section_cnt = $('#server-container .server-section').length;
        if(section_cnt === 1)
        {
            $section_first.find('.remove-server-btn').prop('disabled', true);
        }
        else
        if(section_cnt === 2)
        {
            const status_text = $section_first.find('.connection-status').text().trim();
            if(status_text === 'オフライン')
            {
                $section_first.find('.remove-server-btn').prop('disabled', false);
            }
            else
            {
                $section_first.find('.remove-server-btn').prop('disabled', true);
            }
            $section.find('.remove-server-btn').prop('disabled', false);
        }
        else
        {
            $section.find('.remove-server-btn').prop('disabled', false);
        }

        insertAddButton();
    };

    SocketManager.removeServer = function(p_server_id)
    {
        const $section = $(`.server-section[data-server-id="${p_server_id}"]`);
        $section.remove();

        // 削除ボタンの無効化
        const cnt = $('#server-container .server-section').length;
        if(cnt <= 1)
        {
            $('.remove-server-btn').prop('disabled', true);
        }

        insertAddButton();
    };

    SocketManager.createSocket = function(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        const $header = $section.find('.accordion-header');
        const $protocol = $header.find('.protocol-toggle-icon');
        const $host_input = $header.find('.host-input');
        const $port_input = $header.find('.port-input');
        const $operator_input = $header.find('.operator-input');
        const host = $host_input.val();
        const port = $port_input.val();
        const operator = $operator_input.val();

        const ret = validateInputs($header);
        if(ret !== true)
        {
            return;
        }

        $section.find('.launcher-log').empty();

        const protocol = $header.find('.protocol-toggle-icon').data('protocol');
        const $connect_btn = $header.find('.connect-btn');

        // 入力ロックと接続中表示
        $protocol.prop('disabled', true);
        $host_input.prop('readonly', true);
        $port_input.prop('readonly', true);
        $operator_input.prop('readonly', true);
        $connect_btn.prop('disabled', true);
        updateConnectionStatus($header, '接続中...', 'status-pending');

        const $gui_root = $section.find('.accordion-body');

        sockets.set(p_server_id, new ManagedWebsocket(p_server_id, protocol, host, port, operator, [], $gui_root.get(0)));
    };

    SocketManager.getSocket = function(p_server_id)
    {
        return sockets.get(p_server_id);
    };

    SocketManager.closeSocket = function(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        const $header = $section.find('.accordion-header');

        updateConnectionStatus($header, '切断中...', 'status-pending');
        sockets.get(p_server_id).disconnect();
    };

    SocketManager.preparingServer = function(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        const $header = $section.find('.accordion-header');
        const $protocol = $header.find('.protocol-toggle-icon');
        const $host_input = $header.find('.host-input');
        const $port_input = $header.find('.port-input');
        const $operator_input = $header.find('.operator-input');
        const $connect_btn = $header.find('.connect-btn');

        $protocol.prop('disabled', true);
        $host_input.prop('readonly', true);
        $port_input.prop('readonly', true);
        $operator_input.prop('readonly', true);
        $connect_btn.prop('disabled', true);
        updateConnectionStatus($header, '準備中...', 'status-pending');
    };

    SocketManager.connectionFailure = function(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        const $header = $section.find('.accordion-header');
        const $protocol = $header.find('.protocol-toggle-icon');
        const $host_input = $header.find('.host-input');
        const $port_input = $header.find('.port-input');
        const $operator_input = $header.find('.operator-input');
        const $connect_btn = $header.find('.connect-btn');

        $protocol.prop('disabled', false);
        $host_input.prop('readonly', false);
        $port_input.prop('readonly', false);
        $operator_input.prop('readonly', false);
        $connect_btn.prop('disabled', false);
        updateConnectionStatus($header, '接続失敗', 'status-error');
    };

    SocketManager.connected = function(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        const $header = $section.find('.accordion-header');
        const $protocol = $header.find('.protocol-toggle-icon');
        const $host_input = $header.find('.host-input');
        const $port_input = $header.find('.port-input');
        const $operator_input = $header.find('.operator-input');
        const $connect_btn = $header.find('.connect-btn');

        $header.addClass('connecting');
        $protocol.prop('disabled', true);
        $host_input.prop('readonly', true);
        $port_input.prop('readonly', true);
        $operator_input.prop('readonly', true);
        $connect_btn.text('切断').prop('disabled', false);
        updateConnectionStatus($header, 'オンライン', 'status-online');

        const $body = $section.find('.accordion-body');
        const is_open = $body.is(':visible');
        if(is_open !== true)
        {
            const $toggle_btn = $header.find('.toggle-btn');
            $toggle_btn.trigger('click');
        }
    };

    SocketManager.disconnected = function(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        const $header = $section.find('.accordion-header');
        const $protocol = $header.find('.protocol-toggle-icon');
        const $host_input = $header.find('.host-input');
        const $port_input = $header.find('.port-input');
        const $operator_input = $header.find('.operator-input');

        if(sockets.has(p_server_id))
        {
            sockets.delete(p_server_id);
        }

        $header.removeClass('connecting');
        $protocol.prop('disabled', false);
        $host_input.prop('readonly', false);
        $port_input.prop('readonly', false);
        $operator_input.prop('readonly', false);
        $header.find('.connect-btn').text('接続');
        updateConnectionStatus($header, 'オフライン', 'status-disconnected');

        const $chat_users = $section.find('.chat-users');
        $chat_users.children().remove();
    };

    SocketManager.error = function(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        const $header = $section.find('.accordion-header');
        const $protocol = $header.find('.protocol-toggle-icon');
        const $host_input = $header.find('.host-input');
        const $port_input = $header.find('.port-input');
        const $operator_input = $header.find('.operator-input');
        const $connect_btn = $header.find('.connect-btn');

        if(sockets.has(p_server_id))
        {
            sockets.delete(p_server_id);
        }

        $header.removeClass('connecting');
        $protocol.prop('disabled', false);
        $host_input.prop('readonly', false);
        $port_input.prop('readonly', false);
        $operator_input.prop('readonly', false);
        $connect_btn.text('接続').prop('disabled', false);
        updateConnectionStatus($header, 'エラー発生', 'status-error');

        const $chat_users = $section.find('.chat-users');
        $chat_users.children().remove();
    };

    SocketManager.renderCpuResource = function(p_server_id, p_usage_rate, p_usage_color, p_logical_cpus, p_service_cpus)
    {
        const $section = getServerSection(p_server_id);
        const $value = $section.find('.cpu-total-value');

        // 全体稼働率の数値化
        const usage = parseFloat(p_usage_rate);

        // クラス初期化
        $value.removeClass('cpu-normal cpu-warn cpu-alert cpu-critical');

        // 閾値に応じてクラス付与
        if(p_usage_color === '')
        {
            p_usage_color = 'normal';
        }
        $value.addClass(`total-cpu-${p_usage_color}`);

        // 表示更新（toFixedで桁数安定）
        $value.html(`${usage.toFixed(2)}%`);


        if(logical_cpu_datas.size === 0)
        {
            p_logical_cpus.forEach((p_usage, p_index) =>
            {
                const cpu_id = `cpu${p_index}`;
                logical_cpu_datas.set(cpu_id, new CpuData(p_index, p_usage.rate, p_usage.color));
            });
        }
        else
        {
            p_logical_cpus.forEach((p_usage, p_index) =>
            {
                const cpu_id = `cpu${p_index}`;
                const cpu_data = logical_cpu_datas.get(cpu_id);
                if(cpu_data)
                {
                    cpu_data.update(p_usage.rate, p_usage.color);
                }
            });
        }

        $section.find('.cpu-graph-container').empty();
        p_logical_cpus.forEach((p_point_data, p_cpu_no) =>
        {
            drawLineGraph(p_server_id, `cpu${p_cpu_no}`);
        });

        if(service_list.length <= 0 || p_service_cpus === null)
        {
            return;
        }

        for(let i = 0; i < p_service_cpus.length; i++)
        {
            if(p_service_cpus[i].rate === null)
            {
                service_list[i].cpu = 'ー';
                service_list[i].status = '停止中';
            }
            else
            if(p_service_cpus[i].rate === '')
            {
                service_list[i].cpu = 'ー';
                service_list[i].status = '操作中';
            }
            else
            if(p_service_cpus[i].rate === false)
            {
                service_list[i].cpu = 'ー';
                service_list[i].status = '未検知';
            }
            else
            {
                service_list[i].cpu = p_service_cpus[i].rate + '%';
                service_list[i].status = '起動中';
            }

            service_list[i].cpu_color = '';
            if(p_service_cpus[i].color !== '')
            {
                service_list[i].cpu_color = 'service-' + p_service_cpus[i].color;
            }

            const $body = $section.find('.service-list-body');
            const $row = $body.find('tr').eq(i * 2);
            const $status = $row.find('td').eq(3);
            $status.html(service_list[i].status);
            $status.removeClass('status-running status-stopped status-operation status-abnormality');
            const $start_btn = $row.find('button').eq(0); // 起動／停止ボタン
            const $edit_btn = $row.find('button').eq(2); // 編集ボタン
            const $delete_btn = $row.find('button').eq(3); // 削除ボタン
            if(service_list[i].status === '起動中')
            {
                $status.addClass('status-running');
                $start_btn.text('⏹');
                $start_btn.prop('disabled', false);
                $edit_btn.prop('disabled', true);
                $delete_btn.prop('disabled', true);
            }
            else
            if(service_list[i].status === '停止中')
            {
                $status.addClass('status-stopped');
                $start_btn.text('▶');
                $start_btn.prop('disabled', false);
                $edit_btn.prop('disabled', false);
                $delete_btn.prop('disabled', false);
            }
            else
            if(service_list[i].status === '操作中')
            {
                $status.addClass('status-operation');
                $start_btn.prop('disabled', true);
                $edit_btn.prop('disabled', true);
                $delete_btn.prop('disabled', true);
            }
            else
            if(service_list[i].status === '未検知')
            {
                $status.addClass('status-abnormality');
                $start_btn.text('▶');
                $start_btn.prop('disabled', false);
                $edit_btn.prop('disabled', true);
                $delete_btn.prop('disabled', true);
            }
            $row.find('.service-list-cpu').html(service_list[i].cpu);
            $row.find('.service-list-cpu').removeClass('service-warn service-alert service-critical');
            $row.find('.service-list-cpu').addClass(service_list[i].cpu_color);
        }
    }

    SocketManager.renderMemoryBar = function(p_server_id, p_types, p_services)
    {
        $.each(p_types, function(p_type, p_values) {
            updateMemoryBar(p_server_id, p_type, p_values.used, p_values.total, p_values.color);
        });

        updateMemorySummary(p_server_id, p_types.physical.used, p_types.physical.total, p_types.physical.color);

        // サービスリストへの反映
        if(service_list.length <= 0 || p_services === null)
        {
            return;
        }
        for(let i = 0; i < p_services.length; i++)
        {
            if(p_services[i].rate === null)
            {
                service_list[i].memory = 'ー';
                service_list[i].status = '停止中';
            }
            else
            if(p_services[i].rate === '')
            {
                service_list[i].memory = 'ー';
                service_list[i].status = '操作中';
            }
            else
            if(p_services[i].rate === false)
            {
                service_list[i].memory = 'ー';
                service_list[i].status = '未検知';
            }
            else
            {
                service_list[i].memory = p_services[i].rate + '%';
                service_list[i].status = '起動中';
            }

            service_list[i].memory_color = '';
            if(p_services[i].color !== '')
            {
                service_list[i].memory_color = 'service-' + p_services[i].color;
            }

            const $section = getServerSection(p_server_id);
            const $body = $section.find('.service-list-body');
            const $row = $body.find('tr').eq(i * 2);
            $status = $row.find('td').eq(3);
            $status.html(service_list[i].status);
            $status.removeClass('status-running status-stopped status-operation status-abnormality');
            const $start_btn = $row.find('button').eq(0); // 起動／停止ボタン
            const $edit_btn = $row.find('button').eq(2); // 編集ボタン
            const $delete_btn = $row.find('button').eq(3); // 削除ボタン
            if(service_list[i].status === '起動中')
            {
                $status.addClass('status-running');
                $start_btn.text('⏹');
                $start_btn.prop('disabled', false);
                $edit_btn.prop('disabled', true);
                $delete_btn.prop('disabled', true);
            }
            else
            if(service_list[i].status === '停止中')
            {
                $status.addClass('status-stopped');
                $start_btn.text('▶');
                $start_btn.prop('disabled', false);
                $edit_btn.prop('disabled', false);
                $delete_btn.prop('disabled', false);
            }
            else
            if(service_list[i].status === '操作中')
            {
                $status.addClass('status-operation');
                $start_btn.prop('disabled', true);
                $edit_btn.prop('disabled', true);
                $delete_btn.prop('disabled', true);
            }
            else
            if(service_list[i].status === '未検知')
            {
                $status.addClass('status-abnormality');
                $start_btn.text('▶');
                $start_btn.prop('disabled', false);
                $edit_btn.prop('disabled', true);
                $delete_btn.prop('disabled', true);
            }
            $row.find('.service-list-memory').html(service_list[i].memory);
            $row.find('.service-list-memory').removeClass('service-warn service-alert service-critical');
            $row.find('.service-list-memory').addClass(service_list[i].memory_color);
        }
    }

    SocketManager.renderDiskBar = function(p_server_id, p_disks)
    {
        $section = getServerSection(p_server_id);
        $section.find('.disk-bar-container').empty();
        for(let path in p_disks)
        {
            updateDiskBar($section, path, p_disks[path].label, p_disks[path].used_mb, p_disks[path].total_mb, p_disks[path].active, p_disks[path].color);
        }
    }

    SocketManager.renderCpuCheckboxes = function(p_server_id, p_max_cpu = 24)
    {
        const $section = getServerSection(p_server_id);
        const $row = $section.find('.cpu-checkbox-row');
        $row.empty();
        for(let i = 0; i < p_max_cpu; i++)
        {
            $row.append(`
                <label><input type="checkbox" class="cpu-checkbox" value="${i}"> CPU ${i}</label>
            `);
        }

        $("input[name='cpu-mode'][value='auto']").prop('checked', true).trigger('change');
    }

    SocketManager.renderServiceManager = function(p_server_id, p_service_list, p_enable_save_flg)
    {
        service_list = p_service_list;
        renderServiceList(p_server_id, p_enable_save_flg);
        updateGroupSelector(p_server_id);
    };

    SocketManager.editorSlideUp = function(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        const $editor = $section.find('.service-editor');
        $editor.slideUp(200);
    }

    SocketManager.showServiceOverlay = function(p_server_id, p_message)
    {
        const $section = getServerSection(p_server_id);
        const overlay = $('<div class="service-overlay"><div class="overlay-text"></div></div>');
        overlay.find('.overlay-text').text(p_message);
        $section.find('.service-table-wrapper').append(overlay);
    }

    SocketManager.updateServiceOverlay = function(p_server_id, p_message)
    {
        const $section = getServerSection(p_server_id);
        $section.find('.service-overlay .overlay-text').text(p_message);
    }

    SocketManager.hideServiceOverlay = function(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        $section.find('.service-overlay').remove();
    }

    SocketManager.showServiceOverlayConfirm = function(p_server_id, p_message, p_on_confirm, p_on_cancel)
    {
        const $section = getServerSection(p_server_id);
        const overlay =
        $(`
            <div class="service-overlay confirm-overlay">
            <div class="overlay-dialog">
                <p class="overlay-message">${p_message}</p>
                <div class="overlay-buttons">
                <button class="confirm-ok-btn">✔ OK</button>
                <button class="confirm-cancel-btn">✖ キャンセル</button>
                </div>
            </div>
            </div>
        `);

        overlay.find('.confirm-ok-btn').on('click', () =>
        {
            overlay.remove();
            if(typeof(p_on_confirm) === 'function')
            {
                p_on_confirm();
            }
        });

        overlay.find('.confirm-cancel-btn').on('click', () =>
        {
            overlay.remove();
            if(typeof(p_on_cancel) === 'function')
            {
                p_on_cancel();
            }
        });

        $section.find('.service-table-wrapper').append(overlay);
    }

    SocketManager.appendLauncherLog = function(p_server_id, p_log_entry, p_prepend = false)
    {
        const $section = getServerSection(p_server_id);
        const $log_container = $section.find('.launcher-log');

        const $line = $('<div>').addClass('log-entry').addClass(`log-${p_log_entry.level}`);

        $line.html(`
            <span class="log-timestamp">[${p_log_entry.timestamp}]</span>
            <span class="log-level">[${p_log_entry.level}]</span>
            <span class="log-type">${p_log_entry.type}</span>:
            <span class="log-message">${p_log_entry.message}</span>
            <span class="log-meta">(${p_log_entry.via} / ${p_log_entry.who} / PID:${p_log_entry.pid})</span>
        `);

        if(p_prepend)
        {
            $log_container.prepend($line); // 初期読み込み時（最新が上）
        }
        else
        {
            $log_container.append($line); // WebSocket受信時（最新が下）
        }
        updateOperatorDropdown(p_server_id);

        // 最大件数制限
        const $entries = $log_container.find('.log-entry');
        if($entries.length > max_log_entries)
        {
            $entries.first().remove(); // 最古の1件を削除
        }
    }

    SocketManager.entryUserList = function(p_server_id, p_user_id, p_user_list)
    {
        const $server_section = getServerSection(p_server_id);
        const $chat_users = $server_section.find('.chat-users');

        $chat_users.children().remove();
        for(const [user_id, user_name] of p_user_list)
        {
            let disabled = '';
            if(user_id === p_user_id)
            {
                disabled = 'disabled';
            }

            const $entry = $(`<button data-user-id="${user_id}" ${disabled}>`);
            $entry.addClass('chat-user-btn');
            $entry.html(user_name);
            $chat_users.append($entry);
        }
    };

    SocketManager.entryChatLog = function({p_server_id, p_datetime, p_message, p_user_id, p_user_name, p_is_private = false, p_is_success = true})
    {
        const managed_websocket = SocketManager.getSocket(p_server_id);
        const $entry = $('<div>').addClass('chat-entry');
        const is_self = (p_user_id === managed_websocket.getSelfUserId()) ? true: false;

        if(p_is_private)
        {
            $entry.addClass('private');
        }
        if(p_is_private && is_self === true)
        {
            $entry.addClass(p_is_success ? 'sent-success' : 'sent-failure');
        }

        const status_msg = p_is_private && is_self === true
            ? `<span class="status-msg">${p_is_success ? '✔ 送信成功' : '⚠ 送信失敗'}</span>`
            : '';

        let message_color = '';
        if(is_self !== true)
        {
            message_color = 'other-message';
        }
        if(p_user_id === '')
        {
            message_color = 'admin-message';
        }
        $entry.html(`${p_datetime} <strong class="${message_color}">${p_user_name}:</strong> ${p_message} ${status_msg}`);

        const $server_section = getServerSection(p_server_id);
        const $chat_log = $server_section.find('.chat-log');

        // ログに追加
        $chat_log.append($entry);

        // スクロールを最下部に
        $chat_log.scrollTop($chat_log[0].scrollHeight);

        const $chat_users = $server_section.find('.chat-users');

        // 最後に送信したユーザーのボタン色変更
        $chat_users.find('.chat-user-btn').removeClass('last-sender');
        $chat_users.find(`.chat-user-btn[data-user-id="${p_user_id}"]`).addClass('last-sender');
    }

    SocketManager.clearMessage = function(p_server_id)
    {
        const $section = getServerSection(p_server_id);
        const $chat_form = $section.find('.chat-form');

        $chat_form.find('.chat-message-input').val('');
        $chat_form.find('.chat-recipient').val('');
        $chat_form.find('.chat-recipient').data('user-id', '');
    };

    SocketManager.attachHandler = function()
    {
        $('#server-container').on('click', '.protocol-toggle-icon', function ()
        {
            const current = $(this).data('protocol');
            const next = current === 'ws' ? 'wss' : 'ws';
            const icon = next === 'wss' ? '🔒' : '🔓';

            $(this).data('protocol', next);
            $(this).html(icon);
        });

        $('#server-container').on('click', '.connect-btn', function ()
        {
            const $section = $(this).closest('.server-section');
            const server_id = $section.data('server-id');
            if(sockets.has(server_id))
            {
                SocketManager.closeSocket(server_id);
            }
            else
            {
                SocketManager.createSocket(server_id);
            }
        });

        $('#server-container').on('click', '.toggle-btn', function()
        {
            const $section = $(this).closest('.server-section');
            const $body = $section.find('.accordion-body');
            const is_open = $body.is(':visible');

            $body.slideToggle(200);
            $(this).text(is_open ? '▶' : '▼');
        });

        $('#server-container').on('click', '.add-server-btn', function()
        {
            SocketManager.createServer();
        });

        $('#server-container').on('click', '.remove-server-btn', function()
        {
            const $section = $(this).closest('.server-section');
            const server_id = $section.data('server-id');
            SocketManager.removeServer(server_id);
        });

        // サービス追加
        $('#server-container').on('click', '.add-service-btn', function()
        {
            const $manager = $(this).closest('.service-manager');

            $manager.find('.edit-name').val('');
            $manager.find('.edit-group').val('');
            $manager.find('.edit-path').val('');
            $manager.find('.edit-command').val('');
            // 論理CPUのデフォルトは自動選択
            $manager.find('input[name="cpu-mode"][value="auto"]').prop('checked', true).trigger('change');

            $manager.find('.error-msg').text('');
            $manager.find('.service-editor h3').text('サービス追加');
            $manager.find('.service-editor').slideDown(200);
            $manager.find('.service-editor').data('editing-index', '');
        });

        // 起動／停止ボタン
        $('#server-container').on('click', '.start-btn', function()
        {
            const $section = $(this).closest('.server-section');
            const server_id = $section.data('server-id');
            const $tr = $(this).closest('tr');
            const idx = $tr.data('index');
            const type = $(this).text();

            SocketManager.showServiceOverlay(server_id, 'リクエスト中...');
            if(type === '▶')
            {
                sockets.get(server_id).action({p_action: 'start', p_service: service_list[idx].name});
                $(this).text('⏹');
            }
            else
            {
                sockets.get(server_id).action({p_action: 'stop', p_service: service_list[idx].name});
                $(this).text('▶');
            }
            $(this).prop('disabled', true);
        });

        // 全体起動ボタン
        $('#server-container').on('click', '.start-all-btn', function()
        {
            const $section = $(this).closest('.server-section');
            const server_id = $section.data('server-id');

            SocketManager.showServiceOverlay(server_id, 'リクエスト中...');
            sockets.get(server_id).action({p_action: 'startall', p_service: null, p_group: null});
        });

        // 全体停止ボタン
        $('#server-container').on('click', '.stop-all-btn', function()
        {
            const $section = $(this).closest('.server-section');
            const server_id = $section.data('server-id');

            SocketManager.showServiceOverlay(server_id, 'リクエスト中...');
            sockets.get(server_id).action({p_action: 'stopall', p_service: null, p_group: null});
        });

        // グループ起動ボタン
        $('#server-container').on('click', '.start-group-btn', function()
        {
            const $section = $(this).closest('.server-section');
            const server_id = $section.data('server-id');

            const sel = $section.find('.group-selector').val();
            if(sel === '')
            {
                SocketManager.showServiceOverlay(server_id, 'グループを選択してください...');
                setTimeout(function()
                {
                    SocketManager.hideServiceOverlay(server_id);
                }, 3000);
                return;
            }
            SocketManager.showServiceOverlay(server_id, 'リクエスト中...');
            sockets.get(server_id).action({p_action: 'start', p_service: sel, p_group: true});
        });

        // グループ停止ボタン
        $('#server-container').on('click', '.stop-group-btn', function()
        {
            const $section = $(this).closest('.server-section');
            const server_id = $section.data('server-id');

            const sel = $section.find('.group-selector').val();
            if(sel === '')
            {
                SocketManager.showServiceOverlay(server_id, 'グループを選択してください...');
                setTimeout(function()
                {
                    SocketManager.hideServiceOverlay(server_id);
                }, 3000);
                return;
            }
            SocketManager.showServiceOverlay(server_id, 'リクエスト中...');
            sockets.get(server_id).action({p_action: 'stop', p_service: sel, p_group: true});
        });

        // 自動選択／任意選択
        $('#server-container').on('change', 'input[name="cpu-mode"]', function()
        {
            const mode = $(this).val();
            const $cpu_selector = $(this).closest('.cpu-selector');
            const $row = $cpu_selector.find('.cpu-checkbox-row');

            $cpu_selector.find('.cpu-checkbox').prop('checked', false);
            if(mode === 'manual')
            {
                $row.removeClass('disabled');
            }
            else
            {
                $row.addClass('disabled');
                $cpu_selector.find('.error-cpu-mode').text('');
            }
        });

        // 編集
        $('#server-container').on('click', '.edit-btn', function()
        {
            const $manager = $(this).closest('.service-manager');
            const $row = $(this).closest('tr');
            editing_index = $row.data('index');
            const svc = service_list[editing_index];

            $manager.find('.service-editor').data('editing-index', editing_index);
            $manager.find('.service-editor h3').text('サービス編集');
            $manager.find('.edit-name').val(svc.name);
            $manager.find('.edit-group').val(svc.group);
            $manager.find('.edit-path').val(svc.path);
            $manager.find('.edit-command').val(svc.command);
            if(svc.cores === null)
            {
                // 自動選択
                $manager.find('input[name="cpu-mode"][value="auto"]').prop('checked', true).trigger('change');
            }
            else
            {
                // 任意選択
                $manager.find('input[name="cpu-mode"][value="manual"]').prop('checked', true).trigger('change');
                for(let i = 0; i < svc.cores.length; i++)
                {
                    $manager.find('.cpu-checkbox').each(function()
                    {
                        if($(this).val() == svc.cores[i])
                        {
                            $(this).prop('checked', true);
                        }
                    });
                }
            }

            $manager.find('.error-msg').text(''); // エラー初期化
            $manager.find('.service-editor').slideDown(200);
        });

        // 削除
        $('#server-container').on('click', '.delete-btn', function()
        {
            $section = $(this).closest('.server-section');
            server_id = $section.data('server-id');
            const $row = $(this).closest('tr');
            const index = $row.data('index');

            SocketManager.showServiceOverlayConfirm(server_id, `${service_list[index].name} サービスを削除しますか？`, () =>
            {
                // コマンド送信
                sockets.get(server_id).settingAction({p_type: 'delete', p_service: service_list[index].name});
                SocketManager.showServiceOverlay(server_id, 'リクエスト中...');
            },
            () =>
            {
            });
        });

        // 反映
        $('#server-container').on('click', '.apply-edit-btn', function()
        {
            const $editor = $(this).closest('.service-editor');
            const editing_index = $editor.data('editing-index');
            const name = $editor.find('.edit-name').val().trim();
            const group = $editor.find('.edit-group').val().trim();
            const path = $editor.find('.edit-path').val().trim();
            const command = $editor.find('.edit-command').val().trim();

            let has_error = false;
            $editor.find('.error-msg').text('');

            // サービス名の必須＆ユニークチェック
            if(!name)
            {
                $editor.find('.error-name').text('サービス名は必須です');
                has_error = true;
            }
            else
            if(service_list.some((s, i) => s.name === name && i !== editing_index))
            {
                $editor.find('.error-name').text('サービス名が重複しています');
                has_error = true;
            }

            if(!path)
            {
                $editor.find('.error-path').text('実行パスは必須です');
                has_error = true;
            }

            if(!command)
            {
                $editor.find('.error-command').text('コマンドは必須です');
                has_error = true;
            }

            const mode = $editor.find('input[name="cpu-mode"]:checked').val();
            const selected_cpus = $editor.find('.cpu-checkbox:checked').map((_, el) => $(el).val()).get();

            $('.error-cpu-mode').text('');

            if(typeof(mode) === 'undefined')
            {
                $('.error-cpu-mode').text('「自動選択」、あるいは「任意選択」を選んでください');
                return;
            }
            if(mode === 'manual' && selected_cpus.length === 0)
            {
                $('.error-cpu-mode').text('少なくとも1つのCPUを選択してください');
                return;
            }

            if(has_error)
            {
                return;
            }

            const $section = $(this).closest('.server-section');
            const server_id = $section.data('server-id');

            let type = 'append';
            let service = null;
            let message = `${name} サービスを追加しますか？`;
            let cores = null;
            if(mode === 'manual')
            {
                cores = selected_cpus;
            }
            const items =
            {
                cores: cores,
                name: name,
                group: group,
                path: path,
                command: command
            };
            if(editing_index !== '')
            {
                type = 'edit';
                service = service_list[editing_index].name;
                message = `${service_list[editing_index].name} サービスへ反映しますか？`;
            }

            SocketManager.showServiceOverlayConfirm(server_id, message, () =>
            {
                // コマンド送信
                sockets.get(server_id).settingAction({p_type: type, p_service: service, p_items: items});
                SocketManager.showServiceOverlay(server_id, 'リクエスト中...');
            },
            () =>
            {
            });
        });

        // 編集キャンセル
        $('#server-container').on('click', '.cancel-edit-btn', function()
        {
            const $editor = $(this).closest('.service-editor');
            $editor.slideUp(200);
        });

        // 設定保存
        $('#server-container').on('click', '.save-config-btn', function()
        {
            $section = $(this).closest('.server-section');
            server_id = $section.data('server-id');

            SocketManager.showServiceOverlayConfirm(server_id, `現在の設定をファイルに保存しますか？`, () =>
            {
                // コマンド送信
                sockets.get(server_id).settingAction({p_type: 'save'});
                SocketManager.showServiceOverlay(server_id, 'リクエスト中...');
            },
            () =>
            {
            });
        });

        // 設定ロード
        $('#server-container').on('click', '.load-config-btn', function()
        {
            $section = $(this).closest('.server-section');
            server_id = $section.data('server-id');

            SocketManager.showServiceOverlayConfirm(server_id, `保存済みの設定をロードしますか？`, () =>
            {
                // コマンド送信
                sockets.get(server_id).settingAction({p_type: 'load'});
                SocketManager.showServiceOverlay(server_id, 'リクエスト中...');
            },
            () =>
            {
            });
        });

        // JSONダウンロード
        $('#server-container').on('click', '.download-json-btn', function()
        {
            // サービスリストの間引き
            const w_service_list = [];
            const max = service_list.length;
            for(let i = 0; i < max; i++)
            {
                w_service_list[i] =
                {
                    cores: service_list[i].cores,
                    name: service_list[i].name,
                    group: service_list[i].group,
                    path: service_list[i].path,
                    command: service_list[i].command
                }
            }

            // 各オブジェクトをJSON文字列に変換（インデント付き）
            const json_blocks = w_service_list.map(obj => JSON.stringify(obj, null, 4));

            // カンマ＋改行で連結（角括弧なし）
            const json_text = json_blocks.join(',\n');

            // Blob化してダウンロード
            const blob = new Blob([json_text], { type: 'application/json' });
            const url = URL.createObjectURL(blob);

            const a = $('<a>')
                .attr('href', url)
                .attr('download', 'service.json')
                .css('display', 'none');

            $('body').append(a);
            a[0].click();
            a.remove();
            URL.revokeObjectURL(url);
        });

        $('#server-container').on('click', '.chat-send-btn', function()
        {
            const $section = $(this).closest('.server-section');
            const $message = $section.find('.chat-message-input');
            const $recipient = $section.find('.chat-recipient');

            const server_id = $section.data('server-id');
            const managed_websocket = SocketManager.getSocket(server_id);
            managed_websocket.sendMessage({p_message: $message.val(), p_recipient: $recipient.data('user-id')});
        });

        $('#server-container').on('click', '.chat-user-btn', function()
        {
            const user_id = $(this).data('user-id');
            const user_name = $(this).html();
            const $chat_area = $(this).closest('.chat-area');
            const $recipient = $chat_area.find('.chat-recipient');
            const recipient_user_id = $recipient.data('user-id');

            if(recipient_user_id === user_id)
            {
                $recipient.data('user-id', '');
                $recipient.val('');
            }
            else
            {
                $recipient.data('user-id', user_id);
                $recipient.val(user_name);
            }
        });

        $('#server-container').on('click', '.toggle-detail-btn', function()
        {
            const $row = $(this).closest('tr');
            const $detailRow = $row.next('tr');
            const isVisible = $detailRow.is(':visible');

            if(isVisible)
            {
                $detailRow.hide();
            }
            else
            {
                $detailRow.show();
            }
        });

        $('#server-container').on('change', '.filter-level, .filter-action, .filter-operator', function()
        {
            const $log_area = $(this).closest('.launcher-log-area');
            const selectedLevel = $log_area.find('.filter-level').val();
            const selectedAction = $log_area.find('.filter-action').val();
            const selectedOperator = $log_area.find('.filter-operator').val();

            $log_area.find('.log-entry').each(function()
            {
                const level = $(this).find('.log-level').text().replace(/\[|\]/g, '').trim();
                const action = $(this).find('.log-type').text().trim();
                const metaText = $(this).find('.log-meta').text();
                const operator = metaText.split('/')[1]?.trim();

                const matchLevel = (selectedLevel === 'all' || level === selectedLevel);
                const matchAction = (selectedAction === 'all' || action === selectedAction);
                const matchOperator = (selectedOperator === 'all' || operator === selectedOperator);

                if(matchLevel && matchAction && matchOperator)
                {
                    $(this).show();
                }
                else
                {
                    $(this).hide();
                }
            });
        });
    }

    global.SocketManager = SocketManager;
})(window);

$(window).on('load', function()
{
    SocketManager.createServer();
});

$(function()
{
    SocketManager.attachHandler();
});
