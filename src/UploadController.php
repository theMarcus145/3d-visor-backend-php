<?php
namespace App;

class UploadController {
    /**
     * Process file upload
     * 
     * @param array $files Files from $_FILES
     * @param string $modelName Name of the model
     * @return array|false Model entry or false on failure
     */
    public static function uploadFiles($files, $modelName) {
        if (empty($files['model']) || empty($files['preview'])) {
            return false;
        }
        
        $modelFile = $files['model'];
        $previewFile = $files['preview'];
        
        // Validate model file
        $modelExtension = strtolower(pathinfo($modelFile['name'], PATHINFO_EXTENSION));
        if ($modelExtension !== 'glb') {
            error_log('Invalid model file type: ' . $modelExtension);
            return false;
        }
        
        // Validate preview file
        $allowedPreviewTypes = ['png', 'jpg', 'jpeg', 'webp'];
        $previewExtension = strtolower(pathinfo($previewFile['name'], PATHINFO_EXTENSION));
        if (!in_array($previewExtension, $allowedPreviewTypes)) {
            error_log('Invalid preview file type: ' . $previewExtension);
            return false;
        }
        
        // Create model directory
        $modelDir = MODELS_PATH . '/' . $modelName;
        if (!is_dir($modelDir) && !mkdir($modelDir, 0755, true)) {
            error_log("Could not create model directory: $modelDir");
            return false;
        }
        
        // Generate unique name for preview file
        $previewFilename = time() . '-' . mt_rand(100000, 999999) . '.' . $previewExtension;
        $previewPath = PREVIEWS_PATH . '/' . $previewFilename;
        
        // Save preview file
        if (!move_uploaded_file($previewFile['tmp_name'], $previewPath)) {
            error_log("Failed to move preview file");
            return false;
        }
        
        // Save model file
        $modelPath = $modelDir . '/scene.glb';
        if (!move_uploaded_file($modelFile['tmp_name'], $modelPath)) {
            // Clean up the preview file if model upload fails
            @unlink($previewPath);
            error_log("Failed to move model file");
            return false;
        }
        
        // Update models.json
        return self::updateModelsJson($modelName, $previewFilename);
    }
    
    /**
     * Update models.json file
     * 
     * @param string $modelName Name of the model
     * @param string $previewFilename Filename of the preview image
     * @return array|false Model entry or false on failure
     */
    private static function updateModelsJson($modelName, $previewFilename) {
        // Read existing models.json
        $modelsJson = MODELS_JSON_PATH;
        $modelsData = [];
        
        if (file_exists($modelsJson)) {
            $jsonContent = file_get_contents($modelsJson);
            $modelsData = json_decode($jsonContent, true) ?: ['models' => []];
        } else {
            $modelsData = ['models' => []];
        }
        
        // Create model entry
        $modelEntry = [
            'name' => $modelName,
            'modelPath' => "models/{$modelName}/scene.glb",
            'imagePath' => "previews/{$previewFilename}"
        ];
        
        // Check if model already exists
        $existingModelIndex = -1;
        foreach ($modelsData['models'] as $index => $model) {
            if (strtolower($model['name']) === strtolower($modelName)) {
                $existingModelIndex = $index;
                break;
            }
        }
        
        if ($existingModelIndex !== -1) {
            // Replace existing model
            $oldModel = $modelsData['models'][$existingModelIndex];
            if ($oldModel['imagePath'] !== $modelEntry['imagePath']) {
                // Delete old preview image
                $oldPreviewPath = PUBLIC_PATH . '/' . $oldModel['imagePath'];
                if (file_exists($oldPreviewPath)) {
                    @unlink($oldPreviewPath);
                }
            }
            $modelsData['models'][$existingModelIndex] = $modelEntry;
        } else {
            // Add new model
            $modelsData['models'][] = $modelEntry;
        }
        
        // Write updated models.json
        if (!file_put_contents($modelsJson, json_encode($modelsData, JSON_PRETTY_PRINT))) {
            error_log("Failed to write models.json");
            return false;
        }
        
        return $modelEntry;
    }
    
    /**
     * Delete a model
     * 
     * @param string $modelName Name of the model
     * @param string $modelPath Path to the model file
     * @param string $imagePath Path to the preview image
     * @return array|false Deleted model information or false on failure
     */
    public static function deleteModel($modelName, $modelPath, $imagePath) {
        // Read models.json
        $modelsJson = MODELS_JSON_PATH;
        if (!file_exists($modelsJson)) {
            error_log("models.json not found");
            return false;
        }
        
        $jsonContent = file_get_contents($modelsJson);
        $modelsData = json_decode($jsonContent, true);
        
        if (!$modelsData) {
            error_log("Failed to parse models.json");
            return false;
        }
        
        // Find model in array
        $modelIndex = -1;
        foreach ($modelsData['models'] as $index => $model) {
            if (strtolower($model['name']) === strtolower($modelName)) {
                $modelIndex = $index;
                break;
            }
        }
        
        if ($modelIndex === -1) {
            error_log("Model not found: $modelName");
            return false;
        }
        
        // Get model data before deletion
        $modelToDelete = $modelsData['models'][$modelIndex];
        
        // Delete model directory
        $modelDir = PUBLIC_PATH . '/models/' . $modelName;
        if (is_dir($modelDir)) {
            self::deleteDirectory($modelDir);
        }
        
        // Delete preview image
        $previewFile = PUBLIC_PATH . '/' . $imagePath;
        if (file_exists($previewFile)) {
            @unlink($previewFile);
        }
        
        // Remove model from array
        array_splice($modelsData['models'], $modelIndex, 1);
        
        // Write updated models.json
        if (!file_put_contents($modelsJson, json_encode($modelsData, JSON_PRETTY_PRINT))) {
            error_log("Failed to update models.json after deletion");
            return false;
        }
        
        return $modelToDelete;
    }
    
    /**
     * Helper function to recursively delete a directory
     * 
     * @param string $dir Directory path
     * @return bool Success or failure
     */
    private static function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object === '.' || $object === '..') {
                continue;
            }
            
            $path = $dir . '/' . $object;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        
        return @rmdir($dir);
    }
}