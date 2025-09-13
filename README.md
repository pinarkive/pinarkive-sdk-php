# Pinarkive PHP SDK

PHP client for the Pinarkive API v2.3.0. Easy IPFS file management with directory DAG uploads, file renaming, and enhanced API key management. Perfect for Laravel, Symfony, and plain PHP projects.

## Installation

### Using Composer (Recommended)

```bash
composer require pinarkive/pinarkive-sdk-php
```

### Manual Installation

If you're not using Composer, install Guzzle HTTP client and copy the SDK:

```bash
composer require guzzlehttp/guzzle
```

Then copy `PinarkiveClient.php` to your project.

## Quick Start

```php
<?php

use Pinarkive\PinarkiveClient;

// Initialize with API key (production)
$client = new PinarkiveClient('your-api-key-here');

// Or with custom endpoint
$client = new PinarkiveClient('your-api-key-here', 'https://api.dev.pinarkive.com/api/v2');
$client = new PinarkiveClient('your-api-key-here', 'https://api.cliente-nuevo.com/api/v2');

// Upload a file
$result = $client->uploadFile('document.pdf');
$response = json_decode($result->getBody(), true);
echo "File uploaded: " . $response['cid'];

// Generate API key
$token = $client->generateToken('my-app', [
    'expiresInDays' => 30
]);
$tokenResponse = json_decode($token->getBody(), true);
echo "New API key: " . $tokenResponse['token'];
```

## Authentication

The SDK uses API key authentication with Bearer token format:

```php
$client = new PinarkiveClient('your-api-key-here');
```
**Note:** The SDK automatically sends the API key using the `Authorization: Bearer` header format.

## Custom Endpoints

You can point to different API endpoints:

```php
// Production (default)
$client = new PinarkiveClient('your-api-key-here');

// Development/Staging
$client = new PinarkiveClient('your-api-key-here', 'https://api.dev.pinarkive.com/api/v2');

// Client's custom API
$client = new PinarkiveClient('your-api-key-here', 'https://api.cliente-nuevo.com/api/v2');
```

## Basic Usage

### File Upload
```php
// Upload single file
$result = $client->uploadFile('document.pdf');
$response = json_decode($result->getBody(), true);
echo "CID: " . $response['cid'];
echo "Status: " . $response['status'];
```

### Directory Upload
```php
// Upload directory from local path
$result = $client->uploadDirectory('/path/to/directory');
$response = json_decode($result->getBody(), true);
echo "Directory CID: " . $response['cid'];
```

### List Uploads
```php
// List all uploaded files with pagination
$result = $client->listUploads(1, 20);
$response = json_decode($result->getBody(), true);
echo "Uploads: " . print_r($response['uploads'], true);
echo "Total: " . $response['pagination']['total'];
```

## Advanced Features

### Directory DAG Upload
Upload entire directory structures as DAG (Directed Acyclic Graph):

```php
// Create project structure
$projectFiles = [
    [
        'path' => 'src/index.php',
        'content' => '<?php echo "Hello World"; ?>'
    ],
    [
        'path' => 'src/utils.php',
        'content' => '<?php function utils() {} ?>'
    ],
    [
        'path' => 'composer.json',
        'content' => '{"name": "my-project"}'
    ],
    [
        'path' => 'README.md',
        'content' => '# My Project\n\nThis is my project.'
    ]
];

// Upload as DAG
$result = $client->uploadDirectoryDAG($projectFiles, 'my-project');
$response = json_decode($result->getBody(), true);
echo "DAG CID: " . $response['dagCid'];
echo "Files: " . print_r($response['files'], true);
```

### Directory Cluster Upload
```php
// Upload using cluster-based approach
$files = [
    ['path' => 'file1.txt', 'content' => 'Content 1'],
    ['path' => 'file2.txt', 'content' => 'Content 2']
];

$result = $client->uploadDirectoryCluster($files);
$response = json_decode($result->getBody(), true);
echo "Cluster CID: " . $response['cid'];
```

### Upload File to Existing Directory
```php
// Add file to existing directory
$result = $client->uploadFileToDirectory('new-file.txt', 'existing-directory-path');
$response = json_decode($result->getBody(), true);
echo "File added to directory: " . $response['cid'];
```

### File Renaming
```php
// Rename an uploaded file
$result = $client->renameFile('upload-id-here', 'new-file-name.pdf');
$response = json_decode($result->getBody(), true);
echo "File renamed: " . $response['updated'];
```

