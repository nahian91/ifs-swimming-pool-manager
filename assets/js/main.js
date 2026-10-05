/**
 * Ozone Skypool OS - Unified Terminal Client Engine & Dashboard Runtime (Complete Stable Engine)
 *
 * @package Ozone_Skypool_OS
 */

(function (window, document, $) {
    'use strict';

    // 1. Root Configuration & Safe Merged Localization Fallbacks
    const rawConfig = window.ifsPmsConfig || {};
    const defaultI18n = {
        packageTitle: 'Package title',
        ageCategory: 'Age/category',
        itemName: 'Item name',
        mediaTitle: 'Select Venue Logo',
        mediaBtn: 'Use this logo',
        mediaAlert: 'WordPress Media Uploader is loading or unavailable. Please refresh the page.',
        noSwimmers: 'No swimmers currently registered inside the pool.',
        checkoutBtn: 'Check Out',
        confirmCheckout: 'Check out patron from pool deck?',
        defaultCardHolder: 'Farhan Chowdhury',
        matchingRecords: 'Matching records: ',
        totalMembers: 'Total: %d members',
        contactingGate: 'Contacting Turnstile Controller...',
        accessGranted: 'ACCESS GRANTED • TURNSTILE UNLOCKED',
        accessDenied: 'Invalid or Expired Pass ID',
        accessRejected: 'Access Rejected',
        verificationFailed: 'Verification Failed or Voided',
        gateTimeout: 'Gate Controller Timeout',
        barrierLocked: 'BARRIER LOCKED',
        passOk: 'PASS OK (%ds)',
        barrierRelockNotice: '4000ms actuation pulse • Auto-relock armed',
        passAuthorized: 'Pass Authorized',
        admitted: 'Admitted',
        denied: 'Denied',
        scannerStandby: 'Terminal Standby • Present pass to scanner',
        turnOffCam: 'Turn Off Camera',
        turnOnCam: 'Activate WebCam Scanner',
        camError: 'Camera stream unavailable. Verify browser video permissions or use an external laser scanner.',
        uploaderUnavailable: 'WordPress Media Uploader is unavailable.',
        cashTenderRequired: 'Cash Tendered is required and must be equal to or greater than the net payable amount.'
    };

    const config = {
        ajax_url: rawConfig.ajax_url || window.ajaxurl || '',
        security_token: rawConfig.security_token || rawConfig.nonce || '',
        nonce: rawConfig.nonce || rawConfig.security_token || '',
        currency: rawConfig.currency || 'BDT',
        server_epoch: rawConfig.server_epoch || Math.floor(Date.now() / 1000),
        last_ticket: rawConfig.last_ticket || null,
        members_count: rawConfig.members_count || 0,
        current_mon_prefix: rawConfig.current_mon_prefix || 'OZ-',
        current_operator: rawConfig.current_operator || 'Front Desk Staff',
        i18n: Object.assign({}, defaultI18n, rawConfig.i18n || {})
    };

    function __(key, fallback) {
        return (config.i18n && config.i18n[key]) ? config.i18n[key] : (fallback || key);
    }

    /**
     * 2. Web Audio API Acoustic Feedback
     */
    const ifsPmsAudio = {
        ctx: null,

        init: function () {
            if (!this.ctx && (window.AudioContext || window.webkitAudioContext)) {
                this.ctx = new (window.AudioContext || window.webkitAudioContext)();
            }
        },

        playTone: function (freq, duration, type = 'sine', gainVal = 0.15) {
            this.init();
            if (!this.ctx) return;

            if (this.ctx.state === 'suspended') {
                this.ctx.resume();
            }

            try {
                const now = this.ctx.currentTime;
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();

                osc.type = type;
                osc.frequency.setValueAtTime(freq, now);
                gain.gain.setValueAtTime(gainVal, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + duration);

                osc.connect(gain);
                gain.connect(this.ctx.destination);

                osc.start(now);
                osc.stop(now + duration);
            } catch (e) {}
        },

        playSuccess: function () {
            this.playTone(880, 0.12, 'sine', 0.15);
            setTimeout(() => this.playTone(1320, 0.18, 'sine', 0.15), 120);
        },

        playError: function () {
            this.playTone(260, 0.25, 'sawtooth', 0.25);
            setTimeout(() => this.playTone(220, 0.35, 'sawtooth', 0.25), 180);
        }
    };

    /**
     * 3. Sanitization & Utility Helpers
     */
    function escapeHtml(string) {
        return String(string || '').replace(/[&<>"'`=\/]/g, function (s) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '/': '&#x2F;',
                '`': '&#x60;',
                '=': '&#x3D;'
            }[s];
        });
    }

    function formatDigitalClock(totalSeconds) {
        const hrs = Math.floor(totalSeconds / 3600);
        const mins = Math.floor((totalSeconds % 3600) / 60);
        const secs = totalSeconds % 60;

        return String(hrs).padStart(2, '0') + ':' +
               String(mins).padStart(2, '0') + ':' +
               String(secs).padStart(2, '0');
    }

    /**
     * 4. Theme Management Engine
     */
    function applyTheme(theme) {
        const shell = document.getElementById('ifsPmsAppShell');
        const lightBtn = document.getElementById('ifsThemeLightBtn');
        const darkBtn = document.getElementById('ifsThemeDarkBtn');

        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            if (shell) shell.setAttribute('data-theme', 'dark');
            if (darkBtn) darkBtn.classList.add('active');
            if (lightBtn) lightBtn.classList.remove('active');
        } else {
            document.documentElement.removeAttribute('data-theme');
            if (shell) shell.removeAttribute('data-theme');
            if (lightBtn) lightBtn.classList.add('active');
            if (darkBtn) darkBtn.classList.remove('active');
        }
        localStorage.setItem('ifs_pms_theme', theme);
    }

    function initTheme() {
        const storedTheme = localStorage.getItem('ifs_pms_theme');
        const defaultDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const currentTheme = storedTheme ? storedTheme : (defaultDark ? 'dark' : 'light');
        applyTheme(currentTheme);
    }

    /**
     * 5. Thermal Slip Modal & Printing Handling
     */
    function togglePrintButton(show) {
        const printWrap = document.getElementById('ozReceiptPrintActionWrap');
        if (printWrap) {
            printWrap.style.display = show ? 'block' : 'none';
        }
    }

    function showReceipt(ticket) {
        if (!ticket) return;

        const slipCode = document.getElementById('ifs-pms-slip-code');
        const slipMeta = document.getElementById('ifs-pms-slip-meta');
        const qrContainer = document.getElementById('ifs-pms-thermal-qr');
        const modal = document.getElementById('ifs-pms-thermal-modal');

        if (slipCode) {
            slipCode.innerText = ticket.code || '';
        }

        if (slipMeta) {
            let metaHtml =
                '<strong>Patron:</strong> ' + escapeHtml(ticket.name || 'Walk-in Guest') + '<br>' +
                '<strong>Phone:</strong> ' + escapeHtml(ticket.phone || '-') + '<br>';

            if (ticket.room) {
                metaHtml += '<strong>Hotel Room #:</strong> ' + escapeHtml(ticket.room) + '<br>';
            }

            metaHtml +=
                '<strong>Amount:</strong> ' + escapeHtml(ticket.amount || '0.00') + ' ' + escapeHtml(config.currency) + '<br>' +
                '<strong>Cashier:</strong> ' + escapeHtml(ticket.staff || '-') + '<br>' +
                '<strong>Timestamp:</strong> ' + escapeHtml(ticket.date || '-');

            slipMeta.innerHTML = metaHtml;
        }

        if (qrContainer && typeof window.QRCode !== 'undefined') {
            qrContainer.innerHTML = '';
            new window.QRCode(qrContainer, {
                text: ticket.code || '',
                width: 110,
                height: 110,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: window.QRCode.CorrectLevel.M
            });
        }

        if (modal) modal.classList.add('is-visible');
    }

    function closeReceipt() {
        const modal = document.getElementById('ifs-pms-thermal-modal');
        if (modal) modal.classList.remove('is-visible');
    }

    function exportLedger() {
        const token = config.security_token || config.nonce;
        if (config.ajax_url && token) {
            window.location.href = config.ajax_url + '?action=ifs_pms_export_csv_action&security=' + encodeURIComponent(token);
        }
    }

    /**
     * 6. Tabs, Sub-Tabs & Filter Routing
     */
    function switchSettingsTab(tabKey, btn) {
        document.querySelectorAll('.ifs-pms-subnav-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        const panePos = document.getElementById('ifsPmsSettingsPanePos');
        const paneGeneral = document.getElementById('ifsPmsSettingsPaneGeneral');
        const activeTabInput = document.getElementById('ifsPmsActiveTabInput');

        if (panePos) panePos.classList.toggle('active', tabKey === 'pos');
        if (paneGeneral) paneGeneral.classList.toggle('active', tabKey === 'general');
        if (activeTabInput) activeTabInput.value = tabKey;

        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tabKey);
            window.history.replaceState({}, '', url);
        }
    }

    function switchSubTab(subTabKey) {
        const incomeContent = document.getElementById('ifsPmsIncomeTabContent');
        const expenseContent = document.getElementById('ifsPmsExpenseTabContent');
        const btnIncome = document.getElementById('ifsSubTabBtnIncome');
        const btnExpense = document.getElementById('ifsSubTabBtnExpense');
        const subTabInput = document.getElementById('ifsPmsSubTabInput');

        if (subTabInput) subTabInput.value = subTabKey;

        const isIncome = subTabKey === 'income';
        if (incomeContent) incomeContent.classList.toggle('active', isIncome);
        if (expenseContent) expenseContent.classList.toggle('active', !isIncome);
        if (btnIncome) btnIncome.classList.toggle('active', isIncome);
        if (btnExpense) btnExpense.classList.toggle('active', !isIncome);

        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.set('sub_tab', subTabKey);
            window.history.replaceState({}, '', url.toString());
        }
    }

    function applyIncomeFilter(filterVal) {
        const input = document.getElementById('ifsPmsIncomeFilterInput');
        const form  = document.getElementById('ifsPmsReportGlobalForm');
        if (input && form) {
            input.value = filterVal;
            form.submit();
        }
    }

    function applyExpenseFilter(filterVal) {
        const input = document.getElementById('ifsPmsExpenseCatInput');
        const form  = document.getElementById('ifsPmsReportGlobalForm');
        if (input && form) {
            input.value = filterVal;
            form.submit();
        }
    }

    function switchExpenseTab(tabKey, btn) {
        document.querySelectorAll('.ifs-pms-subnav-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        const paneAdd = document.getElementById('ifsPmsExpensePaneAdd');
        const paneList = document.getElementById('ifsPmsExpensePaneList');

        if (paneAdd) paneAdd.classList.toggle('active', tabKey === 'add');
        if (paneList) paneList.classList.toggle('active', tabKey !== 'add');

        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tabKey);
            window.history.replaceState({}, '', url);
        }
    }

    function switchMemberTab(tabKey, btn) {
        document.querySelectorAll('.ifs-pms-subnav-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        const paneAdd = document.getElementById('ifsPmsMemberPaneAdd');
        const paneList = document.getElementById('ifsPmsMemberPaneList');

        if (paneAdd) paneAdd.classList.toggle('active', tabKey === 'add');
        if (paneList) paneList.classList.toggle('active', tabKey !== 'add');

        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tabKey);
            window.history.replaceState({}, '', url);
        }
    }

    function switchStaffTab(tabKey, btn) {
        document.querySelectorAll('.ifs-pms-subnav-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        const paneAdd = document.getElementById('ifsPmsStaffPaneAdd');
        const paneList = document.getElementById('ifsPmsStaffPaneList');

        if (paneAdd) paneAdd.classList.toggle('active', tabKey === 'add');
        if (paneList) paneList.classList.toggle('active', tabKey === 'list');

        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tabKey);
            window.history.replaceState({}, '', url);
        }
    }

    function switchTicketTab(tabKey, btn) {
        document.querySelectorAll('.oz-subnav-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        const paneAdd = document.getElementById('ozTicketPaneAdd');
        const paneList = document.getElementById('ozTicketPaneList');

        if (paneAdd) paneAdd.classList.toggle('active', tabKey === 'add');
        if (paneList) paneList.classList.toggle('active', tabKey !== 'add');

        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tabKey);
            window.history.replaceState({}, '', url);
        }
    }

    /**
     * 7. Expense Modal & Preset Controllers
     */
    function setExpensePreset(title, cat) {
        const titleInput  = document.getElementById('ifsExpTitle');
        const catSelect   = document.getElementById('ifsExpCat');
        const amountInput = document.getElementById('ifsExpAmount');

        if (titleInput) titleInput.value = title;
        if (catSelect)  catSelect.value = cat;
        if (amountInput) amountInput.focus();
    }

    function openViewExpenseModal(data) {
        const titleEl = document.getElementById('ifsPmsViewExpTitle');
        const catEl   = document.getElementById('ifsPmsViewExpCat');
        const amtEl   = document.getElementById('ifsPmsViewExpAmount');
        const dateEl  = document.getElementById('ifsPmsViewExpDate');
        const byEl    = document.getElementById('ifsPmsViewExpBy');
        const modal   = document.getElementById('ifsPmsViewExpenseModal');

        if (titleEl) titleEl.textContent = data.title;
        if (catEl)   catEl.textContent = data.category;
        if (amtEl)   amtEl.textContent = '-' + data.amount + ' ' + config.currency;
        if (dateEl)  dateEl.textContent = data.date;
        if (byEl)    byEl.textContent = data.by;

        if (modal) modal.classList.add('is-visible');
    }

    function closeViewExpenseModal() {
        const modal = document.getElementById('ifsPmsViewExpenseModal');
        if (modal) modal.classList.remove('is-visible');
    }

    function openEditExpenseModal(data) {
        const idEl    = document.getElementById('ifsPmsEditExpId');
        const titleEl = document.getElementById('ifsPmsEditExpTitle');
        const catEl   = document.getElementById('ifsPmsEditExpCat');
        const amtEl   = document.getElementById('ifsPmsEditExpAmount');
        const dateEl  = document.getElementById('ifsPmsEditExpDate');
        const modal   = document.getElementById('ifsPmsEditExpenseModal');

        if (idEl) idEl.value = data.id;
        if (titleEl) titleEl.value = data.title;
        if (catEl) catEl.value = data.category;
        if (amtEl) amtEl.value = parseFloat(data.amount).toFixed(2);
        if (dateEl) dateEl.value = data.date;

        if (modal) modal.classList.add('is-visible');
    }

    function closeEditExpenseModal() {
        const modal = document.getElementById('ifsPmsEditExpenseModal');
        if (modal) modal.classList.remove('is-visible');
    }

    /**
     * 8. Repeater Rows
     */
    function addPricingTierRow() {
        const tableBody = document.querySelector('#ifsPmsPricingTierTable tbody');
        if (!tableBody) return;

        const emptyRow = tableBody.querySelector('.ifs-pms-empty-row');
        if (emptyRow) emptyRow.remove();

        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" name="ifs_pricing_tier_name[]" required placeholder="${escapeHtml(config.i18n.packageTitle)}"></td>
            <td><input type="text" name="ifs_pricing_tier_age[]" required placeholder="${escapeHtml(config.i18n.ageCategory)}"></td>
            <td><input type="number" step="0.01" name="ifs_pricing_tier_price[]" required class="ifs-pms-mono" placeholder="0.00"></td>
            <td class="ifs-pms-td-action">
                <button type="button" class="ifs-pms-btn ifs-pms-btn-danger ifs-pms-btn-sm" onclick="ifsPms.removeRow(this)">
                    <span class="ifs-pms-icon-action">&times;</span>
                </button>
            </td>
        `;
        tableBody.appendChild(row);
    }

    function addAddonRow() {
        const tableBody = document.querySelector('#ifsPmsAmenityAddonTable tbody');
        if (!tableBody) return;

        const emptyRow = tableBody.querySelector('.ifs-pms-empty-addon-row');
        if (emptyRow) emptyRow.remove();

        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" name="ifs_addon_name[]" required placeholder="${escapeHtml(config.i18n.itemName)}"></td>
            <td><input type="number" step="0.01" name="ifs_addon_price[]" required class="ifs-pms-mono" placeholder="0.00"></td>
            <td class="ifs-pms-td-action">
                <button type="button" class="ifs-pms-btn ifs-pms-btn-danger ifs-pms-btn-sm" onclick="ifsPms.removeRow(this)">
                    <span class="ifs-pms-icon-action">&times;</span>
                </button>
            </td>
        `;
        tableBody.appendChild(row);
    }

    function removeRow(btn) {
        if (!btn) return;
        const tr = btn.closest('tr');
        if (tr) tr.remove();
    }

    /**
     * 9. Member Pass & Directory Engine
     */
    function openMediaUploader(targetInputId, callback) {
        if (typeof window.wp === 'undefined' || !window.wp.media) {
            alert(config.i18n.mediaAlert);
            return;
        }

        const mediaUploader = window.wp.media({
            title: config.i18n.mediaTitle,
            button: { text: config.i18n.mediaBtn },
            multiple: false
        });

        mediaUploader.on('select', function () {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            const targetField = document.getElementById(targetInputId);
            if (targetField && attachment && attachment.url) {
                targetField.value = attachment.url;
                if (typeof callback === 'function') callback(attachment.url);
            }
        });

        mediaUploader.open();
    }

    function openViewMemberModal(data) {
        const tierEl   = document.getElementById('ifsPmsViewModalTierPill');
        const holderEl = document.getElementById('ifsPmsViewModalHolder');
        const phoneEl  = document.getElementById('ifsPmsViewModalPhone');
        const codeEl   = document.getElementById('ifsPmsViewModalCode');
        const expiryEl = document.getElementById('ifsPmsViewModalExpiry');
        const avatarBox = document.getElementById('ifsPmsViewModalAvatarBox');
        const modal    = document.getElementById('ifsPmsViewMemberModal');

        if (tierEl) tierEl.textContent = data.plan;
        if (holderEl) holderEl.textContent = data.name;
        if (phoneEl) phoneEl.textContent = data.phone;
        if (codeEl) codeEl.textContent = data.code;
        if (expiryEl) expiryEl.textContent = data.expiry;

        if (avatarBox) {
            if (data.profile_image) {
                avatarBox.innerHTML = '<img src="' + escapeHtml(data.profile_image) + '" alt="Avatar" class="ifs-pms-modal-avatar-img">';
            } else {
                avatarBox.textContent = data.name ? data.name.charAt(0).toUpperCase() : 'M';
            }
        }

        if (modal) modal.classList.add('is-visible');
    }

    function closeViewMemberModal() {
        const modal = document.getElementById('ifsPmsViewMemberModal');
        if (modal) modal.classList.remove('is-visible');
    }

    function openEditMemberModal(data) {
        const modal = document.getElementById('ifsPmsEditMemberModal');
        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };

        setVal('ifsPmsEditModalId', data.id);
        setVal('ifsPmsEditModalName', data.name);
        setVal('ifsPmsEditModalPhone', data.phone);
        setVal('ifsPmsEditModalPlan', data.plan);
        setVal('ifsPmsEditModalAmount', parseFloat(data.amount).toFixed(2));
        setVal('ifsPmsEditModalExpiry', data.expiry);
        setVal('ifsPmsEditModalStatus', data.status);
        setVal('ifsPmsEditModalAvatarInput', data.profile_image || '');

        const codeEl = document.getElementById('ifsPmsEditModalCode');
        if (codeEl) codeEl.textContent = data.code;

        if (modal) modal.classList.add('is-visible');
    }

    function closeEditMemberModal() {
        const modal = document.getElementById('ifsPmsEditMemberModal');
        if (modal) modal.classList.remove('is-visible');
    }

    function handlePlanSelect() {
        const selectBox = document.getElementById('ifsMemPlan');
        if (!selectBox) return;

        const activeOpt = selectBox.options[selectBox.selectedIndex];
        const months    = activeOpt.getAttribute('data-months');
        const price     = activeOpt.getAttribute('data-price');

        const durationInput = document.getElementById('ifsMemDuration');
        const amountInput   = document.getElementById('ifsMemAmount');

        if (durationInput) durationInput.value = months;
        if (amountInput) amountInput.value = parseFloat(price).toFixed(2);

        syncCardDisplay();
    }

    function syncCardDisplay() {
        const nameInput = document.getElementById('ifsMemName');
        const avatarInput = document.getElementById('ifsMemAvatarInput');
        const selectBox = document.getElementById('ifsMemPlan');
        const durationInput = document.getElementById('ifsMemDuration');

        const nameVal     = nameInput ? nameInput.value.trim() : '';
        const avatarUrl   = avatarInput ? avatarInput.value.trim() : '';
        const planName    = selectBox ? selectBox.options[selectBox.selectedIndex].value : 'Monthly Sky Pass';
        const monthsCount = durationInput ? (parseInt(durationInput.value, 10) || 1) : 1;

        const holderEl  = document.getElementById('ifsPmsCardHolder');
        const tierPill  = document.getElementById('ifsPmsCardTierPill');
        const expiryEl  = document.getElementById('ifsPmsCardExpiryDate');
        const avatarBox = document.getElementById('ifsPmsCardAvatarBox');

        if (holderEl) holderEl.textContent = nameVal ? nameVal : config.i18n.defaultCardHolder;
        if (tierPill) tierPill.textContent = planName;

        if (avatarBox) {
            if (avatarUrl) {
                avatarBox.innerHTML = '<img src="' + escapeHtml(avatarUrl) + '" alt="Avatar" class="ifs-pms-modal-avatar-img">';
            } else {
                avatarBox.textContent = nameVal ? nameVal.charAt(0).toUpperCase() : 'FC';
            }
        }

        const calcDate = new Date();
        calcDate.setMonth(calcDate.getMonth() + monthsCount);

        const yyyy = calcDate.getFullYear();
        const mm   = String(calcDate.getMonth() + 1).padStart(2, '0');
        const dd   = String(calcDate.getDate()).padStart(2, '0');

        if (expiryEl) expiryEl.textContent = yyyy + '-' + mm + '-' + dd;
    }

    function filterDirectory() {
        const inputEl   = document.getElementById('ifsPmsMemberFilterInput');
        const clearBtn  = document.getElementById('ifsPmsFilterClearBtn');
        const filterStr = inputEl ? inputEl.value.toLowerCase().trim() : '';
        const rows      = document.querySelectorAll('.ifs-pms-member-record-row');
        let matched     = 0;

        if (clearBtn) clearBtn.classList.toggle('is-visible', Boolean(filterStr));

        rows.forEach(row => {
            const matches = row.textContent.toLowerCase().includes(filterStr);
            row.classList.toggle('is-hidden', !matches);
            if (matches) matched++;
        });

        const counterEl = document.getElementById('ifsPmsRecordCounter');
        if (counterEl) {
            counterEl.textContent = filterStr
                ? config.i18n.matchingRecords + matched
                : config.i18n.totalMembers.replace('%d', config.members_count);
        }
    }

    function clearFilter() {
        const inputEl = document.getElementById('ifsPmsMemberFilterInput');
        if (inputEl) {
            inputEl.value = '';
            filterDirectory();
            inputEl.focus();
        }
    }

    function filterCustomerTable() {
        const input = document.getElementById('ifsPmsCustomerSearchInput');
        if (!input) return;

        const query = input.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.ifs-pms-customer-row');

        rows.forEach(r => {
            const matches = !query || r.textContent.toLowerCase().includes(query);
            r.classList.toggle('is-hidden', !matches);
        });
    }

    /**
     * 10. Staff Directory Module
     */
    function openViewStaffModal(data) {
        document.getElementById('ifsPmsViewStaffName').textContent = data.name;
        document.getElementById('ifsPmsViewStaffLogin').textContent = data.login;
        document.getElementById('ifsPmsViewStaffEmail').textContent = data.email;
        document.getElementById('ifsPmsViewStaffRoleBadge').textContent = data.role;
        document.getElementById('ifsPmsViewStaffBase').textContent = data.base.toFixed(2) + ' ' + config.currency;
        document.getElementById('ifsPmsViewStaffAllow').textContent = data.allow.toFixed(2) + ' ' + config.currency;
        document.getElementById('ifsPmsViewStaffFreq').textContent = data.freq;

        const avatarBox = document.getElementById('ifsPmsViewAvatarBox');
        if (avatarBox) {
            if (data.avatar) {
                avatarBox.innerHTML = '<img src="' + escapeHtml(data.avatar) + '" alt="Avatar" class="ifs-pms-modal-avatar-img">';
            } else {
                avatarBox.textContent = data.name.charAt(0).toUpperCase();
            }
        }

        const modal = document.getElementById('ifsPmsViewStaffModal');
        if (modal) modal.classList.add('is-visible');
    }

    function closeViewStaffModal() {
        const modal = document.getElementById('ifsPmsViewStaffModal');
        if (modal) modal.classList.remove('is-visible');
    }

    function openEditStaffModal(data) {
        document.getElementById('ifsPmsEditStaffId').value = data.id;
        document.getElementById('ifsPmsEditStaffName').value = data.name;
        document.getElementById('ifsPmsEditStaffEmail').value = data.email;
        document.getElementById('ifsPmsEditStaffRole').value = data.role;
        document.getElementById('ifsPmsEditAvatarUrl').value = data.avatar || '';
        document.getElementById('ifsPmsEditStaffBase').value = data.base.toFixed(2);
        document.getElementById('ifsPmsEditStaffAllow').value = data.allow.toFixed(2);
        document.getElementById('ifsPmsEditStaffFreq').value = data.freq;
        document.getElementById('ifsPmsEditStaffDate').value = data.eff_date;

        const modal = document.getElementById('ifsPmsEditStaffModal');
        if (modal) modal.classList.add('is-visible');
    }

    function closeEditStaffModal() {
        const modal = document.getElementById('ifsPmsEditStaffModal');
        if (modal) modal.classList.remove('is-visible');
    }

    function filterStaffDirectory() {
        const input = document.getElementById('ifsPmsStaffSearchInput');
        const filter = input ? input.value.toLowerCase().trim() : '';
        const rows = document.querySelectorAll('.ifs-pms-staff-record-row');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.classList.toggle('is-hidden', Boolean(filter && !text.includes(filter)));
        });
    }

    /**
     * 11. Point of Sale (POS) Ticket Engine (Fixed & Direct Display Sync)
     */
    let currentGuestType = 'customer';
    let selectedAddonsList = [];
    let addonsTotal = 0;
    let finalPayable = 0;

    function setGuestType(type) {
        currentGuestType = (type === 'room') ? 'room_guest' : 'customer';

        const pillWalkin = document.getElementById('ozTypePillWalkin');
        const pillRoom   = document.getElementById('ozTypePillRoom');
        const roomWrap   = document.getElementById('ozRoomNumberWrap');
        const roomInput  = document.getElementById('ozHotelRoomNo');
        const cashWrap   = document.getElementById('ozCashWrap');
        const cashInput  = document.getElementById('ozCashReceived');
        const radioCust  = document.getElementById('ozGuestTypeCustomer');
        const radioRoom  = document.getElementById('ozGuestTypeRoom');

        if (radioCust && radioRoom) {
            radioCust.checked = (currentGuestType === 'customer');
            radioRoom.checked = (currentGuestType === 'room_guest');
        }

        if (currentGuestType === 'room_guest') {
            if (pillWalkin) pillWalkin.classList.remove('active');
            if (pillRoom) pillRoom.classList.add('active');

            // Override inline style directly with !important priority
            if (roomWrap) {
                roomWrap.style.setProperty('display', 'block', 'important');
            }
            if (roomInput) {
                roomInput.setAttribute('required', 'required');
                setTimeout(() => roomInput.focus(), 50);
            }

            selectTenderByName('Complementary');
            if (cashWrap) cashWrap.style.setProperty('display', 'none', 'important');
            if (cashInput) cashInput.removeAttribute('required');
        } else {
            if (pillRoom) pillRoom.classList.remove('active');
            if (pillWalkin) pillWalkin.classList.add('active');

            if (roomWrap) {
                roomWrap.style.setProperty('display', 'none', 'important');
            }
            if (roomInput) {
                roomInput.removeAttribute('required');
                roomInput.value = '';
            }

            selectTenderByName('Cash');
            if (cashWrap) cashWrap.style.setProperty('display', 'grid', 'important');
            if (cashInput) cashInput.setAttribute('required', 'required');
        }

        syncRoomDisplay(roomInput ? roomInput.value : '');
        recalculatePos();
    }

    function syncRoomDisplay(val) {
        const row = document.getElementById('ozPrevRoomRow');
        const cell = document.getElementById('ozPrevRoom');
        const roomWrap = document.getElementById('ozRoomNumberWrap');
        const isRoomActive = roomWrap && roomWrap.style.display !== 'none';

        if (row && cell) {
            const trimmed = String(val || '').trim();
            if (isRoomActive && trimmed.length > 0) {
                cell.textContent = trimmed;
                row.style.setProperty('display', 'table-row', 'important');
            } else {
                row.style.setProperty('display', 'none', 'important');
                cell.textContent = '-';
            }
        }
    }

    function selectTenderByName(name) {
        document.querySelectorAll('.oz-tender-box').forEach(box => {
            if (box.textContent.trim().includes(name)) {
                document.querySelectorAll('.oz-tender-box').forEach(b => b.classList.remove('active'));
                box.classList.add('active');
                const pInput = document.getElementById('ozSelectedPayment');
                const pPrev = document.getElementById('ozPrevTender');
                if (pInput) pInput.value = name;
                if (pPrev) pPrev.textContent = name;
            }
        });
    }

    function selectTender(method, el) {
        document.querySelectorAll('.oz-tender-box').forEach(b => b.classList.remove('active'));
        el.classList.add('active');

        const pInput = document.getElementById('ozSelectedPayment');
        const pPrev = document.getElementById('ozPrevTender');
        if (pInput) pInput.value = method;
        if (pPrev) pPrev.textContent = method;

        const cashWrap = document.getElementById('ozCashWrap');
        const roomWrap = document.getElementById('ozRoomNumberWrap');
        const cashInput = document.getElementById('ozCashReceived');

        if (method === 'Complementary') {
            if (cashWrap) cashWrap.style.setProperty('display', 'none', 'important');
            if (cashInput) cashInput.removeAttribute('required');
        } else {
            if (cashWrap) cashWrap.style.setProperty('display', (method === 'Cash') ? 'grid' : 'none', 'important');
            if (roomWrap) roomWrap.style.setProperty('display', (currentGuestType === 'room_guest') ? 'block' : 'none', 'important');
            if (cashInput) {
                if (method === 'Cash' && currentGuestType === 'customer') {
                    cashInput.setAttribute('required', 'required');
                } else {
                    cashInput.removeAttribute('required');
                    cashInput.classList.remove('oz-input-invalid');
                }
            }
        }

        recalculatePos();
    }

    function toggleTierSwitch(index) {
        const box    = document.getElementById('ozTierBox_' + index);
        const toggle = document.getElementById('ozTierToggle_' + index);
        const qtyIn  = document.getElementById('ozTierPersons_' + index);

        if (toggle.checked) {
            box.classList.add('is-enabled');
            if (parseInt(qtyIn.value, 10) === 0) qtyIn.value = 1;
        } else {
            box.classList.remove('is-enabled');
            qtyIn.value = 0;
        }

        recalculatePos();
    }

    function deltaModularQty(index, delta) {
        const qtyIn  = document.getElementById('ozTierPersons_' + index);
        const toggle = document.getElementById('ozTierToggle_' + index);
        const box    = document.getElementById('ozTierBox_' + index);

        let val = parseInt(qtyIn.value, 10) || 0;
        val = Math.max(0, Math.min(50, val + delta));
        qtyIn.value = val;

        if (val > 0) {
            toggle.checked = true;
            box.classList.add('is-enabled');
        } else {
            toggle.checked = false;
            box.classList.remove('is-enabled');
        }

        recalculatePos();
    }

    function deltaModularHours(index, delta) {
        const hrsIn = document.getElementById('ozTierHours_' + index);
        let val = parseInt(hrsIn.value, 10) || 1;
        val = Math.max(1, Math.min(12, val + delta));
        hrsIn.value = val;

        recalculatePos();
    }

    function toggleAddon(card, price, name) {
        const isSelected = card.classList.toggle('selected');
        if (isSelected) {
            addonsTotal += price;
            selectedAddonsList.push({ name: name, price: price });
        } else {
            addonsTotal -= price;
            selectedAddonsList = selectedAddonsList.filter(a => a.name !== name);
        }
        recalculatePos();
    }

    function quickCash(val) {
        const tenderInput = document.getElementById('ozCashReceived');
        if (!tenderInput) return;

        if (val === 'exact') {
            tenderInput.value = finalPayable.toFixed(2);
        } else {
            const current = parseFloat(tenderInput.value) || 0;
            tenderInput.value = (current + val).toFixed(2);
        }
        tenderInput.classList.remove('oz-input-invalid');
        computeChange();
    }

    function computeChange() {
        const cashInput = document.getElementById('ozCashReceived');
        if (!cashInput) return;

        const received = parseFloat(cashInput.value) || 0;
        const changeEl = document.getElementById('ozChangeDue');
        const diff     = received - finalPayable;

        if (diff >= 0 && received > 0) {
            changeEl.textContent = diff.toFixed(2) + ' ' + config.currency;
            changeEl.className = 'oz-change-due-positive';
            cashInput.classList.remove('oz-input-invalid');
        } else {
            changeEl.textContent = '0.00 ' + config.currency;
            changeEl.className = 'oz-change-due-default';
        }
    }

    function recalculatePos() {
        const tenderMethod = (document.getElementById('ozSelectedPayment') || {}).value || 'Cash';
        const isFreeTender = (currentGuestType === 'room_guest' || tenderMethod === 'Complementary' || tenderMethod === 'Room Guest' || tenderMethod === 'Complimentary');

        let totalTiersCost = 0;
        let selectedSummary = [];
        const boxes = document.querySelectorAll('.oz-tier-box');

        boxes.forEach(box => {
            const index  = box.getAttribute('data-index');
            const toggle = document.getElementById('ozTierToggle_' + index);

            if (toggle && toggle.checked) {
                const name    = box.getAttribute('data-name');
                const age     = box.getAttribute('data-age');
                const rate    = parseFloat(box.getAttribute('data-price')) || 0;
                const persons = parseInt(document.getElementById('ozTierPersons_' + index).value, 10) || 0;
                const hours   = parseInt(document.getElementById('ozTierHours_' + index).value, 10) || 1;

                if (persons > 0) {
                    const subtotal = isFreeTender ? 0 : (rate * persons * hours);
                    totalTiersCost += subtotal;
                    selectedSummary.push({
                        name: name,
                        age: age,
                        rate: rate,
                        persons: persons,
                        hours: hours,
                        subtotal: subtotal
                    });
                }
            }
        });

        if (selectedSummary.length === 0 && boxes.length > 0) {
            const firstToggle = document.getElementById('ozTierToggle_0');
            const firstQty    = document.getElementById('ozTierPersons_0');
            const firstBox    = document.getElementById('ozTierBox_0');
            if (firstToggle && firstQty && firstBox) {
                firstToggle.checked = true;
                firstBox.classList.add('is-enabled');
                firstQty.value = 1;

                const rate = parseFloat(firstBox.getAttribute('data-price')) || 0;
                const hours = parseInt(document.getElementById('ozTierHours_0').value, 10) || 1;
                const subtotal = isFreeTender ? 0 : (rate * 1 * hours);
                totalTiersCost = subtotal;

                selectedSummary.push({
                    name: firstBox.getAttribute('data-name'),
                    age: firstBox.getAttribute('data-age'),
                    rate: rate,
                    persons: 1,
                    hours: hours,
                    subtotal: subtotal
                });
            }
        }

        finalPayable = totalTiersCost + addonsTotal;

        const amountField = document.getElementById('ozSubmittedAmount');
        if (amountField) amountField.value = finalPayable.toFixed(2);

        const bookedDuration = selectedSummary.reduce((max, item) => Math.max(max, item.hours), 1);
        const durationField = document.getElementById('ozDurationHoursInput');
        if (durationField) durationField.value = bookedDuration;

        const packageInput = document.getElementById('ozPackageNameInput');
        if (packageInput) {
            packageInput.value = selectedSummary.map(s => s.name + ' (' + s.persons + 'p x ' + s.hours + 'h)').join(', ');
        }

        const guestName  = (document.getElementById('ozGuestName') || {}).value || '';
        const guestPhone = (document.getElementById('ozGuestPhone') || {}).value || '';
        const roomVal    = (document.getElementById('ozHotelRoomNo') || {}).value || '';

        const prevClass = document.getElementById('ozPrevClassification');
        const prevName  = document.getElementById('ozPrevName');
        const prevPhone = document.getElementById('ozPrevPhone');
        if (prevClass) prevClass.textContent = (currentGuestType === 'room_guest') ? 'Hotel Room Guest' : 'General Customer';
        if (prevName)  prevName.textContent  = guestName.trim() || 'Walk-in Guest';
        if (prevPhone) prevPhone.textContent = guestPhone.trim() || '017XXXXXXXX';

        const tiersTbody = document.getElementById('ozPrevTiersBody');
        if (tiersTbody) {
            tiersTbody.innerHTML = '';
            if (selectedSummary.length === 0) {
                tiersTbody.innerHTML = '<tr><td colspan="3" class="oz-empty-table-notice">No active package enabled</td></tr>';
            } else {
                selectedSummary.forEach(s => {
                    const tr = document.createElement('tr');
                    tr.className = 'oz-receipt-item-row';
                    const rateInfo = isFreeTender ? 'FREE' : (s.rate.toFixed(2) + ' ' + config.currency + '/hr');

                    tr.innerHTML =
                        '<td class="oz-receipt-col-desc">' +
                            '<div class="oz-receipt-item-name">' + escapeHtml(s.name) + '</div>' +
                            '<div class="oz-receipt-item-sub">' + escapeHtml(s.age) + '</div>' +
                            '<div class="oz-receipt-item-rate">' + s.hours + (s.hours > 1 ? ' hrs session (' : ' hr session (') + rateInfo + ')</div>' +
                        '</td>' +
                        '<td class="oz-receipt-col-qty">' + s.persons + ' x</td>' +
                        '<td class="oz-receipt-col-price">' + (isFreeTender ? '0.00' : s.subtotal.toFixed(2) + ' ' + escapeHtml(config.currency)) + '</td>';
                    tiersTbody.appendChild(tr);
                });
            }
        }

        const addonsTbody = document.getElementById('ozPrevAddonsBody');
        if (addonsTbody) {
            addonsTbody.innerHTML = '';
            selectedAddonsList.forEach(a => {
                const tr = document.createElement('tr');
                tr.className = 'oz-receipt-addon-row';
                tr.innerHTML =
                    '<td colspan="2">+ ' + escapeHtml(a.name) + '</td>' +
                    '<td class="oz-text-right">' + a.price.toFixed(2) + ' ' + escapeHtml(config.currency) + '</td>';
                addonsTbody.appendChild(tr);
            });
        }

        const prevTotal = document.getElementById('ozPrevTotal');
        if (prevTotal) prevTotal.textContent = config.currency + ' ' + finalPayable.toFixed(2);

        syncRoomDisplay(roomVal);

        const cashInput = document.getElementById('ozCashReceived');
        if (cashInput) {
            if (tenderMethod === 'Cash' && currentGuestType === 'customer' && finalPayable > 0) {
                cashInput.setAttribute('required', 'required');
                cashInput.setAttribute('min', finalPayable.toFixed(2));
            } else {
                cashInput.removeAttribute('required');
                cashInput.removeAttribute('min');
                cashInput.classList.remove('oz-input-invalid');
            }
        }

        computeChange();
    }

    function printPreviewReceipt() {
        const receiptEl = document.getElementById('ozReceiptPreviewContainer');
        if (!receiptEl) return;

        const printClone = receiptEl.cloneNode(true);
        const actionBtn = printClone.querySelector('.oz-receipt-print-action');
        if (actionBtn) actionBtn.remove();

        const printIframe = document.createElement('iframe');
        printIframe.className = 'oz-print-hidden-frame';
        document.body.appendChild(printIframe);

        const doc = printIframe.contentWindow.document;
        doc.open();
        doc.write('<!DOCTYPE html><html><head><title>Print Thermal Slip</title>');
        doc.write('<style>');
        doc.write('@page { size: 80mm auto; margin: 0; }');
        doc.write('body { margin: 0; padding: 10px; font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, sans-serif; color: #000; background: #fff; width: 72mm; }');
        doc.write('.ifs-pms-mono { font-family: monospace; }');
        doc.write('.oz-receipt-sep { border-bottom: 1.5px dashed #475569; margin: 8px 0; }');
        doc.write('.oz-receipt-table { width: 100%; border-collapse: collapse; font-size: 11px; }');
        doc.write('.oz-receipt-table td, .oz-receipt-table th { padding: 3px 0; vertical-align: top; }');
        doc.write('.oz-receipt-rules { margin-top: 10px; padding: 8px; border: 1px dashed #64748b; border-radius: 6px; font-size: 8.5px; }');
        doc.write('img { max-height: 40px; }');
        doc.write('</style></head><body>');
        doc.write(printClone.innerHTML);
        doc.write('</body></html>');
        doc.close();

        setTimeout(() => {
            printIframe.contentWindow.focus();
            printIframe.contentWindow.print();
            setTimeout(() => {
                document.body.removeChild(printIframe);
            }, 1000);
        }, 300);
    }

    function validateFormSubmission(e) {
        const tenderMethod = (document.getElementById('ozSelectedPayment') || {}).value || 'Cash';
        const cashInput    = document.getElementById('ozCashReceived');
        const roomInput    = document.getElementById('ozHotelRoomNo');

        if (currentGuestType === 'room_guest') {
            if (!roomInput || !roomInput.value.trim()) {
                if (e) e.preventDefault();
                if (roomInput) {
                    roomInput.focus();
                    roomInput.classList.add('oz-input-invalid');
                }
                alert('Please enter the Hotel Guest Room Number.');
                return false;
            }
        }

        if (tenderMethod === 'Cash' && currentGuestType === 'customer' && finalPayable > 0 && cashInput) {
            const received = parseFloat(cashInput.value) || 0;
            if (received < finalPayable || isNaN(received)) {
                if (e) e.preventDefault();
                cashInput.classList.add('oz-input-invalid');
                cashInput.focus();
                alert(config.i18n.cashTenderRequired);
                return false;
            }
        }
        return true;
    }

    function handleFormSubmit(e) {
        if (typeof validateFormSubmission === 'function') {
            var isValid = validateFormSubmission(e);
            if (!isValid) return false;
        }

        var previewCard = document.getElementById('ozReceiptPreviewContainer');
        if (previewCard) {
            try {
                localStorage.setItem('oz_last_receipt_html', previewCard.innerHTML);
                localStorage.setItem('oz_auto_print', '1');
            } catch (err) {
                console.error("Storage error:", err);
            }
        }
        return true;
    }

    function resetTerminal() {
        localStorage.removeItem('oz_last_receipt_html');
        localStorage.removeItem('oz_auto_print');
        togglePrintButton(false);

        const form = document.getElementById('ozPosMasterForm');
        if (form) form.reset();

        addonsTotal = 0;
        selectedAddonsList = [];
        document.querySelectorAll('.oz-addon-item').forEach(c => c.classList.remove('selected'));

        document.querySelectorAll('.oz-tier-box').forEach((box, idx) => {
            const toggle = document.getElementById('ozTierToggle_' + idx);
            const qtyIn  = document.getElementById('ozTierPersons_' + idx);
            const hrsIn  = document.getElementById('ozTierHours_' + idx);
            if (toggle && qtyIn) {
                toggle.checked = (idx === 0);
                qtyIn.value = (idx === 0) ? 1 : 0;
                box.classList.toggle('is-enabled', idx === 0);
            }
            if (hrsIn) hrsIn.value = 1;
        });

        const durationField = document.getElementById('ozDurationHoursInput');
        if (durationField) durationField.value = 1;

        const cashInput = document.getElementById('ozCashReceived');
        if (cashInput) cashInput.classList.remove('oz-input-invalid');

        const roomInput = document.getElementById('ozHotelRoomNo');
        if (roomInput) roomInput.classList.remove('oz-input-invalid');

        setGuestType('walkin');
        recalculatePos();

        const guestInput = document.getElementById('ozGuestName');
        if (guestInput) guestInput.focus();
    }

    function filterTicketTable() {
        const query = (document.getElementById('ozTicketSearchInput').value || '').toLowerCase().trim();
        const status = document.getElementById('ozTicketStatusFilter').value;
        const rows = document.querySelectorAll('.oz-ticket-row');

        rows.forEach(r => {
            const rowStatus = r.getAttribute('data-status');
            const rowText = r.textContent.toLowerCase();
            const match = (!query || rowText.includes(query)) && (status === 'ALL' || rowStatus === status);
            r.classList.toggle('is-hidden', !match);
        });
    }

    function openEditTicketModal(data) {
        document.getElementById('ozModalTicketId').value = data.id || '';
        document.getElementById('ozModalTicketCode').textContent = data.code || '';
        document.getElementById('ozModalName').value = data.name || '';
        document.getElementById('ozModalPhone').value = data.phone || '';
        document.getElementById('ozModalRoomNo').value = data.room_no || '';
        document.getElementById('ozModalAmount').value = parseFloat(data.amount || 0).toFixed(2);
        document.getElementById('ozModalStatus').value = data.status || 'Valid';

        const modal = document.getElementById('ozEditTicketModal');
        if (modal) modal.classList.add('is-visible');
    }

    function closeEditTicketModal() {
        const modal = document.getElementById('ozEditTicketModal');
        if (modal) modal.classList.remove('is-visible');
    }

    /**
     * 12. Turnstile Scanner & Camera Barcode Pipeline
     */
    let videoStream = null;
    let cameraActive = false;
    let barcodeDetector = null;
    let scanInterval = null;
    let isProcessing = false;
    let barrierTimer = null;
    let countdownTimer = null;
    let typingTimer = null;

    if ('BarcodeDetector' in window) {
        try {
            barcodeDetector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128', 'code_39'] });
        } catch (e) {
            barcodeDetector = null;
        }
    }

    function constructFullToken(rawVal) {
    let clean = rawVal.trim().toUpperCase();
    if (!clean) return '';
    if (clean.startsWith('OZONE-') || clean.startsWith('OZ-')) return clean;
    if (/^\d+$/.test(clean)) clean = clean.padStart(4, '0');
    const prefixSpan = document.getElementById('ozDailyPrefixSpan');
    const prefix = prefixSpan ? prefixSpan.textContent.trim() : (config.current_mon_prefix || 'OZ-');
    return prefix + clean;
}

    function triggerBarrierRelay(open) {
        const pill = document.getElementById('ozTurnstileStatePill');
        const txt  = document.getElementById('ozTurnstileStateTxt');
        const sub  = document.getElementById('ozBarrierSub');
        if (!pill || !txt) return;

        clearTimeout(barrierTimer);
        clearInterval(countdownTimer);

        if (open) {
            pill.style.cssText = 'background: rgba(16, 185, 129, 0.15) !important; color: #059669 !important; border: 1.5px solid #10b981 !important; font-weight: 800; border-radius: 9999px; padding: 6px 16px; display: inline-flex; align-items: center; gap: 6px;';
            txt.innerHTML = '<span class="dashicons dashicons-yes-alt" style="font-size: 16px; width: 16px; height: 16px;"></span> VERIFIED';

            if (sub) {
                sub.textContent = 'Barrier Gate Unlocked • Entry Verified';
            }
        } else {
            pill.style.cssText = 'background: rgba(239, 68, 68, 0.12) !important; color: #dc2626 !important; border: 1.5px solid #ef4444 !important; font-weight: 800; border-radius: 9999px; padding: 6px 16px; display: inline-flex; align-items: center; gap: 6px;';
            txt.innerHTML = '<span class="dashicons dashicons-dismiss" style="font-size: 16px; width: 16px; height: 16px;"></span> ACCESS REJECTED';

            barrierTimer = setTimeout(() => {
                pill.removeAttribute('style');
                pill.className = 'oz-relay-status-pill closed';
                txt.innerHTML = '<span class="dashicons dashicons-shield"></span> ' + __('barrierLocked', 'BARRIER LOCKED');
            }, 3000);
        }
    }

    function displayTelemetry(data, isValid) {
        const card        = document.getElementById('ozResultTelemetryBox');
        const avatar      = document.getElementById('ozResultAvatar');
        const badge       = document.getElementById('ozResultBadge');
        const patronEl    = document.getElementById('ozResultPatron');
        const codeEl      = document.getElementById('ozResultCode');
        const roomWrap    = document.getElementById('ozResultRoomWrap');
        const roomEl      = document.getElementById('ozResultRoom');
        const durationEl  = document.getElementById('ozResultDuration');
        const validUntil  = document.getElementById('ozResultValidUntil');
        const amountEl    = document.getElementById('ozResultAmount');
        const paymentEl   = document.getElementById('ozResultPayment');
        const soldByEl    = document.getElementById('ozResultSoldBy');
        const msgTxt      = document.getElementById('ozResultMessageTxt');
        const msgRow      = document.getElementById('ozResultMessageRow');

        const cardNameEl  = document.getElementById('ozResultCardCustomerName');
        const cardPhoneEl = document.getElementById('ozResultCardCustomerPhone');
        const guestTypeTxt = document.getElementById('ozResultGuestTypeTxt');
        const packageContainer = document.getElementById('ozResultPackage');

        if (!card) return;

        const customerName = data.customer_name || 'Walk-in Guest';
        const customerPhone = data.customer_phone || '-';

        if (codeEl) codeEl.textContent       = data.ticket_code || '-';
        if (patronEl) patronEl.textContent   = customerName;
        if (msgTxt) msgTxt.textContent       = data.message || '';
        if (soldByEl) soldByEl.textContent   = data.sold_by || 'Front Desk Staff';

        if (cardNameEl) cardNameEl.textContent = customerName;
        if (cardPhoneEl) cardPhoneEl.textContent = customerPhone;

        if (avatar) {
            avatar.textContent = customerName ? customerName.charAt(0).toUpperCase() : 'G';
        }

        const isRoomGuest = (data.guest_type === 'room_guest' || Boolean(data.room_no));
        if (guestTypeTxt) {
            if (isRoomGuest) {
                guestTypeTxt.textContent = 'Room Guest';
                guestTypeTxt.style.color = '#0284c7';
            } else {
                guestTypeTxt.textContent = 'Outdoor Guest';
                guestTypeTxt.style.color = '#0f172a';
            }
        }

        if (roomWrap && roomEl) {
            if (isRoomGuest && data.room_no) {
                roomWrap.style.display = 'block';
                roomEl.textContent = data.room_no;
            } else {
                roomWrap.style.display = 'none';
            }
        }

        if (packageContainer) {
            const rawPackages = (data.package || data.package_details || '').trim();

            if (rawPackages && rawPackages !== '-' && rawPackages.toLowerCase() !== 'no active scan telemetry loaded.') {
                const items = rawPackages.split(',').map(item => item.trim()).filter(Boolean);

                if (items.length > 0) {
                    packageContainer.innerHTML = items.map(pkg => `
                        <div style="margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 15px; width: 15px; height: 15px; flex-shrink: 0;"></span>
                            <span style="color: #0f172a; font-weight: 700; font-size: 13px;">${escapeHtml(pkg)}</span>
                        </div>
                    `).join('');
                } else {
                    packageContainer.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 15px; width: 15px; height: 15px; flex-shrink: 0;"></span>
                            <span style="color: #0f172a; font-weight: 700; font-size: 13px;">${escapeHtml(rawPackages)}</span>
                        </div>
                    `;
                }
            } else {
                packageContainer.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 15px; width: 15px; height: 15px; flex-shrink: 0;"></span>
                        <span style="color: #0f172a; font-weight: 700; font-size: 13px;">Standard Sky Swim Pass (1p x 1h)</span>
                    </div>
                `;
            }
        }

        if (isValid) {
            card.style.borderColor = '#10b981';
            if (badge) {
                badge.className = 'ifs-pms-badge ifs-pms-badge-success';
                badge.textContent = __('passAuthorized', 'Pass Authorized');
            }
            if (msgRow) {
                msgRow.style.background = 'rgba(16, 185, 129, 0.1)';
                msgRow.style.color = '#059669';
            }
        } else {
            card.style.borderColor = '#f59e0b';
            if (badge) {
                badge.className = 'ifs-pms-badge ifs-pms-badge-warning';
                badge.textContent = 'Already Admitted';
            }
            if (msgRow) {
                msgRow.style.background = 'rgba(245, 158, 11, 0.1)';
                msgRow.style.color = '#d97706';
            }
        }

        if (durationEl) durationEl.textContent = (data.duration_hours || 1) + ' ' + ((data.duration_hours > 1) ? 'Hours' : 'Hour');
        if (validUntil) validUntil.textContent = data.valid_until || '-';
        if (amountEl) amountEl.textContent   = (data.amount || '0.00') + ' ' + config.currency;
        if (paymentEl) paymentEl.textContent = data.payment_method || 'Cash';
    }

    function logSessionEntry(code, time, isValid, name, guestType, roomNo) {
        const emptyState = document.getElementById('ozScanNoHistory');
        if (emptyState) emptyState.remove();

        const container = document.getElementById('ozScanSessionLogContainer');
        if (!container) return;

        const row = document.createElement('div');
        row.style.cssText = 'display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1.5px solid #e2e8f0; border-radius:10px; padding:10px 14px;';

        const admittedLabel = __('admitted', 'Admitted');
        const deniedLabel   = __('denied', 'Denied');

        const badgeHtml = isValid
            ? '<span class="ifs-pms-badge ifs-pms-badge-success" style="font-size:11px; padding:3px 10px; border-radius:6px; font-weight:800;">' + escapeHtml(admittedLabel) + '</span>'
            : '<span class="ifs-pms-badge ifs-pms-badge-danger" style="font-size:11px; padding:3px 10px; border-radius:6px; font-weight:800;">' + escapeHtml(deniedLabel) + '</span>';

        const roomTag = (guestType === 'room_guest' && roomNo) ? `[Room ${escapeHtml(roomNo)}]` : '';

        row.innerHTML = `
            <div style="display:flex; align-items:center; gap:10px;">
                <span class="dashicons ${isValid ? 'dashicons-yes-alt' : 'dashicons-warning'}" style="color:${isValid ? '#10b981' : '#ef4444'}; font-size:20px; width:20px; height:20px;"></span>
                <div>
                    <strong style="font-size:13.5px; color:#0f172a; display:block; font-weight:800;">${escapeHtml(name || 'Walk-in Guest')}</strong>
                    <span class="ifs-pms-mono" style="font-size:11.5px; color:#64748b;">${roomTag} ${roomTag ? '•' : ''} ${escapeHtml(code)} • ${escapeHtml(time)}</span>
                </div>
            </div>
            <div>${badgeHtml}</div>
        `;

        container.insertBefore(row, container.firstChild);
        while (container.children.length > 10) {
            container.removeChild(container.lastChild);
        }
    }

    function executeVerification(customCode = null) {
        if (isProcessing) return;

        const inputEl  = document.getElementById('ozScanSerialInput');
        const rawVal   = customCode ? customCode : (inputEl ? inputEl.value : '');
        const codeVal  = constructFullToken(rawVal);
        const statusEl = document.getElementById('ozScannerStatusBar');
        const statusTxt = document.getElementById('ozStatusBarText');
        const token    = config.security_token || config.nonce;

        if (!codeVal || codeVal === config.current_mon_prefix) {
            if (inputEl) inputEl.focus();
            return;
        }

        if (inputEl && /^\d+$/.test(rawVal.trim())) {
            inputEl.value = rawVal.trim().padStart(4, '0');
        }

        isProcessing = true;

        if (statusEl) {
            statusEl.style.cssText = 'background: rgba(2, 132, 199, 0.08); border: 1.5px solid rgba(2, 132, 199, 0.35); color: #0284c7; padding: 14px 20px; border-radius: 12px; font-weight: 700; font-size: 13.5px; display: flex; align-items: center; gap: 10px; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08);';
            if (statusTxt) statusTxt.textContent = __('contactingGate', 'Contacting Turnstile Controller...');
        }

        const postData = new URLSearchParams();
        postData.append('action', 'ifs_pms_verify_pass_action');
        postData.append('ticket_code', codeVal);
        postData.append('security', token);

        fetch(config.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: postData.toString()
        })
        .then(response => response.json())
        .then(res => {
            const timeString = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

            if (res && res.success) {
                ifsPmsAudio.playSuccess();
                if (statusEl) {
                    statusEl.style.cssText = 'background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(5, 150, 105, 0.18) 100%); border: 1.5px solid #10b981; color: #065f46; padding: 14px 20px; border-radius: 12px; font-weight: 800; font-size: 14px; display: flex; align-items: center; gap: 10px; transition: all 0.3s ease; box-shadow: 0 6px 18px rgba(16, 185, 129, 0.15);';
                    if (statusTxt) statusTxt.textContent = (res.data && res.data.message) ? res.data.message : __('accessGranted', 'ACCESS GRANTED • TURNSTILE UNLOCKED');
                }

                triggerBarrierRelay(true);
                displayTelemetry(res.data, true);
                logSessionEntry(codeVal, timeString, true, res.data.customer_name, res.data.guest_type, res.data.room_no);
            } else {
                ifsPmsAudio.playError();
                const errorMsg = (res && res.data && res.data.message) ? res.data.message : __('accessDenied', 'Invalid or Expired Pass ID');

                if (statusEl) {
                    statusEl.style.cssText = 'background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.18) 100%); border: 1.5px solid #ef4444; color: #991b1b; padding: 14px 20px; border-radius: 12px; font-weight: 800; font-size: 14px; display: flex; align-items: center; gap: 10px; transition: all 0.3s ease; box-shadow: 0 6px 18px rgba(239, 68, 68, 0.15);';
                    if (statusTxt) statusTxt.textContent = errorMsg;
                }

                triggerBarrierRelay(false);

                const telemetryData = (res && res.data) ? res.data : {};
                const customerName  = telemetryData.customer_name || 'Walk-in Guest';
                const guestType     = telemetryData.guest_type || 'customer';
                const roomNo        = telemetryData.room_no || '';

                const finalData = {
                    ticket_code: codeVal,
                    customer_name: customerName,
                    customer_phone: telemetryData.customer_phone || '-',
                    guest_type: guestType,
                    room_no: roomNo,
                    package: telemetryData.package || 'Standard Swim Pass',
                    amount: telemetryData.amount || '0.00',
                    payment_method: telemetryData.payment_method || 'Cash',
                    duration_hours: telemetryData.duration_hours || 1,
                    valid_until: telemetryData.valid_until || '-',
                    sold_by: telemetryData.sold_by || '-',
                    message: errorMsg,
                    scanned_at: telemetryData.scanned_at || timeString
                };

                displayTelemetry(finalData, false);
                logSessionEntry(codeVal, timeString, false, customerName, guestType, roomNo);
            }
        })
        .catch(err => {
            console.error("Ozone Scanner Error ->", err);
            if (statusEl) {
                statusEl.style.cssText = 'background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.18) 100%); border: 1.5px solid #ef4444; color: #991b1b; padding: 14px 20px; border-radius: 12px; font-weight: 800; font-size: 14px; display: flex; align-items: center; gap: 10px;';
                if (statusTxt) statusTxt.textContent = __('gateTimeout', 'Gate Controller Timeout');
            }
        })
        .finally(() => {
            isProcessing = false;
            if (inputEl) {
                setTimeout(() => {
                    inputEl.value = '';
                    inputEl.focus();
                }, 1000);
            }
        });
    }

    function resetScannerConsole() {
        const input = document.getElementById('ozScanSerialInput');
        if (input) {
            input.value = '';
            input.focus();
        }
        const statusEl = document.getElementById('ozScannerStatusBar');
        const statusTxt = document.getElementById('ozStatusBarText');
        const pill = document.getElementById('ozTurnstileStatePill');
        const pillTxt = document.getElementById('ozTurnstileStateTxt');
        const sub = document.getElementById('ozBarrierSub');

        if (statusEl) {
            statusEl.removeAttribute('style');
            statusEl.className = 'oz-scanner-status-bar is-standby';
            if (statusTxt) statusTxt.textContent = __('scannerStandby', 'Terminal Standby • Present pass to scanner');
        }

        if (pill && pillTxt) {
            pill.removeAttribute('style');
            pill.className = 'oz-relay-status-pill closed';
            pillTxt.innerHTML = '<span class="dashicons dashicons-shield"></span> ' + __('barrierLocked', 'BARRIER LOCKED');
            if (sub) sub.textContent = __('barrierRelockNotice', '4000ms actuation pulse • Auto-relock armed');
        }
    }

    async function toggleCamera() {
        const video        = document.getElementById('ozScannerVideo');
        const placeholder = document.getElementById('ozScannerPlaceholder');
        const label        = document.getElementById('ozCamBtnLabel');

        if (!cameraActive) {
            try {
                videoStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
                });
                if (video) {
                    video.srcObject = videoStream;
                    video.classList.add('is-visible');
                    await video.play();
                }
                if (placeholder) placeholder.classList.add('is-hidden');

                cameraActive = true;
                if (label) label.textContent = __('turnOffCam', 'Turn Off Camera');

                if (barcodeDetector) {
                    scanInterval = setInterval(async () => {
                        if (isProcessing) return;
                        if (video && video.readyState === video.HAVE_ENOUGH_DATA) {
                            try {
                                const codes = await barcodeDetector.detect(video);
                                if (codes.length > 0 && codes[0].rawValue) {
                                    executeVerification(codes[0].rawValue);
                                }
                            } catch (detectErr) {}
                        }
                    }, 280);
                }
            } catch (err) {
                alert(__('camError', 'Camera stream unavailable. Verify browser video permissions or use an external laser scanner.'));
            }
        } else {
            if (videoStream) {
                videoStream.getTracks().forEach(t => t.stop());
            }
            clearInterval(scanInterval);
            if (video) {
                video.srcObject = null;
                video.classList.remove('is-visible');
            }
            if (placeholder) placeholder.classList.remove('is-hidden');
            if (label) label.textContent = __('turnOnCam', 'Activate WebCam Scanner');
            cameraActive = false;
        }
    }

    /**
     * 13. Pool Floor Telemetry, Timers & Checkout Engine
     */
    let serverStartEpoch = parseInt(config.server_epoch, 10) || Math.floor(Date.now() / 1000);
    let clientMountTime  = Date.now();
    let isPolling        = false;

    function getSyncedEpoch() {
        const elapsedSeconds = Math.floor((Date.now() - clientMountTime) / 1000);
        return serverStartEpoch + elapsedSeconds;
    }

    function renderTimeBadge(rowEl, badgeEl, diff) {
        const hourlyRate = parseFloat(rowEl.getAttribute('data-hourly-rate')) || 100.00;

        if (diff > 0) {
            const timeDigits = formatDigitalClock(diff);
            const isWarning = diff <= 900;
            badgeEl.className = 'oz-row-clock-pill ' + (isWarning ? 'oz-clock-warning' : 'oz-clock-safe') + ' oz-timer-display';
            badgeEl.innerHTML = '<span class="oz-icon-wrapper oz-icon-sm"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.47 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg></span> <span class="oz-clock-digits">' + timeDigits + ' left</span>';
        } else {
            const overstaySeconds = Math.abs(diff);
            const timeDigits = formatDigitalClock(overstaySeconds);
            const extraHours = overstaySeconds / 3600;
            const surchargeAmount = (extraHours * hourlyRate).toFixed(2);

            badgeEl.className = 'oz-row-clock-pill oz-clock-overstay oz-timer-display';
            badgeEl.innerHTML = '<span class="oz-icon-wrapper oz-icon-sm"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg></span>' +
                                '<span class="oz-clock-digits">+' + timeDigits + ' extra</span>' +
                                '<span class="oz-surcharge-text">Fee: +' + surchargeAmount + ' ' + escapeHtml(config.currency) + '</span>';
        }
    }

    function runLiveClockAndCounters() {
        const currentEpoch = getSyncedEpoch();
        const serverDate   = new Date(currentEpoch * 1000);

        let hours = serverDate.getUTCHours();
        const minutes = String(serverDate.getUTCMinutes()).padStart(2, '0');
        const seconds = String(serverDate.getUTCSeconds()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;

        const clockEl = document.getElementById('ozWatcherClock');
        if (clockEl) {
            clockEl.textContent = String(hours).padStart(2, '0') + ':' + minutes + ':' + seconds + ' ' + ampm;
        }

        const rows = document.querySelectorAll('.oz-swimmer-row');
        rows.forEach(function (row) {
            const exitStamp = parseInt(row.getAttribute('data-exit-timestamp'), 10);
            const badgeEl = row.querySelector('.oz-timer-display');
            if (!badgeEl || isNaN(exitStamp)) return;

            const diff = exitStamp - currentEpoch;
            renderTimeBadge(row, badgeEl, diff);
        });
    }

    function pollPoolFloorData() {
        if (isPolling) return;
        isPolling = true;

        $.ajax({
            url: config.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ifs_pms_get_live_telemetry_action',
                security: config.security_token || config.nonce
            },
            success: function (res) {
                isPolling = false;
                if (!res || !res.success || !res.data) return;

                const data = res.data;
                if (data.server_epoch) {
                    serverStartEpoch = data.server_epoch;
                    clientMountTime  = Date.now();
                }

                $('#ozLiveCensusCount').text(data.current_count);
                $('#ozLiveCensusCapacity').text(data.max_capacity);
                $('#ozLiveCapacityPctText').text(data.capacity_pct + '%');
                $('#ozLiveCapacityBar').css({
                    'width': data.capacity_pct + '%',
                    'background': data.bar_gradient
                });
                $('#ozLiveStatusPill').attr('class', 'ifs-pms-badge ' + data.status_badge + ' oz-status-pill-padded');
                $('#ozLiveStatusText').text(data.status_label);

                const tbody = $('#ozActiveSwimmersTbody');
                if (!data.swimmers || data.swimmers.length === 0) {
                    tbody.html(
                        '<tr id="ozEmptyNoticeRow">' +
                            '<td colspan="6" class="oz-empty-state">' +
                                '<div class="oz-empty-icon-wrap"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="36" height="36" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></div>' +
                                escapeHtml(config.i18n.noSwimmers) +
                            '</td>' +
                        '</tr>'
                    );
                    return;
                }

                $('#ozEmptyNoticeRow').remove();

                const existingRowIds = [];
                $('.oz-swimmer-row').each(function () {
                    existingRowIds.push(parseInt($(this).attr('data-id'), 10));
                });

                const incomingIds = data.swimmers.map(s => parseInt(s.id, 10));

                existingRowIds.forEach(function (id) {
                    if (!incomingIds.includes(id)) {
                        $('#ozRow-' + id).fadeOut(250, function () { $(this).remove(); });
                    }
                });

                data.swimmers.forEach(function (swimmer) {
                    const rowEl = $('#ozRow-' + swimmer.id);
                    if (rowEl.length === 0) {
                        const newRowHtml =
                            '<tr class="oz-swimmer-row" id="ozRow-' + swimmer.id + '" data-id="' + swimmer.id + '" data-hourly-rate="' + swimmer.hourly_rate + '" data-exit-timestamp="' + swimmer.exit_stamp + '" data-entry-timestamp="' + swimmer.entry_stamp + '">' +
                                '<td class="ifs-pms-mono oz-text-blue oz-text-weight-heavy">' + escapeHtml(swimmer.ticket_code) + '</td>' +
                                '<td class="ifs-pms-mono oz-text-dark oz-text-weight-bold">' + escapeHtml(swimmer.entry_formatted) + '</td>' +
                                '<td class="ifs-pms-mono"><span class="ifs-pms-badge oz-badge-soft-blue">' + swimmer.duration_hours + (swimmer.duration_hours > 1 ? ' Hrs' : ' Hr') + '</span></td>' +
                                '<td class="ifs-pms-mono oz-text-sub oz-text-weight-heavy">' + escapeHtml(swimmer.exit_formatted) + '</td>' +
                                '<td><span class="oz-row-clock-pill oz-clock-safe oz-timer-display" id="ozTimer-' + swimmer.id + '"><span class="oz-icon-wrapper oz-icon-sm"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.47 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg></span> <span class="oz-clock-digits">00:00:00 left</span></span></td>' +
                                '<td class="oz-td-right"><button type="button" class="oz-checkout-btn" onclick="ifsPms.checkoutSwimmer(' + swimmer.id + ', this)"><span class="oz-icon-wrapper oz-icon-sm"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span> ' + escapeHtml(config.i18n.checkoutBtn) + '</button></td>' +
                            '</tr>';
                        tbody.prepend($(newRowHtml).hide().fadeIn(300));
                    } else {
                        rowEl.attr('data-exit-timestamp', swimmer.exit_stamp);
                        rowEl.attr('data-entry-timestamp', swimmer.entry_stamp);
                        rowEl.attr('data-hourly-rate', swimmer.hourly_rate);
                    }
                });

                runLiveClockAndCounters();
            },
            error: function () {
                isPolling = false;
            }
        });
    }

    function checkoutSwimmer(ticketId, btn) {
        if (!confirm(config.i18n.confirmCheckout)) return;

        const $btn = $(btn);
        $btn.prop('disabled', true).html('<span class="oz-spinner-icon"></span>');

        $.ajax({
            url: config.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ifs_pms_checkout_swimmer_ajax',
                ticket_id: ticketId,
                security: config.security_token || config.nonce
            },
            success: function (res) {
                if (res && res.success) {
                    $('#ozRow-' + ticketId).fadeOut(300, function () {
                        $(this).remove();
                    });

                    const successMsg = (res.data && res.data.message) ? res.data.message : 'Patron checked out from pool deck successfully.';
                    const noticeHtml = '<div class="notice notice-success is-dismissible oz-notice-spacing" style="margin: 0 0 15px 0; padding: 10px 14px; background: #ecfdf5; border-left: 4px solid #10b981; color: #065f46; font-weight: 700; border-radius: 4px;">' + escapeHtml(successMsg) + '</div>';

                    $('.oz-live-status-stack').prepend(noticeHtml);

                    setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } else {
                    alert((res && res.data && res.data.message) ? res.data.message : 'Checkout failed');
                    $btn.prop('disabled', false).html('<span class="oz-icon-wrapper oz-icon-sm"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span> ' + escapeHtml(config.i18n.checkoutBtn));
                }
            },
            error: function () {
                alert('Connection error during checkout.');
                $btn.prop('disabled', false).html('<span class="oz-icon-wrapper oz-icon-sm"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span> ' + escapeHtml(config.i18n.checkoutBtn));
            }
        });
    }

    /**
     * 14. Master Clock & Countdown Scheduler
     */
    function updateMasterClockAndCountdown() {
        const now = new Date();

        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        const hoursStr = String(hours).padStart(2, '0');

        const clockEl = document.getElementById('ifsPmsLiveClock');
        if (clockEl) {
            clockEl.textContent = `${hoursStr}:${minutes}:${seconds} ${ampm}`;
        }

        const dateEl = document.getElementById('ifsPmsLiveDate');
        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        const countdownEl = document.getElementById('ifsPmsCountdownText');
        const containerEl = document.getElementById('ifsPmsClosingCountdown');
        const dashConfig = window.ifsPmsDashboardConfig;

        if (!countdownEl || !dashConfig) return;

        const dayStatus = dashConfig.day_status || 'open';
        if (dayStatus === 'closed') {
            countdownEl.textContent = 'Venue Closed Today';
            if (containerEl) containerEl.classList.add('is-visible');
            return;
        }

        const openingStr = dashConfig.opening_time || '10:00';
        const closingStr = dashConfig.closing_time || '23:00';

        const [openH, openM] = openingStr.split(':').map(Number);
        const [closeH, closeM] = closingStr.split(':').map(Number);

        const currentTotalMins = now.getHours() * 60 + now.getMinutes();
        const openTotalMins = openH * 60 + openM;
        const closeTotalMins = closeH * 60 + closeM;

        const targetDate = new Date(now);

        if (currentTotalMins >= openTotalMins && currentTotalMins <= closeTotalMins) {
            targetDate.setHours(closeH, closeM, 0, 0);
            const diff = targetDate - now;

            if (diff > 0) {
                const hrs = Math.floor(diff / (1000 * 60 * 60));
                const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const secs = Math.floor((diff % (1000 * 60)) / 1000);
                countdownEl.textContent = `Closes in ${hrs}h ${mins}m ${secs}s`;
                if (containerEl) containerEl.classList.add('is-visible');
            } else if (containerEl) {
                containerEl.classList.remove('is-visible');
            }
        } else {
            targetDate.setHours(openH, openM, 0, 0);
            if (currentTotalMins > closeTotalMins) {
                targetDate.setDate(targetDate.getDate() + 1);
            }
            const diff = targetDate - now;

            if (diff > 0) {
                const hrs = Math.floor(diff / (1000 * 60 * 60));
                const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const secs = Math.floor((diff % (1000 * 60)) / 1000);
                countdownEl.textContent = `Starts in ${hrs}h ${mins}m ${secs}s`;
                if (containerEl) containerEl.classList.add('is-visible');
            } else if (containerEl) {
                containerEl.classList.remove('is-visible');
            }
        }
    }

    /**
     * 15. Public API Namespace Registration
     */
    window.ifsPms = {
        audio: ifsPmsAudio,
        applyTheme: applyTheme,
        initTheme: initTheme,
        showReceipt: showReceipt,
        closeReceipt: closeReceipt,
        exportLedger: exportLedger,
        switchSettingsTab: switchSettingsTab,
        switchSubTab: switchSubTab,
        applyIncomeFilter: applyIncomeFilter,
        applyExpenseFilter: applyExpenseFilter,
        switchExpenseTab: switchExpenseTab,
        switchMemberTab: switchMemberTab,
        switchStaffTab: switchStaffTab,
        switchTicketTab: switchTicketTab,
        setExpensePreset: setExpensePreset,
        openViewExpenseModal: openViewExpenseModal,
        closeViewExpenseModal: closeViewExpenseModal,
        openEditExpenseModal: openEditExpenseModal,
        closeEditExpenseModal: closeEditExpenseModal,
        addPricingTierRow: addPricingTierRow,
        addAddonRow: addAddonRow,
        removeRow: removeRow,
        filterCustomerTable: filterCustomerTable,
        filterDirectory: filterDirectory,
        clearFilter: clearFilter,
        handlePlanSelect: handlePlanSelect,
        syncCardDisplay: syncCardDisplay,
        openViewMemberModal: openViewMemberModal,
        closeViewMemberModal: closeViewMemberModal,
        openEditMemberModal: openEditMemberModal,
        closeEditMemberModal: closeEditMemberModal,
        openViewStaffModal: openViewStaffModal,
        closeViewStaffModal: closeViewStaffModal,
        openEditStaffModal: openEditStaffModal,
        closeEditStaffModal: closeEditStaffModal,
        filterStaffDirectory: filterStaffDirectory,
        setGuestType: setGuestType,
        syncRoomDisplay: syncRoomDisplay,
        selectTender: selectTender,
        selectTenderByName: selectTenderByName,
        toggleTierSwitch: toggleTierSwitch,
        deltaModularQty: deltaModularQty,
        deltaModularHours: deltaModularHours,
        toggleAddon: toggleAddon,
        quickCash: quickCash,
        computeChange: computeChange,
        recalculatePos: recalculatePos,
        printPreviewReceipt: printPreviewReceipt,
        validateFormSubmission: validateFormSubmission,
        resetTerminal: resetTerminal,
        filterTicketTable: filterTicketTable,
        openEditTicketModal: openEditTicketModal,
        closeEditTicketModal: closeEditTicketModal,
        executeVerification: executeVerification,
        resetScannerConsole: resetScannerConsole,
        toggleCamera: toggleCamera,
        checkoutSwimmer: checkoutSwimmer,
        openMediaUploader: (id, cb) => openMediaUploader(id, cb),
        escapeHtml: escapeHtml
    };

    // Global Backward-Compatibility Aliases
    window.ifs_pms_audio = ifsPmsAudio;
    window.ifs_pms_show_receipt = showReceipt;
    window.ifs_pms_close_receipt = closeReceipt;
    window.ifs_pms_export_ledger = exportLedger;
    window.ifsPmsSwitchSettingsTab = switchSettingsTab;
    window.ifsPmsSwitchSubTab = switchSubTab;
    window.ifsPmsApplyIncomeFilter = applyIncomeFilter;
    window.ifsPmsApplyExpenseFilter = applyExpenseFilter;
    window.ifsPmsSwitchExpenseTab = switchExpenseTab;
    window.ifsPmsSwitchMemberTab = switchMemberTab;
    window.ifsPmsSwitchStaffTab = switchStaffTab;
    window.ifsPmsSetExpensePreset = setExpensePreset;
    window.ifsPmsOpenViewExpenseModal = openViewExpenseModal;
    window.ifsPmsCloseViewExpenseModal = closeViewExpenseModal;
    window.ifsPmsOpenEditExpenseModal = openEditExpenseModal;
    window.ifsPmsCloseEditExpenseModal = closeEditExpenseModal;
    window.ifsPmsAddPricingTierRow = addPricingTierRow;
    window.ifsPmsAddAddonRow = addAddonRow;
    window.ifsPmsRemoveRow = removeRow;
    window.ifsPmsFilterCustomerTable = filterCustomerTable;
    window.ifsPmsFilterDirectory = filterDirectory;
    window.ifsPmsClearFilter = clearFilter;
    window.ifsPmsHandlePlanSelect = handlePlanSelect;
    window.ifsPmsSyncCardDisplay = syncCardDisplay;
    window.ifsPmsOpenViewMemberModal = openViewMemberModal;
    window.ifsPmsCloseViewMemberModal = closeViewMemberModal;
    window.ifsPmsOpenEditMemberModal = openEditMemberModal;
    window.ifsPmsCloseEditMemberModal = closeEditMemberModal;
    window.ifsPmsOpenViewStaffModal = openViewStaffModal;
    window.ifsPmsCloseViewStaffModal = closeViewStaffModal;
    window.ifsPmsOpenEditStaffModal = openEditStaffModal;
    window.ifsPmsCloseEditStaffModal = closeEditStaffModal;
    window.ifsPmsFilterStaffDirectory = filterStaffDirectory;
    window.ozSwitchTicketTab = switchTicketTab;
    window.ozSetGuestType = setGuestType;
    window.ozSyncRoomDisplay = syncRoomDisplay;
    window.ozSelectTenderByName = selectTenderByName;
    window.ozSelectTender = selectTender;
    window.ozToggleTierSwitch = toggleTierSwitch;
    window.ozDeltaModularQty = deltaModularQty;
    window.ozDeltaModularHours = deltaModularHours;
    window.ozToggleAddon = toggleAddon;
    window.ozQuickCash = quickCash;
    window.ozComputeChange = computeChange;
    window.ozRecalculate = recalculatePos;
    window.ozPrintPreviewReceipt = printPreviewReceipt;
    window.ozValidateFormSubmission = validateFormSubmission;
    window.ozResetTerminal = resetTerminal;
    window.ozFilterTicketTable = filterTicketTable;
    window.ozOpenEditModal = openEditTicketModal;
    window.ozCloseEditModal = closeEditTicketModal;
    window.ozExecuteVerification = executeVerification;
    window.ozResetScannerConsole = resetScannerConsole;
    window.ozToggleCamera = toggleCamera;
    window.ozCheckoutSwimmer = checkoutSwimmer;
    window.ozHandleFormSubmit = handleFormSubmit;

    function initScannerConsole() {
        const input = document.getElementById('ozScanSerialInput');
        if (!input) return;

        input.focus();
        document.addEventListener('click', function() {
            if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                input.focus();
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(typingTimer);
                if (input.value.trim().length > 0) {
                    executeVerification(input.value.trim());
                }
            }
        });

        input.addEventListener('paste', function () {
            setTimeout(() => {
                let pasteVal = input.value.trim().toUpperCase();
                const prefix = config.current_mon_prefix || 'OZ-';
                if (pasteVal.startsWith(prefix)) {
                    input.value = pasteVal.replace(prefix, '');
                }
                if (input.value.trim().length > 0) {
                    executeVerification(input.value.trim());
                }
            }, 50);
        });

        window.addEventListener('beforeunload', function () {
            if (videoStream) {
                videoStream.getTracks().forEach(t => t.stop());
            }
            clearInterval(scanInterval);
            clearTimeout(barrierTimer);
            clearInterval(countdownTimer);
            clearTimeout(typingTimer);
        });
    }

    /**
     * 17. POS Keyboard Shortcuts & Realtime Event Setup
     */
    function initPosEvents() {
        ['ozGuestName', 'ozGuestPhone', 'ozHotelRoomNo'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', recalculatePos);
        });

        const cashInput = document.getElementById('ozCashReceived');
        if (cashInput) cashInput.addEventListener('input', computeChange);

        const tokenEl = document.getElementById('ozPrevToken');
        if (tokenEl) {
            const wrap = document.getElementById('ozReceiptQrWrap');
            if (wrap && typeof window.QRCode !== 'undefined') {
                wrap.innerHTML = '';
                new window.QRCode(wrap, {
                    text: tokenEl.textContent.trim(),
                    width: 85,
                    height: 85,
                    colorDark: '#0f172a',
                    colorLight: '#ffffff',
                    correctLevel: window.QRCode.CorrectLevel.M
                });
            }
        }

        recalculatePos();

        window.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const form = document.getElementById('ozPosMasterForm');
                if (form && validateFormSubmission(e) && form.checkValidity()) {
                    e.preventDefault();
                    form.submit();
                }
            }
            if (e.altKey && (e.key === 'c' || e.key === 'C')) {
                e.preventDefault();
                resetTerminal();
            }
        });
    }

    /**
     * 18. DOM Readiness Bootstrap
     */
    $(document).ready(function () {
        initTheme();

        if (config.last_ticket) {
            showReceipt(config.last_ticket);
        }

        const $amenCheckbox =$('input[name="ifs_pms_enable_amenities"]');
        const $amenBox =$('#ifsPmsAmenitiesRepeaterBox');
        if ($amenCheckbox.length &&$amenBox.length) {
            $amenCheckbox.on('change', function () {$amenBox.toggleClass('is-visible', this.checked);
            });
        }

        ['ifsMemName', 'ifsMemPhone'].forEach(id => {
            const input = document.getElementById(id);
            if (input) input.addEventListener('input', syncCardDisplay);
        });
        syncCardDisplay();

        initScannerConsole();
        initPosEvents();

        updateMasterClockAndCountdown();
        runLiveClockAndCounters();
        setInterval(updateMasterClockAndCountdown, 1000);
        setInterval(runLiveClockAndCounters, 1000);
        setInterval(pollPoolFloorData, 5000);

        function triggerAutoPrint() {
            if (typeof window.ozPrintPreviewReceipt === 'function') {
                window.ozPrintPreviewReceipt();
            } else {
                window.print();
            }
        }

        const hasPhpTicket = Boolean(config.last_ticket);
        const needsPrint = localStorage.getItem('oz_auto_print') === '1';
        const savedHtml = localStorage.getItem('oz_last_receipt_html');
        const previewCard = document.getElementById('ozReceiptPreviewContainer');

        if (needsPrint && savedHtml && previewCard) {
            previewCard.innerHTML = savedHtml;
            togglePrintButton(true);
            localStorage.removeItem('oz_auto_print');
            setTimeout(triggerAutoPrint, 450);
        } else if (hasPhpTicket) {
            togglePrintButton(true);
        }

        if (window.history && window.history.replaceState) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page') || 'ifs-pms';
            const currentView = urlParams.get('view');
            
            let cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=' + currentPage;
            if (currentView) {
                cleanUrl += '&view=' + currentView;
            }
            
            window.history.replaceState({path: cleanUrl}, '', cleanUrl);
        }
    });

})(window, document, jQuery);