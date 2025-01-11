<?php

namespace beck\S3Uploader\Services;

use Aws\S3\S3Client;

class S3Service
{
    protected $s3Client;
    protected $bucket;

    public function __construct()
    {
        $this->s3Client = new S3Client([
            'region' => config('s3Uploader.s3_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('s3Uploader.s3_key'),
                'secret' => config('s3Uploader.s3_secret'),
            ],
        ]);

        $this->bucket = config('s3Uploader.s3_bucket');
    }

    /**
     * Generate a presigned URL for file upload.
     *
     * @param array $files
     * @return array
     */
    public function generatePresignedUrl($files): array
    {
        $urls = [];
        foreach ($files as $file) {
            $key = 'uploads/' . $file->getClientOriginalName();

            $cmd = $this->s3Client->getCommand('PutObject', [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'ContentType' => $file->getMimeType()
            ]);

            $request = $this->s3Client->createPresignedRequest($cmd, '+15 minutes');

            $urls[] = [
                'url' => (string) $request->getUri(),
                'key' => $key,
                'localPath' => $file->getRealPath()
            ];
        }

        return $urls;
    }

    public function bulkUploadFilePresignedUrl($files)
    {
        $presignedUrls = $this->generatePresignedUrl($files);
        $responses = [];
        foreach ($presignedUrls as $urlData) {
            $fileContent = file_get_contents($urlData['localPath']);
            if ($fileContent === false) {
                return response()->json(['error' => 'Failed to read file: ' . $urlData['key']], 500);
            }

            $ch = curl_init($urlData['url']);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/octet-stream",
                "Content-Length: " . strlen($fileContent),
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);  // Capture any curl errors

            if ($response === false || $curlError) {
                $errorMessage = $curlError ?: 'Unknown error during upload of ' . $urlData['key'];
                curl_close($ch);
                return response()->json(['error' => 'Curl error: ' . $errorMessage], 500);
            }

            curl_close($ch);
            $responses[] = [
                'key' => $urlData['key'],
                'status' => 'Success',
            ];
        }

        return response()->json(['message' => 'Files uploaded successfully', 'data' => $responses]);
    }
}
