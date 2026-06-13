/* Paste area */
const source = document.body.dataset.name || location.host;
const pasteArea = document.getElementById('paste-text');
const pastePlaceholder = document.querySelector('.paste-placeholder');
const pasteSaveButtons = document.querySelectorAll('.paste-save');
const fileSelectButton = document.getElementById('paste-select-file');
const pasteClipboardButton = document.getElementById('paste-clipboard');
const pasteError = document.getElementById('paste-error');
let errorDismissTimer = null;

/*
 * Large-paste buffering. <textarea> rendering is the bottleneck once content
 * crosses a few hundred KB; the browser locks up while it lays out millions
 * of characters. For pastes / file loads above the threshold, we hold the
 * full content in `bufferedContent` and only render a small preview into the
 * textarea. The save path uses `bufferedContent` when set.
 */
const PASTE_BUFFER_THRESHOLD_BYTES = 256 * 1024; // 256 KB
const PASTE_PREVIEW_LINES = 50;
let bufferedContent = null;

pasteArea.focus();
pasteArea.addEventListener('input', () => {
    // User-typed input invalidates the buffer (the textarea is now the
    // source of truth). The buffer is only set programmatically, so a
    // user-driven `input` event always means edit-from-preview.
    if (bufferedContent !== null) {
        clearBuffer();
    }
    reevaluateContentStatus();
});
pasteArea.addEventListener('paste', handlePasteEvent);
pasteSaveButtons.forEach(button => button.addEventListener('click', sendLog));
fileSelectButton.addEventListener('click', selectLogFile);
pasteClipboardButton.addEventListener('click', pasteFromClipboard);

reevaluateContentStatus();

document.addEventListener('keydown', event => {
    if (event.key.toLowerCase() === 's' && (event.ctrlKey || event.metaKey)) {
        void sendLog();
        event.preventDefault();
        return false;
    }

    return true;
});

/**
 * Save the log to the API
 * @returns {Promise<void>}
 */
async function sendLog() {
    if (pasteArea.value === "") {
        return;
    }

    clearError();
    pasteSaveButtons.forEach(button => {
        button.classList.add("btn-working");
        button.disabled = true;
        button.dataset.originalText = button.textContent.trim();
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving\u2026';
    });

    try {
        let log = bufferedContent ?? pasteArea.value;
        log = applyFilters(log);

        const bodyData = {
            "content": log,
            "source": source,
            "metadata": Array.isArray(self.METADATA) ? self.METADATA : []
        };

        let headers = {
            "Content-Type": "application/json"
        }

        let body = JSON.stringify(bodyData);
        if (isGzSupported()) {
            headers["Content-Encoding"] = "gzip";
            body = await packGz(body);
        }

        const response = await fetch(`/new`, {
            method: "POST",
            credentials: "include",
            headers: {
                "Content-Type": "application/json",
                "Content-Encoding": "gzip"
            },
            body
        });

        if (!response.ok) {
            showError(`${response.status} (${response.statusText})`);
            return;
        }

        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            console.error("Failed to parse JSON returned by API", e);
            showError("API returned invalid JSON");
            return;
        }

        if (typeof data === 'object' && !data.success && data.error) {
            console.error(new Error("API returned an error"), data.error);
            showError(data.error);
            return;
        }

        if (typeof data !== 'object' || !data.success || !data.id) {
            console.error(new Error("API returned an invalid response"), data);
            showError("API returned an invalid response");
            return;
        }

        location.href = data.url;
    } catch (e) {
        showError("Network error");
    }
}

/* filters */
function applyFilters(text) {
    if (typeof FILTERS === "undefined" || !Array.isArray(FILTERS)) {
        return text;
    }
    for (let filter of FILTERS) {
        text = applyFilter(text, filter);
    }
    return text;
}

function applyFilter(text, filter) {
    switch (filter.type) {
        case 'trim':
            return text.trim();
        case 'limit-bytes':
            return text.substring(0, filter.data.limit);
        case 'limit-lines':
            return text.split('\n').slice(0, filter.data.limit).join('\n');
        case 'regex':
            try {
                for (const pattern of filter.data.patterns) {
                    const regex = new RegExp(pattern.pattern, 'g' + pattern.modifiers.join());
                    text = text.replace(regex, (match) => {
                        for (const exemption of filter.data.exemptions) {
                            if (new RegExp(exemption.pattern, exemption.modifiers.join()).test(match)) {
                                return match;
                            }
                        }
                        return pattern.replacement;
                    });
                }
            } catch (e) {
                console.error('Error applying regex filter', e);
            }
            return text;
        default:
            console.error('Unknown filter type', filter.type);
            return text;
    }
}

