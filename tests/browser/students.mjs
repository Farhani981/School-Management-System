import assert from 'node:assert/strict';
import { writeFile } from 'node:fs/promises';

const targets = await (await fetch('http://127.0.0.1:9222/json/list')).json();
const ws = new WebSocket(targets.find(tab => tab.type === 'page').webSocketDebuggerUrl);
await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
const pending = new Map();
const errors = [];
let nextId = 0;
ws.addEventListener('message', ({ data }) => {
    const message = JSON.parse(data);
    if (message.id) {
        const job = pending.get(message.id);
        pending.delete(message.id);
        message.error ? job.reject(message.error) : job.resolve(message.result);
    }
    if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
});
function send(method, params = {}) {
    return new Promise((resolve, reject) => { const id = ++nextId; pending.set(id, { resolve, reject }); ws.send(JSON.stringify({ id, method, params })); });
}
async function evaluate(expression) {
    const response = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (response.exceptionDetails) throw new Error(JSON.stringify(response.exceptionDetails));
    return response.result.value;
}
async function until(expression) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (await evaluate(expression)) return;
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    throw new Error(`Timed out: ${expression}`);
}
async function navigate(path, ready) {
    await send('Page.navigate', { url: `http://127.0.0.1:8000${path}` });
    await until(`document.readyState === 'complete' && !!document.querySelector(${JSON.stringify(ready)})`);
    await new Promise(resolve => setTimeout(resolve, 300));
}

