<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    private string $articleUploadDir;
    private SluggerInterface $slugger;

    public function __construct(SluggerInterface $slugger, string $articleUploadDir = 'uploads/articles')
    {
        $this->slugger = $slugger;
        $this->articleUploadDir = $articleUploadDir;
    }

    /**
     * Upload article image file
     * 
     * @param UploadedFile $file
     * @param ?string $oldFilename Optional old filename to delete if updating
     * @return string New filename or empty string if upload failed
     */
    public function uploadArticleImage(UploadedFile $file, ?string $oldFilename = null): string
    {
        // Delete old file if updating
        if ($oldFilename) {
            $this->deleteFile($oldFilename, $this->articleUploadDir);
        }

        // Generate safe filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Move file
        try {
            $file->move(
                $this->getUploadPath($this->articleUploadDir),
                $newFilename
            );
            return $newFilename;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Delete a file
     */
    public function deleteFile(string $filename, string $uploadDir): bool
    {
        if (empty($filename)) {
            return true;
        }

        $filepath = $this->getUploadPath($uploadDir) . '/' . $filename;
        
        if (file_exists($filepath)) {
            try {
                unlink($filepath);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Delete article image
     */
    public function deleteArticleImage(string $filename): bool
    {
        return $this->deleteFile($filename, $this->articleUploadDir);
    }

    /**
     * Get full upload directory path
     */
    private function getUploadPath(string $uploadDir): string
    {
        return getcwd() . '/public/' . $uploadDir;
    }

    /**
     * Get web-accessible path for image
     */
    public function getWebPath(string $filename, string $uploadDir = 'uploads/articles'): string
    {
        return '/' . $uploadDir . '/' . $filename;
    }

    /**
     * Validate image file
     */
    public function validateImageFile(UploadedFile $file): array
    {
        $errors = [];
        
        // Check file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            $errors[] = 'Le fichier est trop volumineux (max 5MB)';
        }

        // Check MIME type
        $validMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $validMimes)) {
            $errors[] = 'Format de fichier non supporté. Utilisez JPG, PNG ou WebP';
        }

        // Check extension
        $validExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower($file->guessExtension());
        if (!in_array($extension, $validExtensions)) {
            $errors[] = 'Extension de fichier non autorisée';
        }

        return $errors;
    }
}
