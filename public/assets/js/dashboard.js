console.log('>>>> dashboard.js HAS LOADED <<<<');

const sessionToken = localStorage.getItem('session_token');
if (!sessionToken) {
    window.location.href = 'index.html';
}

// --- Standardized Fetch Function ---
async function fetchApi(url, options = {}) {
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${sessionToken}`,
        ...options.headers,
    };

    const response = await fetch(url, { ...options, headers });

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({ error: 'Invalid JSON response from server.' }));
        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
    }

    return response.json();
}


document.addEventListener('DOMContentLoaded', () => {
    const chatHistoryDiv = document.getElementById('chatHistory');
    const sendButton = document.getElementById('sendButton');
    const typingIndicator = document.getElementById('typingIndicator');
    const apiProviderSelect = document.getElementById('apiProvider');
    const deepseekKeyInput = document.getElementById('deepseekKey');
    const openAIKeyInput = document.getElementById('openAIKey');
    const geminiKeyInput = document.getElementById('geminiKey');
    const apiKeyAlert = document.getElementById('apiKeyAlert');
    const saveKeysButton = document.getElementById('saveKeysButton');
    const userInput = document.getElementById('userInput');
    const apiSettingsModalEl = document.getElementById('apiSettingsModal');
    const apiSettingsModal = new bootstrap.Modal(apiSettingsModalEl);
    const timerEl = document.getElementById('timer');
    const relationshipEl = document.getElementById('relationship');
    const nyxFormEl = document.getElementById('nyxForm');
    const nyxCharacterEl = document.getElementById('nyxCharacter');
    const showOlderMessagesButton = document.createElement('button');

    let currentHistoryOffset = 0;
    const historyLimitPerPage = 10;

    // === Functions ===
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
        const secs = (seconds % 60).toString().padStart(2, '0');
        return `${mins}:${secs}`;
    }

    function formatMessageText(text) {
        let sanitizedText = text.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        return sanitizedText.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    }

    function appendMessage(speaker, text, prepend = false) {
        const div = document.createElement('div');
        let bubbleClass = 'narrator-bubble'; // Default
        if (speaker.toLowerCase() === 'nyx') bubbleClass = 'nyx-bubble';
        if (speaker.toLowerCase() === 'you') bubbleClass = 'user-bubble';
        if (speaker.toLowerCase() === 'system') bubbleClass = 'narrator-bubble text-danger';


        div.className = `chat-bubble ${bubbleClass}`;
        div.innerHTML = `
            <div class="speaker-name">${speaker}</div>
            <div class="message-text">${formatMessageText(text)}</div>
        `;
        if (prepend) {
            const button = chatHistoryDiv.querySelector('#showOlderMessages');
            if (button) {
                button.after(div);
            } else {
                chatHistoryDiv.prepend(div);
            }
        } else {
            chatHistoryDiv.appendChild(div);
        }
    }

    async function loadChatHistory(offset, limit, prepend = false) {
        try {
            const data = await fetchApi('/Nyxara2/api/chat/get_history.php', {
                method: 'POST',
                body: JSON.stringify({ offset, limit })
            });

            if (data.success && data.history) {
                if (data.history.length < limit) {
                    showOlderMessagesButton.style.display = 'none';
                } else {
                    showOlderMessagesButton.style.display = 'block';
                }

                if (prepend) {
                    data.history.reverse().forEach(msg => appendMessage(msg.speaker, msg.text, true));
                } else {
                    data.history.forEach(msg => appendMessage(msg.speaker, msg.text));
                    chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
                }
                currentHistoryOffset += data.history.length;
            }
        } catch (e) {
            console.error('Error loading chat history:', e);
            appendMessage('System', `Error: ${e.message}`, !prepend);
        }
    }

    async function saveApiKeys() {
        const originalButtonText = saveKeysButton.innerHTML;
        saveKeysButton.disabled = true;
        saveKeysButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

        try {
            await fetchApi('/Nyxara2/api/user/save_api_keys.php', {
                method: 'POST',
                body: JSON.stringify({
                    keys: {
                        deepseek: deepseekKeyInput.value,
                        openai: openAIKeyInput.value,
                        gemini: geminiKeyInput.value
                    }
                })
            });

            apiKeyAlert.className = 'alert alert-success';
            apiKeyAlert.textContent = 'API keys saved successfully!';
            apiKeyAlert.style.display = 'block';

            setTimeout(() => {
                apiSettingsModal.hide();
            }, 1500);

        } catch (error) {
            apiKeyAlert.className = 'alert alert-danger';
            apiKeyAlert.textContent = 'Error: ' + error.message;
            apiKeyAlert.style.display = 'block';
        } finally {
            saveKeysButton.disabled = false;
            saveKeysButton.innerHTML = originalButtonText;
            setTimeout(() => {
                apiKeyAlert.style.display = 'none';
            }, 4000);
        }
    }

    async function sendMessage() {
        const messageText = userInput.value;
        if (!messageText.trim()) return;

        sendButton.disabled = true;
        sendButton.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processing...';
        typingIndicator.style.display = 'block';

        appendMessage('You', messageText);
        userInput.value = '';
        userInput.style.height = 'auto';
        chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;

        try {
            const data = await fetchApi('/Nyxara2/api/chat/send.php', {
                method: 'POST',
                body: JSON.stringify({
                    input: messageText,
                    provider: apiProviderSelect.value
                })
            });

            if (data.dialogue) {
                data.dialogue.forEach(msg => appendMessage(msg.speaker, msg.text));
            }

            if (data.context) {
                timerEl.textContent = formatTime(data.context.time_remaining);
                relationshipEl.textContent = data.context.relationship_score;
                const formName = data.context.nyx_form.split('_')[0];
                nyxFormEl.textContent = formName.charAt(0).toUpperCase() + formName.slice(1);
                nyxCharacterEl.src = `assets/nyx/${data.context.nyx_form}.png`;
            }

        } catch (error) {
            console.error('Error details:', error);
            appendMessage('System', `Error: ${error.message}`);
        } finally {
            sendButton.disabled = false;
            sendButton.innerHTML = '<i class="bi bi-send-fill me-2"></i>Act';
            typingIndicator.style.display = 'none';
            chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
        }
    }

    timerEl.textContent = formatTime(300);
    relationshipEl.textContent = '0';
    nyxFormEl.textContent = 'Human';

    showOlderMessagesButton.id = 'showOlderMessages';
    showOlderMessagesButton.textContent = 'Show Older Messages';
    showOlderMessagesButton.className = 'btn btn-sm btn-outline-secondary w-100 mb-3';
    showOlderMessagesButton.style.display = 'none';
    chatHistoryDiv.prepend(showOlderMessagesButton);
    showOlderMessagesButton.addEventListener('click', () => {
        loadChatHistory(currentHistoryOffset, historyLimitPerPage, true)
    });

    loadChatHistory(currentHistoryOffset, historyLimitPerPage, false);

    sendButton.addEventListener('click', sendMessage);
    saveKeysButton.addEventListener('click', saveApiKeys);

    userInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    userInput.addEventListener('input', () => {
        userInput.style.height = 'auto';
        userInput.style.height = userInput.scrollHeight + 'px';
    });

    apiSettingsModalEl.addEventListener('show.bs.modal', async () => {
        apiKeyAlert.style.display = 'none';
        try {
            const data = await fetchApi('/Nyxara2/api/user/get_api_keys.php', { method: 'POST' });
            if (data.success && data.keys) {
                deepseekKeyInput.value = data.keys.deepseek || '';
                openAIKeyInput.value = data.keys.openai || '';
                geminiKeyInput.value = data.keys.gemini || '';
            }
        } catch (e) {
            console.error(e);
        }
    });
});