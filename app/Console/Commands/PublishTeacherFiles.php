<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Teacher;

class PublishTeacherFiles extends Command
{
    protected $signature = 'teacher:publish-files';
    protected $description = 'Set existing teacher CV and ID card files on Cloudinary to public access';

    public function handle(): void
    {
        // Parse from cloudinary://KEY:SECRET@CLOUD_NAME
        $cloudUrl  = (string) config('cloudinary.cloud_url', '');
        preg_match('#cloudinary://([^:]+):([^@]+)@(.+)#', $cloudUrl, $m);
        $apiKey    = $m[1] ?? null;
        $apiSecret = $m[2] ?? null;
        $cloudName = $m[3] ?? null;

        if (!$cloudName || !$apiKey || !$apiSecret) {
            $this->error('Cloudinary credentials not found. Check CLOUDINARY_URL in .env');
            return;
        }

        $teachers = Teacher::whereNotNull('cv_file')
            ->orWhereNotNull('id_card_file')
            ->get(['id', 'cv_file', 'id_card_file']);

        $this->info("Found {$teachers->count()} teachers with files.");

        $updated = 0;
        foreach ($teachers as $teacher) {
            foreach (['cv_file', 'id_card_file'] as $field) {
                $url = $teacher->$field;
                if (!$url) continue;

                $publicId = $this->extractPublicId($url);
                if (!$publicId) continue;

                $resourceType = str_contains($url, '/raw/upload/') ? 'raw' : 'image';

                $response = $this->callCloudinaryApi($cloudName, $apiKey, $apiSecret, $publicId, $resourceType);

                if ($response === true) {
                    $this->line("  ✓ Published: {$publicId}");
                    $updated++;
                } else {
                    $this->warn("  ✗ Failed: {$publicId} — {$response}");
                }
            }
        }

        $this->info("Done. {$updated} file(s) set to public.");
    }

    private function extractPublicId(string $url): ?string
    {
        // Extract public_id from Cloudinary URL (everything after /upload/ excluding version and extension)
        if (preg_match('#/(?:image|raw)/upload/(?:v\d+/)?(.+)$#', $url, $m)) {
            // Remove file extension
            return preg_replace('/\.[^.]+$/', '', $m[1]);
        }
        return null;
    }

    private function callCloudinaryApi(string $cloud, string $key, string $secret, string $publicId, string $resourceType): bool|string
    {
        $timestamp = time();
        $params    = ['access_mode' => 'public', 'public_id' => $publicId, 'timestamp' => $timestamp];
        ksort($params);
        $sigString = implode('&', array_map(fn($k, $v) => "{$k}={$v}", array_keys($params), $params));
        $signature = hash('sha256', $sigString . $secret);

        $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloud}/{$resourceType}/explicit");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => array_merge($params, [
                'api_key'   => $key,
                'signature' => $signature,
                'type'      => 'upload',
            ]),
        ]);
        $body = curl_exec($ch);
        unset($ch);

        $json = json_decode($body, true);
        if (isset($json['access_mode']) && $json['access_mode'] === 'public') return true;
        return $json['error']['message'] ?? $body;
    }
}
