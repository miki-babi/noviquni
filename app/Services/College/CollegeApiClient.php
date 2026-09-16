<?php

namespace App\Services\College;

use App\Enums\CollegeResourceKind;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CollegeApiClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listCourses(?string $stream = null): array
    {
        $cacheKey = 'noviq_college.courses.'.md5((string) $stream);

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($stream): array {
            $query = array_filter(['stream' => $stream]);

            $payload = $this->get('courses', $query);

            return $this->normalizeList($payload);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listModules(string $courseId): array
    {
        $cacheKey = 'noviq_college.modules.'.md5($courseId);

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($courseId): array {
            $payload = $this->get('courses/'.$courseId.'/modules');

            return $this->normalizeList($payload);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function curriculum(string $moduleId): array
    {
        $cacheKey = 'noviq_college.curriculum.'.md5($moduleId);

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($moduleId): array {
            $payload = $this->get('modules/'.$moduleId.'/curriculum');

            if (! is_array($payload)) {
                return [];
            }

            if (array_key_exists('success', $payload) && ! ($payload['success'] ?? false)) {
                throw new CollegeApiException(
                    message: 'College API curriculum response was unsuccessful.',
                    body: $payload,
                );
            }

            $data = $payload['data'] ?? null;

            if (is_array($data) && ! array_is_list($data)) {
                return $data;
            }

            return $payload;
        });
    }

    /**
     * @param  array{sectionId?: string, unitId?: string, moduleId?: string}  $scope
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function generate(CollegeResourceKind $kind, array $scope, array $options = []): array
    {
        $body = array_merge($scope, $options);

        try {
            $response = $this->http(retry: false)
                ->post($kind->generatePath(), $body)
                ->throw();
        } catch (RequestException $exception) {
            throw CollegeApiException::fromRequestException($exception);
        } catch (Throwable $exception) {
            throw new CollegeApiException(
                message: 'College API generate request failed: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ! ($payload['success'] ?? false)) {
            throw new CollegeApiException(
                message: 'College API generate response was unsuccessful.',
                status: $response->status(),
                body: $payload,
            );
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|list<mixed>
     */
    protected function get(string $path, array $query = []): array
    {
        try {
            $response = $this->http(retry: true)
                ->get($path, $query)
                ->throw();
        } catch (RequestException $exception) {
            throw CollegeApiException::fromRequestException($exception);
        } catch (Throwable $exception) {
            throw new CollegeApiException(
                message: 'College API request failed: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    protected function http(bool $retry): PendingRequest
    {
        $apiKey = config('services.noviq_college.api_key');

        if (! filled($apiKey)) {
            throw new CollegeApiException(
                'College API key is not configured. Set NOVIQ_COLLEGE_API_KEY or COLLEGE_PUBLIC_API_KEY in your .env file.',
            );
        }

        $request = Http::baseUrl(rtrim((string) config('services.noviq_college.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->withHeaders(['X-College-API-Key' => $apiKey])
            ->connectTimeout((int) config('services.noviq_college.connect_timeout', 5))
            ->timeout((int) config('services.noviq_college.timeout', 60));

        if ($retry) {
            $request = $request->retry(
                [100, 500, 1000],
                0,
                function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->serverError() || $exception->response->status() === 429));
                },
            );
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return list<array<string, mixed>>
     */
    protected function normalizeList(array $payload): array
    {
        if (array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        foreach (['data', 'courses', 'items', 'results'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $this->normalizeList($payload[$key]);
            }
        }

        return [];
    }

    protected function cacheTtl(): int
    {
        return (int) config('services.noviq_college.cache_ttl', 300);
    }
}
