<?php

declare(strict_types=1);

namespace RafikiDB;

/**
 * Module APIs: storage, env vars, secrets, webhooks, edge functions, payments.
 */

final class Storage
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @param string[]|null $allowedMimeTypes */
    public function createBucket(string $name, string $slug, bool $isPublic = false, ?int $fileSizeLimit = null, ?array $allowedMimeTypes = null): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/storage/buckets', [
            'name' => $name, 'slug' => $slug, 'is_public' => $isPublic,
            'file_size_limit' => $fileSizeLimit, 'allowed_mime_types' => $allowedMimeTypes,
        ]);
    }

    public function listBuckets(): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/storage/buckets');
    }

    public function getBucket(string $bucketId): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/storage/buckets/' . $bucketId);
    }

    public function deleteBucket(string $bucketId): Envelope
    {
        return $this->client->delete('/projects/' . $this->client->projectId() . '/storage/buckets/' . $bucketId);
    }

    public function listObjects(string $bucketId): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/storage/buckets/' . $bucketId . '/objects');
    }

    public function createFolder(string $bucketId, string $name): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/storage/buckets/' . $bucketId . '/folders', ['name' => $name]);
    }

    public function deleteObject(string $objectId): Envelope
    {
        return $this->client->delete('/projects/' . $this->client->projectId() . '/storage/objects/' . $objectId);
    }

    public function signedUploadUrl(string $bucketId, string $objectName, ?int $contentLength = null): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/storage/signed-upload-url', [
            'bucket_id' => $bucketId, 'object_name' => $objectName, 'content_length' => $contentLength,
        ]);
    }

    public function signedDownloadUrl(string $objectId): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/storage/signed-download-url', ['object_id' => $objectId]);
    }

    public function publicUrl(string $bucketId, string $objectId): string
    {
        return $this->client->baseUrl() . '/projects/' . $this->client->projectId() . '/storage/public/' . $bucketId . '/' . $objectId;
    }
}
