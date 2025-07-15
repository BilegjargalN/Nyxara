const alertBox = document.getElementById('alertBox');

function showAlert(message) {
    alertBox.textContent = message;
    alertBox.style.display = 'block';
    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 5000);
}

function toggleSpinner(spinnerId, show) {
    document.getElementById(spinnerId).style.display = show ? 'inline-block' : 'none';
}

async function login() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    alertBox.style.display = 'none';
    
    if (!username || !password) {
        showAlert('Username and password are required.');
        return;
    }

    toggleSpinner('loginSpinner', true);

    try {
        const response = await fetch('/Nyxara2/api/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Server responded with ${response.status}: ${errorText}`);
        }

        const data = await response.json();

        if (data.success) {
            localStorage.setItem('session_token', data.session_token);
            window.location.href = 'dashboard.html';
        } else {
            showAlert(data.error || 'Login failed.');
        }
    } catch (error) {
        console.error('Login error:', error);
        showAlert('A network error occurred. Please try again.');
    } finally {
        toggleSpinner('loginSpinner', false);
    }
}

async function register() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    alertBox.style.display = 'none';

    if (!username || !password) {
        showAlert('Username and password are required.');
        return;
    }

    toggleSpinner('registerSpinner', true);

    try {
        const response = await fetch('/Nyxara2/api/auth/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        
        if (!response.ok) {
            let errorMessage = 'Registration failed.';
            try {
                const errorData = await response.json();
                if (errorData.error) {
                    errorMessage = errorData.error;
                }
            } catch (e) {
                // If response is not JSON, use generic message
                errorMessage = `Server responded with ${response.status}.`;
            }
            showAlert(errorMessage);
            return; // Stop execution here if there's an error
        }

        const data = await response.json();
        if (data.success) {
            showAlert('Character created successfully! You may now enter the dungeon.');
        } else {
            // This else block might be redundant if backend always sends !response.ok on failure
            showAlert(data.error || 'Registration failed.');
        }
    } catch (error) {
        console.error('Registration error:', error);
        showAlert('A network error occurred. Please try again.');
    } finally {
        toggleSpinner('registerSpinner', false);
    }
}

document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        if (document.activeElement.id === 'password' || document.activeElement.id === 'username') {
            login();
        }
    }
});