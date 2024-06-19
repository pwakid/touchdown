<?php
// Database configuration
$host = 'localhost';
$db = 'your_database';
$user = 'your_username';
$pass = 'your_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Flatfile database paths
$filesFlatfile = __DIR__ . '/files.json';
$fileUpdatesFlatfile = __DIR__ . '/file_updates.json';

// Function to read JSON data from a file
function readJsonFile($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    $data = file_get_contents($filePath);
    return json_decode($data, true);
}

// Function to write JSON data to a file
function writeJsonFile($filePath, $data) {
    file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Retrieve the base64-encoded JSON data from the request
    $encodedData = isset($_REQUEST['data']) ? $_REQUEST['data'] : '';

    // Decode the base64-encoded JSON data
    $decodedData = base64_decode($encodedData);

    // Parse the JSON data
    $data = json_decode($decodedData, true);

    // Check if decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data.']);
        exit;
    }

    // Retrieve the necessary fields from the decoded JSON data
    $fileName = isset($data['fileName']) ? $data['fileName'] : '';
    $fileName2 = isset($data['fileName2']) ? $data['fileName2'] : '';
    $content = isset($data['content']) ? $data['content'] : '';
    $action = isset($data['action']) ? $data['action'] : '';

    // Check if the file name is provided
    if (empty($fileName) || empty($fileName2)) {
        echo json_encode(['status' => 'error', 'message' => 'Both file names are required.']);
        exit;
    }

    // Calculate the MD5 hashes
    $md5fileHash = md5($content);
    $md5hashKey = md5($fileName . $fileName2);

    // Define the file path
    $filePath = __DIR__ . '/' . $fileName;

    // Read flatfile databases
    $filesData = readJsonFile($filesFlatfile);
    $fileUpdatesData = readJsonFile($fileUpdatesFlatfile);

    // Database operations
    try {
        $pdo->beginTransaction();

        // Check if the record already exists in the database
        $stmt = $pdo->prepare("SELECT id FROM files WHERE fileName = ? AND fileName2 = ?");
        $stmt->execute([$fileName, $fileName2]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file) {
            // Update the existing record in the database
            $stmt = $pdo->prepare("UPDATE files SET content = ?, md5fileHash = ?, md5hashKey = ?, createdAt = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$content, $md5fileHash, $md5hashKey, $file['id']]);

            // Insert into file_updates table in the database
            $stmt = $pdo->prepare("INSERT INTO file_updates (file_id, content, md5fileHash, md5hashKey, updatedAt) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$file['id'], $content, $md5fileHash, $md5hashKey]);

            // Update flatfile database
            $filesData[$file['id']] = [
                'fileName' => $fileName,
                'fileName2' => $fileName2,
                'content' => $content,
                'md5fileHash' => $md5fileHash,
                'md5hashKey' => $md5hashKey,
                'createdAt' => date('Y-m-d H:i:s')
            ];
            $fileUpdatesData[] = [
                'file_id' => $file['id'],
                'content' => $content,
                'md5fileHash' => $md5fileHash,
                'md5hashKey' => $md5hashKey,
                'updatedAt' => date('Y-m-d H:i:s')
            ];

            echo json_encode(['status' => 'success', 'message' => 'File updated successfully.']);
        } else {
            // Insert a new record in the database
            $stmt = $pdo->prepare("INSERT INTO files (fileName, fileName2, content, md5fileHash, md5hashKey, createdAt) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$fileName, $fileName2, $content, $md5fileHash, $md5hashKey]);

            // Get the inserted record ID
            $fileId = $pdo->lastInsertId();

            // Update flatfile database
            $filesData[$fileId] = [
                'fileName' => $fileName,
                'fileName2' => $fileName2,
                'content' => $content,
                'md5fileHash' => $md5fileHash,
                'md5hashKey' => $md5hashKey,
                'createdAt' => date('Y-m-d H:i:s')
            ];

            echo json_encode(['status' => 'success', 'message' => 'File created successfully.']);
        }

        $pdo->commit();

        // Write changes to flatfile databases
        writeJsonFile($filesFlatfile, $filesData);
        writeJsonFile($fileUpdatesFlatfile, $fileUpdatesData);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }

    // Handle file actions
    if ($action === 'create') {
        // Create a new file with the given content
        if (file_put_contents($filePath, $content) !== false) {
            echo json_encode(['status' => 'success', 'message' => 'File created successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create the file.']);
        }
    } elseif ($action === 'edit') {
        // Edit an existing file or create if it doesn't exist
        if (file_exists($filePath)) {
            if (file_put_contents($filePath, $content, FILE_APPEND) !== false) {
                echo json_encode(['status' => 'success', 'message' => 'File edited successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to edit the file.']);
            }
        } else {
            if (file_put_contents($filePath, $content) !== false) {
                echo json_encode(['status' => 'success', 'message' => 'File created successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to create the file.']);
            }
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