### File Removal
```php
// Remove a file from storage
$result = $client->removeFile('QmYourCIDHere');
$response = json_decode($result->getBody(), true);
echo "File removed: " . $response['success'];
```

### Pinning Operations

#### Basic CID Pinning
```php
// Pin with filename
$result = $client->pinCid('QmYourCIDHere', 'my-file.pdf');
$response = json_decode($result->getBody(), true);
echo "CID pinned: " . $response['pinned'];

// Pin without filename (backend will use default)
$result2 = $client->pinCid('QmYourCIDHere');
$response2 = json_decode($result2->getBody(), true);
echo "CID pinned: " . $response2['pinned'];
```

#### Pin with Custom Name
```php
$result = $client->pinCid('QmYourCIDHere', 'my-important-file');
$response = json_decode($result->getBody(), true);
echo "CID pinned with name: " . $response['pinned'];
```

### API Key Management

#### Generate API Key
```php
// Basic token generation
$token = $client->generateToken('my-app');

// Advanced token with options
$token = $client->generateToken('my-app', [
    'expiresInDays' => 30,
    'ipAllowlist' => ['192.168.1.1', '10.0.0.1'],
    'permissions' => ['upload', 'pin']
]);
$response = json_decode($token->getBody(), true);
echo "New API key: " . $response['token'];
```

#### List API Keys
```php
$tokens = $client->listTokens();
$response = json_decode($tokens->getBody(), true);
echo "API Keys: " . print_r($response['tokens'], true);
```

#### Revoke API Key
```php
$result = $client->revokeToken('my-app');
$response = json_decode($result->getBody(), true);
echo "Token revoked: " . $response['revoked'];
```

## Error Handling

```php
use GuzzleHttp\Exception\RequestException;

try {
    $result = $client->uploadFile('document.pdf');
    $response = json_decode($result->getBody(), true);
    echo "Success: " . print_r($response, true);
} catch (RequestException $e) {
    if ($e->hasResponse()) {
        echo "API Error: " . $e->getResponse()->getStatusCode();
        echo "Response: " . $e->getResponse()->getBody();
    } else {
        echo "Network Error: " . $e->getMessage();
    }
}
```

## Framework Integration

### Laravel Integration
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Pinarkive\PinarkiveClient;

class FileController extends Controller
{
    private $client;

