<?php
/**
 * Pugo Core 3.0 - Media Library
 * 
 * Manage images and files for the Hugo site.
 */

namespace Pugo\Media;

use Pugo\Config\PugoConfig;

class MediaLibrary
{
    private string $uploadDir;
    private string $publicPath;
    private array $allowedImages = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    private array $allowedFiles = ['pdf', 'doc', 'docx', 'txt', 'zip'];
    private int $maxFileSize;
    
    public function __construct(?string $uploadDir = null)
    {
        $hugoRoot = defined('HUGO_ROOT') ? HUGO_ROOT : getcwd();
        $this->uploadDir = $uploadDir ?? $hugoRoot . '/static/images';
        $this->publicPath = '/images';
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Upload a file
     */
    public function upload(array $file, ?string $folder = null): MediaResult
    {
        // Validate upload
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return MediaResult::failure('Invalid upload');
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return MediaResult::failure($this->getUploadErrorMessage($file['error']));
        }
        
        if ($file['size'] > $this->maxFileSize) {
            return MediaResult::failure('File too large. Max size: ' . $this->formatBytes($this->maxFileSize));
        }
        
        // Get extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check allowed types
        $isImage = in_array($ext, $this->allowedImages);
        $isFile = in_array($ext, $this->allowedFiles);
        
        if (!$isImage && !$isFile) {
            return MediaResult::failure('File type not allowed: ' . $ext);
        }
        
        // Generate unique filename
        $basename = pathinfo($file['name'], PATHINFO_FILENAME);
        $basename = $this->sanitizeFilename($basename);
        $filename = $basename . '-' . substr(uniqid(), -6) . '.' . $ext;
        
        // Determine target directory
        $targetDir = $this->uploadDir;
        if ($folder) {
            $folder = $this->sanitizePath($folder);
            $targetDir .= '/' . $folder;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
        }
        
        $targetPath = $targetDir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return MediaResult::failure('Failed to save file');
        }
        
        // Set permissions
        chmod($targetPath, 0644);
        
        // Get file info
        $info = $this->getFileInfo($targetPath);
        
