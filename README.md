CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fileName VARCHAR(255) NOT NULL,
    fileName2 VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    md5fileHash CHAR(32) NOT NULL,
    md5hashKey CHAR(32) NOT NULL,
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(fileName, fileName2)
);

CREATE TABLE file_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    content TEXT NOT NULL,
    md5fileHash CHAR(32) NOT NULL,
    md5hashKey CHAR(32) NOT NULL,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id)
);
