<?php

namespace App\Modules\ParentContacts;

use App\Core\ApiException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ParentContactController
{
    private const RELATIONSHIPS = ['father', 'mother', 'guardian', 'other'];

    public function __construct(private ParentContactService $service)
    {
    }

    public function list(Request $request, Response $response, array $args): Response
    {
        try {
            return $this->success($response, $this->service->list((int) $args['studentId']));
        } catch (ApiException $e) {
            return $this->failure($response, $e);
        }
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $error = $this->validate($body, true);
        if ($error !== null) {
            return $this->validationFailure($response, $error);
        }

        try {
            $contact = $this->service->create((int) $args['studentId'], $this->validatedData($body));
            return $this->success($response, $contact, 201);
        } catch (ApiException $e) {
            return $this->failure($response, $e);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $error = $this->validate($body, false);
        if ($error !== null) {
            return $this->validationFailure($response, $error);
        }

        try {
            $contact = $this->service->update(
                (int) $args['studentId'],
                (int) $args['id'],
                $this->validatedData($body)
            );
            return $this->success($response, $contact);
        } catch (ApiException $e) {
            return $this->failure($response, $e);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $this->service->delete((int) $args['studentId'], (int) $args['id']);
            return $this->success($response, null);
        } catch (ApiException $e) {
            return $this->failure($response, $e);
        }
    }

    private function validate(array $body, bool $creating): ?string
    {
        if ($creating && (!isset($body['parent_name']) || !is_string($body['parent_name']) || trim($body['parent_name']) === '')) {
            return 'نام والد الزامی است';
        }
        if ($creating && (!isset($body['relationship']) || !in_array($body['relationship'], self::RELATIONSHIPS, true))) {
            return 'نسبت والد نامعتبر است';
        }
        if ($creating && (!isset($body['phone']) || !is_string($body['phone']) || trim($body['phone']) === '')) {
            return 'شماره تماس الزامی است';
        }
        if (!$creating && $body === []) {
            return 'حداقل یک فیلد برای ویرایش الزامی است';
        }

        if (array_key_exists('parent_name', $body) && (!is_string($body['parent_name']) || trim($body['parent_name']) === '')) {
            return 'نام والد نمی‌تواند خالی باشد';
        }
        if (array_key_exists('relationship', $body) && !in_array($body['relationship'], self::RELATIONSHIPS, true)) {
            return 'نسبت والد نامعتبر است';
        }
        if (array_key_exists('phone', $body) && (!is_string($body['phone']) || trim($body['phone']) === '')) {
            return 'شماره تماس نمی‌تواند خالی باشد';
        }
        if (isset($body['phone']) && mb_strlen(trim($body['phone'])) > 15) {
            return 'شماره تماس نمی‌تواند بیشتر از ۱۵ کاراکتر باشد';
        }
        if (array_key_exists('is_primary', $body) && !in_array($body['is_primary'], [true, false, 0, 1, '0', '1'], true)) {
            return 'مقدار مخاطب اصلی باید بولی باشد';
        }

        foreach (array_keys($body) as $field) {
            if (!in_array($field, ['parent_name', 'relationship', 'phone', 'is_primary'], true)) {
                return 'فیلد ارسالی پشتیبانی نمی‌شود';
            }
        }
        return null;
    }

    private function validatedData(array $body): array
    {
        $data = [];
        foreach (['parent_name', 'relationship', 'phone', 'is_primary'] as $field) {
            if (array_key_exists($field, $body)) {
                $data[$field] = in_array($field, ['parent_name', 'phone'], true) ? trim($body[$field]) : $body[$field];
            }
        }
        if (array_key_exists('is_primary', $data)) {
            $data['is_primary'] = (bool) $data['is_primary'];
        }
        return $data;
    }

    private function success(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => null,
            'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function failure(Response $response, ApiException $exception): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'data' => null,
            'pagination' => null,
            'error' => ['code' => $exception->getErrorCode(), 'message' => $exception->getMessage()],
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus($exception->getHttpStatus())->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function validationFailure(Response $response, string $message): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'data' => null,
            'pagination' => null,
            'error' => ['code' => 'VALIDATION_ERROR', 'message' => $message],
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(422)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
