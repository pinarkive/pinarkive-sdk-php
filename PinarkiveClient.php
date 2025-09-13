<?php

namespace Pinarkive;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * PinarkiveClient - PHP SDK for Pinarkive API v2.3
 * 
 * Easy IPFS file management with directory DAG uploads, file renaming, 
 * and enhanced API key management. Perfect for Laravel, Symfony, and plain PHP projects.
 */
class PinarkiveClient {
    private $client;
    private $apiKey;
    private $baseUrl;

    /**
     * Constructor
     * 
     * @param string|null $apiKey API key for authentication (Bearer token)
     * @param string $baseUrl Base URL for the API
     */
    public function __construct($apiKey = null, $baseUrl = 'https://api.pinarkive.com/api/v2') {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client = new Client();
    }

    /**
     * Get authentication headers
     * 
     * @return array
     */
    private function headers() {
        $headers = [];
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        return $headers;
    }

    // --- Authentication ---
    

    // --- File Management ---
    
    /**
     * Upload a single file
     * 
     * @param string $filePath Path to the file to upload
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function uploadFile($filePath) {
        return $this->client->post($this->baseUrl . '/files', [
            'headers' => $this->headers(),
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r')
                ]
            ]
        ]);
    }

    /**
     * Upload directory from local path
     * 
     * @param string $dirPath Path to the directory
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function uploadDirectory($dirPath) {
        return $this->client->post($this->baseUrl . '/files/directory', [
            'headers' => $this->headers(),
            'json' => ['dirPath' => $dirPath]
        ]);
    }

    /**
     * Upload directory structure as DAG (Directed Acyclic Graph)
     * 
     * @param array $files Array of files with path and content
     * @param string|null $dirName Optional directory name
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function uploadDirectoryDAG($files, $dirName = null) {
        $multipart = [];
        
        // Add directory name if provided
        if ($dirName) {
            $multipart[] = [
                'name' => 'dirName',
                'contents' => $dirName
            ];
        }
        
        // Add files
        foreach ($files as $index => $file) {
            if (is_array($file) && isset($file['path']) && isset($file['content'])) {
                $multipart[] = [
                    'name' => "files[{$index}][path]",
                    'contents' => $file['path']
                ];
                $multipart[] = [
                    'name' => "files[{$index}][content]",
                    'contents' => $file['content']
                ];
            } else {
                // Handle simple file path
                $multipart[] = [
                    'name' => 'files',
                    'contents' => fopen($file, 'r')
                ];
            }
        }
        
        return $this->client->post($this->baseUrl . '/files/directory-dag', [
            'headers' => $this->headers(),
            'multipart' => $multipart
        ]);
    }


    /**
     * Rename an uploaded file
     * 
     * @param string $uploadId ID of the uploaded file
     * @param string $newName New name for the file
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function renameFile($uploadId, $newName) {
        return $this->client->put($this->baseUrl . "/files/rename/{$uploadId}", [
            'headers' => $this->headers(),
            'json' => ['newName' => $newName]
        ]);
    }

    /**
     * Pin a CID to your account
     * 
     * @param string $cid IPFS CID to pin
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function pinCid($cid, $filename = null) {
        $data = [];
        if ($filename) {
            $data['filename'] = $filename;
        }
        return $this->client->post($this->baseUrl . "/files/pin/{$cid}", [
            'json' => $data,
            'headers' => $this->headers()
        ]);
    }


    /**
     * Remove file from storage
     * 
     * @param string $cid IPFS CID to remove
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function removeFile($cid) {
        return $this->client->delete($this->baseUrl . "/files/remove/{$cid}", [
            'headers' => $this->headers()
        ]);
    }


    /**
     * List uploaded files with pagination
     * 
     * @param int $page Page number
     * @param int $limit Number of items per page
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function listUploads($page = 1, $limit = 10) {
        return $this->client->get($this->baseUrl . '/users/me/uploads', [
            'headers' => $this->headers(),
            'query' => ['page' => $page, 'limit' => $limit]
        ]);
    }



    // --- Token Management ---
    
    /**
     * Generate API token with enhanced options
     * 
     * @param string $name Name for the token
     * @param array $options Optional parameters:
     *                       - permissions: Array of permissions
     *                       - expiresInDays: Number of days until expiration
     *                       - ipAllowlist: Array of allowed IP addresses
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function generateToken($name, $options = []) {
        $data = ['name' => $name];
        
        if (isset($options['permissions'])) {
            $data['permissions'] = $options['permissions'];
        }
        if (isset($options['expiresInDays'])) {
            $data['expiresInDays'] = $options['expiresInDays'];
        }
        if (isset($options['ipAllowlist'])) {
            $data['ipAllowlist'] = $options['ipAllowlist'];
        }
        
        return $this->client->post($this->baseUrl . '/tokens/generate', [
            'headers' => $this->headers(),
            'json' => $data
        ]);
    }

    /**
     * List all API tokens
     * 
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function listTokens() {
        return $this->client->get($this->baseUrl . '/tokens/list', [
            'headers' => $this->headers()
        ]);
    }

    /**
     * Revoke API token
     * 
     * @param string $name Name of the token to revoke
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function revokeToken($name) {
        return $this->client->delete($this->baseUrl . "/tokens/revoke/{$name}", [
            'headers' => $this->headers()
        ]);
    }

    // --- Status and Monitoring ---
    
    /**
     * Get file status
     * 
     * @param string $cid IPFS CID to check
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getStatus($cid) {
        return $this->client->get($this->baseUrl . "/status/{$cid}", [
            'headers' => $this->headers()
        ]);
    }

    /**
     * Get storage allocations for a CID
     * 
     * @param string $cid IPFS CID to check
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getAllocations($cid) {
        return $this->client->get($this->baseUrl . "/status/allocations/{$cid}", [
            'headers' => $this->headers()
        ]);
    }
}