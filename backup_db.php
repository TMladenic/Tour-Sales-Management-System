<?php
echo "Script started\n";
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define your secret token (this should be kept private and secure)
$secret_token = "ENTER_YOUR_SECRET_TOKEN_HERE";  // Replace with a strong, random token

// Check if the token is provided in the request
if (isset($_GET['token']) && $_GET['token'] === $secret_token) {
    
    // Database connection details
    $host = 'hostname';
    $username = 'username';
    $password = 'password';
    $database = 'database_name';
    
    // Create a new mysqli connection
    $conn = new mysqli($host, $username, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get all the table names in the database
    $sql = "SHOW TABLES";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $backup_content = "";
        while ($row = $result->fetch_row()) {
            $table = $row[0];
            
            // Get table creation statement
            $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
            $create_table_result = $conn->query("SHOW CREATE TABLE `$table`");
            $create_table_row = $create_table_result->fetch_row();
            $backup_content .= $create_table_row[1] . ";\n\n";

            // Get table data
            $table_data_result = $conn->query("SELECT * FROM `$table`");
            while ($data_row = $table_data_result->fetch_assoc()) {
                $columns = array_keys($data_row);
                $values = array_map(function ($value) {
                    return "'" . $value . "'";
                }, array_values($data_row));
                $backup_content .= "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
            }
            $backup_content .= "\n\n";
        }

        // Define a relative backup directory (inside htdocs)
        $backup_dir = "./backup";  // This will save in 'htdocs/backup'

        // Ensure the backup directory exists, create it if not
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);  // Create the backup directory if it doesn't exist
        }

        // Save the backup to a relative path within the 'backup' directory
        $backup_file = $backup_dir . "/your_backup_" . date('Y-m-d_H-i-s') . ".sql";
        file_put_contents($backup_file, $backup_content);

        echo "Backup completed successfully! Backup saved as: " . basename($backup_file);
    } else {
        echo "No tables found in the database.";
    }

    $conn->close();

} else {
    // If the token is incorrect or missing, return an error
    header("HTTP/1.1 403 Forbidden");
    echo "Error: Invalid or missing token.";
}
?>