async function pasteFromClipboard() {
    try {
        let content = await navigator.clipboard.readText();
        if (!content || content.trim().length === 0) {
            showError("Clipboard is empty.");
            return;
        }
        loadContent(content);
    } catch (err) {
        showError("Clipboard is empty or not accessible.");
    }
}

/*
 * Single entry point for "place this string in the editor."
 * Routes large content into the buffer + preview; small content goes into
 * the textarea normally so the user can keep editing it.
 */
function loadContent(text) {
    if (text.length > PASTE_BUFFER_THRESHOLD_BYTES) {
        loadIntoBuffer(text);
    } else {
        clearBuffer();
        pasteArea.value = text;
        reevaluateContentStatus();
    }
}

function loadIntoBuffer(text) {
    bufferedContent = text;
    const lines = text.split('\n');
    const sizeMb = (text.length / 1024 / 1024).toFixed(2);
    if (lines.length <= PASTE_PREVIEW_LINES) {
        pasteArea.value = text;
    } else {
        const preview = lines.slice(0, PASTE_PREVIEW_LINES).join('\n');
        const remaining = lines.length - PASTE_PREVIEW_LINES;
        pasteArea.value =
            `[Large paste buffered: ${lines.length.toLocaleString()} lines, ${sizeMb} MB.\n` +
            ` Full content uploads on Save. Edit this textarea to clear the buffer.]\n` +
            `\n` +
            `--- preview: first ${PASTE_PREVIEW_LINES} of ${lines.length.toLocaleString()} lines ---\n` +
            preview +
            `\n--- ${remaining.toLocaleString()} more lines hidden ---\n`;
    }
    pasteArea.readOnly = true;
    reevaluateContentStatus();
}

function clearBuffer() {
    if (bufferedContent === null) {
        return;
    }
    bufferedContent = null;
    pasteArea.readOnly = false;
}

function reevaluateContentStatus() {
    clearError();
    if (pasteArea.value.length > 0) {
        pastePlaceholder.style.display = 'none';
        pasteSaveButtons.forEach(button => button.removeAttribute("disabled"));
    } else {
        pastePlaceholder.style.display = 'flex';
        pasteSaveButtons.forEach(button => button.setAttribute("disabled", "disabled"));
    }
}

function showError(message) {
    pasteSaveButtons.forEach(button => {
        button.classList.remove("btn-working");
        button.disabled = false;
        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
            delete button.dataset.originalText;
        }
    });
    pasteError.innerText = message;
    pasteError.style.display = 'block';
    if (errorDismissTimer) clearTimeout(errorDismissTimer);
    errorDismissTimer = setTimeout(clearError, 5000);
}

function clearError() {
    pasteSaveButtons.forEach(button => {
        button.classList.remove("btn-working");
        button.disabled = false;
        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
            delete button.dataset.originalText;
        }
    });
    pasteError.innerText = '';
    pasteError.style.display = 'none';
    if (errorDismissTimer) { clearTimeout(errorDismissTimer); errorDismissTimer = null; }
}

/* File handling */
async function handlePasteEvent(e) {
    if (e.clipboardData?.files?.length > 0) {
        e.preventDefault();
        await loadFileContents(e.clipboardData.files[0]);
        return;
    }
    // Intercept large text pastes before the browser commits them to the
    // textarea — assigning multi-MB strings to a <textarea> freezes the page.
    const text = e.clipboardData?.getData('text');
    if (text && text.length > PASTE_BUFFER_THRESHOLD_BYTES) {
        e.preventDefault();
        loadIntoBuffer(text);
    }
}

/**
 * @param {Blob} file
 * @return {Promise<Uint8Array>}
 */
function readFile(file) {
    return new Promise((resolve, reject) => {
        let reader = new FileReader();
        // noinspection JSCheckFunctionSignatures
        reader.onload = () => resolve(new Uint8Array(reader.result));
        reader.onerror = e => reject(e);
        reader.readAsArrayBuffer(file);
    });
}

