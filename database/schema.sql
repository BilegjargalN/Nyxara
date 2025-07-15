-- Users Table (Combined with Profile)
CREATE TABLE IF NOT EXISTS Users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(191) UNIQUE NOT NULL,
    email VARCHAR(191) UNIQUE NOT NULL,
    password_hash VARCHAR(191) NOT NULL,
    avatar_url TEXT NULL,
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    api_key_encrypted VARCHAR(512) NULL -- New: stores encrypted API key for each user
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- Chatbot Interactions (Logs user/AI messages, updated with nyx_state & time_cost)
CREATE TABLE IF NOT EXISTS Chatbot_Interactions (
    interaction_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    input TEXT NOT NULL,
    response TEXT NOT NULL,
    nyx_state VARCHAR(50) NOT NULL, -- New field for tracking conversation state
    time_cost INT NOT NULL, -- New field for tracking AI response time
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    INDEX (user_id, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Game Progress (Save states)
CREATE TABLE IF NOT EXISTS Game_Progress (
    progress_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    save_data JSON NOT NULL, -- Store story context, inventory, etc.
    last_saved DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sessions (User login sessions)
CREATE TABLE IF NOT EXISTS Sessions (
    session_id VARCHAR(191) PRIMARY KEY, -- Use UUIDs/JWT tokens
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    INDEX (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments (Optional for monetization later)
CREATE TABLE IF NOT EXISTS Payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(191) UNIQUE NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