    public function __construct()
    {
        $this->client = new PinarkiveClient(null, config('services.pinarkive.api_key'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240' // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $tempPath = $file->getRealPath();
            
            $result = $this->client->uploadFile($tempPath);
            $response = json_decode($result->getBody(), true);
            
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function list()
    {
        try {
            $result = $this->client->listUploads();
            $response = json_decode($result->getBody(), true);
            
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
```

### Symfony Integration
```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Pinarkive\PinarkiveClient;

class FileController extends AbstractController
{
    private $client;

    public function __construct()
    {
        $this->client = new PinarkiveClient(null, $this->getParameter('pinarkive.api_key'));
    }

    /**
     * @Route("/upload", name="upload_file", methods={"POST"})
     */
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        
        if (!$file) {
            return new JsonResponse(['error' => 'No file provided'], 400);
        }

        try {
            $result = $this->client->uploadFile($file->getRealPath());
            $response = json_decode($result->getBody(), true);
            
            return new JsonResponse($response);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/files", name="list_files", methods={"GET"})
     */
    public function list(): JsonResponse
    {
        try {
            $result = $this->client->listUploads();
            $response = json_decode($result->getBody(), true);
            
            return new JsonResponse($response);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
```

### Plain PHP Integration
```php
<?php

require_once 'vendor/autoload.php';

use Pinarkive\PinarkiveClient;

// Initialize client
$client = new PinarkiveClient(null, $_ENV['PINARKIVE_API_KEY']);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        $file = $_FILES['file'];
        $result = $client->uploadFile($file['tmp_name']);
        $response = json_decode($result->getBody(), true);
        
        header('Content-Type: application/json');
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Handle file listing
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_SERVER['REQUEST_URI'] === '/files') {
    try {
        $result = $client->listUploads();
        $response = json_decode($result->getBody(), true);
        
        header('Content-Type: application/json');
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
```

## API Reference

### Constructor
```php
new PinarkiveClient(string|null $token = null, string|null $apiKey = null, string $baseUrl = 'https://api.pinarkive.com/api/v2')
```
- `$token`: Optional JWT token for authentication
- `$apiKey`: Optional API key for authentication
- `$baseUrl`: Base URL for the API (defaults to production)

### File Operations
- `uploadFile(string $filePath)` - Upload single file
- `uploadDirectory(string $dirPath)` - Upload directory recursively (calls uploadFile for each file)
- `uploadDirectoryDAG(array $files, string|null $dirName = null)` - Upload directory as DAG structure
- `renameFile(string $uploadId, string $newName)` - Rename uploaded file
- `removeFile(string $cid)` - Remove file from storage

### Pinning Operations
- `pinCid(string $cid, string|null $filename = null)` - Pin CID to account with optional filename

### User Operations
- `listUploads(int $page = 1, int $limit = 10)` - List uploaded files

### Token Management
- `generateToken(string $name, array $options = [])` - Generate API key
- `listTokens()` - List all API keys
- `revokeToken(string $name)` - Revoke API key


### Status & Monitoring
- `getStatus(string $cid)` - Get file status
- `getAllocations(string $cid)` - Get storage allocations

## Examples

### Complete File Management Workflow
```php
<?php

use Pinarkive\PinarkiveClient;

function manageFiles() {
    $client = new PinarkiveClient(null, 'your-api-key');
    
    try {
        // 1. Upload a file
        $result = $client->uploadFile('document.pdf');
        $uploadData = json_decode($result->getBody(), true);
        echo "Uploaded: " . $uploadData['cid'] . "\n";
        
        // 2. Pin the CID with a custom name
        $pinResult = $client->pinCid($uploadData['cid'], 'important-document');
        $pinData = json_decode($pinResult->getBody(), true);
        echo "Pinned: " . $pinData['pinned'] . "\n";
        
        // 3. Rename the file
        if (isset($uploadData['uploadId'])) {
            $renameResult = $client->renameFile($uploadData['uploadId'], 'my-document.pdf');
            $renameData = json_decode($renameResult->getBody(), true);
            echo "Renamed: " . $renameData['updated'] . "\n";
        }
        
        // 4. List all uploads
        $uploads = $client->listUploads();
        $uploadsData = json_decode($uploads->getBody(), true);
        echo "All uploads: " . print_r($uploadsData['uploads'], true) . "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

manageFiles();
```

### Directory Upload Workflow
```php
function uploadProject() {
    $client = new PinarkiveClient(null, 'your-api-key');
    
    // Create project structure
    $projectFiles = [
        [
            'path' => 'src/main.php',
            'content' => '<?php echo "Hello World"; ?>'
        ],
        [
            'path' => 'src/utils.php',
            'content' => '<?php function helper() {} ?>'
        ],
        [
            'path' => 'composer.json',
            'content' => '{"name": "my-project"}'
        ],
        [
            'path' => 'README.md',
            'content' => '# My Project\n\nThis is my project.'
        ]
    ];
    
    try {
        $result = $client->uploadDirectoryDAG($projectFiles, 'my-project');
        $response = json_decode($result->getBody(), true);
        echo "Project uploaded: " . $response['dagCid'] . "\n";
        echo "Files: " . print_r($response['files'], true) . "\n";
    } catch (Exception $e) {
        echo "Upload failed: " . $e->getMessage() . "\n";
    }
}

uploadProject();
```

## Publishing Instructions

### Publishing to Packagist

This package is automatically published to Packagist when you push to the main branch:

```bash
# Update version in composer.json
git add composer.json
git commit -m "Bump version to 2.3.0"
git tag v2.3.0
git push origin main --tags
# Packagist will auto-sync from GitHub
```

### Manual Publishing

If you need to manually trigger Packagist sync:

1. Go to [Packagist](https://packagist.org/packages/pinarkive/pinarkive-sdk-php)
2. Click "Update" to sync the latest changes
3. Verify the new version appears in the package listing

### Version Management

- Update version in `composer.json`
- Create git tag with format `v2.3.0`
- Push to main branch with tags
- Packagist automatically detects and publishes new versions

## Support

For issues or questions:
- GitHub Issues: [https://github.com/pinarkive/pinarkive-sdk-php/issues](https://github.com/pinarkive/pinarkive-sdk-php/issues)
- API Documentation: [https://api.pinarkive.com/docs](https://api.pinarkive.com/docs)
- Contact: [https://pinarkive.com/docs.php](https://pinarkive.com/docs.php) 