async function loadFileContents(file) {
    if (file.size > 1024 * 1024 * 100) {
        showError(`File is too large.`);
        return;
    }
    let content = await readFile(file);
    if (file.name.endsWith('.gz')) {
        if (!isGzSupported()) {
            showError(`Gzip files are not supported in this browser.`);
            return;
        }
        content = await unpackGz(content);
    }

    if (content.includes(0)) {
        showError(`This file is not supported.`);
        return;
    }

    loadContent(new TextDecoder().decode(content));
}

function selectLogFile() {
    let input = document.createElement('input');
    input.type = 'file';
    input.style.display = 'none';
    document.body.appendChild(input);
    input.onchange = async () => {
        if (input.files.length) {
            await loadFileContents(input.files[0]);
        }
    }
    input.click();
    document.body.removeChild(input);
}

/* Gzip compression */
function isGzSupported() {
    return (typeof CompressionStream !== 'undefined') && (typeof DecompressionStream !== 'undefined');
}

/**
 * @param {string} raw
 * @returns {Promise<Uint8Array>}
 */
async function packGz(raw) {
    let data = new TextEncoder().encode(raw);
    let inputStream = new ReadableStream({
        start: (controller) => {
            controller.enqueue(data);
            controller.close();
        }
    });
    const cs = new CompressionStream('gzip');
    const compressedStream = inputStream.pipeThrough(cs);
    return new Uint8Array(await new Response(compressedStream).arrayBuffer());
}

/**
 * @param {Uint8Array} data
 * @return {Promise<Uint8Array>}
 */
async function unpackGz(data) {
    let inputStream = new ReadableStream({
        start: (controller) => {
            controller.enqueue(data);
            controller.close();
        }
    });
    const ds = new DecompressionStream('gzip');
    const decompressedStream = inputStream.pipeThrough(ds);
    return new Uint8Array(await new Response(decompressedStream).arrayBuffer());
}

function isDragEventValid(e) {
    if (!e.dataTransfer) {
        return false;
    }
    let types = Array.from(e.dataTransfer.types);
    if (types.includes('text/uri-list')) {
        return false;
    }
    return types.includes('Files') || types.includes('text/plain');
}

/* Drag and drop */
const dropZone = document.getElementById('dropzone');
let windowDragCount = 0;
let dropZoneDragCount = 0;

window.addEventListener('dragover', e => e.preventDefault());
window.addEventListener('dragenter', e => {
    e.preventDefault();
    if (isDragEventValid(e)) {
        updateWindowDragCount(1);
    }
});
window.addEventListener('dragleave', e => {
    e.preventDefault();
    if (isDragEventValid(e)) {
        updateWindowDragCount(-1);
    }
});
window.addEventListener('drop', e => {
    e.preventDefault();
    if (isDragEventValid(e)) {
        updateWindowDragCount(-1);
    }
});

dropZone.addEventListener('dragenter', e => {
    e.preventDefault();
    if (isDragEventValid(e)) {
        updateDropZoneDragCount(1);
    }
});
dropZone.addEventListener('dragleave', e => {
    e.preventDefault();
    if (isDragEventValid(e)) {
        updateDropZoneDragCount(-1);
    }
});
dropZone.addEventListener('drop', async e => {
    e.preventDefault();
    if (isDragEventValid(e)) {
        updateDropZoneDragCount(-1);
    }
    await handleDropEvent(e);
});

function updateWindowDragCount(amount) {
    windowDragCount = Math.max(0, windowDragCount + amount);
    if (windowDragCount > 0) {
        dropZone.classList.add('window-dragover');
    } else {
        dropZone.classList.remove('window-dragover');
    }
}

function updateDropZoneDragCount(amount) {
    dropZoneDragCount = Math.max(0, dropZoneDragCount + amount);
    if (dropZoneDragCount > 0) {
        dropZone.classList.add('dragover');
    } else {
        dropZone.classList.remove('dragover');
    }
}

async function handleDropEvent(e) {
    console.log(e.dataTransfer?.types);
    let files = e.dataTransfer.files;
    if (files.length !== 1) {
        if (Array.from(e.dataTransfer.types).includes('text/plain')) {
            loadContent(e.dataTransfer.getData('text/plain'));
        }
        return;
    }

    await loadFileContents(files[0]);
}