        return MediaResult::success('File uploaded successfully', [
            'filename' => $filename,
            'path' => $targetPath,
            'url' => $this->publicPath . ($folder ? '/' . $folder : '') . '/' . $filename,
            'size' => $file['size'],
            'type' => $isImage ? 'image' : 'file',
            'extension' => $ext,
            'info' => $info,
        ]);
    }
    
    /**
     * Get all media files
     */
    public function getAll(?string $folder = null, ?string $type = null): array
    {
        $dir = $this->uploadDir;
        if ($folder) {
            $dir .= '/' . $this->sanitizePath($folder);
        }
        
        if (!is_dir($dir)) {
            return [];
        }
        
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            
            $ext = strtolower($file->getExtension());
            $isImage = in_array($ext, $this->allowedImages);
            $isFile = in_array($ext, $this->allowedFiles);
            
            if (!$isImage && !$isFile) continue;
            
            if ($type === 'image' && !$isImage) continue;
            if ($type === 'file' && !$isFile) continue;
            
            $relativePath = str_replace($this->uploadDir, '', $file->getPathname());
            $relativePath = ltrim($relativePath, '/');
            
            $files[] = [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'url' => $this->publicPath . '/' . $relativePath,
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
                'type' => $isImage ? 'image' : 'file',
                'extension' => $ext,
            ];
        }
        
        // Sort by modified time desc
        usort($files, fn($a, $b) => $b['modified'] - $a['modified']);
        
        return $files;
    }
    
    /**
     * Get folders
     */
    public function getFolders(): array
    {
        $folders = [];
        
        if (!is_dir($this->uploadDir)) {
            return $folders;
        }
        
        $iterator = new \DirectoryIterator($this->uploadDir);
        
        foreach ($iterator as $item) {
            if ($item->isDot() || !$item->isDir()) continue;
            
            $folders[] = [
                'name' => $item->getFilename(),
                'path' => $item->getPathname(),
                'count' => $this->countFilesInDir($item->getPathname()),
            ];
        }
        
        sort($folders);
        
        return $folders;
    }
    
    /**
     * Create a folder
     */
    public function createFolder(string $name): MediaResult
    {
        $name = $this->sanitizePath($name);
        $path = $this->uploadDir . '/' . $name;
        
        if (is_dir($path)) {
            return MediaResult::failure('Folder already exists');
        }
        
        if (!mkdir($path, 0755, true)) {
            return MediaResult::failure('Failed to create folder');
        }
        
        return MediaResult::success('Folder created', ['name' => $name, 'path' => $path]);
    }
    
    /**
     * Delete a file
     */
    public function delete(string $filename): MediaResult
    {
        $path = $this->uploadDir . '/' . $this->sanitizePath($filename);
        
        if (!file_exists($path)) {
            return MediaResult::failure('File not found');
        }
        
        if (!unlink($path)) {
            return MediaResult::failure('Failed to delete file');
        }
        
        return MediaResult::success('File deleted');
    }
    
    /**
     * Rename a file
     */
    public function rename(string $oldName, string $newName): MediaResult
    {
        $oldPath = $this->uploadDir . '/' . $this->sanitizePath($oldName);
        
        if (!file_exists($oldPath)) {
            return MediaResult::failure('File not found');
        }
        
        $ext = pathinfo($oldPath, PATHINFO_EXTENSION);
        $newBasename = $this->sanitizeFilename(pathinfo($newName, PATHINFO_FILENAME));
        $newPath = dirname($oldPath) . '/' . $newBasename . '.' . $ext;
        
        if (file_exists($newPath)) {
            return MediaResult::failure('A file with that name already exists');
        }
        
        if (!rename($oldPath, $newPath)) {
            return MediaResult::failure('Failed to rename file');
        }
        
        return MediaResult::success('File renamed', [
            'new_name' => $newBasename . '.' . $ext,
            'new_path' => $newPath,
        ]);
    }
    
    /**
     * Get file info
     */
    public function getFileInfo(string $path): array
    {
        $info = [
            'size' => filesize($path),
            'modified' => filemtime($path),
        ];
        
        // Get image dimensions if it's an image
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, $this->allowedImages) && $ext !== 'svg') {
            $imageInfo = @getimagesize($path);
            if ($imageInfo) {
                $info['width'] = $imageInfo[0];
                $info['height'] = $imageInfo[1];
                $info['mime'] = $imageInfo['mime'];
            }
        }
        
        return $info;
    }
    
    /**
     * Sanitize filename
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove non-ASCII characters
        $filename = preg_replace('/[^\x20-\x7E]/', '', $filename);
        // Replace spaces with dashes
        $filename = preg_replace('/\s+/', '-', $filename);
        // Remove special characters except dashes and underscores
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $filename);
        // Limit length
        $filename = substr($filename, 0, 100);
        // Default if empty
        return $filename ?: 'file';
    }
    
    /**
     * Sanitize path
     */
    protected function sanitizePath(string $path): string
    {
        // Remove path traversal attempts
        $path = str_replace(['../', '..\\'], '', $path);
        // Remove leading/trailing slashes
        $path = trim($path, '/\\');
        // Remove any remaining dangerous characters
        return preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $path);
    }
    
    /**
     * Count files in directory
     */
    protected function countFilesInDir(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) $count++;
        }
        return $count;
    }
    
    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
    
    /**
     * Get upload error message
     */
    protected function getUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
            default => 'Unknown upload error',
        };
    }
}

/**
 * Media operation result
 */
class MediaResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $data = []
    ) {}
    
    public static function success(string $message, array $data = []): self
    {
        return new self(true, $message, $data);
    }
    
    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}

