# Nyxara: Apophasis

**Nyxara: Apophasis** is an immersive, AI-powered visual novel where players must use their wits and words to escape a mystical dungeon. The primary guardian, **Nyx**, is a powerful and unpredictable entity whose form and disposition change based on the player's interactions.

This project is built with a **vanilla PHP backend** and a **JavaScript frontend**, designed to be a lightweight and engaging web experience.

![Screenshot of Dialogue](screenshots/example_dialogue.png)

## Features

- **Dynamic AI Storyteller:** The narrative is driven by a flexible AI backend that can connect to various services (DeepSeek, OpenAI, Gemini).
- **Evolving Character:** Nyx's personality and physical form change in response to the player's dialogue choices and actions.
- **Stateful Gameplay:** Tracks key story events, relationship scores, and a countdown timer for urgency.
- **Secure User System:** User registration, login, and encrypted API key storage.
- **Modern UI:** Clean, responsive user interface designed for immersive storytelling.

## Walkthrough with Screenshots

A visual guide to using the platform:

### 1. Registration

Start by creating your account.

![Registration Screen](screenshots/reg.png)  
*Figure 1: Registration screen.*

### 2. Login

Log in with your newly created credentials.

![Login Screen](screenshots/login.png)  
*Figure 2: Login screen.*

### 3. Dashboard & API Access

After logging in, you’re taken to the dashboard where you can access API settings and model options.

![Dashboard](screenshots/dashboard_api_icon.png)  
*Figure 3: Dashboard view.*

### 4. API Key Settings

Securely enter your API key to connect with AI services.

![API Settings](screenshots/api_settings.png)  
*Figure 4: API key input screen.*

### 5. Model Selection

Choose which AI model (e.g., DeepSeek, OpenAI, Gemini) to use in your story.

![Model Settings](screenshots/model_settings.png)  
*Figure 5: Model selection screen.*

## Project Structure

The project is organized into a clear and logical structure:

- **`/api`**: Contains all backend PHP scripts for handling authentication, chat logic, and user settings.
- **`/config`**: Holds configuration files for the database connection, environment variables, and API settings.
- **`/database`**: Includes the SQL schema for setting up the database.
- **`/logs`**: Stores logs for database errors, API responses, and authentication attempts.
- **`/public`**: The web-accessible directory containing the main HTML files, CSS, JavaScript, and image assets.

## Setup and Installation

To run this project locally, you will need a web server with PHP and a MySQL database (like WAMP, XAMPP, or MAMP).

1.  **Clone the Repository:**
    ```bash
    git clone <repository-url>
    ```

2.  **Database Setup:**
    - Create a new database in your MySQL server (e.g., `nyxara2`).
    - Import the schema from the `database/schema.sql` file to create the necessary tables.

3.  **Environment Configuration:**
    - Create a `.env` file in the root of the project by copying the `.env.example` file.
    - Update the `.env` file with your database credentials (DB_HOST, DB_NAME, DB_USER, DB_PASS).
    - Generate a secure, 32-character random string for the `ENCRYPTION_KEY`. This is crucial for securing user API keys.

4.  **Web Server Configuration:**
    - Point your local web server's root directory to the `/public` folder of the project.
    - Ensure the server has the necessary PHP extensions (e.g., `pdo_mysql`, `openssl`).

5.  **Launch the Application:**
    - Open your web browser and navigate to the local server address. You should see the login/registration page.
    - After creating an account, you can log in and start playing.

## How to Play

1.  **Register and Log In:** Create a new character to begin your journey.
2.  **Set Your API Key:** Click the gear icon in the top right to open the settings modal. Enter your API key for one of the supported services (DeepSeek, OpenAI, or Gemini).
3.  **Interact with Nyx:** Type your actions and dialogue in the input box at the bottom and press "Act" to see how Nyx responds.
4.  **Escape:** Your goal is to escape the dungeon before the timer runs out. Good luck!