try {
    await send('Page.enable');
    await send('Runtime.enable');
    await send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 900, deviceScaleFactor: 1, mobile: false });
    await navigate('/students', '.student-table');
    assert.equal(await evaluate('document.characterSet'), 'UTF-8');
    assert.equal(await evaluate('document.querySelectorAll(".student-filters select").length'), 6);
    assert.equal(await evaluate('document.querySelectorAll("#students-submenu a").length'), 3);
    assert.equal(await evaluate('document.querySelector("#students-submenu .active").textContent.trim()'), 'All Students');
    assert.equal(await evaluate('document.querySelector("[data-nav-toggle]").getAttribute("aria-expanded")'), 'true');
    await evaluate('document.querySelector("[data-nav-toggle]").click()');
    assert.equal(await evaluate('document.querySelector("#students-submenu").inert'), true);
    await evaluate('document.querySelector("[data-nav-toggle]").click()');
    assert.equal(await evaluate('document.querySelector("#students-submenu").inert'), false);
    assert.equal(await evaluate('document.querySelector("#filter-status").value'), 'Active');
    assert.equal(await evaluate('document.querySelector("#student-bulk-form").hidden'), true);
    assert.equal(await evaluate('new URL(document.querySelector(".page-heading a.button-primary").href).pathname'), '/admissions/new');
    const profileUrl = await evaluate('new URL(document.querySelector(".student-name").href).pathname');
    const editUrl = await evaluate('new URL(document.querySelector(".action-edit").href).pathname');
    assert.ok(await evaluate('document.querySelector(".student-avatar").textContent.trim().length > 0'));
    await evaluate('document.querySelector("[data-sort=name]").click()');
    await until('location.search.includes("sort=name") && !document.querySelector(".student-panel").hasAttribute("aria-busy")');
    assert.equal(await evaluate('document.querySelector("[data-sort=name]").closest("th").getAttribute("aria-sort")'), 'ascending');
    await evaluate('document.querySelector("[data-sort=name]").click()');
    await until('location.search.includes("direction=desc") && !document.querySelector(".student-panel").hasAttribute("aria-busy")');
    await evaluate('const input=document.querySelector("#student-search"); input.value="Ali"; input.dispatchEvent(new Event("input",{bubbles:true})); input.value="no-student-zzzz"; input.dispatchEvent(new Event("input",{bubbles:true}));');
    await until('location.search.includes("no-student-zzzz") && document.querySelector(".students-empty")');
    assert.equal(await evaluate('document.querySelector(".students-empty h3").textContent'), 'No matching students');
    assert.ok(await evaluate('document.querySelector("[data-export=xlsx]").href.includes("no-student-zzzz")'));
    await evaluate('document.querySelector("#student-search").value=""; document.querySelector("#student-search").dispatchEvent(new Event("input",{bubbles:true}));');
    await until('!!document.querySelector("[data-student-select]") && !location.search.includes("no-student-zzzz")');
    await evaluate('document.querySelector("#per-page").value="25"; document.querySelector("#per-page").dispatchEvent(new Event("change",{bubbles:true}));');
    await until('location.search.includes("per_page=25") && !document.querySelector(".student-panel").hasAttribute("aria-busy")');
    await evaluate('document.querySelector("[data-select-all]").click(); document.querySelector("#bulk-action").value="id_cards"; document.querySelector("#bulk-action").dispatchEvent(new Event("change",{bubbles:true}));');
    assert.equal(await evaluate('document.querySelector("#student-bulk-form").hidden'), false);
    assert.equal(await evaluate('document.querySelector("#bulk-submit").disabled'), false);
    await evaluate('document.querySelector("[data-select-all]").click();');
    assert.equal(await evaluate('document.querySelector("#student-bulk-form").hidden'), true);
    await evaluate('document.querySelector("#filter-status").value=""; document.querySelector("#filter-status").dispatchEvent(new Event("change",{bubbles:true}));');
    await until('new URLSearchParams(location.search).has("status") && new URLSearchParams(location.search).get("status") === "" && !document.querySelector(".student-panel").hasAttribute("aria-busy")');
    assert.equal(await evaluate('new URL(document.querySelector("[data-export=xlsx]").href).searchParams.get("status")'), '');
    await evaluate('document.querySelector("[data-delete-student]").click();');
    assert.equal(await evaluate('document.querySelector("#delete-student-dialog").open'), true);
    await evaluate('document.querySelector("#cancel-student-delete").click();');
    for (const width of [390, 320]) {
        await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
        assert.equal(await evaluate('document.documentElement.scrollWidth > document.documentElement.clientWidth'), false, `Directory overflow at ${width}`);
    }
    await navigate(editUrl, '#student-photo-placeholder');
    assert.equal(await evaluate('document.querySelector("#student-photo-placeholder").hidden'), false);
    assert.ok(await evaluate('document.querySelector("#student-photo-placeholder").textContent.trim().length > 0'));
    assert.equal(await evaluate('document.body.textContent.includes("Â·")'), false);
    assert.match(await evaluate('document.querySelector("#admission_date").value'), /^\d{2}-\d{2}-\d{4}$/);
    await navigate('/admissions/new', '#admission_no');
    assert.equal(await evaluate('document.querySelector("#students-submenu .active").textContent.trim()'), 'Admissions');
    assert.equal(await evaluate('document.querySelector("#admission_no").readOnly'), true);
    await evaluate('document.querySelector("#first_name").value="Ali"; document.querySelector("#last_name").value="Hassan"; document.querySelector("#first_name").dispatchEvent(new Event("input",{bubbles:true}));');
    assert.equal(await evaluate('document.querySelector("#student-photo-placeholder").textContent'), 'AH');
    await navigate(profileUrl, '[data-profile-tab]');
    assert.equal(await evaluate('document.querySelectorAll("[data-profile-tab]").length'), 7);
    await evaluate('document.querySelector("[data-profile-tab=family]").click()');
    assert.equal(await evaluate('document.querySelector("#family").hidden'), false);
    assert.equal(await evaluate('document.querySelectorAll(".page-heading .dropdown-menu a").length'), 3);
    await evaluate('document.querySelector("[data-profile-tab=documents]").click();');
    assert.equal(await evaluate('document.querySelector("#documents").hidden'), false);
    assert.equal(await evaluate('document.querySelector("#overview").hidden'), true);
    await evaluate('document.querySelector("[data-profile-tab=fees]").click();');
    assert.equal(await evaluate('document.querySelector("#fees").hidden'), false);
    assert.equal(await evaluate('document.documentElement.scrollWidth > document.documentElement.clientWidth'), false);
    await navigate('/admissions', '[name="applicant_name"]');
    assert.equal(await evaluate('document.querySelector("#students-submenu .active").textContent.trim()'), 'Admissions');
    await navigate('/admissions/register', '#register-from');
    await navigate('/promotion', '#promotion-workflow-form');
    assert.equal(await evaluate('document.querySelector("#students-submenu .active").textContent.trim()'), 'Promotion');
    if (await evaluate('!!document.querySelector("[data-student-select]")')) {
        await evaluate('document.querySelector("[data-student-select]").click(); const decision=document.querySelector("[data-promotion-decision]:not(:disabled)"); decision.value="left"; decision.dispatchEvent(new Event("change",{bubbles:true})); document.querySelector("#promotion-workflow-form").requestSubmit();');
        assert.equal(await evaluate('!!document.querySelector("dialog[open] [data-confirm-message]")'), true);
        await evaluate('document.querySelector("[data-confirm-cancel]").click()');
    }
    assert.equal(await evaluate('document.documentElement.scrollWidth > document.documentElement.clientWidth'), false);
    await navigate('/promotion/history', '#history-action');
    await navigate('/students/import', '#import-file');
    assert.deepEqual(errors, []);
    await send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 900, deviceScaleFactor: 1, mobile: false });
    await navigate('/students', '.student-table');
    const screenshot = await send('Page.captureScreenshot', { format: 'png' });
    await writeFile('storage/app/students-module-desktop.png', Buffer.from(screenshot.data, 'base64'));
    console.log('PASS: three-child sidebar, accordion, active navigation, conditional bulk toolbar, default/all status, live search, sorting, exports, initials, seven profile tabs, admissions/register, promotion confirmation/history and mobile layout.');
} finally {
    ws.close();
}
