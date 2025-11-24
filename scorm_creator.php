<?php
header('Content-Type: application/json');

try {
    // 1. Setup Directories
    $submissionsDir = __DIR__ . '/submissions';
    if (!file_exists($submissionsDir)) {
        if (!mkdir($submissionsDir, 0777, true)) {
            throw new Exception("Failed to create submissions directory.");
        }
    }

    // Create unique subfolder for this submission
    $uniqueId = 'scorm_' . date('Y-m-d_H-i-s') . '_' . uniqid();
    $targetDir = $submissionsDir . '/' . $uniqueId;

    if (!mkdir($targetDir, 0777, true)) {
        throw new Exception("Failed to create submission subfolder.");
    }

    // 2. Save JSON Data
    if (!isset($_POST['quiz_data'])) {
        throw new Exception("No quiz data received.");
    }

    $jsonPath = $targetDir . '/quiz_data.json';
    if (file_put_contents($jsonPath, $_POST['quiz_data']) === false) {
        throw new Exception("Failed to save quiz_data.json.");
    }

    // 3. Handle File Uploads
    $uploadedFiles = [];
    $fileInputs = ['media-logo', 'media-success', 'media-failure'];

    foreach ($fileInputs as $inputName) {
        if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES[$inputName]['tmp_name'];
            $name = basename($_FILES[$inputName]['name']); // Default name
            // Rename specific files
            switch ($inputName) {
                case 'media-logo':
                    $name = 'logo.png';
                    break;
                case 'media-success':
                    $name = 'on_success.gif';
                    break;
                case 'media-failure':
                    $name = 'on_failure.gif';
                    break;
            }
            $destination = $targetDir . '/' . $name;

            if (move_uploaded_file($tmpName, $destination)) {
                $uploadedFiles[] = $name;
                $sourceDir = __DIR__ . '/source';
                $files = glob($sourceDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $destination = $targetDir . '/' . basename($file);
                        if (!file_exists($destination)) {
                            if (!copy($file, $destination)) {
                                error_log("Failed to copy file: " . basename($file));
                            }
                        }
                    }
                }
            } else {
                // Warning: Failed to move file, but we continue
                error_log("Failed to move uploaded file: $name");
            }
        }
    }
    // on success, copy  index.hmtl, imsmanifest.xml, and missing media files (if they were not uploaded) from the source directory             
    $sourceDir = __DIR__ . '/source';
    $files = glob($sourceDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $destination = $targetDir . '/' . basename($file);
            if (!file_exists($destination)) {
                if (!copy($file, $destination)) {
                    error_log("Failed to copy file: " . basename($file));
                }
            }
        }
    }
    // compress the target directory as zip file, and send status of this process to the user.
    $zipFileName = $uniqueId . '.zip';
    $zipFilePath = $submissionsDir . '/' . $zipFileName;

    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        error_log("SCORM Creator: Starting to compress directory: $targetDir to $zipFilePath");

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            // Skip directories (they are added automatically when their contents are added)
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($targetDir) + 1);
                $zip->addFile($filePath, $relativePath);
                error_log("SCORM Creator: Added file to zip: $relativePath");
            }
        }
        $zip->close();
        error_log("SCORM Creator: Successfully created zip file: $zipFilePath");
    } else {
        error_log("SCORM Creator: Failed to create zip file: $zipFilePath");
    }
    // 4. Return Success Response
    echo json_encode([
        'success' => true,
        'message' => 'SCORM package created successfully.',
        'path' => 'submissions/' . $uniqueId,
        'files_uploaded' => $uploadedFiles
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>