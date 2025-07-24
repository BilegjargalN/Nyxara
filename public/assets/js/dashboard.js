const sessionToken = localStorage.getItem('session_token');
if (!sessionToken) {
    window.location.href = 'index.html';
}

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
    const elements = {
        loadingOverlay: document.getElementById('loadingOverlay'),
        apiProvider: document.getElementById('apiProvider'),
        timer: document.getElementById('timer'),
        relationship: document.getElementById('relationship'),
        nyxCharacter: document.getElementById('nyxCharacter'),
        speakerName: document.getElementById('speakerName'),
        messageText: document.getElementById('messageText'),
        continueBtn: document.getElementById('continueBtn'),
        choicesSidebar: document.getElementById('choicesSidebar'),
        choiceList: document.getElementById('choiceList'),
        customResponseBtn: document.getElementById('customResponseBtn'),
        customInputArea: document.getElementById('customInputArea'),
        userInput: document.getElementById('userInput'),
        sendCustomBtn: document.getElementById('sendCustomBtn'),
        apiSettingsModalEl: document.getElementById('apiSettingsModal'),
        historyModalEl: document.getElementById('historyModal'),
        historyModalBody: document.getElementById('historyModalBody'),
        deepseekKeyInput: document.getElementById('deepseekKey'),
        openAIKeyInput: document.getElementById('openAIKey'),
        geminiKeyInput: document.getElementById('geminiKey'),
        apiKeyAlert: document.getElementById('apiKeyAlert'),
        saveKeysButton: document.getElementById('saveKeysButton'),
    };
    
    const apiSettingsModal = new bootstrap.Modal(elements.apiSettingsModalEl);
    const historyModal = new bootstrap.Modal(elements.historyModalEl);

    let dialogueQueue = [];
    let currentDialogueIndex = 0;
    let isProcessing = false;
    let lastApiResponse = {};

    function typeWriter(text, element, callback) {
        element.innerHTML = text.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        if (callback) callback();
    }

    function updateCharacter(context) {
        if (!context) return;
        const newSrc = `assets/nyx/${context.nyx_form}.png`;
        if (elements.nyxCharacter.src !== newSrc) {
            elements.nyxCharacter.style.opacity = 0;
            setTimeout(() => {
                elements.nyxCharacter.src = newSrc;
                elements.nyxCharacter.style.opacity = 1;
            }, 300);
        }
    }

    function updateHUD(context) {
        if (!context) return;
        elements.timer.textContent = formatTime(context.time_remaining);
        elements.relationship.textContent = context.relationship_score;
    }

    function processDialogueQueue() {
        elements.continueBtn.style.display = 'none';
        if (currentDialogueIndex >= dialogueQueue.length) {
            if (lastApiResponse.choices) {
                displayChoices(lastApiResponse.choices);
            }
            return;
        }
        const currentItem = dialogueQueue[currentDialogueIndex];
        elements.speakerName.textContent = currentItem.speaker;
        typeWriter(currentItem.text, elements.messageText, () => {
            elements.continueBtn.style.display = 'block';
        });
        currentDialogueIndex++;
    }

    function displayChoices(choices) {
        elements.choiceList.innerHTML = '';
        if (choices && choices.length > 0) {
            choices.forEach(choice => {
                const button = document.createElement('button');
                button.className = 'choice-btn';
                button.textContent = choice.text;
                button.onclick = () => onChoiceClick(choice.text);
                elements.choiceList.appendChild(button);
            });
        }
        elements.choicesSidebar.style.display = 'flex';
    }

    async function sendMessage(messageText) {
        if (!messageText.trim() || isProcessing) return;
        isProcessing = true;
        elements.choicesSidebar.style.display = 'none';
        elements.customInputArea.style.display = 'none';
        elements.speakerName.textContent = '';
        elements.messageText.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';

        try {
            const data = await fetchApi('/NyxaraCopy/api/chat/send.php', {
                method: 'POST',
                body: JSON.stringify({ input: messageText, provider: elements.apiProvider.value })
            });
            lastApiResponse = data;
            updateHUD(data.context);
            updateCharacter(data.context);
            if (data.dialogue && data.dialogue.length > 0) {
                dialogueQueue = data.dialogue;
                currentDialogueIndex = 0;
                processDialogueQueue();
            } else {
                showError('No dialogue received from the server.');
            }
        } catch (error) {
            showError(error.message);
        } finally {
            isProcessing = false;
        }
    }
    
    function showError(message) {
        elements.speakerName.textContent = 'System Error';
        elements.messageText.textContent = message;
        elements.choicesSidebar.style.display = 'flex';
    }

    async function saveApiKeys() {
        const originalButtonText = elements.saveKeysButton.innerHTML;
        elements.saveKeysButton.disabled = true;
        elements.saveKeysButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

        try {
            await fetchApi('/NyxaraCopy/api/user/save_api_keys.php', {
                method: 'POST',
                body: JSON.stringify({
                    keys: {
                        deepseek: elements.deepseekKeyInput.value,
                        openai: elements.openAIKeyInput.value,
                        gemini: elements.geminiKeyInput.value
                    }
                })
            });

            elements.apiKeyAlert.className = 'alert alert-success';
            elements.apiKeyAlert.textContent = 'API keys saved successfully!';
            elements.apiKeyAlert.style.display = 'block';

            setTimeout(() => {
                apiSettingsModal.hide();
            }, 1500);

        } catch (error) {
            elements.apiKeyAlert.className = 'alert alert-danger';
            elements.apiKeyAlert.textContent = 'Error: ' + error.message;
            elements.apiKeyAlert.style.display = 'block';
        } finally {
            elements.saveKeysButton.disabled = false;
            elements.saveKeysButton.innerHTML = originalButtonText;
            setTimeout(() => {
                elements.apiKeyAlert.style.display = 'none';
            }, 4000);
        }
    }

    function onChoiceClick(choiceText) {
        sendMessage(choiceText);
    }

    elements.continueBtn.addEventListener('click', () => processDialogueQueue());
    elements.customResponseBtn.addEventListener('click', () => {
        elements.customInputArea.style.display = 'flex';
        elements.userInput.focus();
    });
    elements.sendCustomBtn.addEventListener('click', () => {
        sendMessage(elements.userInput.value);
        elements.userInput.value = '';
    });
    elements.userInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(elements.userInput.value);
            elements.userInput.value = '';
        }
    });

    elements.saveKeysButton.addEventListener('click', saveApiKeys);

    elements.apiSettingsModalEl.addEventListener('show.bs.modal', async () => {
        elements.apiKeyAlert.style.display = 'none';
        try {
            const data = await fetchApi('/NyxaraCopy/api/user/get_api_keys.php');
            if (data.success && data.keys) {
                elements.deepseekKeyInput.value = data.keys.deepseek || '';
                elements.openAIKeyInput.value = data.keys.openai || '';
                elements.geminiKeyInput.value = data.keys.gemini || '';
            }
        } catch (e) {
            elements.apiKeyAlert.className = 'alert alert-danger';
            elements.apiKeyAlert.textContent = `Error loading keys: ${e.message}`;
            elements.apiKeyAlert.style.display = 'block';
        }
    });

    elements.historyModalEl.addEventListener('show.bs.modal', async () => {
        elements.historyModalBody.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        try {
            const data = await fetchApi('/NyxaraCopy/api/chat/get_history.php', { method: 'POST' });
            if (data.success && data.history) {
                renderHistory(data.history);
            } else {
                elements.historyModalBody.textContent = 'Could not load history.';
            }
        } catch (e) {
            elements.historyModalBody.textContent = `Error: ${e.message}`;
        }
    });

    function renderHistory(history) {
        elements.historyModalBody.innerHTML = '';
        if (history.length === 0) {
            elements.historyModalBody.textContent = 'No history yet.';
            return;
        }
        history.slice().reverse().forEach(item => {
            const div = document.createElement('div');
            div.className = 'history-item';
            let content = `<p class="history-speaker-you">You: ${item.input}</p>`;
            try {
                const response = JSON.parse(item.response);
                if(response.dialogue) {
                    response.dialogue.forEach(d => {
                        content += `<p class="history-speaker-${d.speaker.toLowerCase()}">${d.speaker}: ${d.text}</p>`;
                    });
                }
            } catch (e) {
                content += `<p class="history-speaker-narrator">Narrator: (An old memory fades...)</p>`;
            }
            div.innerHTML = content;
            elements.historyModalBody.appendChild(div);
        });
    }

    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
        const secs = (seconds % 60).toString().padStart(2, '0');
        return `${mins}:${secs}`;
    }

    async function initializeGame() {
        try {
            const initialState = await fetchApi('/NyxaraCopy/api/user/get_initial_state.php');
            if (initialState.has_history) {
                lastApiResponse = { choices: initialState.last_choices };
                updateHUD(initialState.context);
                updateCharacter(initialState.context);
                dialogueQueue = initialState.last_dialogue;
                currentDialogueIndex = 0;
                processDialogueQueue();
            } else {
                startNewGame();
            }
        } catch (error) {
            showError(error.message);
        } finally {
            elements.loadingOverlay.style.display = 'none';
        }
    }

    function startNewGame() {
        elements.speakerName.textContent = 'Narrator';
        elements.messageText.textContent = 'The cold stone floor sends a shiver through you as you awaken in the oppressive dark of a dungeon cell. Your head throbs. How did you get here?';
        displayChoices([
            { text: "Look around the cell." },
            { text: "Check myself for injuries." },
            { text: "Shout to see if anyone is there." }
        ]);
    }

    initializeGame();
});