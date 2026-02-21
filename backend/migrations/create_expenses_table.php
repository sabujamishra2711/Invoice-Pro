<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDB();
    
    // Create expenses table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS expenses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            category VARCHAR(100) NOT NULL,
            description VARCHAR(500),
            amount DECIMAL(12,2) NOT NULL,
            expense_date DATE NOT NULL,
            vendor VARCHAR(200),
            receipt_path VARCHAR(500),
            payment_method ENUM('cash', 'credit_card', 'debit_card', 'bank_transfer', 'check', 'other') DEFAULT 'cash',
            is_billable TINYINT(1) DEFAULT 0,
            client_id INT UNSIGNED NULL,
            invoice_id INT UNSIGNED NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
            INDEX idx_user_date (user_id, expense_date),
            INDEX idx_category (category),
            INDEX idx_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create expense_categories table for user-defined categories
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS expense_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(7) DEFAULT '#6366f1',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_category (user_id, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    echo "Expenses tables created successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
