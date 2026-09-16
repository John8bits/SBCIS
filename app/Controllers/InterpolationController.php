<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Models\InterpolationRepository;
use App\Services\BoundaryService;
use App\Services\InterpolationDataService;
use App\Services\InterpolationPublicationService;
use App\Services\InterpolationGeneratorService;
use App\Services\InterpolationResultStore;
use App\Support\AdminSession;
use Config\InterpolationConfig;
use InvalidArgumentException;
use Throwable;

final class InterpolationController
{
    public function data(array $query = []): array
    {
        $config = new InterpolationConfig();
        $query = $query ?: $config->systemQuery();
        $config->selection($query);
        $service = new InterpolationDataService(new BoundaryService(), $config);
        return $service->build((new InterpolationRepository(Connection::get()))->snapshot(), $query);
    }

    public function publication(): InterpolationPublicationService
    {
        // Generation is server-owned. The browser may request regeneration but
        // cannot provide an algorithm, measurements, legend, or surface.
        return new InterpolationPublicationService(
            fn() => $this->data(),
            new InterpolationResultStore(),
            static fn(array $input): array => (new InterpolationGeneratorService())->generate($input)
        );
    }

    public function json(array $query, string $method): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        try {
            if (array_diff(array_keys($query), ['action']) || !is_string($query['action'] ?? 'result')) {
                throw new InvalidArgumentException('Unsupported interpolation parameter.');
            }
            $action = $query['action'] ?? 'result';
            if (!in_array($action, ['result', 'status', 'measurements', 'regenerate'], true)) {
                throw new InvalidArgumentException('Unsupported interpolation action.');
            }
            if ($action !== 'result') {
                AdminSession::start();
                if (($_SESSION['admin_logged_in'] ?? false) !== true) {
                    $this->respond(['status' => 'unauthorized', 'message' => 'Sign in as an administrator.'], 401);
                    return;
                }
            }
            $allowedMethod = $action === 'regenerate' ? 'POST' : 'GET';
            if ($method !== $allowedMethod) {
                header('Allow: ' . $allowedMethod);
                $this->respond(['status' => 'invalid_selection', 'message' => 'Unsupported request method.'], 405);
                return;
            }
            if ($action === 'regenerate') {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
                if (!is_string($token) || empty($_SESSION['interpolation_csrf']) ||
                    !hash_equals($_SESSION['interpolation_csrf'], $token)) {
                    $this->respond(['status' => 'forbidden', 'message' => 'Reload the admin page before retrying.'], 403);
                    return;
                }
                if ($_POST) throw new InvalidArgumentException('Generation settings are managed by the system.');
            }
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            if ($action === 'measurements') $result = $this->data();
            elseif ($action === 'status') $result = $this->publication()->status();
            elseif ($action === 'regenerate') $result = $this->publication()->regenerate();
            else $result = $this->publication()->result();
            $this->respond($result);
        } catch (InvalidArgumentException $error) {
            $this->respond(['status' => 'invalid_selection', 'message' => $error->getMessage()], 400);
        } catch (Throwable $error) {
            error_log('Interpolation endpoint: ' . $error->getMessage());
            $this->respond(['status' => 'system_error', 'message' => 'The interpolation service is unavailable. Please retry.'], 503);
        }
    }

    private function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }
}
