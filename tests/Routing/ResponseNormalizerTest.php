<?php

namespace YasserElgammal\Green\Tests\Routing;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Http\JsonResponse;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Routing\ResponseNormalizer;

class ResponseNormalizerTest extends TestCase
{
    public function test_it_returns_response_instances_unchanged(): void
    {
        $response = new Response('ok', 201);

        $this->assertSame($response, (new ResponseNormalizer())->normalize($response));
    }

    public function test_it_converts_arrays_to_json_responses(): void
    {
        $response = (new ResponseNormalizer())->normalize(['status' => 'ok']);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"status":"ok"}', $response->getContent());
    }

    public function test_it_rejects_unsupported_response_types(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unsupported response type');

        (new ResponseNormalizer())->normalize(new \stdClass());
    }
}
